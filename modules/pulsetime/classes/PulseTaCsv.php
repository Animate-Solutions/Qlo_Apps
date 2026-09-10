<?php
/**
 * Scheduled import of a punch file — the honest escape hatch for a brand nobody anticipated, and the way a
 * property gets its history in on day one. The file is read from a local folder (newest matching file, or
 * every unprocessed file) or fetched from a URL, and the column mapping is configuration, not code.
 *
 * Device options:
 *   path              /var/pulse/punches            folder to scan, or a single file
 *   pattern           *.csv                         glob inside that folder
 *   url               https://.../punches.csv       used instead of path when set (Basic auth via credentials)
 *   delimiter         ,   ;   |   tab               default: auto-detected from the first line
 *   has_header        1                             skip the first row
 *   encoding          UTF-8                         converted to UTF-8 on read
 *   date_format       Y-m-d H:i:s                   PHP format; leave empty to let strtotime() decide
 *   col_ref           0                             column index (or header name) holding the device user id
 *   col_datetime      1                             a single date+time column ...
 *   col_date / col_time                             ... or two separate columns
 *   col_direction     2      map_in=IN,I,0          map_out=OUT,O,1
 *   col_verify        3      col_workcode  4
 *   archive_dir       /var/pulse/punches/done       imported files are moved here instead of being deleted
 *
 * Nothing is ever deleted: a processed file is moved to archive_dir, or left alone when no archive is set,
 * with the dedupe hash on pulse_ta_punch making a re-read harmless.
 */
class PulseTaCsv extends PulseTaDeviceBase
{
    protected $vendor = 'csv';

    protected function delimiter($line)
    {
        $d = (string) $this->opt('delimiter', '');
        if ($d === 'tab' || $d === '\t') { return "\t"; }
        if ($d !== '') { return Tools::substr($d, 0, 1); }
        $best = ','; $bestN = 0;
        foreach (array(',', ';', "\t", '|') as $c) { $n = substr_count($line, $c); if ($n > $bestN) { $best = $c; $bestN = $n; } }
        return $best;
    }

    /** Files to read this run: a URL, one file, or every file matching the glob. */
    protected function sources()
    {
        if ($this->opt('url')) { return array(array('name' => $this->opt('url'), 'body' => $this->fetch($this->opt('url')), 'file' => null)); }
        $path = (string) $this->opt('path', '');
        if ($path === '') { $this->fail('no file path or URL set for this import', PulseTaDeviceException::NOT_CONFIGURED); }
        $files = array();
        if (is_file($path)) { $files[] = $path; }
        elseif (is_dir($path)) { $g = glob(rtrim($path, '/').'/'.$this->opt('pattern', '*.csv')); if (is_array($g)) { sort($g); $files = array_slice($g, 0, (int) $this->opt('max_files', 20)); } }
        else { $this->fail('path not found: '.$path, PulseTaDeviceException::NOT_CONFIGURED); }
        $out = array();
        foreach ($files as $f) {
            if (!is_readable($f)) { continue; }
            if (filesize($f) > (int) $this->opt('max_bytes', 8388608)) { continue; }
            $out[] = array('name' => basename($f), 'body' => (string) file_get_contents($f), 'file' => $f);
        }
        return $out;
    }

    protected function fetch($url)
    {
        $r = $this->http('GET', $url, null, array('Accept: text/csv, text/plain'), $this->cred('user') !== '' ? 'basic' : 'none', true);
        if ((int) $r['http'] >= 400) { $this->fail('HTTP '.$r['http'].' fetching '.$url, PulseTaDeviceException::BAD_RESPONSE); }
        return $r['body'];
    }

    /** Header name or numeric index -> a value from the row. */
    protected function col($row, $header, $key, $default = '')
    {
        $spec = $this->opt($key, null);
        if ($spec === null || $spec === '') { return $default; }
        if (ctype_digit((string) $spec)) { return isset($row[(int) $spec]) ? trim($row[(int) $spec]) : $default; }
        $i = array_search(Tools::strtolower((string) $spec), $header);
        return $i !== false && isset($row[$i]) ? trim($row[$i]) : $default;
    }

