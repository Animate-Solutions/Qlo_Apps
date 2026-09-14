<?php
/**
 * Static service facade for the Pulse suite.
 * Other modules call PulseCoreService::event(), ::audit(), ::setting().
 */
class PulseCoreService
{
    /** Raise a suite-wide event. Every Pulse module hooked to the event name receives $params. */
    public static function event($name, array $params = array())
    {
        $params['_event'] = $name;
        $params['_time'] = time();
        Hook::exec($name, $params);             // module-specific hook (e.g. actionPulseCheckIn)
        Hook::exec('actionPulseEvent', $params); // generic firehose
    }

    /**
     * Append an audit row. $payload is JSON-encoded.
     *
     * Audit rows are per property, but some of what is worth recording happens above any one of them —
     * a licence activated, a hotel access grant changed, a new property seeded. Those are filed against
     * hotel 0, meaning "the group", rather than refused. Failing to file an audit row must never undo
     * the thing being audited, so a write that cannot happen is dropped rather than thrown.
     */
    public static function audit($module, $event, $payload = null, $entity = null, $idEntity = null)
    {
        $ctx = Context::getContext();
        if ((int) PulseDb::hotel() <= 0) {
            return PulseDb::inHotel(0, function () use ($module, $event, $payload, $entity, $idEntity, $ctx) {
                return PulseDb::unscoped(function () use ($module, $event, $payload, $entity, $idEntity, $ctx) {
                    return Db::getInstance()->insert('pulse_audit', array(
                        'module' => pSQL($module), 'event' => pSQL($event),
                        'id_employee' => isset($ctx->employee) ? (int) $ctx->employee->id : null,
                        'entity' => pSQL($entity), 'id_entity' => (int) $idEntity, 'id_hotel' => 0,
                        'payload' => pSQL(is_string($payload) ? $payload : json_encode($payload), true),
                        'date_add' => date('Y-m-d H:i:s'),
                    ));
                });
            });
        }
        return PulseDb::insert('pulse_audit', array(
            'module'      => pSQL($module),
            'event'       => pSQL($event),
            'id_employee' => isset($ctx->employee) ? (int) $ctx->employee->id : null,
            'entity'      => pSQL($entity),
            'id_entity'   => (int) $idEntity,
            'payload'     => pSQL(is_string($payload) ? $payload : json_encode($payload), true),
            'date_add'    => date('Y-m-d H:i:s'),
        ));
    }

