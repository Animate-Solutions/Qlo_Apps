<?php
/**
 * Rewrites a SQL statement so it can only see one hotel.
 *
 * Pulse is 2,300-odd hand-written queries across seventeen modules. Adding a hotel predicate to each
 * one by hand would be seventeen chances in a row to miss a line, and a missed line is one property
 * reading another's guests. So the predicate is added in one place instead: every statement that
 * touches a hotel-scoped Pulse table gets `id_hotel = <the session's hotel>` before it reaches MySQL.
 *
 * What the rewriter guarantees:
 *   - each SELECT, UPDATE and DELETE gets a predicate for every scoped table it names, including
 *     tables named inside subqueries, which are treated as their own scope;
 *   - a LEFT or RIGHT joined table gets its predicate in that join's ON clause, never in the WHERE,
 *     because a WHERE predicate on the null side of an outer join silently turns it into an inner one;
 *   - statements that touch no scoped table are returned byte-for-byte unchanged;
 *   - anything the tokenizer cannot account for is left alone and recorded, rather than guessed at.
 *
 * What it does not do: INSERT. Inserts are stamped by PulseDb::insert(), which knows the column list.
 *
 * The class is deliberately paranoid about string literals: this codebase writes SQL string constants
 * with double quotes as often as single, so both are opaque to the scanner, as are backticked names.
 */
class PulseHotelScope
{
    const COLUMN = 'id_hotel';

    protected static $tables = null;
    protected static $skipped = array();
    protected static $enabled = true;

    /* ---------------- which tables are scoped ---------------- */

    /** The scoped table names, without the database prefix. Loaded once from the generated manifest. */
    public static function tables()
    {
        if (self::$tables !== null) { return self::$tables; }
        $file = dirname(__FILE__).'/scoped_tables.php';
        $list = file_exists($file) ? include $file : array();
        self::$tables = array();
        foreach ($list as $t) { self::$tables[$t] = true; }
        return self::$tables;
    }

    /** True when this table — with or without the prefix — is one we scope. */
    public static function isScoped($table)
    {
        $t = trim($table, '`');
        if (strpos($t, _DB_PREFIX_) === 0) { $t = substr($t, strlen(_DB_PREFIX_)); }
        $tables = self::tables();
        return isset($tables[$t]);
    }

    /** Turned off for the few places that are legitimately cross-hotel (licensing, hotel access, group payroll reference data). */
    public static function disable() { self::$enabled = false; }
    public static function enable() { self::$enabled = true; }
    public static function isEnabled() { return self::$enabled; }

    /** Statements the rewriter declined to touch, for the diagnostics screen. */
    public static function skipped() { return self::$skipped; }
    public static function clearSkipped() { self::$skipped = array(); }

    /* ---------------- the entry point ---------------- */

    /**
     * Return $sql scoped to $idHotel. Pass null to use the session's hotel.
     * A statement that names no scoped table comes back untouched.
     */
    public static function apply($sql, $idHotel = null)
    {
        if (!self::$enabled) { return $sql; }
        if (!is_string($sql) || $sql === '') { return $sql; }
        if (!self::mayTouchScopedTable($sql)) { return $sql; }
        if ($idHotel === null) { $idHotel = class_exists('PulseHotelContext') ? (int) PulseHotelContext::id() : 0; }

        try {
            return self::rewrite($sql, (int) $idHotel);
        } catch (Exception $e) {
            self::$skipped[] = array('sql' => substr($sql, 0, 400), 'why' => $e->getMessage());
            return $sql;
        }
    }

    /**
     * A cheap reject, so the great majority of statements never reach the tokenizer.
     *
     * Almost every scoped table is named `<prefix>pulse_…`, which a single stripos settles. The
     * handful that are not — QloApps' own hotel-bearing tables, such as htl_room_information — are
     * checked against a short list built once from the manifest.
     */
    protected static function mayTouchScopedTable($sql)
    {
        if (stripos($sql, _DB_PREFIX_.'pulse') !== false) { return true; }
        foreach (self::foreignTables() as $t) {
            if (stripos($sql, $t) !== false) { return true; }
        }
        return false;
    }

