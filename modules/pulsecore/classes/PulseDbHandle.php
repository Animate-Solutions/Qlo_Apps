<?php
/**
 * An object-shaped PulseDb, for the places that keep a handle in a variable.
 *
 * A fair amount of Pulse code opens a method with `$db = Db::getInstance();` and then calls
 * `$db->executeS(...)` a dozen times. Those call sites are `$db->`, not `Db::getInstance()->`, so
 * swapping the class name at each call would have missed them. Instead the handle itself changes —
 * `$db = PulseDb::handle();` — and every `$db->` call from then on is scoped, with no other edit.
 */
class PulseDbHandle
{
    public function executeS($sql, $array = true, $useCache = true) { return PulseDb::executeS($sql, $array, $useCache); }
    public function getRow($sql, $useCache = true) { return PulseDb::getRow($sql, $useCache); }
    public function getValue($sql, $useCache = true) { return PulseDb::getValue($sql, $useCache); }
    public function execute($sql, $useCache = true) { return PulseDb::execute($sql, $useCache); }
    public function insert($table, $data, $nullValues = false, $useCache = true, $type = Db::INSERT, $addPrefix = true)
    {
        return PulseDb::insert($table, $data, $nullValues, $useCache, $type, $addPrefix);
    }
    public function update($table, $data, $where = '', $limit = 0, $nullValues = false, $useCache = true, $addPrefix = true)
    {
        return PulseDb::update($table, $data, $where, $limit, $nullValues, $useCache, $addPrefix);
    }
    public function delete($table, $where = '', $limit = 0, $useCache = true, $addPrefix = true)
    {
        return PulseDb::delete($table, $where, $limit, $useCache, $addPrefix);
    }
    public function numRows() { return PulseDb::numRows(); }
    public function Insert_ID() { return PulseDb::Insert_ID(); }
    public function Affected_Rows() { return PulseDb::Affected_Rows(); }
    public function getMsgError() { return PulseDb::getMsgError(); }
    public function getNumberError() { return PulseDb::getNumberError(); }
    public function escape($s, $html = false, $bq = false) { return PulseDb::escape($s, $html, $bq); }
}
