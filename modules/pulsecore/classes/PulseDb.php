<?php
/**
 * The database handle every Pulse module uses instead of Db::getInstance().
 *
 * It is a thin pass-through with one job: make sure a statement can only reach the hotel the session
 * is working in. Reads go through PulseHotelScope, which adds the hotel predicate to the SQL. Writes
 * are handled here, where the column list is known: an insert is stamped with the current hotel, and
 * an update or delete gets the hotel added to its WHERE so a crafted id cannot reach another
 * property's row.
 *
 * The signatures match Db's, so a call site changes by its first token and nothing else:
 *
 *     Db::getInstance()->executeS($sql)      ->   PulseDb::executeS($sql)
 *     Db::getInstance()->insert($t, $data)   ->   PulseDb::insert($t, $data)
 *
 * Where a query is legitimately group-wide — licensing, the hotel access screen, the statutory
 * reference data payroll shares across the group — wrap it:
 *
 *     PulseDb::unscoped(function () { ... });      // or PulseHotelScope::disable()/enable()
 */
class PulseDb
{
    protected static $overrideHotel = null;

    public static function db() { return Db::getInstance(); }

    /** A scoped handle for code that keeps the connection in a variable. See PulseDbHandle. */
    public static function handle()
    {
        static $h = null;
        if ($h === null) { $h = new PulseDbHandle(); }
        return $h;
    }

    /* ---------------- scope control ---------------- */

    /** Run a callback with scoping off. Restores the previous state even if the callback throws. */
    public static function unscoped($callback)
    {
        $was = PulseHotelScope::isEnabled();
        PulseHotelScope::disable();
        try {
            $r = call_user_func($callback);
        } catch (Exception $e) {
            if ($was) { PulseHotelScope::enable(); }
            throw $e;
        }
        if ($was) { PulseHotelScope::enable(); }
        return $r;
    }

    /**
     * Run a callback as though the session were in another hotel. Used by the night audit, the
     * group-level reports and the clone-to-new-hotel routine, which each have a legitimate reason to
     * touch a hotel other than the one on screen.
     */
    public static function inHotel($idHotel, $callback)
    {
        $prev = self::$overrideHotel;
        self::$overrideHotel = (int) $idHotel;
        try {
            $r = call_user_func($callback);
        } catch (Exception $e) {
            self::$overrideHotel = $prev;
            throw $e;
        }
        self::$overrideHotel = $prev;
        return $r;
    }

    /** The hotel writes are stamped with and reads are filtered by. */
    public static function hotel()
    {
        if (self::$overrideHotel !== null) { return (int) self::$overrideHotel; }
        return class_exists('PulseHotelContext') ? (int) PulseHotelContext::id() : 0;
    }

    protected static function scope($sql)
    {
        return PulseHotelScope::apply($sql, self::hotel());
    }

    /* ---------------- reads ---------------- */

    public static function executeS($sql, $array = true, $useCache = true)
    {
        return self::db()->executeS(self::scope($sql), $array, $useCache);
    }

    public static function getRow($sql, $useCache = true)
    {
        return self::db()->getRow(self::scope($sql), $useCache);
    }

    public static function getValue($sql, $useCache = true)
    {
        return self::db()->getValue(self::scope($sql), $useCache);
    }

    public static function numRows()
    {
        return self::db()->numRows();
    }

    /* ---------------- writes ---------------- */

    /** Raw execute. Scoped like a read, because it is usually an UPDATE or a DELETE. */
    public static function execute($sql, $useCache = true)
    {
        return self::db()->execute(self::scope($sql), $useCache);
    }

    /** DDL and anything else that must reach MySQL exactly as written. */
    public static function raw($sql, $useCache = true)
    {
        return self::db()->execute($sql, $useCache);
    }

