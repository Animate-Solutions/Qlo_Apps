<?php
/** Typed device failure. The Devices screen shows userMessage() so a duty manager knows what to do without calling the vendor. */
class PulseTaDeviceException extends PrestaShopException
{
    const UNREACHABLE = 2001;
    const TIMED_OUT = 2002;
    const AUTH_FAILED = 2003;
    const NOT_CONFIGURED = 2004;
    const UNSUPPORTED = 2005;
    const BAD_RESPONSE = 2006;
    const REJECTED = 2007;
    const BUSY = 2008;

    protected $device;

    public function __construct($message, $code = self::UNREACHABLE, $device = null)
    {
        parent::__construct($message, $code);
        $this->device = $device;
    }

    public function deviceName() { return $this->device; }
    public function isOffline() { return in_array($this->getCode(), array(self::UNREACHABLE, self::TIMED_OUT, self::NOT_CONFIGURED, self::BUSY)); }

    /** One line a duty manager can act on at 6 a.m. — never a stack trace. */
    public function userMessage()
    {
        $where = $this->device ? ' ('.$this->device.')' : '';
        switch ($this->getCode()) {
            case self::TIMED_OUT: return 'Clocking device timed out'.$where.' — punches stay on the device and will be pulled at the next poll. Staff can keep clocking; nothing is lost.';
            case self::AUTH_FAILED: return 'Device refused the credentials'.$where.' — check the comm key / user and password in Time & Attendance ▸ Devices.';
            case self::NOT_CONFIGURED: return 'Device not configured'.$where.' — set host, port and credentials in Time & Attendance ▸ Devices.';
            case self::UNSUPPORTED: return 'This device family does not support that operation'.$where.': '.$this->getMessage();
            case self::BAD_RESPONSE: return 'Unexpected reply from the device'.$where.': '.$this->getMessage().' — verify the adapter matches the firmware.';
            case self::REJECTED: return 'Device rejected the request'.$where.': '.$this->getMessage();
            case self::BUSY: return 'Device is busy'.$where.' (someone is enrolling a finger, or another pull is running) — it will be retried automatically.';
            default: return 'Clocking device offline'.$where.' — check power, network and the reader itself. Supervisors can record punches by hand on the Exceptions screen meanwhile.';
        }
    }
}
