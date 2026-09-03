<?php
/** Dependency-free inline SVG charts for the dashboard and (optionally) emails. */
class PulseChart
{
    public static function bars(array $labels, array $series, $w = 680, $h = 200, array $colors = array('#2e86c1', '#f39c12', '#7f8c8d'))
    {
        $n = count($labels); if (!$n) { return ''; } $max = 1; foreach ($series as $s) { foreach ($s['values'] as $v) { $max = max($max, (float) $v); } }
        $pad = 30; $gw = ($w - $pad - 10) / $n; $bw = max(2, ($gw - 6) / count($series)); $svg = '<svg viewBox="0 0 '.$w.' '.$h.'" width="100%" style="max-width:'.$w.'px;font-family:Arial;font-size:10px">';
        for ($g = 0; $g <= 4; $g++) { $y = $h - 20 - ($h - 40) * $g / 4; $svg .= '<line x1="'.$pad.'" y1="'.$y.'" x2="'.$w.'" y2="'.$y.'" stroke="#eee"/><text x="0" y="'.($y + 3).'" fill="#888">'.self::k($max * $g / 4).'</text>'; }
        foreach ($labels as $i => $lab) { foreach ($series as $si => $s) { $v = (float) $s['values'][$i]; $bh = ($h - 40) * $v / $max; $x = $pad + $i * $gw + 3 + $si * $bw; $svg .= '<rect x="'.$x.'" y="'.($h - 20 - $bh).'" width="'.$bw.'" height="'.$bh.'" fill="'.$colors[$si % count($colors)].'"><title>'.htmlspecialchars($s['name'].' '.$lab.': '.number_format($v, 0)).'</title></rect>'; } if ($n <= 31 && ($n <= 14 || $i % 2 === 0)) { $svg .= '<text x="'.($pad + $i * $gw + $gw / 2).'" y="'.($h - 6).'" text-anchor="middle" fill="#666">'.htmlspecialchars($lab).'</text>'; } }
        $lx = $pad; foreach ($series as $si => $s) { $svg .= '<rect x="'.$lx.'" y="2" width="10" height="10" fill="'.$colors[$si % count($colors)].'"/><text x="'.($lx + 14).'" y="11" fill="#444">'.htmlspecialchars($s['name']).'</text>'; $lx += 14 + strlen($s['name']) * 6 + 12; }
        return $svg.'</svg>';
    }
    public static function line(array $labels, array $values, $w = 680, $h = 160, $color = '#1e8449', $suffix = '')
    {
        $n = count($labels); if ($n < 2) { return ''; } $max = max(1, max($values)); $pad = 30; $pts = array();
        foreach ($values as $i => $v) { $pts[] = round($pad + ($w - $pad - 10) * $i / ($n - 1), 1).','.round($h - 20 - ($h - 40) * $v / $max, 1); }
        $svg = '<svg viewBox="0 0 '.$w.' '.$h.'" width="100%" style="max-width:'.$w.'px;font-family:Arial;font-size:10px">';
        for ($g = 0; $g <= 4; $g++) { $y = $h - 20 - ($h - 40) * $g / 4; $svg .= '<line x1="'.$pad.'" y1="'.$y.'" x2="'.$w.'" y2="'.$y.'" stroke="#eee"/><text x="0" y="'.($y + 3).'" fill="#888">'.self::k($max * $g / 4).$suffix.'</text>'; }
        $svg .= '<polyline fill="none" stroke="'.$color.'" stroke-width="2" points="'.implode(' ', $pts).'"/>';
        foreach ($pts as $i => $p) { list($x, $y) = explode(',', $p); $svg .= '<circle cx="'.$x.'" cy="'.$y.'" r="2.5" fill="'.$color.'"><title>'.htmlspecialchars($labels[$i].': '.$values[$i].$suffix).'</title></circle>'; if ($n <= 14 || $i % 3 === 0) { $svg .= '<text x="'.$x.'" y="'.($h - 6).'" text-anchor="middle" fill="#666">'.htmlspecialchars($labels[$i]).'</text>'; } }
        return $svg.'</svg>';
    }
    public static function donut(array $items, $size = 160)
    {
        $tot = 0; foreach ($items as $i) { $tot += (float) $i[1]; } if ($tot <= 0) { return ''; }
        $cols = array('#2e86c1', '#1e8449', '#f39c12', '#8e44ad', '#c0392b', '#16a085', '#7f8c8d', '#d35400'); $r = 60; $c = 2 * M_PI * $r; $off = 0; $svg = '<svg viewBox="0 0 '.($size + 200).' '.$size.'" width="100%" style="max-width:'.($size + 200).'px;font-family:Arial;font-size:11px">';
        foreach ($items as $k => $i) { $len = $c * $i[1] / $tot; $svg .= '<circle r="'.$r.'" cx="'.($size / 2).'" cy="'.($size / 2).'" fill="none" stroke="'.$cols[$k % 8].'" stroke-width="28" stroke-dasharray="'.$len.' '.($c - $len).'" stroke-dashoffset="'.(-$off).'" transform="rotate(-90 '.($size / 2).' '.($size / 2).')"><title>'.htmlspecialchars($i[0].': '.number_format($i[1], 0)).'</title></circle>'; $off += $len; $svg .= '<rect x="'.($size + 10).'" y="'.(10 + $k * 16).'" width="10" height="10" fill="'.$cols[$k % 8].'"/><text x="'.($size + 24).'" y="'.(19 + $k * 16).'" fill="#444">'.htmlspecialchars($i[0]).' '.round($i[1] / $tot * 100).'%</text>'; }
        return $svg.'</svg>';
    }
    protected static function k($v) { return $v >= 1000000 ? round($v / 1000000, 1).'M' : ($v >= 1000 ? round($v / 1000).'k' : round($v)); }
}