    /** Scoped tables whose names do not start with "pulse_" — prefixed, ready for a substring test. */
    protected static function foreignTables()
    {
        static $list = null;
        if ($list !== null) { return $list; }
        $list = array();
        foreach (array_keys(self::tables()) as $t) {
            if (strpos($t, 'pulse_') !== 0) { $list[] = _DB_PREFIX_.$t; }
        }
        return $list;
    }

    /** The predicate itself. No hotel in the session means "match nothing", never "match everything". */
    public static function predicate($qualifier, $idHotel)
    {
        if ((int) $idHotel <= 0) { return '1 = 0'; }
        return $qualifier.'`'.self::COLUMN.'` = '.(int) $idHotel;
    }

    /* ---------------- tokenizer ---------------- */

    /**
     * Split SQL into a flat list of tokens. Quoted strings, backticked identifiers and comments
     * become single opaque tokens so that nothing inside them is ever mistaken for a keyword.
     * Each token is array(text, isCode) — isCode false means "opaque, never look inside".
     */
    public static function tokenize($sql)
    {
        $out = array();
        $len = strlen($sql);
        $i = 0;
        $buf = '';
        while ($i < $len) {
            $c = $sql[$i];
            if ($c === "'" || $c === '"' || $c === '`') {
                if ($buf !== '') { $out[] = array($buf, true); $buf = ''; }
                $j = $i + 1;
                while ($j < $len) {
                    if ($sql[$j] === '\\' && $c !== '`') { $j += 2; continue; }
                    if ($sql[$j] === $c) {
                        // a doubled delimiter is an escaped delimiter, not the end
                        if ($j + 1 < $len && $sql[$j + 1] === $c) { $j += 2; continue; }
                        break;
                    }
                    ++$j;
                }
                if ($j >= $len) { throw new Exception('unterminated '.$c.' literal'); }
                $out[] = array(substr($sql, $i, $j - $i + 1), false);
                $i = $j + 1;
                continue;
            }
            if ($c === '-' && $i + 1 < $len && $sql[$i + 1] === '-') {
                if ($buf !== '') { $out[] = array($buf, true); $buf = ''; }
                $j = strpos($sql, "\n", $i);
                $j = $j === false ? $len : $j;
                $out[] = array(substr($sql, $i, $j - $i), false);
                $i = $j;
                continue;
            }
            if ($c === '/' && $i + 1 < $len && $sql[$i + 1] === '*') {
                if ($buf !== '') { $out[] = array($buf, true); $buf = ''; }
                $j = strpos($sql, '*/', $i + 2);
                if ($j === false) { throw new Exception('unterminated block comment'); }
                $out[] = array(substr($sql, $i, $j - $i + 2), false);
                $i = $j + 2;
                continue;
            }
            $buf .= $c;
            ++$i;
        }
        if ($buf !== '') { $out[] = array($buf, true); }
        return $out;
    }

    /** Rebuild the statement text from tokens. */
    protected static function untokenize($tokens)
    {
        $s = '';
        foreach ($tokens as $t) { $s .= $t[0]; }
        return $s;
    }

    /* ---------------- the rewrite ---------------- */

    /**
     * The statement with everything that is not bare SQL code blanked out to same-length filler, so
     * that an offset found by a regex on the masked text is the same offset in the real string.
     *
     * Two kinds of filler, because they mean different things to the caller: LITERAL for strings and
     * comments, whose contents must never be read; IDENT for a backticked name, whose contents are
     * read straight from the original text once the mask has told us a name starts there. Both are
     * opaque to the keyword patterns, so a column called `from` or a string containing "LEFT JOIN"
     * can never be mistaken for the real thing.
     */
    const LITERAL = "\x01";
    const IDENT = "\x02";

    protected static function mask($sql)
    {
        $masked = '';
        foreach (self::tokenize($sql) as $t) {
            if ($t[1]) { $masked .= $t[0]; continue; }
            $fill = $t[0][0] === '`' ? self::IDENT : self::LITERAL;
            $masked .= str_repeat($fill, strlen($t[0]));
        }
        return $masked;
    }

