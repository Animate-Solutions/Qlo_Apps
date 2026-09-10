<?php
/** Every message in or out of the channel manager, with the request and response kept for support. */
class PulseChLog
{
    const MAX_BODY = 60000;

    /** Write one log row. Bodies are truncated so a runaway XML response cannot fill the table. */
    public static function write($idChannel, $direction, $type, $reference, $httpStatus, $request, $response, $ms, $status = 'ok', $error = null)
    {
        return Db::getInstance()->insert('pulse_ch_log', array(
            'id_pulse_ch_channel' => (int) $idChannel, 'direction' => pSQL($direction), 'type' => pSQL($type), 'reference' => pSQL(Tools::substr((string) $reference, 0, 64)),
            'http_status' => $httpStatus !== null ? (int) $httpStatus : null,
            'request' => pSQL(Tools::substr(is_string($request) ? $request : json_encode($request), 0, self::MAX_BODY), true),
            'response' => pSQL(Tools::substr(is_string($response) ? $response : json_encode($response), 0, self::MAX_BODY), true),
            'duration_ms' => (int) $ms, 'status' => pSQL($status), 'error' => pSQL(Tools::substr((string) $error, 0, 250)),
            'business_date' => pSQL(PulseChService::businessDate()), 'date_add' => date('Y-m-d H:i:s'),
        ), true);
    }

    public static function recent($idChannel = 0, $status = null, $type = null, $limit = 200)
    {
        return Db::getInstance()->executeS('SELECT l.*, c.name channel FROM `'._DB_PREFIX_.'pulse_ch_log` l LEFT JOIN `'._DB_PREFIX_.'pulse_ch_channel` c ON c.id_pulse_ch_channel=l.id_pulse_ch_channel
            WHERE 1'.($idChannel ? ' AND l.id_pulse_ch_channel='.(int) $idChannel : '').($status ? ' AND l.status="'.pSQL($status).'"' : '').($type ? ' AND l.type="'.pSQL($type).'"' : '').'
            ORDER BY l.id_pulse_ch_log DESC LIMIT '.(int) $limit);
    }

    public static function one($id)
    {
        return Db::getInstance()->getRow('SELECT l.*, c.name channel FROM `'._DB_PREFIX_.'pulse_ch_log` l LEFT JOIN `'._DB_PREFIX_.'pulse_ch_channel` c ON c.id_pulse_ch_channel=l.id_pulse_ch_channel WHERE l.id_pulse_ch_log='.(int) $id);
    }

    /** 24-hour traffic profile per channel for the health dashboard. */
    public static function health()
    {
        return Db::getInstance()->executeS('SELECT c.id_pulse_ch_channel, c.name, c.health, c.last_success, c.last_failure, c.last_error,
                COUNT(l.id_pulse_ch_log) calls, SUM(l.status="error") errors, ROUND(AVG(l.duration_ms)) avg_ms, MAX(l.duration_ms) max_ms
            FROM `'._DB_PREFIX_.'pulse_ch_channel` c LEFT JOIN `'._DB_PREFIX_.'pulse_ch_log` l ON l.id_pulse_ch_channel=c.id_pulse_ch_channel AND l.date_add>=DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY c.id_pulse_ch_channel ORDER BY c.enabled DESC, c.name');
    }
}
