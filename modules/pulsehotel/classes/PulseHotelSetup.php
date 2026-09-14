<?php
/**
 * Give a new property its own copy of the reference data.
 *
 * Operations are separate per hotel, which means each property owns its charge codes, its chart of
 * accounts, its menu, its pay elements, its laundry price list and the rest — not a shared set. A
 * property created today therefore starts empty, and an empty property cannot take a booking: there
 * is no ROOM charge code to post against and no revenue account to post it to.
 *
 * So a new hotel is seeded by copying an existing one. The copy has to keep the rows joined up:
 * pulse_pos_item points at pulse_pos_category by its auto-increment id, so the copied items must
 * point at the copied categories, not the originals. That is what the id map below is for.
 *
 * What is NOT copied: anything transactional. A new property inherits the shape of the business, not
 * its history — no folios, no journals, no punches, no bookings.
 */
class PulseHotelSetup
{
    /** Tables that are the property's operating history rather than its setup, matched on the name. */
    protected static $transactionalHints = array(
        'folio', 'journal', 'invoice', 'receipt', 'payment', 'payslip', 'punch', 'timesheet', 'booking',
        'audit', 'log', 'session', 'queue', 'txn', 'transaction', 'movement', 'reservation', 'response',
        'run', 'batch', 'ari', 'exception', 'task', 'ticket', 'case', 'review', 'message', 'command',
        'event', 'balance', 'depreciation', 'remittance', 'count', 'grn', 'po_line', 'request', 'wht',
        'vat', 'einvoice', 'bill', 'expense', 'work_order', 'trace', 'comms', 'stock', 'batch', 'post',
        'recipient', 'member', 'consent', 'points', 'survey_answer', 'clock', 'roster', 'enrolment',
        'allot', 'loan', 'tronc', 'advance', 'claim', 'block', 'waitlist', 'status', 'cursor', 'play',
        'feedback', 'sale', 'check', 'order', 'shift', 'move', 'card', 'key', 'door_event', 'daily',
        'inv_po', 'opportunity', 'requisition', 'transfer', 'adjustment', 'writeoff',
        // The rate limiters are live counters, not configuration.
        'gp_rate', 'hr_rate',
    );

    /**
     * Columns that mark a table as being about a particular person, room, guest or booking rather
     * than about how the property runs.
     *
     * This is the rule that matters, and it is stronger than any list of names: reference data never
     * points at an individual. An employee's contract, a guest's stated preference, a door fitted to
     * room 214 — each belongs to one property's people and rooms, and the new property has its own.
     * Copying them would put the first hotel's staff and guests on the second hotel's books.
     */
    protected static $individualColumns = array(
        'id_employee', 'id_customer', 'id_room', 'id_htl_booking', 'id_order', 'id_address',
        'id_pulse_hr_employee', 'id_pulse_ta_staff', 'id_pulse_pr_employee', 'id_pulse_crm_member',
        'id_pulse_guest_profile', 'id_pulse_company', 'id_pulse_folio',
    );

    /** Tables that look transactional by name but are genuinely setup, so the hint list must not win. */
    protected static $alwaysCopy = array(
        'pulse_setting', 'pulse_charge_code', 'pulse_acc_account', 'pulse_acc_map', 'pulse_acc_period',
        'pulse_acc_asset_class', 'pulse_acc_bank_account', 'pulse_pos_order_type',
        'pulse_pr_element', 'pulse_pr_paygroup', 'pulse_expense_category', 'pulse_inv_unit_conversion',
    );

    /* ---------------- what gets copied ---------------- */

    /**
     * The reference tables, parents before children.
     *
     * A table is reference data when it is scoped to a hotel and is not one of the property's
     * transaction records. The ordering comes from the columns themselves: a table with a column
     * `id_<other table>` is copied after that other table, so the id map is ready when it is needed.
     */
    public static function referenceTables()
    {
        $scoped = array();
        foreach (array_keys(PulseHotelScope::tables()) as $t) {
            if (strpos($t, 'pulse_') !== 0) { continue; }   // QloApps' own tables are not ours to clone
            if (in_array($t, self::$alwaysCopy)) { $scoped[$t] = true; continue; }
            if (self::looksTransactional($t) || self::isAboutIndividuals($t)) { continue; }
            $scoped[$t] = true;
        }
        // Drop anything that is not actually installed, then sort parents first.
        $present = array();
        foreach (PulseDb::unscoped(function () {
            return PulseDb::executeS('SELECT TABLE_NAME FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE "'._DB_PREFIX_.'pulse%"');
        }) as $r) {
            $present[substr($r['TABLE_NAME'], strlen(_DB_PREFIX_))] = true;
        }
        $tables = array_keys(array_intersect_key($scoped, $present));
        return self::sortByDependency($tables);
    }