    /**
     * Get or set a per-module setting.
     *
     * Settings are per property — each hotel runs its own business date, its own check-out time, its
     * own numbering — but a property is not born knowing all of them. So a read that finds nothing for
     * the current hotel falls back to the group row (id_hotel 0), which is what the installer seeds and
     * what a new property inherits until somebody changes it there. A write always lands on the current
     * hotel: changing a setting in one property must never quietly change it in the others.
     *
     * Pass $group = true to read or write the group row itself, which is what the installers do.
     */
    public static function setting($module, $name, $value = null, $group = false)
    {
        $hotel = $group ? 0 : (int) PulseDb::hotel();
        if ($value === null) {
            $read = function ($idHotel) use ($module, $name) {
                return PulseDb::unscoped(function () use ($module, $name, $idHotel) {
                    return PulseDb::getValue('SELECT `value` FROM `'._DB_PREFIX_.'pulse_setting`
                        WHERE `module`="'.pSQL($module).'" AND `name`="'.pSQL($name).'" AND `id_hotel`='.(int) $idHotel);
                });
            };
            $v = $read($hotel);
            if ($v !== false && $v !== null && $v !== '') { return $v; }
            return $hotel === 0 ? $v : $read(0);
        }
        return PulseDb::unscoped(function () use ($module, $name, $value, $hotel) {
            return PulseDb::execute('INSERT INTO `'._DB_PREFIX_.'pulse_setting` (`module`,`name`,`value`,`id_hotel`,`date_upd`)
                VALUES ("'.pSQL($module).'","'.pSQL($name).'","'.pSQL($value, true).'",'.(int) $hotel.',NOW())
                ON DUPLICATE KEY UPDATE `value`=VALUES(`value`), `date_upd`=NOW()');
        });
    }

    /** The group-level default for a setting: what a new property inherits until it is given its own. */
    public static function groupSetting($module, $name, $value = null)
    {
        return self::setting($module, $name, $value, true);
    }

    /**
     * Run $callback once for every active hotel, each pass inside that hotel's scope.
     *
     * A cron has no back-office session, so there is no cookie to say which property it is working in,
     * and hotel 0 means "match nothing" everywhere — a job left to run that way reads nothing and is
     * refused every write. So the jobs work through the properties instead: each pass runs inside
     * PulseDb::inHotel() with PulseHotelContext::assume() set, which is what the two scoping layers
     * read (PulseDb stamps and filters from the first, hand-written PulseHotelContext::sql() fragments
     * from the second), so the pass sees exactly what an operator sitting in that hotel would.
     *
     * One property failing must not cost the others their run, so an exception is caught, recorded
     * against its hotel and the loop carries on. Only Exception is caught, deliberately: an Error
     * escapes before PulseDb::inHotel() has restored its override, and carrying on from there would
     * write the remaining hotels' rows through a scope nobody can vouch for.
     *
     * The callback is called as $callback($idHotel, $hotelName). The summary it returns is what the
     * caller logs: array('hotels','ok','failed','results' => id_hotel => array('id_hotel','name',
     * 'ok','result','error')), where 'result' is whatever the callback gave back for that hotel.
     */
    public static function forEachHotel($callback)
    {
        $ctx = Context::getContext();
        $idLang = isset($ctx->language) && $ctx->language ? (int) $ctx->language->id : (int) Configuration::get('PS_LANG_DEFAULT');
        // htl_branch_info is group-level by design — it is the list of properties — so it is read raw.
        $hotels = Db::getInstance()->executeS('SELECT hbi.id AS id_hotel, hbl.hotel_name
            FROM `'._DB_PREFIX_.'htl_branch_info` hbi
            LEFT JOIN `'._DB_PREFIX_.'htl_branch_info_lang` hbl ON hbl.id = hbi.id AND hbl.id_lang = '.$idLang.'
            WHERE hbi.active = 1 ORDER BY hbi.id');

        $summary = array('hotels' => 0, 'ok' => 0, 'failed' => 0, 'results' => array());
        // pulsehotel carries the request-level context; without it PulseDb::inHotel() still scopes
        // every query that goes through PulseDb, which is all a cron issues.
        $hasContext = class_exists('PulseHotelContext');
        $restore = $hasContext ? (int) PulseHotelContext::id() : 0;
        foreach ((array) $hotels as $h) {
            $id = (int) $h['id_hotel'];
            $name = !empty($h['hotel_name']) ? $h['hotel_name'] : 'Hotel '.$id;
            $row = array('id_hotel' => $id, 'name' => $name, 'ok' => true, 'result' => null, 'error' => '');
            try {
                $row['result'] = PulseDb::inHotel($id, function () use ($callback, $id, $name, $hasContext) {
                    if ($hasContext) { PulseHotelContext::assume($id); }
                    return call_user_func($callback, $id, $name);
                });
                ++$summary['ok'];
            } catch (Exception $e) {
                $row['ok'] = false;
                $row['error'] = $e->getMessage();
                ++$summary['failed'];
            }
            ++$summary['hotels'];
            $summary['results'][$id] = $row;
        }
        if ($hasContext) { PulseHotelContext::assume($restore); }
        return $summary;
    }

    /* ---------------- the hotel a public request acts for ---------------- */

    /**
     * A public front controller has no back-office session, so there is no cookie to say which property
     * it is working in — and hotel 0 means "match nothing" everywhere, so a page left that way reads
     * nothing and is refused every write. The property has to come out of the request itself: the token
     * in the link the guest was sent, the device that dialled in, the property the screen's own URL
     * names. The helpers below are what each module's resolution is built from.
     *
     * They are also the only unscoped reads on the public surface, and deliberately so: the identifier
     * the request proves lives in a scoped table, so a scoped read of it comes back empty and the page
     * could never find its property at all. Each read is one indexed equality that returns an id and
     * never a row — the caller re-reads the record through the ordinary scoped path once the hotel is
     * settled, so a forged token still has to survive the normal query.
     */

    /** $idHotel if it is a real, active property; 0 otherwise. An id from a query string goes through here first. */
    public static function activeHotel($idHotel)
    {
        $idHotel = (int) $idHotel;
        if ($idHotel <= 0) { return 0; }
        return (int) PulseDb::unscoped(function () use ($idHotel) {
            return (int) PulseDb::getValue('SELECT `id` FROM `'._DB_PREFIX_.'htl_branch_info` WHERE `id` = '.$idHotel.' AND `active` = 1');
        });
    }

    /**
     * The hotel that owns the row where $column = $value in $table, or 0 when nothing owns it.
     *
     * Rows in two properties answering to the same identifier means the identifier names no property at
     * all, so an ambiguous match returns 0 rather than picking one: several of these tables are unique
     * per hotel rather than group-wide, and choosing between them is the exact failure being prevented.
     */
    public static function hotelOf($table, $column, $value)
    {
        if ((string) $value === '') { return 0; }
        $rows = PulseDb::unscoped(function () use ($table, $column, $value) {
            return PulseDb::executeS('SELECT DISTINCT `id_hotel` FROM `'._DB_PREFIX_.bqSQL($table).'`
                WHERE `'.bqSQL($column).'` = "'.pSQL($value).'" LIMIT 2');
        });
        return is_array($rows) && count($rows) === 1 ? self::activeHotel($rows[0]['id_hotel']) : 0;
    }

    /** The property a URL names, in either spelling: `hotel` for the screens, `id_hotel` as the API spells it. */
    public static function namedHotel()
    {
        $id = (int) Tools::getValue('hotel', 0);
        return $id > 0 ? $id : (int) Tools::getValue('id_hotel', 0);
    }

    /** Put this request into $idHotel for the rest of its life, or return 0 so the caller can refuse it. */
    public static function enterHotel($idHotel)
    {
        $id = self::activeHotel($idHotel);
        if ($id && class_exists('PulseHotelContext')) { PulseHotelContext::assume($id); }
        return $id;
    }

    /**
     * Enter the hotel a screen's own URL names.
     *
     * Some public surfaces carry no token at all: a TV that has never paired, the staff portal shell, a
     * POS tablet, a kitchen display. Each is a fixture of one property and its URL is configured once
     * with that property in it, so the URL is what identifies it. A query string is the visitor's to
     * write, so it is never taken at face value — it must name an active property, and where the request
     * also carries a signed-in employee it must be one that employee holds a grant for. With no property
     * named, a signed-in employee's own current hotel stands in; a request with neither gets 0.
     */
    public static function enterHotelNamed($idHotel)
    {
        $idHotel = (int) $idHotel;
        $emp = class_exists('PulseHotelContext') ? PulseHotelContext::emp() : 0;
        if ($idHotel > 0) {
            if ($emp && !PulseHotelContext::mayUse($idHotel)) { return 0; }
            return self::enterHotel($idHotel);
        }
        return $emp ? self::enterHotel(PulseHotelContext::id()) : 0;
    }

    /**
     * End a public request that could not establish its property.
     *
     * A page with no hotel reads nothing and is refused every write, so left alone it would render as an
     * empty shell — a screen that looks like it is working and shows nobody's data. Say so plainly and
     * stop instead. $what says which link or device could not be placed, so the person holding it knows
     * what to tell the desk.
     */
    public static function refuseNoHotel($what)
    {
        http_response_code(400);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        die('<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<title>Property not identified</title></head>'
            .'<body style="font:16px/1.6 system-ui,-apple-system,Segoe UI,Arial,sans-serif;margin:3rem auto;max-width:34rem;padding:0 1rem">'
            .'<h1 style="font-size:1.25rem">We could not tell which property this page is for</h1>'
            .'<p>'.htmlspecialchars((string) $what, ENT_QUOTES, 'UTF-8').'</p></body></html>');
    }

    /** Current hotel business date (rolled by night audit); falls back to today. */
    public static function businessDate()
    {
        $d = self::setting('pulsefrontdesk', 'business_date');
        return $d ? $d : date('Y-m-d');
    }

    /** Symmetric encryption for gateway/lock credentials using the shop cookie key. */
    public static function encrypt($plain)
    {
        $c = new PhpEncryption(_NEW_COOKIE_KEY_);
        return $c->encrypt($plain);
    }

    public static function decrypt($cipher)
    {
        $c = new PhpEncryption(_NEW_COOKIE_KEY_);
        return $c->decrypt($cipher);
    }
}