    /** Offsets of the top-level (unnested) parenthesis pairs in the masked text. */
    protected static function topLevelGroups($masked)
    {
        $groups = array();
        $depth = 0;
        $start = 0;
        $len = strlen($masked);
        for ($i = 0; $i < $len; ++$i) {
            if ($masked[$i] === '(') {
                if ($depth === 0) { $start = $i; }
                ++$depth;
            } elseif ($masked[$i] === ')') {
                if ($depth > 0) { --$depth; if ($depth === 0) { $groups[] = array($start, $i); } }
            }
        }
        if ($depth !== 0) { throw new Exception('unbalanced parentheses'); }
        return $groups;
    }

    /**
     * The statement masked one level deep: literals and backticked names as before, and each
     * top-level parenthesised group — brackets and all — blanked to GROUP filler. Keywords inside a
     * subquery are therefore invisible here, so a FROM in the outer query and a FROM in a subquery
     * can never be confused, while every offset still lines up with the original string.
     */
    const GROUP = "\x03";

    protected static function maskLevel($sql)
    {
        $masked = self::mask($sql);
        foreach (self::topLevelGroups($masked) as $g) {
            list($a, $b) = $g;
            $masked = substr($masked, 0, $a).str_repeat(self::GROUP, $b - $a + 1).substr($masked, $b + 1);
        }
        return $masked;
    }

    /**
     * Rewrite one statement.
     *
     * The whole statement is considered at once rather than piece by piece, because a query's FROM
     * and its WHERE are routinely separated by a subquery — `SELECT (SELECT …) FROM t WHERE …` — and
     * a predicate has to be able to travel from one to the other. So: find this level's tables and
     * this level's WHERE on the level-masked text, note where each insertion goes, then recurse into
     * the subqueries and splice everything back together from the end forwards.
     */
    protected static function rewrite($sql, $idHotel)
    {
        $masked = self::mask($sql);
        if (strlen($masked) !== strlen($sql)) { throw new Exception('mask length drift'); }

        // UNION arms are separate queries that happen to share a statement; each is scoped alone.
        $level = self::maskLevel($sql);
        if (preg_match('/\bUNION\b/i', $level)) { return self::rewriteUnion($sql, $level, $idHotel); }

        $edits = self::planEdits($sql, $level, $idHotel);

        // Subqueries: rewrite each top-level group's contents in its own right.
        foreach (self::topLevelGroups($masked) as $g) {
            list($a, $b) = $g;
            $inner = substr($sql, $a + 1, $b - $a - 1);
            // A group that is not itself a query — a value list, a function's arguments, an IN list
            // of constants — must come back byte for byte.
            if (!preg_match('/^\s*[\(\s]*SELECT\b/i', $inner)) { continue; }
            $edits[] = array('at' => $a + 1, 'len' => $b - $a - 1, 'text' => self::rewrite($inner, $idHotel));
        }

        // Apply from the end backwards so earlier offsets stay valid.
        usort($edits, array(__CLASS__, 'byOffsetDesc'));
        foreach ($edits as $e) {
            $len = isset($e['len']) ? $e['len'] : 0;
            $sql = substr($sql, 0, $e['at']).$e['text'].substr($sql, $e['at'] + $len);
        }
        return $sql;
    }

    /** Split on top-level UNION / UNION ALL and rewrite each arm as its own query. */
    protected static function rewriteUnion($sql, $level, $idHotel)
    {
        $parts = array();
        $cursor = 0;
        $offset = 0;
        while (preg_match('/\bUNION(\s+(ALL|DISTINCT))?\b/i', $level, $m, PREG_OFFSET_CAPTURE, $offset)) {
            $at = $m[0][1];
            $parts[] = array($cursor, $at - $cursor);
            $cursor = $at + strlen($m[0][0]);
            $offset = $cursor;
        }
        $parts[] = array($cursor, strlen($sql) - $cursor);

        $out = '';
        $prev = 0;
        foreach ($parts as $p) {
            list($a, $len) = $p;
            $out .= substr($sql, $prev, $a - $prev);              // the UNION keyword itself
            $out .= self::rewrite(substr($sql, $a, $len), $idHotel);
            $prev = $a + $len;
        }
        return $out.substr($sql, $prev);
    }

    /** Latest edit first, so applying one does not move the offsets of the others. */
    public static function byOffsetDesc($a, $b) { return $b['at'] - $a['at']; }

