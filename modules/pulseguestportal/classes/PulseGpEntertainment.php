<?php
/**
 * Entertainment: the IP-multicast channel list fed by the DStv headend, the VOD catalogue (paid titles post
 * to the folio), radio streams and the per-room casting pairing code. Adult content is gated by a PIN that
 * is stored hashed and checked server-side — never by hiding the row in the client.
 */
class PulseGpEntertainment
{
    /* ---------- channels ---------- */
    /** $adultOk comes from a verified PIN, never from a client flag. */
    public static function channels($adultOk = false)
    {
        $rows = Db::getInstance()->executeS('SELECT id_pulse_gp_channel id, number, name, logo, url, category, adult, hd FROM `'._DB_PREFIX_.'pulse_gp_channel` WHERE active=1'.($adultOk ? '' : ' AND adult=0').' ORDER BY sort, number');
        foreach ($rows as &$r) { $r['id'] = (int) $r['id']; $r['number'] = (int) $r['number']; $r['adult'] = (int) $r['adult']; $r['hd'] = (int) $r['hd']; }
        return $rows;
    }
    public static function channel($id) { return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_gp_channel` WHERE id_pulse_gp_channel='.(int) $id); }
    public static function allChannels() { return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_gp_channel` ORDER BY sort, number'); }

    /** Accepts udp://@239.1.1.1:1234 (multicast from the headend) as well as http/https HLS. */
    public static function validStreamUrl($url)
    {
        $url = trim((string) $url);
        if ($url === '') { return false; }
        if (preg_match('#^(udp|rtp)://@?((25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?):[0-9]{1,5}$#', $url)) { return true; }
        if (preg_match('#^rtsp://[^\s]+$#i', $url)) { return true; }
        return (bool) Validate::isAbsoluteUrl($url);
    }

    public static function saveChannel($id, array $d)
    {
        if (!self::validStreamUrl(isset($d['url']) ? $d['url'] : '')) { throw new PrestaShopException('Stream URL must be udp://@239.x.x.x:port, rtsp:// or an http(s) URL'); }
        $now = date('Y-m-d H:i:s');
        $row = array('number' => (int) $d['number'], 'name' => pSQL($d['name']), 'logo' => pSQL(isset($d['logo']) ? $d['logo'] : ''), 'url' => pSQL(trim($d['url'])),
            'category' => pSQL(in_array($d['category'], array('general', 'news', 'sport', 'movies', 'series', 'kids', 'music', 'documentary', 'religious', 'local', 'adult')) ? $d['category'] : 'general'),
            'source' => pSQL(isset($d['source']) ? $d['source'] : 'dstv'), 'adult' => (int) (isset($d['adult']) ? $d['adult'] : 0), 'hd' => (int) (isset($d['hd']) ? $d['hd'] : 0),
            'sort' => (int) (isset($d['sort']) ? $d['sort'] : 0), 'active' => (int) (isset($d['active']) ? $d['active'] : 1), 'date_upd' => $now);
        if ((int) $row['number'] <= 0) { throw new PrestaShopException('Channel number must be positive'); }
        $clash = (int) Db::getInstance()->getValue('SELECT id_pulse_gp_channel FROM `'._DB_PREFIX_.'pulse_gp_channel` WHERE number='.(int) $row['number'].($id ? ' AND id_pulse_gp_channel<>'.(int) $id : ''));
        if ($clash) { throw new PrestaShopException('Channel number '.$row['number'].' is already used'); }
        if ($id) { Db::getInstance()->update('pulse_gp_channel', $row, 'id_pulse_gp_channel='.(int) $id); }
        else { $row['date_add'] = $now; Db::getInstance()->insert('pulse_gp_channel', $row); $id = (int) Db::getInstance()->Insert_ID(); }
        PulseGpDevice::broadcast('reload', array('reason' => 'channels'));
        return $id;
    }
    public static function deleteChannel($id) { Db::getInstance()->delete('pulse_gp_channel', 'id_pulse_gp_channel='.(int) $id); PulseGpDevice::broadcast('reload', array('reason' => 'channels')); return true; }

    /** Bulk import of an M3U/CSV line list from the headend: "number,name,url[,category][,adult]" or #EXTINF M3U. */
    public static function importChannels($text)
    {
        $n = 0; $lines = preg_split('/\r\n|\r|\n/', (string) $text); $pending = null;
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || Tools::substr($line, 0, 7) === '#EXTM3U') { continue; }
            if (Tools::substr($line, 0, 7) === '#EXTINF') { $pending = array('name' => trim(Tools::substr($line, strrpos($line, ',') + 1)), 'number' => 0); continue; }
            if ($pending) {
                $num = (int) Db::getInstance()->getValue('SELECT COALESCE(MAX(number),0)+1 FROM `'._DB_PREFIX_.'pulse_gp_channel`');
                try { self::saveChannel(0, array('number' => $num, 'name' => $pending['name'], 'url' => $line, 'category' => 'general')); $n++; } catch (Exception $e) { PulseGpService::audit('channel_import_skip', array('line' => $line, 'error' => $e->getMessage())); }
                $pending = null; continue;
            }
            $p = str_getcsv($line);
            if (count($p) < 3) { continue; }
            try { self::saveChannel(0, array('number' => (int) $p[0], 'name' => $p[1], 'url' => $p[2], 'category' => isset($p[3]) ? $p[3] : 'general', 'adult' => isset($p[4]) ? (int) $p[4] : 0)); $n++; }
            catch (Exception $e) { PulseGpService::audit('channel_import_skip', array('line' => $line, 'error' => $e->getMessage())); }
        }
        return $n;
    }

    /* ---------- VOD ---------- */
    public static function vod($adultOk = false)
    {
        $rows = Db::getInstance()->executeS('SELECT id_pulse_gp_vod id, title, poster, synopsis, category, rating, year, duration_min, language, price, free, adult FROM `'._DB_PREFIX_.'pulse_gp_vod` WHERE active=1'.($adultOk ? '' : ' AND adult=0').' ORDER BY sort, title');
        foreach ($rows as &$r) { $r['id'] = (int) $r['id']; $r['price'] = round((float) $r['price'], 2); $r['free'] = (int) $r['free']; $r['adult'] = (int) $r['adult']; }
        return $rows;
    }
    public static function allVod() { return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_gp_vod` ORDER BY sort, title'); }
    public static function vodItem($id) { return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_gp_vod` WHERE id_pulse_gp_vod='.(int) $id); }

    public static function saveVod($id, array $d, $poster = '')
    {
        if (!self::validStreamUrl(isset($d['stream_url']) ? $d['stream_url'] : '')) { throw new PrestaShopException('A playable stream URL is required'); }
        $now = date('Y-m-d H:i:s');
        $free = (int) (isset($d['free']) ? $d['free'] : 1);
        $row = array('title' => pSQL($d['title']), 'synopsis' => pSQL(isset($d['synopsis']) ? $d['synopsis'] : '', true), 'stream_url' => pSQL(trim($d['stream_url'])),
            'category' => pSQL(isset($d['category']) ? $d['category'] : 'movie'), 'rating' => pSQL(isset($d['rating']) ? $d['rating'] : 'PG'), 'year' => (int) (isset($d['year']) ? $d['year'] : 0),
            'duration_min' => (int) (isset($d['duration_min']) ? $d['duration_min'] : 0), 'language' => pSQL(isset($d['language']) ? $d['language'] : 'English'),
            'price' => $free ? 0 : round((float) (isset($d['price']) ? $d['price'] : 0), 2), 'free' => $free, 'adult' => (int) (isset($d['adult']) ? $d['adult'] : 0),
            'sort' => (int) (isset($d['sort']) ? $d['sort'] : 0), 'active' => (int) (isset($d['active']) ? $d['active'] : 1), 'date_upd' => $now);
        if (!trim($d['title'])) { throw new PrestaShopException('A title is required'); }
        if (!$free && $row['price'] <= 0) { throw new PrestaShopException('A paid title needs a price'); }
        if ($poster) { $row['poster'] = pSQL($poster); }
        if ($id) { Db::getInstance()->update('pulse_gp_vod', $row, 'id_pulse_gp_vod='.(int) $id); }
        else { $row['date_add'] = $now; Db::getInstance()->insert('pulse_gp_vod', $row); $id = (int) Db::getInstance()->Insert_ID(); }
        return $id;
    }
    public static function deleteVod($id) { Db::getInstance()->delete('pulse_gp_vod', 'id_pulse_gp_vod='.(int) $id); return true; }

    /**
     * Start playback. A paid title posts to the folio first: if the post fails the guest is not charged and
     * gets no stream, and the desk sees the failed row. Re-starting a title already charged today is free.
     */
    public static function play($idVod, array $device, $session, $pin = null)
    {
        $v = self::vodItem($idVod);
        if (!$v || !$v['active']) { throw new PrestaShopException('That title is not available', 404); }
        if ((int) $v['adult'] && !PulseGpService::checkPin($pin)) { throw new PrestaShopException('PIN required for this title', 403); }
        $idBooking = $session && !empty($session['id_htl_booking']) ? (int) $session['id_htl_booking'] : 0;
        $price = round((float) $v['price'], 2);
        if ((int) $v['free'] || $price <= 0) {
            self::logPlay($v, $device, $session, 0, 'free', null);
            return array('stream_url' => $v['stream_url'], 'charged' => 0, 'price' => 0, 'title' => $v['title']);
        }
        if (!$idBooking) { throw new PrestaShopException('Paid titles need a guest checked into this room', 403); }
        $already = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_gp_vod_play` WHERE id_pulse_gp_vod='.(int) $idVod.' AND id_htl_booking='.$idBooking.' AND status="charged" AND business_date="'.pSQL(PulseGpService::bd()).'"');
        if ($already) { self::logPlay($v, $device, $session, 0, 'free', null); return array('stream_url' => $v['stream_url'], 'charged' => 0, 'price' => 0, 'title' => $v['title'], 'note' => 'already_paid_today'); }
        if (!PulseGpService::fd()) { throw new PrestaShopException('Charging is unavailable right now — please call the front desk', 503); }
        $f = PulseFolio::openForBooking($idBooking);
        if (!$f) { throw new PrestaShopException('No open folio for this room', 409); }
        $tax = (float) PulseGpService::cfg('VOD_TAX_PCT', 7.5);
        $line = $f->post(PulseGpService::cfg('VOD_CHARGE_CODE', 'VOD'), 'In-room movie — '.$v['title'], 1, round($price / (1 + $tax / 100), 2), $tax, false, null, 'portal', 'vod:'.(int) $idVod);
        self::logPlay($v, $device, $session, $price, 'charged', (int) $line);
        PulseGpService::event('actionPulsePortalVodPlay', array('id_vod' => (int) $idVod, 'id_room' => isset($device['id_room']) ? (int) $device['id_room'] : 0, 'price' => $price));
        return array('stream_url' => $v['stream_url'], 'charged' => 1, 'price' => $price, 'title' => $v['title'], 'folio_line' => (int) $line);
    }
    protected static function logPlay(array $v, array $device, $session, $price, $status, $line)
    {
        Db::getInstance()->insert('pulse_gp_vod_play', array('id_pulse_gp_vod' => (int) $v['id_pulse_gp_vod'], 'title' => pSQL($v['title']),
            'id_pulse_gp_device' => (int) $device['id_pulse_gp_device'], 'id_room' => $device['id_room'] ? (int) $device['id_room'] : null,
            'id_htl_booking' => $session && $session['id_htl_booking'] ? (int) $session['id_htl_booking'] : null, 'id_customer' => $session && $session['id_customer'] ? (int) $session['id_customer'] : null,
            'price' => (float) $price, 'posted_line' => $line ? (int) $line : null, 'status' => pSQL($status), 'business_date' => pSQL(PulseGpService::bd()), 'date_add' => date('Y-m-d H:i:s')));
    }
    public static function plays($from, $to) { return Db::getInstance()->executeS('SELECT p.*, r.room_num FROM `'._DB_PREFIX_.'pulse_gp_vod_play` p LEFT JOIN `'._DB_PREFIX_.'htl_room_information` r ON r.id=p.id_room WHERE p.business_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'" ORDER BY p.id_pulse_gp_vod_play DESC LIMIT 200'); }

    /* ---------- radio & games ---------- */
    /** Radio streams and the games/apps launcher live in settings so a property can run without extra tables. */
    public static function radio() { $j = json_decode((string) PulseCoreService::setting('pulseguestportal', 'radio'), true); return is_array($j) ? $j : array(); }
    public static function saveRadio(array $list) { $clean = array(); foreach ($list as $r) { if (!empty($r['name']) && self::validStreamUrl(isset($r['url']) ? $r['url'] : '')) { $clean[] = array('name' => Tools::substr($r['name'], 0, 64), 'url' => $r['url'], 'genre' => isset($r['genre']) ? Tools::substr($r['genre'], 0, 32) : ''); } } PulseCoreService::setting('pulseguestportal', 'radio', json_encode($clean)); return count($clean); }
    public static function apps() { $j = json_decode((string) PulseCoreService::setting('pulseguestportal', 'apps'), true); return is_array($j) ? $j : array(); }
    public static function saveApps(array $list) { $clean = array(); foreach ($list as $a) { if (!empty($a['name']) && !empty($a['url'])) { $clean[] = array('name' => Tools::substr($a['name'], 0, 64), 'url' => $a['url'], 'icon' => isset($a['icon']) ? $a['icon'] : '', 'type' => isset($a['type']) && $a['type'] === 'game' ? 'game' : 'app'); } } PulseCoreService::setting('pulseguestportal', 'apps', json_encode($clean)); return count($clean); }

    /* ---------- casting ---------- */
    /** One live pairing code per screen: the guest types it into the casting app, the desk can see who is paired. */
    public static function castCode(array $device, $protocol = 'chromecast')
    {
        $live = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_gp_cast` WHERE id_pulse_gp_device='.(int) $device['id_pulse_gp_device'].' AND status IN ("waiting","paired") AND expires_at>NOW() ORDER BY id_pulse_gp_cast DESC');
        if ($live) { return array('code' => $live['code'], 'pin' => $live['pin'], 'protocol' => $live['protocol'], 'status' => $live['status'], 'expires_at' => $live['expires_at'], 'guest_device' => $live['guest_device']); }
        $ttl = max(2, (int) PulseGpService::cfg('CAST_TTL_MIN', 10));
        $code = Tools::substr(strtoupper(str_replace(array('O', '0', 'I', '1'), array('W', 'X', 'Y', 'Z'), Tools::passwdGen(6, 'NO_NUMERIC'))), 0, 6);
        $pin = str_pad((string) mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
        Db::getInstance()->insert('pulse_gp_cast', array('id_pulse_gp_device' => (int) $device['id_pulse_gp_device'], 'id_room' => $device['id_room'] ? (int) $device['id_room'] : null,
            'code' => pSQL($code), 'pin' => pSQL($pin), 'protocol' => pSQL(in_array($protocol, array('chromecast', 'airplay', 'miracast', 'dlna')) ? $protocol : 'chromecast'),
            'expires_at' => date('Y-m-d H:i:s', time() + $ttl * 60), 'date_add' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s')));
        return array('code' => $code, 'pin' => $pin, 'protocol' => $protocol, 'status' => 'waiting', 'expires_at' => date('Y-m-d H:i:s', time() + $ttl * 60), 'guest_device' => null);
    }
    public static function castClaim($code, $pin, $guestDevice)
    {
        $c = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_gp_cast` WHERE code="'.pSQL(strtoupper($code)).'" AND status="waiting" AND expires_at>NOW()');
        if (!$c || !hash_equals($c['pin'], (string) $pin)) { throw new PrestaShopException('That casting code is not valid', 403); }
        Db::getInstance()->update('pulse_gp_cast', array('status' => 'paired', 'guest_device' => pSQL(Tools::substr((string) $guestDevice, 0, 64)), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_gp_cast='.(int) $c['id_pulse_gp_cast']);
        PulseGpDevice::command((int) $c['id_pulse_gp_device'], 'notify', array('kind' => 'cast', 'text' => 'Casting from '.Tools::substr((string) $guestDevice, 0, 40)));
        return array('id_device' => (int) $c['id_pulse_gp_device'], 'id_room' => (int) $c['id_room'], 'protocol' => $c['protocol']);
    }
    public static function castEnd($idDevice) { return Db::getInstance()->update('pulse_gp_cast', array('status' => 'ended', 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_gp_device='.(int) $idDevice.' AND status IN ("waiting","paired")'); }
    public static function castExpire() { return Db::getInstance()->update('pulse_gp_cast', array('status' => 'expired', 'date_upd' => date('Y-m-d H:i:s')), 'status="waiting" AND expires_at<NOW()'); }
}