    /**
     * Insert, stamped with the session's hotel.
     *
     * A row whose hotel is set explicitly is left alone — the night audit and the clone routine both
     * write into a named hotel. A row with no hotel while no hotel is chosen is refused rather than
     * written as hotel 0, because an unstamped row is invisible to every screen afterwards.
     */
    public static function insert($table, $data, $nullValues = false, $useCache = true, $type = Db::INSERT, $addPrefix = true)
    {
        if (PulseHotelScope::isScoped(($addPrefix ? _DB_PREFIX_ : '').$table)) {
            $data = self::stampRows($table, $data);
        }
        return self::db()->insert($table, $data, $nullValues, $useCache, $type, $addPrefix);
    }

    public static function insertOnDuplicate($table, $data, $nullValues = false, $useCache = true)
    {
        if (PulseHotelScope::isScoped(_DB_PREFIX_.$table)) { $data = self::stampRows($table, $data); }
        return self::db()->insert($table, $data, $nullValues, $useCache, Db::ON_DUPLICATE_KEY);
    }

    /**
     * Accepts both shapes Db::insert() does: one row, or a list of rows.
     *
     * The two are told apart by the keys, not by whether the first value is an array. A single row's
     * keys are column names, and one of its values may legitimately be an array — PrestaShop's
     * literal form, array('type' => 'sql', 'value' => 'NULL'), which Pulse uses for nullable columns.
     * Testing the first value would read such a row as a list of rows and then index into a string.
     */
    protected static function stampRows($table, $data)
    {
        if (!is_array($data) || !$data) { return $data; }
        $isList = isset($data[0]) && is_array($data[0]);
        $rows = $isList ? $data : array($data);
        $h = self::hotel();
        foreach ($rows as $k => $row) {
            if (isset($row[PulseHotelScope::COLUMN]) && (int) $row[PulseHotelScope::COLUMN] > 0) { continue; }
            if ($h <= 0) {
                throw new PrestaShopException('Pulse: refusing to write to '.$table
                    .' with no hotel chosen — the row would belong to no property and be invisible everywhere.');
            }
            $rows[$k][PulseHotelScope::COLUMN] = $h;
        }
        return $isList ? $rows : $rows[0];
    }

    /**
     * Update, with the hotel added to the WHERE so an id from another property matches nothing.
     * The data itself is never re-stamped: an update must not be able to move a row between hotels.
     */
    public static function update($table, $data, $where = '', $limit = 0, $nullValues = false, $useCache = true, $addPrefix = true)
    {
        if (PulseHotelScope::isScoped(($addPrefix ? _DB_PREFIX_ : '').$table)) {
            unset($data[PulseHotelScope::COLUMN]);
            $where = self::andHotel($where);
        }
        return self::db()->update($table, $data, $where, $limit, $nullValues, $useCache, $addPrefix);
    }

    public static function delete($table, $where = '', $limit = 0, $useCache = true, $addPrefix = true)
    {
        if (PulseHotelScope::isScoped(($addPrefix ? _DB_PREFIX_ : '').$table)) {
            $where = self::andHotel($where);
        }
        return self::db()->delete($table, $where, $limit, $useCache, $addPrefix);
    }

    /** The hotel clause for a WHERE string, matching nothing when no hotel is chosen. */
    public static function andHotel($where)
    {
        $h = self::hotel();
        $clause = $h > 0 ? '`'.PulseHotelScope::COLUMN.'` = '.(int) $h : '1 = 0';
        return trim($where) === '' ? $clause : '('.$where.') AND '.$clause;
    }

    /* ---------------- pass-through ---------------- */

    public static function getMsgError() { return self::db()->getMsgError(); }
    public static function getNumberError() { return self::db()->getNumberError(); }
    public static function Insert_ID() { return self::db()->Insert_ID(); }
    public static function getInsertId() { return self::db()->Insert_ID(); }
    public static function Affected_Rows() { return self::db()->Affected_Rows(); }
    public static function escape($s, $html = false, $bq = false) { return self::db()->escape($s, $html, $bq); }
}
