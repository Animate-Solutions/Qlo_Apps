<?php
/**
 * File-drop / e-mail adapter for OTAs and agents with no API — still very common in Nigeria.
 * ARI is written as a dated CSV into csv_out_dir (attach it to the OTA's extranet or e-mail it);
 * reservations are read from any *.csv dropped into csv_in_dir, then moved to csv_in_dir/processed.
 *
 * Inbound header (order-independent, case-insensitive):
 *   reference,status,guest_name,email,phone,room_code,rate_code,arrival,departure,rooms,adults,children,amount,currency,commission_pct,payment_type
 * Outbound ARI header:
 *   date,room_code,rate_code,available,rate,rate_single,extra_adult,child,min_los,max_los,cta,ctd,stop_sell,currency
 */
class PulseChAdapterCsv extends PulseChAdapterBase
{
    const IN_COLS = 'reference,status,guest_name,email,phone,room_code,rate_code,arrival,departure,rooms,adults,children,amount,currency,commission_pct,payment_type';

    protected function dir($which)
    {
        $d = trim((string) $this->channel[$which === 'in' ? 'csv_in_dir' : 'csv_out_dir']);
        if ($d === '') { $d = _PS_DOWNLOAD_DIR_.'pulsechannel/'.$this->channel['code'].'/'.$which; }
        if (!is_dir($d)) { @mkdir($d, 0755, true); }
        return rtrim($d, '/').'/';
    }

    public function pushAri(array $rows)
    {
        if (!$rows) { return array('ok' => true, 'sent' => 0, 'error' => null, 'raw' => null); }
        $dir = $this->dir('out');
        if (!is_dir($dir) || !is_writable($dir)) { $e = 'ARI folder is not writable: '.$dir; PulseChLog::write($this->id(), 'out', 'ari_push', null, 0, count($rows).' rows', '', 0, 'error', $e); return array('ok' => false, 'sent' => 0, 'error' => $e, 'raw' => null); }
        $file = $dir.'ari_'.$this->channel['code'].'_'.date('Ymd_His').'_'.count($rows).'.csv';
        $t0 = microtime(true);
        $fh = @fopen($file, 'w');
        if (!$fh) { $e = 'Could not open '.$file; PulseChLog::write($this->id(), 'out', 'ari_push', null, 0, count($rows).' rows', '', 0, 'error', $e); return array('ok' => false, 'sent' => 0, 'error' => $e, 'raw' => null); }
        fputcsv($fh, array('date', 'room_code', 'rate_code', 'available', 'rate', 'rate_single', 'extra_adult', 'child', 'min_los', 'max_los', 'cta', 'ctd', 'stop_sell', 'currency'));
        foreach ($rows as $r) { fputcsv($fh, array($r['date'], $r['room_code'], $r['rate_code'], (int) $r['available'], round((float) $r['rate'], 2), round((float) $r['rate_single'], 2), round((float) $r['rate_extra_adult'], 2), round((float) $r['rate_child'], 2), (int) $r['min_los'], (int) $r['max_los'], (int) $r['cta'], (int) $r['ctd'], (int) $r['stop_sell'], $r['currency'])); }
        fclose($fh);
        PulseChLog::write($this->id(), 'out', 'ari_push', basename($file), 200, count($rows).' rows -> '.$file, 'written', (int) round((microtime(true) - $t0) * 1000), 'ok', null);
        return array('ok' => true, 'sent' => count($rows), 'error' => null, 'raw' => $file);
    }

    /** Read every unprocessed CSV in the drop folder; files that parse are moved aside so they are never re-imported. */
    public function pullReservations($since)
    {
        $dir = $this->dir('in');
        if (!is_dir($dir)) { return array('ok' => false, 'reservations' => array(), 'error' => 'Drop folder does not exist: '.$dir); }
        $done = $dir.'processed/'; if (!is_dir($done)) { @mkdir($done, 0755, true); }
        $out = array(); $files = 0;
        foreach ((array) glob($dir.'*.[cC][sS][vV]') as $file) {
            $fh = @fopen($file, 'r');
            if (!$fh) { continue; }
            $head = fgetcsv($fh);
            if (!$head) { fclose($fh); continue; }
            $map = array(); foreach ($head as $i => $h) { $map[Tools::strtolower(trim($h))] = $i; }
            $n = 0;
            while (($line = fgetcsv($fh)) !== false) {
                if (count($line) === 1 && trim((string) $line[0]) === '') { continue; }
                $row = array('_source_file' => basename($file));
                foreach (explode(',', self::IN_COLS) as $col) { $row[$col] = isset($map[$col]) && isset($line[$map[$col]]) ? trim($line[$map[$col]]) : ''; }
                if ($row['reference'] === '') { continue; }
                $out[] = $row; $n++;
            }
            fclose($fh);
            @rename($file, $done.date('Ymd_His').'_'.basename($file));
            $files++;
            PulseChLog::write($this->id(), 'in', 'reservation_pull', basename($file), 200, $file, $n.' rows', 0, 'ok', null);
        }
        if (!$files) { PulseChLog::write($this->id(), 'in', 'reservation_pull', null, 204, $dir, 'no files', 0, 'ok', null); }
        return array('ok' => true, 'reservations' => $out, 'error' => null);
    }

    /** Nothing to call back to; the acknowledgement is the processed/ copy of the file. */
    public function ackReservation($ref) { return array('ok' => true, 'error' => null); }

    public function testConnection()
    {
        $in = $this->dir('in'); $out = $this->dir('out');
        $problems = array();
        if (!is_dir($in) || !is_readable($in)) { $problems[] = 'drop folder unreadable ('.$in.')'; }
        if (!is_dir($out) || !is_writable($out)) { $problems[] = 'ARI folder not writable ('.$out.')'; }
        $e = $problems ? implode('; ', $problems) : null;
        PulseChLog::write($this->id(), 'out', 'test', null, $e ? 500 : 200, $in.' | '.$out, $e ? $e : 'folders ok', 0, $e ? 'error' : 'ok', $e);
        return array('ok' => !$e, 'error' => $e, 'status' => null, 'ms' => 0);
    }

    public function capabilities() { return array('ari' => true, 'rates' => true, 'restrictions' => true, 'pull' => true, 'ack' => false, 'test' => true); }
}
