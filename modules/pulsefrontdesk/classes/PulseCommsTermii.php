<?php
/** Termii (Nigeria) SMS + WhatsApp adapter. Config: api_key, sender_id. */
class PulseCommsTermii implements PulseCommsAdapterInterface
{
    protected $cfg;
    public function __construct(array $config) { $this->cfg = $config; }
    protected function call($channel, $to, $text)
    {
        $payload = array('api_key' => $this->cfg['api_key'], 'to' => preg_replace('/[^0-9+]/', '', $to), 'from' => $this->cfg['sender_id'], 'sms' => $text, 'type' => 'plain', 'channel' => $channel);
        $ch = curl_init('https://api.ng.termii.com/api/sms/send');
        curl_setopt_array($ch, array(CURLOPT_POST => 1, CURLOPT_POSTFIELDS => json_encode($payload), CURLOPT_HTTPHEADER => array('Content-Type: application/json'), CURLOPT_RETURNTRANSFER => 1, CURLOPT_TIMEOUT => 15));
        $res = json_decode(curl_exec($ch), true); $err = curl_error($ch); curl_close($ch);
        if ($err) { return array('ok' => false, 'ref' => null, 'error' => $err); }
        return array('ok' => isset($res['message_id']), 'ref' => isset($res['message_id']) ? $res['message_id'] : null, 'error' => isset($res['message']) && !isset($res['message_id']) ? $res['message'] : null);
    }
    public function sendSms($to, $text) { return $this->call('generic', $to, $text); }
    public function sendWhatsApp($to, $text) { return $this->call('whatsapp', $to, $text); }
}
