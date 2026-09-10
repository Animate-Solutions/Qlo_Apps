<?php
/**
 * Reputation. Reviews are entered by hand or imported from a TripAdvisor / Google / Booking.com export
 * (CSV or JSON — every one of them will give you one or the other), normalised onto a single percentage
 * scale so a 4.5/5 and an 8/10 can share a chart, and queued for a reply. A one- or two-star review
 * opens a recovery case, because a public complaint is a complaint first.
 */
class PulseCrmReview
{
    public static $sources = array('tripadvisor' => 5, 'google' => 5, 'booking' => 10, 'expedia' => 5, 'agoda' => 10, 'hotels_ng' => 5, 'facebook' => 5, 'direct' => 5, 'other' => 5);

    public static function all($from = null, $to = null, $source = null, $unanswered = false, $limit = 300)
    {
        return Db::getInstance()->executeS('SELECT r.*, CONCAT(e.firstname," ",e.lastname) responder FROM `'._DB_PREFIX_.'pulse_crm_review` r
            LEFT JOIN `'._DB_PREFIX_.'employee` e ON e.id_employee=r.responded_by WHERE 1'
            .($from ? ' AND r.review_date>="'.pSQL($from).'"' : '').($to ? ' AND r.review_date<="'.pSQL($to).'"' : '')
            .($source ? ' AND r.source="'.pSQL($source).'"' : '').($unanswered ? ' AND r.responded=0' : '')
            .' ORDER BY r.review_date DESC, r.id_pulse_crm_review DESC LIMIT '.(int) $limit);
    }
    public static function get($id) { return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_crm_review` WHERE id_pulse_crm_review='.(int) $id); }

    /** Save one review. Returns array(id, created) so an import can report what actually changed. */
    public static function save(array $d, $id = 0)
    {
        $source = isset($d['source']) && isset(self::$sources[$d['source']]) ? $d['source'] : 'other';
        $scale = isset($d['rating_scale']) && (int) $d['rating_scale'] > 0 ? (int) $d['rating_scale'] : self::$sources[$source];
        $rating = round((float) $d['rating'], 2);
        if ($rating > $scale) { $scale = $rating > 10 ? 100 : ($rating > 5 ? 10 : 5); }
        $pct = $scale > 0 ? round($rating / $scale * 100, 2) : 0;
        $body = isset($d['body']) ? $d['body'] : '';
        $row = array('source' => pSQL($source), 'external_id' => pSQL(isset($d['external_id']) ? $d['external_id'] : ''), 'url' => pSQL(isset($d['url']) ? $d['url'] : ''),
            'rating' => $rating, 'rating_scale' => $scale, 'rating_pct' => $pct, 'title' => pSQL(isset($d['title']) ? Tools::substr($d['title'], 0, 190) : ''),
            'body' => pSQL($body, true), 'language' => pSQL(isset($d['language']) ? Tools::substr($d['language'], 0, 8) : 'en'),
            'author' => pSQL(isset($d['author']) ? Tools::substr($d['author'], 0, 128) : ''), 'review_date' => pSQL(self::date(isset($d['review_date']) ? $d['review_date'] : '')),
            'trip_type' => pSQL(isset($d['trip_type']) ? $d['trip_type'] : ''), 'department' => pSQL(isset($d['department']) && $d['department'] ? $d['department'] : self::guessDepartment($body)),
            'sentiment' => pSQL(isset($d['sentiment']) && $d['sentiment'] ? $d['sentiment'] : PulseCrmSurvey::sentiment($body, null, $pct)),
            'id_customer' => !empty($d['id_customer']) ? (int) $d['id_customer'] : null, 'id_htl_booking' => !empty($d['id_htl_booking']) ? (int) $d['id_htl_booking'] : null,
            'date_upd' => date('Y-m-d H:i:s'));
        if (!$id && $row['external_id']) { $id = (int) Db::getInstance()->getValue('SELECT id_pulse_crm_review FROM `'._DB_PREFIX_.'pulse_crm_review` WHERE source="'.pSQL($source).'" AND external_id="'.$row['external_id'].'"'); }
        if ($id) { Db::getInstance()->update('pulse_crm_review', $row, 'id_pulse_crm_review='.(int) $id); return array('id' => (int) $id, 'created' => false); }
        $row['date_add'] = date('Y-m-d H:i:s');
        Db::getInstance()->insert('pulse_crm_review', $row);
        $newId = (int) Db::getInstance()->Insert_ID();
        if ($pct <= 40) { self::escalate($newId, $row); }
        PulseCoreService::event('actionPulseCrmReviewAdded', array('id_review' => $newId, 'source' => $source, 'rating_pct' => $pct));
        return array('id' => $newId, 'created' => true);
    }

    protected static function date($v)
    {
        $v = trim((string) $v);
        if ($v === '') { return date('Y-m-d'); }
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $v)) { return substr($v, 0, 10); }
        if (preg_match('#^(\d{1,2})[/-](\d{1,2})[/-](\d{4})$#', $v, $m)) { return $m[3].'-'.str_pad($m[2], 2, '0', STR_PAD_LEFT).'-'.str_pad($m[1], 2, '0', STR_PAD_LEFT); }
        $t = strtotime($v);
        return $t ? date('Y-m-d', $t) : date('Y-m-d');
    }

    /** Route a review to the department it is really about, from the words in it. */
    public static function guessDepartment($text)
    {
        $t = Tools::strtolower((string) $text);
        $map = array('housekeeping' => array('dirty', 'clean', 'towel', 'bedsheet', 'bed sheet', 'linen', 'room was', 'smell', 'toilet', 'bathroom', 'mosquito'),
            'fnb' => array('breakfast', 'food', 'restaurant', 'bar', 'meal', 'chef', 'waiter', 'dinner', 'lunch', 'menu'),
            'engineering' => array('power', 'light', 'generator', 'ac ', 'air condition', 'water', 'wifi', 'internet', 'lift', 'elevator', 'hot water', 'leak'),
            'frontdesk' => array('reception', 'check in', 'check-in', 'checkout', 'check out', 'front desk', 'staff at the desk', 'booking', 'reservation'),
            'security' => array('security', 'car park', 'parking', 'safe', 'theft', 'stolen'));
        $best = ''; $hits = 0;
        foreach ($map as $dept => $words) { $n = 0; foreach ($words as $w) { if (strpos($t, $w) !== false) { $n++; } } if ($n > $hits) { $hits = $n; $best = $dept; } }
        return $best;
    }

    protected static function escalate($id, array $row)
    {
        return PulseCrmCase::open(array('source' => 'review', 'severity' => $row['rating_pct'] <= 25 ? 'high' : 'medium',
            'department' => $row['department'] ? $row['department'] : 'management', 'id_pulse_crm_review' => $id,
            'title' => Tools::substr(($row['rating'].'/'.$row['rating_scale'].' on '.$row['source'].' — '.($row['title'] ? $row['title'] : 'no title')), 0, 190),
            'description' => $row['body']));
    }

    /**
     * Import an export file. Accepts JSON (an array of objects, or {reviews:[...]}) and CSV with a header
     * row; column names are matched loosely because every portal spells them differently.
     * Returns array(created, updated, skipped, errors).
     */
    public static function import($content, $source = 'other', $format = 'auto')
    {
        $content = trim((string) $content);
        if ($content === '') { throw new PrestaShopException('Nothing to import'); }
        if ($format === 'auto') { $format = ($content[0] === '[' || $content[0] === '{') ? 'json' : 'csv'; }
        $rows = $format === 'json' ? self::parseJson($content) : self::parseCsv($content);
        $out = array('created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => array());
        foreach ($rows as $n => $r) {
            try {
                $d = self::mapRow($r, $source);
                if ($d === null) { $out['skipped']++; continue; }
                $res = self::save($d);
                if ($res['created']) { $out['created']++; } else { $out['updated']++; }
            } catch (Exception $e) { $out['skipped']++; if (count($out['errors']) < 10) { $out['errors'][] = 'row '.($n + 1).': '.$e->getMessage(); } }
        }
        PulseCoreService::audit('pulsecrm', 'review_import', $out);
        Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'pulse_crm_review` SET imported_at=NOW() WHERE imported_at IS NULL AND source="'.pSQL($source).'"');
        return $out;
    }

