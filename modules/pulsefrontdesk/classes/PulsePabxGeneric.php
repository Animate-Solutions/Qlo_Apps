<?php
class PulsePabxGeneric implements PulsePabxInterface
{
    protected $cfg;
    public function __construct(array $config) { $this->cfg = $config; }
    protected function post($path, array $body)
    {
        if (empty($this->cfg['bridge_url'])) { return array('ok' => false, 'error' => 'No PABX bridge URL configured'); }
        $ch = curl_init(rtrim($this->cfg['bridge_url'], '/').$path);
        curl_setopt_array($ch, array(CURLOPT_POST => 1, CURLOPT_POSTFIELDS => json_encode($body), CURLOPT_HTTPHEADER => array('Content-Type: application/json', 'X-Pulse-Key: '.$this->cfg['key']), CURLOPT_RETURNTRANSFER => 1, CURLOPT_TIMEOUT => 8));
        $res = curl_exec($ch); $err = curl_error($ch); curl_close($ch);
        return $err ? array('ok' => false, 'error' => $err) : (json_decode($res, true) ?: array('ok' => true));
    }
    public function setWakeUp($extension, DateTime $at) { return $this->post('/wakeup', array('ext' => $extension, 'at' => $at->format('Y-m-d H:i'))); }
    public function cancelWakeUp($extension) { return $this->post('/wakeup/cancel', array('ext' => $extension)); }
    public function setRoomPhone($extension, $enabled, $guestName = '') { return $this->post('/room', array('ext' => $extension, 'enabled' => (bool) $enabled, 'name' => $guestName)); }
    public function test() { return $this->post('/ping', array()); }
}
