<?php
/**
 * The Pulse generic JSON contract — what an integration partner or an intermediary (eZee Centrix,
 * RateTiger, SiteMinder) builds against. Documented in README.md; the shape below is the contract.
 *
 * POST {endpoint}                      {"hotel_code","currency","test","sent_at","rows":[ARI...]}
 * GET  {pull_endpoint}?since=…         -> {"reservations":[ {…} ]}
 * POST {ack_endpoint}                  {"hotel_code","reference","status":"delivered"}
 * GET  {endpoint}?ping=1               -> 2xx
 *
 * Every request carries the channel's auth headers (basic / bearer / api_key / hmac) plus
 * X-Pulse-Hotel and X-Pulse-Idempotency so a partner can dedupe a retried batch.
 */
class PulseChAdapterGeneric extends PulseChAdapterBase
{
    public function pushAri(array $rows)
    {
        if (!$rows) { return array('ok' => true, 'sent' => 0, 'error' => null, 'raw' => null); }
        $body = json_encode(array(
            'hotel_code' => $this->channel['hotel_code'], 'currency' => $this->channel['currency_iso'],
            'test' => (bool) $this->channel['test_mode'], 'sent_at' => date('c'), 'rows' => $rows,
        ));
        $r = $this->http($this->channel['endpoint'], $body, 'ari_push', 'batch:'.count($rows), array('X-Pulse-Hotel: '.$this->channel['hotel_code'], 'X-Pulse-Idempotency: '.md5($body)));
        if (!$r['ok']) { return array('ok' => false, 'sent' => 0, 'error' => $r['error'], 'raw' => $r['body']); }
        $j = json_decode($r['body'], true);
        // A partner may accept the batch but reject individual cells; surface that instead of a silent success.
        if (is_array($j) && !empty($j['errors'])) { return array('ok' => false, 'sent' => 0, 'error' => 'Channel rejected '.count($j['errors']).' cell(s): '.Tools::substr(json_encode($j['errors']), 0, 180), 'raw' => $r['body']); }
        return array('ok' => true, 'sent' => count($rows), 'error' => null, 'raw' => $r['body']);
    }

    public function pullReservations($since)
    {
        $url = $this->channel['pull_endpoint'] ? $this->channel['pull_endpoint'] : $this->channel['endpoint'];
        if (!$url) { return array('ok' => false, 'reservations' => array(), 'error' => 'No pull endpoint configured'); }
        $url .= (strpos($url, '?') === false ? '?' : '&').'hotel_code='.urlencode($this->channel['hotel_code']).'&since='.urlencode($since);
        $r = $this->http($url, '', 'reservation_pull', $since, array(), 'GET');
        if (!$r['ok']) { return array('ok' => false, 'reservations' => array(), 'error' => $r['error']); }
        $j = json_decode($r['body'], true);
        if (!is_array($j)) { return array('ok' => false, 'reservations' => array(), 'error' => 'Unparseable response from '.$this->channel['name']); }
        $list = isset($j['reservations']) ? $j['reservations'] : (isset($j[0]) ? $j : array());
        return array('ok' => true, 'reservations' => is_array($list) ? $list : array(), 'error' => null);
    }

    public function ackReservation($ref)
    {
        $url = $this->channel['ack_endpoint'];
        if (!$url) { return array('ok' => true, 'error' => null); } // nothing to acknowledge against — not a failure
        $body = json_encode(array('hotel_code' => $this->channel['hotel_code'], 'reference' => $ref, 'status' => 'delivered', 'acked_at' => date('c')));
        $r = $this->http($url, $body, 'ack', $ref);
        return array('ok' => $r['ok'], 'error' => $r['error']);
    }

    public function testConnection()
    {
        $url = $this->channel['endpoint'] ? $this->channel['endpoint'] : $this->channel['pull_endpoint'];
        if (!$url) { return array('ok' => false, 'error' => 'Set an endpoint first'); }
        $r = $this->http($url.(strpos($url, '?') === false ? '?' : '&').'ping=1&hotel_code='.urlencode($this->channel['hotel_code']), '', 'test', 'ping', array(), 'GET');
        return array('ok' => $r['ok'], 'error' => $r['error'], 'status' => $r['status'], 'ms' => $r['ms']);
    }
}