    protected function mapped($value, $key, $fallback)
    {
        $list = (string) $this->opt($key, '');
        if ($list === '' || $value === '') { return $fallback; }
        foreach (explode(',', $list) as $t) { if (Tools::strtolower(trim($t)) === Tools::strtolower($value)) { return true; } }
        return false;
    }

    public function testConnection()
    {
        $src = $this->sources();
        $rows = 0;
        foreach ($src as $s) { $rows += max(0, substr_count($s['body'], "\n")); }
        return array('ok' => true, 'firmware' => 'csv import', 'model' => 'File import', 'serial' => $this->dev['serial'], 'users' => 0, 'punches' => $rows,
            'device_time' => date('Y-m-d H:i:s'), 'drift_sec' => 0,
            'message' => count($src).' file(s) readable, about '.$rows.' data line(s). Column mapping: ref='.$this->opt('col_ref', '0').', datetime='.$this->opt('col_datetime', '1').'.');
    }

    public function pullPunches($since)
    {
        $out = array(); $archive = (string) $this->opt('archive_dir', '');
        $fmt = (string) $this->opt('date_format', '');
        foreach ($this->sources() as $s) {
            $body = (string) $s['body'];
            $enc = (string) $this->opt('encoding', '');
            if ($enc !== '' && Tools::strtoupper($enc) !== 'UTF-8' && function_exists('iconv')) { $c = @iconv($enc, 'UTF-8//TRANSLIT', $body); if ($c !== false) { $body = $c; } }
            $lines = preg_split('/\r\n|\r|\n/', $body);
            if (!$lines) { continue; }
            $delim = $this->delimiter($lines[0]);
            $header = array(); $first = true;
            foreach ($lines as $line) {
                if (trim($line) === '') { continue; }
                $row = str_getcsv($line, $delim);
                if ($first && (int) $this->opt('has_header', 1)) { foreach ($row as $h) { $header[] = Tools::strtolower(trim($h)); } $first = false; continue; }
                $first = false;
                $ref = $this->col($row, $header, 'col_ref', isset($row[0]) ? trim($row[0]) : '');
                if ($ref === '') { continue; }
                $when = $this->col($row, $header, 'col_datetime', '');
                if ($when === '') {
                    $d = $this->col($row, $header, 'col_date', ''); $t = $this->col($row, $header, 'col_time', '');
                    $when = trim($d.' '.$t);
                }
                if ($when === '') { continue; }
                if ($fmt !== '') { $dt = DateTime::createFromFormat($fmt, $when); if ($dt) { $when = $dt->format('Y-m-d H:i:s'); } }
                $ts = strtotime($when);
                if (!$ts) { continue; }
                $dirRaw = $this->col($row, $header, 'col_direction', '');
                $dir = $dirRaw;
                if ($this->opt('map_in') || $this->opt('map_out')) { $dir = $this->mapped($dirRaw, 'map_in', false) ? 'in' : ($this->mapped($dirRaw, 'map_out', false) ? 'out' : 'unknown'); }
                $p = $this->punch($ref, date('Y-m-d H:i:s', $ts), $dir, $this->col($row, $header, 'col_verify', ''), $this->col($row, $header, 'col_workcode', ''), Tools::substr($line, 0, 500));
                $p['source'] = 'import';
                $out[] = $p;
            }
            if ($s['file'] && $archive !== '' && is_dir($archive) && is_writable($archive)) { @rename($s['file'], rtrim($archive, '/').'/'.date('Ymd-His').'-'.basename($s['file'])); }
        }
        return $this->after($out, $since);
    }

    public function capabilities()
    {
        return array('vendor' => 'CSV / delimited file import', 'pull' => true, 'push_endpoint' => false, 'sync_time' => false, 'push_user' => false, 'delete_user' => false,
            'pull_users' => false, 'clear_log' => false, 'card' => false, 'face' => false, 'palm' => false, 'realtime' => false, 'work_codes' => true, 'default_port' => 0,
            'verify_note' => 'Map the columns to your export before the first live run — Test connection prints the mapping it will use.');
    }
}
