<?php
/**
 * CMS behind the portal: directory pages and dayparted promotions, each with per-language text and an image.
 * Content falls back to the default language when a translation is missing, so a half-translated property
 * still shows something sensible on an Arabic screen.
 */
class PulseGpContent
{
    public static function categories() { return array('service' => 'Hotel services', 'dining' => 'Restaurants & bars', 'spa' => 'Spa & wellness', 'facility' => 'Facilities', 'transport' => 'Transport', 'emergency' => 'Emergency & safety', 'rules' => 'House rules', 'wifi' => 'WiFi', 'attraction' => 'Local attractions', 'info' => 'Information'); }

    /* ---------- read ---------- */
    /** Directory for the guest app: active pages in one language, filtered to the room type when targeted. */
    public static function directory($lang = 'en', $idProduct = null)
    {
        $lang = PulseGpService::lang($lang); $def = PulseGpService::defaultLang();
        $rows = Db::getInstance()->executeS('SELECT p.*, COALESCE(l.title,d.title) title, COALESCE(l.summary,d.summary) summary, COALESCE(l.body,d.body) body, (l.title IS NULL) translated
            FROM `'._DB_PREFIX_.'pulse_gp_page` p
            LEFT JOIN `'._DB_PREFIX_.'pulse_gp_page_lang` l ON l.id_pulse_gp_page=p.id_pulse_gp_page AND l.lang="'.pSQL($lang).'"
            LEFT JOIN `'._DB_PREFIX_.'pulse_gp_page_lang` d ON d.id_pulse_gp_page=p.id_pulse_gp_page AND d.lang="'.pSQL($def).'"
            WHERE p.active=1 ORDER BY p.category, p.sort, p.id_pulse_gp_page');
        $out = array();
        foreach ($rows as $r) {
            if ($idProduct && $r['room_types'] && !in_array((int) $idProduct, array_map('intval', explode(',', $r['room_types'])))) { continue; }
            if (!$r['title']) { continue; }
            if ($r['code'] === 'wifi') { $r['summary'] = self::wifiSummary($r['summary']); }
            $out[] = array('code' => $r['code'], 'category' => $r['category'], 'icon' => $r['icon'], 'image' => $r['image'], 'phone' => $r['phone'], 'extension' => $r['extension'],
                'opens' => $r['opens'], 'location' => $r['location'], 'title' => $r['title'], 'summary' => $r['summary'], 'body' => $r['body'], 'translated' => (int) !$r['translated']);
        }
        return $out;
    }
    protected static function wifiSummary($s) { $ssid = PulseGpService::cfg('WIFI_SSID', ''); return $ssid ? trim($s.' — network '.$ssid) : $s; }

    /** Promotions for a placement, dayparted and room-type targeted. */
    public static function promos($placement = 'home', $lang = 'en', $idBooking = null)
    {
        $lang = PulseGpService::lang($lang); $def = PulseGpService::defaultLang(); $now = date('H:i:s'); $today = date('Y-m-d');
        $idProduct = $idBooking ? (int) Db::getInstance()->getValue('SELECT id_product FROM `'._DB_PREFIX_.'htl_booking_detail` WHERE id='.(int) $idBooking) : 0;
        $rows = Db::getInstance()->executeS('SELECT p.*, COALESCE(l.title,d.title) title, COALESCE(l.body,d.body) body, COALESCE(l.cta,d.cta) cta
            FROM `'._DB_PREFIX_.'pulse_gp_promo` p
            LEFT JOIN `'._DB_PREFIX_.'pulse_gp_promo_lang` l ON l.id_pulse_gp_promo=p.id_pulse_gp_promo AND l.lang="'.pSQL($lang).'"
            LEFT JOIN `'._DB_PREFIX_.'pulse_gp_promo_lang` d ON d.id_pulse_gp_promo=p.id_pulse_gp_promo AND d.lang="'.pSQL($def).'"
            WHERE p.active=1 AND p.placement="'.pSQL($placement).'"
              AND (p.date_from IS NULL OR p.date_from<="'.pSQL($today).'") AND (p.date_to IS NULL OR p.date_to>="'.pSQL($today).'")
              AND ((p.day_start<=p.day_end AND "'.pSQL($now).'" BETWEEN p.day_start AND p.day_end) OR (p.day_start>p.day_end AND ("'.pSQL($now).'">=p.day_start OR "'.pSQL($now).'"<=p.day_end)))
            ORDER BY p.sort, p.id_pulse_gp_promo');
        $out = array();
        foreach ($rows as $r) {
            if ($idProduct && $r['room_types'] && !in_array($idProduct, array_map('intval', explode(',', $r['room_types'])))) { continue; }
            if (!$r['title']) { continue; }
            $out[] = array('code' => $r['code'], 'image' => $r['image'], 'target' => $r['target'], 'title' => $r['title'], 'body' => $r['body'], 'cta' => $r['cta']);
        }
        return $out;
    }

    /** Welcome copy: a per-language greeting template with {guest}, {room} and {hotel} placeholders. */
    public static function welcome($lang, array $vars = array())
    {
        $lang = PulseGpService::lang($lang);
        $tpl = PulseCoreService::setting('pulseguestportal', 'welcome_'.$lang);
        if (!$tpl) { $tpl = PulseCoreService::setting('pulseguestportal', 'welcome_'.PulseGpService::defaultLang()); }
        if (!$tpl) { $tpl = self::defaultWelcome($lang); }
        foreach ($vars as $k => $v) { $tpl = str_replace('{'.$k.'}', (string) $v, $tpl); }
        return preg_replace('/\{[a-z_]+\}/', '', $tpl);
    }
    public static function saveWelcome($lang, $text) { return PulseCoreService::setting('pulseguestportal', 'welcome_'.PulseGpService::lang($lang), (string) $text); }
    public static function defaultWelcome($lang)
    {
        $d = array(
            'en' => "Welcome to {hotel}, {guest}.\nYour room {room} is ready. Order room service, message the front desk or explore the hotel — all from this screen.",
            'fr' => "Bienvenue au {hotel}, {guest}.\nVotre chambre {room} est prête. Commandez au service en chambre, écrivez à la réception ou découvrez l'hôtel depuis cet écran.",
            'pcm' => "Welcome to {hotel}, {guest}.\nYour room {room} don ready. You fit order food, message front desk or check wetin dey the hotel — all from this screen.",
            'ar' => "أهلاً بك في {hotel}، {guest}.\nغرفتك {room} جاهزة. اطلب خدمة الغرف أو راسل مكتب الاستقبال من هذه الشاشة.",
        );
        return isset($d[$lang]) ? $d[$lang] : $d['en'];
    }

    /* ---------- write (back office) ---------- */
    public static function pages() { return Db::getInstance()->executeS('SELECT p.*, (SELECT GROUP_CONCAT(lang) FROM `'._DB_PREFIX_.'pulse_gp_page_lang` l WHERE l.id_pulse_gp_page=p.id_pulse_gp_page) langs, (SELECT title FROM `'._DB_PREFIX_.'pulse_gp_page_lang` l2 WHERE l2.id_pulse_gp_page=p.id_pulse_gp_page ORDER BY l2.lang="'.pSQL(PulseGpService::defaultLang()).'" DESC LIMIT 1) title FROM `'._DB_PREFIX_.'pulse_gp_page` p ORDER BY p.category, p.sort, p.id_pulse_gp_page'); }
    public static function page($id) { $p = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_gp_page` WHERE id_pulse_gp_page='.(int) $id); if ($p) { $p['lang'] = array(); foreach (Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_gp_page_lang` WHERE id_pulse_gp_page='.(int) $id) as $l) { $p['lang'][$l['lang']] = $l; } $p['room_type_ids'] = $p['room_types'] ? array_map('intval', explode(',', $p['room_types'])) : array(); } return $p; }

    public static function savePage($id, array $d, array $texts, $image = '')
    {
        $now = date('Y-m-d H:i:s');
        $row = array('code' => pSQL(Tools::substr(preg_replace('/[^a-z0-9_]/', '', Tools::strtolower($d['code'])), 0, 32)), 'category' => pSQL(array_key_exists(isset($d['category']) ? $d['category'] : '', self::categories()) ? $d['category'] : 'info'),
            'icon' => pSQL(isset($d['icon']) ? $d['icon'] : ''), 'phone' => pSQL(isset($d['phone']) ? $d['phone'] : ''), 'extension' => pSQL(isset($d['extension']) ? $d['extension'] : ''),
            'opens' => pSQL(isset($d['opens']) ? $d['opens'] : ''), 'location' => pSQL(isset($d['location']) ? $d['location'] : ''),
            'room_types' => isset($d['room_types']) && $d['room_types'] ? pSQL(implode(',', array_map('intval', (array) $d['room_types']))) : null,
            'sort' => (int) (isset($d['sort']) ? $d['sort'] : 0), 'active' => (int) (isset($d['active']) ? $d['active'] : 1), 'date_upd' => $now);
        if (!$row['code']) { throw new PrestaShopException('A page code is required (lowercase letters, digits and underscores)'); }
        if ($image) { $row['image'] = pSQL($image); }
        if ($id) { Db::getInstance()->update('pulse_gp_page', $row, 'id_pulse_gp_page='.(int) $id); }
        else {
            if (Db::getInstance()->getValue('SELECT id_pulse_gp_page FROM `'._DB_PREFIX_.'pulse_gp_page` WHERE code="'.$row['code'].'"')) { throw new PrestaShopException('A page with that code already exists'); }
            $row['date_add'] = $now; Db::getInstance()->insert('pulse_gp_page', $row); $id = (int) Db::getInstance()->Insert_ID();
        }
        foreach ($texts as $lang => $t) {
            $lang = PulseGpService::lang($lang);
            if (trim((string) $t['title']) === '') { Db::getInstance()->delete('pulse_gp_page_lang', 'id_pulse_gp_page='.(int) $id.' AND lang="'.pSQL($lang).'"'); continue; }
            Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'pulse_gp_page_lang` (id_pulse_gp_page,lang,title,summary,body) VALUES ('.(int) $id.',"'.pSQL($lang).'","'.pSQL($t['title']).'","'.pSQL(isset($t['summary']) ? $t['summary'] : '').'","'.pSQL(isset($t['body']) ? $t['body'] : '', true).'")
                ON DUPLICATE KEY UPDATE title=VALUES(title), summary=VALUES(summary), body=VALUES(body)');
        }
        PulseGpService::audit('page_save', array('code' => $row['code']), 'pulse_gp_page', $id);
        return $id;
    }
    public static function deletePage($id) { Db::getInstance()->delete('pulse_gp_page_lang', 'id_pulse_gp_page='.(int) $id); Db::getInstance()->delete('pulse_gp_page', 'id_pulse_gp_page='.(int) $id); PulseGpService::audit('page_delete', null, 'pulse_gp_page', (int) $id); return true; }

    public static function allPromos() { return Db::getInstance()->executeS('SELECT p.*, (SELECT title FROM `'._DB_PREFIX_.'pulse_gp_promo_lang` l WHERE l.id_pulse_gp_promo=p.id_pulse_gp_promo ORDER BY lang="'.pSQL(PulseGpService::defaultLang()).'" DESC LIMIT 1) title FROM `'._DB_PREFIX_.'pulse_gp_promo` p ORDER BY p.placement, p.sort'); }
    public static function promo($id) { $p = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_gp_promo` WHERE id_pulse_gp_promo='.(int) $id); if ($p) { $p['lang'] = array(); foreach (Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_gp_promo_lang` WHERE id_pulse_gp_promo='.(int) $id) as $l) { $p['lang'][$l['lang']] = $l; } $p['room_type_ids'] = $p['room_types'] ? array_map('intval', explode(',', $p['room_types'])) : array(); } return $p; }

    public static function savePromo($id, array $d, array $texts, $image = '')
    {
        $now = date('Y-m-d H:i:s');
        $row = array('code' => pSQL(Tools::substr(preg_replace('/[^a-z0-9_]/', '', Tools::strtolower($d['code'])), 0, 32)),
            'placement' => pSQL(in_array($d['placement'], array('home', 'dining', 'entertainment', 'directory', 'checkout')) ? $d['placement'] : 'home'),
            'target' => pSQL(isset($d['target']) ? $d['target'] : ''), 'day_start' => pSQL(isset($d['day_start']) && $d['day_start'] ? $d['day_start'] : '00:00:00'), 'day_end' => pSQL(isset($d['day_end']) && $d['day_end'] ? $d['day_end'] : '23:59:59'),
            'date_from' => !empty($d['date_from']) ? pSQL($d['date_from']) : null, 'date_to' => !empty($d['date_to']) ? pSQL($d['date_to']) : null,
            'room_types' => !empty($d['room_types']) ? pSQL(implode(',', array_map('intval', (array) $d['room_types']))) : null,
            'sort' => (int) (isset($d['sort']) ? $d['sort'] : 0), 'active' => (int) (isset($d['active']) ? $d['active'] : 1), 'date_upd' => $now);
        if (!$row['code']) { throw new PrestaShopException('A promotion code is required'); }
        if ($image) { $row['image'] = pSQL($image); }
        if ($id) { Db::getInstance()->update('pulse_gp_promo', $row, 'id_pulse_gp_promo='.(int) $id); }
        else { $row['date_add'] = $now; Db::getInstance()->insert('pulse_gp_promo', $row); $id = (int) Db::getInstance()->Insert_ID(); }
        foreach ($texts as $lang => $t) {
            $lang = PulseGpService::lang($lang);
            if (trim((string) $t['title']) === '') { Db::getInstance()->delete('pulse_gp_promo_lang', 'id_pulse_gp_promo='.(int) $id.' AND lang="'.pSQL($lang).'"'); continue; }
            Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'pulse_gp_promo_lang` (id_pulse_gp_promo,lang,title,body,cta) VALUES ('.(int) $id.',"'.pSQL($lang).'","'.pSQL($t['title']).'","'.pSQL(isset($t['body']) ? $t['body'] : '').'","'.pSQL(isset($t['cta']) ? $t['cta'] : '').'")
                ON DUPLICATE KEY UPDATE title=VALUES(title), body=VALUES(body), cta=VALUES(cta)');
        }
        PulseGpService::audit('promo_save', array('code' => $row['code']), 'pulse_gp_promo', $id);
        return $id;
    }
    public static function deletePromo($id) { Db::getInstance()->delete('pulse_gp_promo_lang', 'id_pulse_gp_promo='.(int) $id); Db::getInstance()->delete('pulse_gp_promo', 'id_pulse_gp_promo='.(int) $id); return true; }

    /** Room types, for targeting. */
    public static function roomTypes()
    {
        $id = (int) Context::getContext()->language->id;
        return Db::getInstance()->executeS('SELECT p.id_product, pl.name FROM `'._DB_PREFIX_.'product` p INNER JOIN `'._DB_PREFIX_.'product_lang` pl ON pl.id_product=p.id_product AND pl.id_lang='.($id ? $id : 1).' WHERE p.active=1 ORDER BY pl.name');
    }
}