    protected static function looksTransactional($table)
    {
        foreach (self::$transactionalHints as $h) {
            if (strpos($table, $h) !== false) { return true; }
        }
        return false;
    }

    /** True when the table records something about a particular person, room, guest or booking. */
    public static function isAboutIndividuals($table)
    {
        $cols = self::columns($table);
        foreach (self::$individualColumns as $c) {
            if (isset($cols[$c])) { return true; }
        }
        return false;
    }

    /** Columns of $table, as name => true. */
    public static function columns($table)
    {
        $out = array();
        foreach (PulseDb::unscoped(function () use ($table) {
            return PulseDb::executeS('SELECT COLUMN_NAME, COLUMN_KEY, EXTRA FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "'._DB_PREFIX_.pSQL($table).'" ORDER BY ORDINAL_POSITION');
        }) as $c) {
            $out[$c['COLUMN_NAME']] = $c;
        }
        return $out;
    }

    /** The auto-increment column of $table, or '' when it has none (a composite-key link table). */
    public static function autoColumn($table)
    {
        foreach (self::columns($table) as $name => $c) {
            if (strpos($c['EXTRA'], 'auto_increment') !== false) { return $name; }
        }
        return '';
    }

    /**
     * Parents before children: a table whose column names point at another table in the set is copied
     * after it. Anything left in a cycle is appended, and its references are remapped best-effort.
     */
    protected static function sortByDependency(array $tables)
    {
        $set = array_flip($tables);
        $deps = array();
        foreach ($tables as $t) {
            $deps[$t] = array();
            foreach (array_keys(self::columns($t)) as $col) {
                if (strpos($col, 'id_') !== 0) { continue; }
                $target = substr($col, 3);
                if ($target !== $t && isset($set[$target])) { $deps[$t][$target] = true; }
            }
        }
        $out = array();
        $done = array();
        $guard = 0;
        while (count($out) < count($tables) && ++$guard < 50) {
            foreach ($tables as $t) {
                if (isset($done[$t])) { continue; }
                foreach (array_keys($deps[$t]) as $d) {
                    if (!isset($done[$d])) { continue 2; }
                }
                $out[] = $t;
                $done[$t] = true;
            }
        }
        foreach ($tables as $t) { if (!isset($done[$t])) { $out[] = $t; } }
        return $out;
    }

    /* ---------------- the copy ---------------- */

    /**
     * Copy the reference data of $idSource into $idTarget.
     *
     * Returns array(table => rows copied). Runs in one transaction: a half-seeded property is worse
     * than an empty one, because it looks ready.
     */
    public static function cloneTo($idTarget, $idSource = null, $dryRun = false)
    {
        $idTarget = (int) $idTarget;
        $idSource = (int) ($idSource ? $idSource : self::suggestSource($idTarget));
        if (!$idTarget || !$idSource || $idTarget === $idSource) {
            throw new PrestaShopException('Choose a different property to copy from.');
        }
        foreach (array($idTarget, $idSource) as $h) {
            if (!PulseDb::unscoped(function () use ($h) {
                return PulseDb::getValue('SELECT id FROM `'._DB_PREFIX_.'htl_branch_info` WHERE id = '.(int) $h);
            })) { throw new PrestaShopException('Unknown hotel '.(int) $h); }
        }

        $tables = self::referenceTables();
        $maps = array();     // table => array(old id => new id)
        $counts = array();

        if (!$dryRun) { PulseDb::raw('START TRANSACTION'); }
        try {
            foreach ($tables as $table) {
                $counts[$table] = self::copyTable($table, $idSource, $idTarget, $maps, $dryRun);
            }
            if (!$dryRun) { PulseDb::raw('COMMIT'); }
        } catch (Exception $e) {
            if (!$dryRun) { PulseDb::raw('ROLLBACK'); }
            throw new PrestaShopException('Copying '.$table.' failed: '.$e->getMessage());
        }

        if (!$dryRun) {
            // The record of a property being seeded belongs to that property, not to whoever ran it.
            PulseDb::inHotel($idTarget, function () use ($idSource, $idTarget, $counts) {
                PulseCoreService::audit('pulsehotel', 'seeded', array('from' => $idSource, 'to' => $idTarget,
                    'rows' => array_sum($counts)), 'htl_branch_info', $idTarget);
            });
        }
        return $counts;
    }

