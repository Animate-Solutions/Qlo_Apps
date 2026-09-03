<?php
class PulseWaitlist
{
    public static function add(array $d)
    {
        Db::getInstance()->insert('pulse_waitlist', array('id_customer' => (int) $d['id_customer'] ?: null, 'guest_name' => pSQL($d['guest_name']), 'phone' => pSQL($d['phone']), 'email' => pSQL($d['email']), 'id_product' => (int) $d['id_product'], 'date_from' => pSQL($d['date_from']), 'date_to' => pSQL($d['date_to']), 'rooms' => (int) ($d['rooms'] ?: 1), 'priority' => (int) ($d['priority'] ?: 5), 'note' => pSQL($d['note']), 'date_add' => date('Y-m-d H:i:s')));
        return (int) Db::getInstance()->Insert_ID();
    }
    public static function queue($status = 'waiting,offered')
    {
        return Db::getInstance()->executeS('SELECT w.*, pl.name room_type FROM `'._DB_PREFIX_.'pulse_waitlist` w INNER JOIN `'._DB_PREFIX_.'product_lang` pl ON pl.id_product=w.id_product AND pl.id_lang='.(int) Context::getContext()->language->id.' WHERE w.status IN ("'.implode('","', array_map('pSQL', explode(',', $status))).'") ORDER BY w.priority, w.date_add');
    }
    /** Cron/hourly: for every waiting entry, check availability; if room(s) free, mark offered and notify guest. */
    public static function processOffers()
    {
        $n = 0;
        foreach (self::queue('waiting') as $w) {
            $av = PulseReservation::availability($w['id_product'], $w['date_from'], $w['date_to']);
            if ($av['available'] >= $w['rooms']) {
                Db::getInstance()->update('pulse_waitlist', array('status' => 'offered', 'offered_at' => date('Y-m-d H:i:s')), 'id_pulse_waitlist='.(int) $w['id_pulse_waitlist']);
                PulseComms::sendRaw($w['email'], $w['phone'], 'waitlist_offer', array('name' => $w['guest_name'], 'room_type' => $w['room_type'], 'from' => $w['date_from'], 'to' => $w['date_to']));
                $n++;
            }
        }
        // expire offers not converted within 24h
        Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'pulse_waitlist` SET status="expired" WHERE status="offered" AND offered_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)');
        return $n;
    }
    /** Convert an entry into a real reservation. */
    public static function convert($id, array $guestExtra = array())
    {
        $w = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_waitlist` WHERE id_pulse_waitlist='.(int) $id);
        $parts = explode(' ', $w['guest_name'], 2);
        $res = PulseReservation::create(array_merge(array('firstname' => $parts[0], 'lastname' => isset($parts[1]) ? $parts[1] : '-', 'email' => $w['email'], 'phone' => $w['phone']), $guestExtra), $w['date_from'], $w['date_to'], array_fill(0, (int) $w['rooms'], array('id_product' => $w['id_product'], 'adults' => 1, 'children' => 0)), array('source' => 'waitlist'));
        Db::getInstance()->update('pulse_waitlist', array('status' => 'booked'), 'id_pulse_waitlist='.(int) $id);
        return $res;
    }
}
