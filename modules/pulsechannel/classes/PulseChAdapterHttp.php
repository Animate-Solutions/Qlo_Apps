<?php
/**
 * Worked HTTP adapter for OTAs that speak OpenTravel (OTA_HotelAvailNotifRQ / OTA_HotelRateAmountNotifRQ /
 * OTA_HotelResNotifRQ) or a flat JSON equivalent — the shape Booking.com, Expedia, Agoda, Traveloka and most
 * intermediaries use. It is a complete implementation of request building, signing, response parsing and
 * error mapping; only the endpoint, auth style and (optionally) a body template are configuration, because
 * those OTAs issue endpoints and machine accounts per property after certification. Nothing here is faked:
 * set the endpoint and credentials your account manager gives you and press Test connection.
 *
 * payload_format = xml  -> OTA XML (SOAP envelope when a template supplies one)
 * payload_format = json -> the same message tree serialised as JSON
 * payload_template      -> optional wrapper with {{body}}, {{hotel_code}}, {{username}}, {{password}}, {{timestamp}}, {{echo_token}}
 */
class PulseChAdapterHttp extends PulseChAdapterBase
{
    /** Availability + restrictions first, then rates: an OTA that rejects the rate must not have taken the inventory. */
    public function pushAri(array $rows)
    {
        if (!$rows) { return array('ok' => true, 'sent' => 0, 'error' => null, 'raw' => null); }
        $avail = $this->wrap($this->channel['payload_format'] === 'xml' ? $this->availXml($rows) : json_encode($this->availTree($rows)));
        $a = $this->http($this->channel['endpoint'], $avail, 'ari_push', 'avail:'.count($rows), $this->soapHeaders('OTA_HotelAvailNotifRQ'));
        if (!$a['ok']) { return array('ok' => false, 'sent' => 0, 'error' => $a['error'], 'raw' => $a['body']); }
        if (($e = $this->parseErrors($a['body']))) { return array('ok' => false, 'sent' => 0, 'error' => 'Availability rejected: '.$e, 'raw' => $a['body']); }
        $rate = $this->wrap($this->channel['payload_format'] === 'xml' ? $this->rateXml($rows) : json_encode($this->rateTree($rows)));
        $r = $this->http($this->channel['endpoint'], $rate, 'ari_push', 'rate:'.count($rows), $this->soapHeaders('OTA_HotelRateAmountNotifRQ'));
        if (!$r['ok']) { return array('ok' => false, 'sent' => 0, 'error' => $r['error'], 'raw' => $r['body']); }
        if (($e = $this->parseErrors($r['body']))) { return array('ok' => false, 'sent' => 0, 'error' => 'Rates rejected: '.$e, 'raw' => $r['body']); }
        return array('ok' => true, 'sent' => count($rows), 'error' => null, 'raw' => $r['body']);
    }

    public function pullReservations($since)
    {
        $url = $this->channel['pull_endpoint'] ? $this->channel['pull_endpoint'] : $this->channel['endpoint'];
        if (!$url) { return array('ok' => false, 'reservations' => array(), 'error' => 'No pull endpoint configured'); }
        $body = $this->wrap($this->channel['payload_format'] === 'xml' ? $this->resRetrieveXml($since) : json_encode(array('OTA_ReadRQ' => array('EchoToken' => $this->echo(), 'TimeStamp' => date('c'), 'HotelCode' => $this->channel['hotel_code'], 'SelectionCriteria' => array('Start' => $since, 'SelectionType' => 'Undelivered')))));
        $r = $this->http($url, $body, 'reservation_pull', $since, $this->soapHeaders('OTA_ReadRQ'));
        if (!$r['ok']) { return array('ok' => false, 'reservations' => array(), 'error' => $r['error']); }
        if (($e = $this->parseErrors($r['body']))) { return array('ok' => false, 'reservations' => array(), 'error' => $e); }
        $list = $this->channel['payload_format'] === 'xml' ? $this->parseResXml($r['body']) : $this->parseResJson($r['body']);
        return array('ok' => true, 'reservations' => $list, 'error' => null);
    }

