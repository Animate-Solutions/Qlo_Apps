<?php
/** SMS / WhatsApp provider. Ships with PulseCommsTermii; add others by implementing this. */
interface PulseCommsAdapterInterface
{
    public function __construct(array $config);
    /** @return array [ok => bool, ref => string|null, error => string|null] */
    public function sendSms($to, $text);
    public function sendWhatsApp($to, $text);
}