    protected static function parseJson($content)
    {
        $j = json_decode($content, true);
        if (!is_array($j)) { throw new PrestaShopException('That file is not valid JSON'); }
        foreach (array('reviews', 'data', 'items', 'results') as $k) { if (isset($j[$k]) && is_array($j[$k])) { return $j[$k]; } }
        return isset($j[0]) ? $j : array($j);
    }

    protected static function parseCsv($content)
    {
        $lines = preg_split('/\r\n|\r|\n/', $content);
        $head = null; $out = array();
        foreach ($lines as $line) {
            if (trim($line) === '') { continue; }
            $cells = str_getcsv($line, strpos($line, ';') !== false && strpos($line, ',') === false ? ';' : ',');
            if ($head === null) { $head = array_map(function ($h) { return Tools::strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '_', $h), '_')); }, $cells); continue; }
            $row = array();
            foreach ($head as $i => $h) { $row[$h] = isset($cells[$i]) ? $cells[$i] : ''; }
            $out[] = $row;
        }
        return $out;
    }

    /** Loose column matching — "Rating", "score", "overall_rating" and "note" all mean the same thing. */
    protected static function mapRow(array $r, $source)
    {
        $pick = function (array $keys) use ($r) { foreach ($keys as $k) { if (isset($r[$k]) && trim((string) $r[$k]) !== '') { return trim((string) $r[$k]); } } return ''; };
        $rating = $pick(array('rating', 'score', 'overall_rating', 'overall', 'stars', 'note', 'average_score', 'reviewer_score'));
        if ($rating === '') { return null; }
        $rating = (float) str_replace(',', '.', $rating);
        $src = $pick(array('source', 'site', 'platform', 'channel'));
        return array(
            'source' => $src && isset(self::$sources[Tools::strtolower($src)]) ? Tools::strtolower($src) : $source,
            'external_id' => $pick(array('id', 'review_id', 'external_id', 'reference', 'uuid')),
            'url' => $pick(array('url', 'link', 'review_url', 'permalink')),
            'rating' => $rating,
            'rating_scale' => (int) $pick(array('rating_scale', 'scale', 'max_rating')),
            'title' => $pick(array('title', 'headline', 'review_title', 'summary')),
            'body' => trim($pick(array('body', 'text', 'review', 'comment', 'content', 'description')).' '
                .($pick(array('positive', 'pros', 'liked')) ? "\nLiked: ".$pick(array('positive', 'pros', 'liked')) : '')
                .($pick(array('negative', 'cons', 'disliked')) ? "\nDisliked: ".$pick(array('negative', 'cons', 'disliked')) : '')),
            'language' => $pick(array('language', 'lang', 'locale')),
            'author' => $pick(array('author', 'reviewer', 'name', 'user', 'guest_name', 'reviewer_name')),
            'review_date' => $pick(array('date', 'review_date', 'published', 'created_at', 'stay_date', 'submitted')),
            'trip_type' => $pick(array('trip_type', 'travel_type', 'traveller_type', 'stayed_as')),
        );
    }

    public static function respond($id, $text)
    {
        if (!trim($text)) { throw new PrestaShopException('A reply cannot be empty'); }
        Db::getInstance()->update('pulse_crm_review', array('responded' => 1, 'response_text' => pSQL($text, true), 'responded_at' => date('Y-m-d H:i:s'),
            'responded_by' => PulseCrmService::emp() ?: null, 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_crm_review='.(int) $id);
        PulseCoreService::audit('pulsecrm', 'review_respond', null, 'pulse_crm_review', (int) $id);
        return true;
    }

    /** Rolling average, volume and response performance by source. */
    public static function bySource($from, $to)
    {
        return Db::getInstance()->executeS('SELECT source, COUNT(*) reviews, ROUND(AVG(rating),2) avg_rating, MAX(rating_scale) scale, ROUND(AVG(rating_pct),1) pct,
                SUM(responded) responded, ROUND(SUM(responded)/COUNT(*)*100,1) response_rate,
                ROUND(AVG(TIMESTAMPDIFF(HOUR, CONCAT(review_date," 12:00:00"), responded_at)),1) avg_response_hours,
                SUM(sentiment="negative") negative FROM `'._DB_PREFIX_.'pulse_crm_review`
            WHERE review_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'" GROUP BY source ORDER BY reviews DESC');
    }

    /** Median response time in hours — the mean is meaningless once one review sits unanswered for a year. */
    public static function medianResponseHours($from, $to)
    {
        $rows = Db::getInstance()->executeS('SELECT TIMESTAMPDIFF(HOUR, CONCAT(review_date," 12:00:00"), responded_at) h FROM `'._DB_PREFIX_.'pulse_crm_review`
            WHERE responded=1 AND responded_at IS NOT NULL AND review_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'" ORDER BY h');
        if (!$rows) { return null; }
        $n = count($rows); $mid = (int) floor($n / 2);
        return $n % 2 ? (float) $rows[$mid]['h'] : round(((float) $rows[$mid - 1]['h'] + (float) $rows[$mid]['h']) / 2, 1);
    }

    public static function trend($months = 12)
    {
        return Db::getInstance()->executeS('SELECT DATE_FORMAT(review_date,"%Y-%m") ym, COUNT(*) reviews, ROUND(AVG(rating_pct),1) pct, SUM(sentiment="negative") negative, SUM(responded) responded
            FROM `'._DB_PREFIX_.'pulse_crm_review` WHERE review_date>=DATE_SUB(CURDATE(), INTERVAL '.(int) $months.' MONTH) GROUP BY ym ORDER BY ym');
    }

    public static function needsReply($limit = 50) { return self::all(null, null, null, true, $limit); }
    public static function remove($id) { return Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.'pulse_crm_review` WHERE id_pulse_crm_review='.(int) $id); }
}