    /** OTA_NotifReportRQ — tells the channel we have the booking so it stops redelivering it. */
    public function ackReservation($ref)
    {
        $url = $this->channel['ack_endpoint'] ? $this->channel['ack_endpoint'] : $this->channel['endpoint'];
        if (!$url) { return array('ok' => true, 'error' => null); }
        if ($this->channel['payload_format'] === 'xml') {
            $body = $this->wrap('<OTA_NotifReportRQ xmlns="http://www.opentravel.org/OTA/2003/05" Version="1.0" EchoToken="'.$this->esc($this->echo()).'" TimeStamp="'.date('c').'">'
                .'<Success/><NotifDetails><HotelNotifReport><HotelReservations><HotelReservation><UniqueID Type="14" ID="'.$this->esc($ref).'"/></HotelReservation></HotelReservations></HotelNotifReport></NotifDetails></OTA_NotifReportRQ>');
        } else {
            $body = $this->wrap(json_encode(array('OTA_NotifReportRQ' => array('EchoToken' => $this->echo(), 'TimeStamp' => date('c'), 'HotelCode' => $this->channel['hotel_code'], 'Success' => true, 'UniqueID' => $ref))));
        }
        $r = $this->http($url, $body, 'ack', $ref, $this->soapHeaders('OTA_NotifReportRQ'));
        return array('ok' => $r['ok'] && !$this->parseErrors($r['body']), 'error' => $r['error']);
    }

    /** A one-cell ping: read undelivered reservations from now, which every OTA answers cheaply. */
    public function testConnection()
    {
        if (!$this->channel['endpoint'] && !$this->channel['pull_endpoint']) { return array('ok' => false, 'error' => 'Set the endpoint issued for your property first'); }
        if (in_array($this->channel['auth_type'], array('basic', 'bearer', 'api_key', 'hmac')) && !$this->cred('username') && !$this->cred('api_key') && !$this->cred('secret')) { return array('ok' => false, 'error' => 'No credentials stored — enter the machine account issued for your property'); }
        $r = $this->pullReservations(date('Y-m-d H:i:s', time() - 3600));
        return array('ok' => $r['ok'], 'error' => $r['error'], 'status' => null, 'ms' => null);
    }

    /* ---------- request building ---------- */