    /**
     * Where the predicates for this level's tables have to go: into each outer join's ON clause, and
     * into this level's WHERE — creating one if the query has none.
     */
    protected static function planEdits($sql, $level, $idHotel)
    {
        $edits = array();
        if (!self::mayTouchScopedTable($sql)) { return $edits; }

        if (preg_match('/^\s*(INSERT|REPLACE)\b/i', $level)) {
            // A hand-written INSERT gets its id_hotel column added here, because PulseDb::insert()
            // only sees the ones built from an array. Its SELECT, if it has one, is scoped below.
            return array_merge(self::insertEdits($sql, $level, $idHotel), self::selectPartEdits($sql, $level, $idHotel));
        }

        $refs = self::tableRefs($sql, $level);
        if (!$refs) { return $edits; }

        $where = array();
        foreach ($refs as $r) {
            if (!self::isScoped($r['table'])) { continue; }
            $qual = $r['alias'] !== '' ? '`'.$r['alias'].'`.' : '`'.trim($r['table'], '`').'`.';
            $p = self::predicate($qual, $idHotel);
            if (!$r['outer']) { $where[] = $p; continue; }
            if ($r['on_end'] === null) {
                // An outer join with no ON is a cross join in disguise; scoping it in the WHERE would
                // change the result set, so it is recorded and left alone.
                self::$skipped[] = array('sql' => substr($sql, 0, 300), 'why' => 'outer join without ON');
                continue;
            }
            // The trailing space matters: on_end is the offset of the next keyword, and the space
            // that separated it from the ON clause now sits to the left of what we are inserting.
            $edits[] = array('at' => $r['on_end'], 'text' => ' AND '.$p.' ');
        }

        if ($where) { $edits[] = self::whereEdit($sql, $level, implode(' AND ', $where)); }
        return $edits;
    }

    /** Latest ON clause first, so inserting into one does not move the offsets of the others. */
    public static function byOnEndDesc($a, $b) { return (int) $b[0]['on_end'] - (int) $a[0]['on_end']; }

    /**
     * Table references at this level: FROM a, b / JOIN x ON ... — with the alias, whether the join is
     * outer, and where that join's ON clause ends so a predicate can be appended to it.
     */
    protected static function tableRefs($frag, $masked)
    {
        $refs = array();
        // Four shapes, in this order: FROM and UPDATE (both of which may list several tables), an
        // outer join, any other join. A group that did not take part is simply absent from $m, so
        // every read is guarded. UPDATE is here because its target is named before SET, not after FROM.
        $re = '/\b(?:(FROM|UPDATE(?:\s+(?:LOW_PRIORITY|IGNORE))*)|((?:LEFT|RIGHT)(?:\s+OUTER)?\s+JOIN)|((?:INNER|CROSS|STRAIGHT)\s+)?(JOIN))\s+/i';
        $offset = 0;
        while (preg_match($re, $masked, $m, PREG_OFFSET_CAPTURE, $offset)) {
            $kwEnd = $m[0][1] + strlen($m[0][0]);
            $isFrom = isset($m[1]) && $m[1][1] !== -1;
            $isOuter = isset($m[2]) && $m[2][1] !== -1;
            $offset = $kwEnd;

            // FROM may list several comma-separated tables; JOIN takes exactly one.
            $list = self::readTableList($frag, $masked, $kwEnd, $isFrom);
            foreach ($list as $t) {
                $t['outer'] = $isOuter;
                $t['on_end'] = $isOuter ? self::onClauseEnd($masked, $t['end']) : null;
                $refs[] = $t;
            }
            if ($list) { $offset = max($offset, $list[count($list) - 1]['end']); }
        }
        return $refs;
    }

