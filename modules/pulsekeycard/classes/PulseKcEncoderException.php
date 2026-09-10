<?php
/** Typed encoder failure. The desk UI shows userMessage() and offers the mechanical-key fallback. */
class PulseKcEncoderException extends PrestaShopException
{
    const UNREACHABLE = 1001;
    const TIMED_OUT = 1002;
    const REJECTED = 1003;
    const NOT_CONFIGURED = 1004;
    const UNSUPPORTED = 1005;
    const BAD_RESPONSE = 1006;

    protected $encoder;

    public function __construct($message, $code = self::UNREACHABLE, $encoder = null)
    {
        parent::__construct($message, $code);
        $this->encoder = $encoder;
    }

    public function encoderName() { return $this->encoder; }
    public function isOffline() { return in_array($this->getCode(), array(self::UNREACHABLE, self::TIMED_OUT, self::NOT_CONFIGURED)); }

    /** One line a clerk can act on at 2 a.m. */
    public function userMessage()
    {
        $where = $this->encoder ? ' ('.$this->encoder.')' : '';
        switch ($this->getCode()) {
            case self::TIMED_OUT: return 'Encoder timed out'.$where.' — issue a mechanical key, the card will be queued and encoded when the encoder is back.';
            case self::REJECTED: return 'Encoder rejected the key'.$where.': '.$this->getMessage();
            case self::NOT_CONFIGURED: return 'Encoder not configured'.$where.' — set host, port and credentials in Key Card ▸ Encoders.';
            case self::UNSUPPORTED: return 'This lock system does not support that operation'.$where.': '.$this->getMessage();
            case self::BAD_RESPONSE: return 'Unexpected reply from the encoder'.$where.': '.$this->getMessage();
            default: return 'Encoder offline'.$where.' — issue a mechanical key and record it on the guest folio.';
        }
    }
}