    protected function echo() { return 'PULSE-'.date('YmdHis').'-'.Tools::substr(md5(uniqid('', true)), 0, 8); }
    protected function esc($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
    protected function soapHeaders($action) { return $this->channel['payload_format'] === 'xml' && stripos((string) $this->channel['payload_template'], 'Envelope') !== false ? array('SOAPAction: "'.$action.'"') : array(); }

    /** Apply the configured template, if any: it carries the SOAP envelope and WS-Security header for OTAs that need one. */
    protected function wrap($body)
    {
        $tpl = trim((string) $this->channel['payload_template']);
        if ($tpl === '' || strpos($tpl, '{{body}}') === false) { return $body; }
        return str_replace(array('{{body}}', '{{hotel_code}}', '{{username}}', '{{password}}', '{{api_key}}', '{{timestamp}}', '{{echo_token}}'),
            array($body, $this->esc($this->channel['hotel_code']), $this->esc($this->cred('username')), $this->esc($this->cred('password')), $this->esc($this->cred('api_key')), date('c'), $this->esc($this->echo())), $tpl);
    }

    protected function availXml(array $rows)
    {
        $x = '<OTA_HotelAvailNotifRQ xmlns="http://www.opentravel.org/OTA/2003/05" Version="1.0" EchoToken="'.$this->esc($this->echo()).'" TimeStamp="'.date('c').'"><AvailStatusMessages HotelCode="'.$this->esc($this->channel['hotel_code']).'">';
        foreach ($rows as $r) {
            $x .= '<AvailStatusMessage BookingLimit="'.(int) $r['available'].'">'
                .'<StatusApplicationControl Start="'.$this->esc($r['date']).'" End="'.$this->esc($r['date']).'" InvTypeCode="'.$this->esc($r['room_code']).'" RatePlanCode="'.$this->esc($r['rate_code']).'"/>'
                .'<RestrictionStatus Restriction="Master" Status="'.($r['stop_sell'] ? 'Close' : 'Open').'"/>';
            if (!empty($r['cta'])) { $x .= '<RestrictionStatus Restriction="Arrival" Status="Close"/>'; }
            if (!empty($r['ctd'])) { $x .= '<RestrictionStatus Restriction="Departure" Status="Close"/>'; }
            $x .= '<LengthsOfStay><LengthOfStay Time="'.(int) $r['min_los'].'" TimeUnit="Day" MinMaxMessageType="SetMinLOS"/>';
            if ((int) $r['max_los'] > 0) { $x .= '<LengthOfStay Time="'.(int) $r['max_los'].'" TimeUnit="Day" MinMaxMessageType="SetMaxLOS"/>'; }
            $x .= '</LengthsOfStay></AvailStatusMessage>';
        }
        return $x.'</AvailStatusMessages></OTA_HotelAvailNotifRQ>';
    }

    protected function rateXml(array $rows)
    {
        $x = '<OTA_HotelRateAmountNotifRQ xmlns="http://www.opentravel.org/OTA/2003/05" Version="1.0" EchoToken="'.$this->esc($this->echo()).'" TimeStamp="'.date('c').'"><RateAmountMessages HotelCode="'.$this->esc($this->channel['hotel_code']).'">';
        foreach ($rows as $r) {
            $x .= '<RateAmountMessage><StatusApplicationControl Start="'.$this->esc($r['date']).'" End="'.$this->esc($r['date']).'" InvTypeCode="'.$this->esc($r['room_code']).'" RatePlanCode="'.$this->esc($r['rate_code']).'"/>'
                .'<Rates><Rate CurrencyCode="'.$this->esc($r['currency']).'"><BaseByGuestAmts>'
                .'<BaseByGuestAmt NumberOfGuests="1" AmountAfterTax="'.number_format((float) $r['rate_single'], 2, '.', '').'"/>'
                .'<BaseByGuestAmt NumberOfGuests="'.(int) $r['base_occupancy'].'" AmountAfterTax="'.number_format((float) $r['rate'], 2, '.', '').'"/>'
                .'</BaseByGuestAmts><AdditionalGuestAmounts>'
                .'<AdditionalGuestAmount AgeQualifyingCode="10" Amount="'.number_format((float) $r['rate_extra_adult'], 2, '.', '').'"/>'
                .'<AdditionalGuestAmount AgeQualifyingCode="8" Amount="'.number_format((float) $r['rate_child'], 2, '.', '').'"/>'
                .'</AdditionalGuestAmounts></Rate></Rates></RateAmountMessage>';
        }
        return $x.'</RateAmountMessages></OTA_HotelRateAmountNotifRQ>';
    }

    protected function resRetrieveXml($since)
    {
        return '<OTA_ReadRQ xmlns="http://www.opentravel.org/OTA/2003/05" Version="1.0" EchoToken="'.$this->esc($this->echo()).'" TimeStamp="'.date('c').'">'
            .'<ReadRequests><HotelReadRequest HotelCode="'.$this->esc($this->channel['hotel_code']).'"><SelectionCriteria SelectionType="Undelivered" Start="'.$this->esc(date('c', strtotime($since))).'"/></HotelReadRequest></ReadRequests></OTA_ReadRQ>';
    }

    protected function availTree(array $rows)
    {
        $msgs = array();
        foreach ($rows as $r) { $msgs[] = array('Start' => $r['date'], 'End' => $r['date'], 'InvTypeCode' => $r['room_code'], 'RatePlanCode' => $r['rate_code'], 'BookingLimit' => (int) $r['available'], 'Status' => $r['stop_sell'] ? 'Close' : 'Open', 'ClosedToArrival' => (bool) $r['cta'], 'ClosedToDeparture' => (bool) $r['ctd'], 'MinLOS' => (int) $r['min_los'], 'MaxLOS' => (int) $r['max_los']); }
        return array('OTA_HotelAvailNotifRQ' => array('EchoToken' => $this->echo(), 'TimeStamp' => date('c'), 'HotelCode' => $this->channel['hotel_code'], 'AvailStatusMessages' => $msgs));
    }

    protected function rateTree(array $rows)
    {
        $msgs = array();
        foreach ($rows as $r) { $msgs[] = array('Start' => $r['date'], 'End' => $r['date'], 'InvTypeCode' => $r['room_code'], 'RatePlanCode' => $r['rate_code'], 'CurrencyCode' => $r['currency'], 'AmountAfterTax' => round((float) $r['rate'], 2), 'SingleAmountAfterTax' => round((float) $r['rate_single'], 2), 'ExtraAdultAmount' => round((float) $r['rate_extra_adult'], 2), 'ChildAmount' => round((float) $r['rate_child'], 2)); }
        return array('OTA_HotelRateAmountNotifRQ' => array('EchoToken' => $this->echo(), 'TimeStamp' => date('c'), 'HotelCode' => $this->channel['hotel_code'], 'RateAmountMessages' => $msgs));
    }

    /* ---------- response parsing ---------- */

    /** OTA puts failures in <Errors><Error …>; JSON partners use errors[]. Returns a sentence or ''. */
    protected function parseErrors($body)
    {
        $body = (string) $body;
        if ($body === '') { return ''; }
        if (strpos(ltrim($body), '<') === 0) {
            $prev = libxml_use_internal_errors(true);
            $xml = simplexml_load_string($body);
            libxml_clear_errors(); libxml_use_internal_errors($prev);
            if ($xml === false) { return 'Unparseable XML response'; }
            $out = array();
            foreach ($xml->xpath('//*[local-name()="Error"]') as $e) { $a = $e->attributes(); $out[] = trim((string) $e) ?: (isset($a['ShortText']) ? (string) $a['ShortText'] : 'error'); }
            foreach ($xml->xpath('//*[local-name()="Fault"]/*[local-name()="faultstring"]') as $e) { $out[] = (string) $e; }
            return $out ? Tools::substr(implode('; ', $out), 0, 200) : '';
        }
        $j = json_decode($body, true);
        if (!is_array($j)) { return ''; }
        if (!empty($j['errors'])) { return Tools::substr(is_array($j['errors']) ? json_encode($j['errors']) : (string) $j['errors'], 0, 200); }
        if (isset($j['success']) && !$j['success']) { return Tools::substr(isset($j['message']) ? $j['message'] : 'Channel reported failure', 0, 200); }
        return '';
    }

    /** OTA_HotelResNotifRQ / OTA_ResRetrieveRS -> the neutral array PulseChReservation::normalise() consumes. */
    protected function parseResXml($body)
    {
        $prev = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        libxml_clear_errors(); libxml_use_internal_errors($prev);
        if ($xml === false) { return array(); }
        $out = array();
        foreach ($xml->xpath('//*[local-name()="HotelReservation"]') as $h) {
            $attr = $h->attributes();
            $r = array('reference' => '', 'status' => isset($attr['ResStatus']) ? Tools::strtolower((string) $attr['ResStatus']) : 'new', 'raw_xml' => $h->asXML());
            foreach ($h->xpath('.//*[local-name()="UniqueID"]') as $u) { $ua = $u->attributes(); if (!$r['reference'] && isset($ua['ID'])) { $r['reference'] = (string) $ua['ID']; } }
            foreach ($h->xpath('.//*[local-name()="RoomStay"]') as $rs) {
                foreach ($rs->xpath('.//*[local-name()="RoomType"]') as $rt) { $a = $rt->attributes(); if (isset($a['RoomTypeCode'])) { $r['room_code'] = (string) $a['RoomTypeCode']; } }
                foreach ($rs->xpath('.//*[local-name()="RatePlan"]') as $rp) { $a = $rp->attributes(); if (isset($a['RatePlanCode'])) { $r['rate_code'] = (string) $a['RatePlanCode']; } }
                foreach ($rs->xpath('.//*[local-name()="TimeSpan"]') as $ts) { $a = $ts->attributes(); if (isset($a['Start'])) { $r['arrival'] = Tools::substr((string) $a['Start'], 0, 10); } if (isset($a['End'])) { $r['departure'] = Tools::substr((string) $a['End'], 0, 10); } }
                foreach ($rs->xpath('.//*[local-name()="GuestCount"]') as $gc) { $a = $gc->attributes(); $code = isset($a['AgeQualifyingCode']) ? (int) $a['AgeQualifyingCode'] : 10; $cnt = isset($a['Count']) ? (int) $a['Count'] : 1; if ($code === 8) { $r['children'] = $cnt; } else { $r['adults'] = $cnt; } }
                foreach ($rs->xpath('.//*[local-name()="Total"]') as $t) { $a = $t->attributes(); if (isset($a['AmountAfterTax'])) { $r['amount'] = (float) $a['AmountAfterTax']; } if (isset($a['AmountBeforeTax']) && !isset($a['AmountAfterTax'])) { $r['amount'] = (float) $a['AmountBeforeTax']; } if (isset($a['CurrencyCode'])) { $r['currency'] = (string) $a['CurrencyCode']; } }
                foreach ($rs->xpath('.//*[local-name()="NumberOfUnits"]') as $nu) { $r['rooms'] = (int) $nu; }
            }
            foreach ($h->xpath('.//*[local-name()="ResGuest"]//*[local-name()="PersonName"]') as $pn) {
                foreach ($pn->xpath('.//*[local-name()="GivenName"]') as $g) { $r['firstname'] = (string) $g; }
                foreach ($pn->xpath('.//*[local-name()="Surname"]') as $s) { $r['lastname'] = (string) $s; }
            }
            foreach ($h->xpath('.//*[local-name()="Email"]') as $e) { $r['email'] = trim((string) $e); }
            foreach ($h->xpath('.//*[local-name()="Telephone"]') as $t) { $a = $t->attributes(); if (isset($a['PhoneNumber'])) { $r['phone'] = (string) $a['PhoneNumber']; } }
            foreach ($h->xpath('.//*[local-name()="Commission"]') as $c) { $a = $c->attributes(); if (isset($a['Percent'])) { $r['commission_pct'] = (float) $a['Percent']; } }
            if ($r['reference']) { $out[] = $r; }
        }
        return $out;
    }

    protected function parseResJson($body)
    {
        $j = json_decode($body, true);
        if (!is_array($j)) { return array(); }
        foreach (array('reservations', 'Reservations', 'bookings', 'HotelReservations') as $k) { if (isset($j[$k]) && is_array($j[$k])) { return $j[$k]; } }
        return isset($j[0]) ? $j : array();
    }
}