    /**
     * Read `tbl` [AS] [alias] at $pos, and for FROM keep reading past commas.
     *
     * A backticked name shows up in the masked text as a run of IDENT filler; its real text is taken
     * from the original string at the same offset, which is why the mask preserves length exactly.
     */
    protected static function readTableList($frag, $masked, $pos, $allowCommas)
    {
        $out = array();
        $ident = preg_quote(self::IDENT, '/');
        while (true) {
            if (!preg_match('/\G\s*('.$ident.'+|[A-Za-z0-9_$.]+)\s*(?:(AS)\s+)?([A-Za-z_][A-Za-z0-9_]*|'.$ident.'+)?/i',
                            $masked, $m, PREG_OFFSET_CAPTURE, $pos)) { break; }
            $tableTok = substr($frag, $m[1][1], strlen($m[1][0]));
            if ($tableTok === '' || $tableTok[0] === '(') { break; }
            $alias = '';
            if (isset($m[3]) && $m[3][1] !== -1) {
                // The alias may itself be backticked, in which case read it from the original too.
                $cand = trim(substr($frag, $m[3][1], strlen($m[3][0])), '`');
                // A clause keyword is not an alias.
                if (!preg_match('/^(ON|USING|WHERE|GROUP|ORDER|LIMIT|HAVING|SET|LEFT|RIGHT|INNER|CROSS|JOIN|STRAIGHT_JOIN|UNION|FOR|VALUES|AS|IGNORE|FORCE|USE)$/i', $cand)) {
                    $alias = $cand;
                }
            }
            $end = $alias !== '' && isset($m[3]) ? $m[3][1] + strlen($m[3][0]) : $m[1][1] + strlen($m[1][0]);
            $out[] = array('table' => $tableTok, 'alias' => $alias, 'start' => $m[1][1], 'end' => $end);
            if (!$allowCommas) { break; }
            if (!preg_match('/\G\s*,/', $masked, $c, PREG_OFFSET_CAPTURE, $end)) { break; }
            $pos = $c[0][1] + strlen($c[0][0]);
        }
        return $out;
    }

    /** Where the ON clause that starts after $pos ends — at the next clause keyword at this level. */
    protected static function onClauseEnd($masked, $pos)
    {
        if (!preg_match('/\G\s*ON\b/i', $masked, $m, PREG_OFFSET_CAPTURE, $pos)) { return null; }
        $from = $m[0][1] + strlen($m[0][0]);
        if (preg_match('/\b(LEFT|RIGHT|INNER|CROSS|STRAIGHT_JOIN|JOIN|WHERE|GROUP\s+BY|ORDER\s+BY|HAVING|LIMIT|UNION|INTO|FOR\s+UPDATE)\b/i',
                       $masked, $k, PREG_OFFSET_CAPTURE, $from)) {
            return $k[0][1];
        }
        return strlen($masked);
    }

    /** Put $pred into the fragment's WHERE, creating one at the right place when there is none. */
    /**
     * Add id_hotel to a hand-written INSERT so the row lands in the right property.
     *
     * Three shapes are handled, which is every shape this codebase writes:
     *   INSERT INTO t (a, b) VALUES (1, 2), (3, 4)   → the column and a value in each tuple
     *   INSERT INTO t (a, b) SELECT x, y FROM …      → the column and a constant in the projection
     *   INSERT INTO t SET a = 1, b = 2               → one more assignment
     * An INSERT with no column list cannot be extended without knowing the table's column order, so
     * it is recorded instead of guessed at. An ON DUPLICATE KEY UPDATE tail is never touched.
     */
    protected static function insertEdits($sql, $level, $idHotel)
    {
        if (!preg_match('/^\s*(?:INSERT|REPLACE)(?:\s+(?:LOW_PRIORITY|DELAYED|HIGH_PRIORITY|IGNORE))*\s+(?:INTO\s+)?/i',
                        $level, $m, PREG_OFFSET_CAPTURE)) { return array(); }
        $after = $m[0][1] + strlen($m[0][0]);
        $list = self::readTableList($sql, $level, $after, false);
        if (!$list || !self::isScoped($list[0]['table'])) { return array(); }

        $value = $idHotel > 0 ? (int) $idHotel : 0;
        if ($value <= 0) {
            self::$skipped[] = array('sql' => substr($sql, 0, 300), 'why' => 'insert with no hotel chosen');
            return array();
        }
        $tableEnd = $list[0]['end'];

        // SET form.
        if (preg_match('/\G\s*SET\b/i', $level, $s, PREG_OFFSET_CAPTURE, $tableEnd)) {
            if (preg_match('/\bid_hotel\b/i', $level)) { return array(); }
            $stop = preg_match('/\bON\s+DUPLICATE\s+KEY\b/i', $level, $d, PREG_OFFSET_CAPTURE)
                ? $d[0][1] : strlen(rtrim($sql, " \t\r\n;"));
            return array(array('at' => $stop, 'text' => ', `'.self::COLUMN.'` = '.$value.' '));
        }

        // Column-list form: the first top-level group after the table name is the column list.
        $cols = null;
        foreach (self::topLevelGroups(self::mask($sql)) as $g) {
            if ($g[0] >= $tableEnd) { $cols = $g; break; }
        }
        if (!$cols) {
            self::$skipped[] = array('sql' => substr($sql, 0, 300), 'why' => 'insert without a column list');
            return array();
        }
        $colText = substr($sql, $cols[0] + 1, $cols[1] - $cols[0] - 1);
        if (preg_match('/\bid_hotel\b/i', $colText)) { return array(); }

        $edits = array(array('at' => $cols[1], 'text' => ', `'.self::COLUMN.'`'));

        // VALUES: one more value in each tuple. SELECT: one more column in the projection.
        $rest = self::maskLevel($sql);
        if (preg_match('/\bVALUES?\b/i', $rest, $v, PREG_OFFSET_CAPTURE, $cols[1])) {
            $stop = preg_match('/\bON\s+DUPLICATE\s+KEY\b/i', $rest, $d, PREG_OFFSET_CAPTURE)
                ? $d[0][1] : strlen($sql);
            foreach (self::topLevelGroups(self::mask($sql)) as $g) {
                if ($g[0] > $v[0][1] && $g[1] < $stop) { $edits[] = array('at' => $g[1], 'text' => ', '.$value); }
            }
            return $edits;
        }
        if (preg_match('/\bSELECT\b/i', $rest, $sel, PREG_OFFSET_CAPTURE, $cols[1])) {
            $projEnd = preg_match('/\bFROM\b/i', $rest, $f, PREG_OFFSET_CAPTURE, $sel[0][1])
                ? $f[0][1] : strlen(rtrim($sql, " \t\r\n;"));
            $edits[] = array('at' => $projEnd, 'text' => ', '.$value.' ');
            return $edits;
        }
        self::$skipped[] = array('sql' => substr($sql, 0, 300), 'why' => 'insert with neither VALUES nor SELECT');
        return array();
    }