    /** One table's rows, with its id map recorded and its parents' ids remapped. */
    protected static function copyTable($table, $idSource, $idTarget, array &$maps, $dryRun)
    {
        $cols = self::columns($table);
        if (!isset($cols['id_hotel'])) { return 0; }
        $auto = self::autoColumn($table);

        $rows = PulseDb::unscoped(function () use ($table, $idSource) {
            return PulseDb::executeS('SELECT * FROM `'._DB_PREFIX_.pSQL($table).'` WHERE `id_hotel` = '.(int) $idSource);
        });
        if (!$rows) { return 0; }

        // Already seeded? Leave it alone rather than doubling it.
        $existing = (int) PulseDb::unscoped(function () use ($table, $idTarget) {
            return PulseDb::getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.pSQL($table).'` WHERE `id_hotel` = '.(int) $idTarget);
        });
        if ($existing) { return 0; }
        if ($dryRun) { return count($rows); }

        $copied = 0;
        foreach ($rows as $row) {
            $oldId = $auto !== '' && isset($row[$auto]) ? $row[$auto] : null;
            if ($auto !== '') { unset($row[$auto]); }
            $row['id_hotel'] = (int) $idTarget;

            // Point the copy at the copies of its parents.
            foreach ($row as $col => $val) {
                if (strpos($col, 'id_') !== 0 || $col === 'id_hotel' || !$val) { continue; }
                $parent = substr($col, 3);
                if (isset($maps[$parent][$val])) { $row[$col] = $maps[$parent][$val]; }
            }

            foreach ($row as $col => $val) {
                $row[$col] = $val === null ? array('type' => 'sql', 'value' => 'NULL') : pSQL($val, true);
            }
            // $nullValues stays false: real NULLs are already carried as the SQL-literal form above,
            // and turning it on would additionally rewrite every empty string as NULL, which a NOT NULL
            // column rejects — a copied row must come out exactly as the original went in.
            if (!PulseDb::unscoped(function () use ($table, $row) {
                return PulseDb::insert($table, $row, false);
            })) {
                throw new Exception(PulseDb::getMsgError());
            }
            if ($auto !== '' && $oldId !== null) { $maps[$table][$oldId] = (int) PulseDb::Insert_ID(); }
            ++$copied;
        }
        return $copied;
    }

    /** The property to copy from by default: the oldest one that actually has reference data. */
    public static function suggestSource($exclude = 0)
    {
        return (int) PulseDb::unscoped(function () use ($exclude) {
            // No trailing LIMIT: getValue() appends its own, and MySQL rejects "LIMIT 1 LIMIT 1".
            return PulseDb::getValue('SELECT c.id_hotel FROM `'._DB_PREFIX_.'pulse_charge_code` c
                WHERE c.id_hotel <> '.(int) $exclude.' GROUP BY c.id_hotel ORDER BY COUNT(*) DESC, c.id_hotel');
        });
    }

    /** Which properties have no reference data yet — what the setup screen warns about. */
    public static function unseeded()
    {
        $out = array();
        foreach (PulseDb::unscoped(function () {
            return PulseDb::executeS('SELECT hbi.id, hbl.hotel_name,
                    (SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_charge_code` c WHERE c.id_hotel = hbi.id) charge_codes,
                    (SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_acc_account` a WHERE a.id_hotel = hbi.id) accounts
                FROM `'._DB_PREFIX_.'htl_branch_info` hbi
                LEFT JOIN `'._DB_PREFIX_.'htl_branch_info_lang` hbl ON hbl.id = hbi.id AND hbl.id_lang = '
                .(int) Context::getContext()->language->id.'
                WHERE hbi.active = 1 ORDER BY hbi.id');
        }) as $h) {
            if (!(int) $h['charge_codes'] || !(int) $h['accounts']) { $out[] = $h; }
        }
        return $out;
    }
}