    /** For an INSERT ... SELECT, scope the SELECT's own tables the way any other query would be. */
    protected static function selectPartEdits($sql, $level, $idHotel)
    {
        if (!preg_match('/\bSELECT\b/i', $level, $m, PREG_OFFSET_CAPTURE)) { return array(); }
        $refs = self::tableRefs($sql, $level);
        $where = array();
        $edits = array();
        foreach ($refs as $r) {
            if (!self::isScoped($r['table'])) { continue; }
            $qual = $r['alias'] !== '' ? '`'.$r['alias'].'`.' : '`'.trim($r['table'], '`').'`.';
            $p = self::predicate($qual, $idHotel);
            if ($r['outer'] && $r['on_end'] !== null) { $edits[] = array('at' => $r['on_end'], 'text' => ' AND '.$p.' '); continue; }
            if (!$r['outer']) { $where[] = $p; }
        }
        if ($where) { $edits[] = self::whereEdit($sql, $level, implode(' AND ', $where)); }
        return $edits;
    }

    /**
     * The single edit that puts $pred into this level's WHERE. An existing WHERE gets the predicate
     * at its front, which keeps it ahead of any OR the query already has; a query with no WHERE gets
     * one immediately before its first trailing clause, or at the very end if it has none.
     */
    protected static function whereEdit($sql, $level, $pred)
    {
        if (preg_match('/\bWHERE\b/i', $level, $m, PREG_OFFSET_CAPTURE)) {
            $at = $m[0][1] + strlen($m[0][0]);
            return array('at' => $at, 'text' => ' '.$pred.' AND');
        }
        if (preg_match('/\b(GROUP\s+BY|HAVING|ORDER\s+BY|LIMIT|PROCEDURE|INTO\s+OUTFILE|FOR\s+UPDATE|LOCK\s+IN\s+SHARE)\b/i',
                       $level, $m, PREG_OFFSET_CAPTURE)) {
            return array('at' => $m[0][1], 'text' => 'WHERE '.$pred.' ');
        }
        // Nothing follows, so the predicate goes on the end — after any trailing semicolon or space.
        $at = strlen(rtrim($sql, " \t\r\n;"));
        return array('at' => $at, 'text' => ' WHERE '.$pred);
    }
}
