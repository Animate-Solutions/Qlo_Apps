<?php
/**
 * Bridge to Front Desk's PulseComms. When Front Desk is installed the CRM inherits its template map,
 * its SMS/WhatsApp adapter and its pulse_comms_log, so there is exactly one place a guest message can
 * come from. On a standalone install this stand-in offers the same surface and still sends email through
 * PrestaShop's mailer — SMS then has no adapter to reach, which the Campaigns screen reports honestly.
 */
if (class_exists('PulseComms')) {
    class PulseCrmCommsBase extends PulseComms
    {
    }
} else {
    class PulseCrmCommsBase
    {
        protected static $templates = array();

        /** No Front Desk means no configured SMS adapter; campaigns on SMS report "no adapter" rather than pretending. */
        protected static function adapter() { return null; }

        protected static function vars(array $extra)
        {
            $v = array('hotel' => Configuration::get('PS_SHOP_NAME'), 'name' => '', 'room' => '', 'ref' => '', 'from' => '', 'to' => '', 'folio' => '', 'title' => '', 'status' => '');
            return array_merge($v, $extra);
        }

        protected static function fill($text, array $vars) { foreach ($vars as $k => $val) { if (is_scalar($val)) { $text = str_replace('{'.$k.'}', $val, $text); } } return $text; }

        public static function send($template, Customer $customer, array $extra = array())
        {
            $extra['name'] = $customer->firstname; $extra['id_customer'] = $customer->id;
            return self::sendRaw($customer->email, null, $template, $extra);
        }

        public static function sendRaw($email, $phone, $template, array $extra = array())
        {
            if (!isset(self::$templates[$template])) { return false; }
            $t = self::$templates[$template]; $v = self::vars($extra);
            $text = self::fill($t['sms'], $v); $subject = self::fill($t['subject'], $v);
            if (!$email || !Validate::isEmail($email) || strpos($email, '@walkin.local') !== false) { return false; }
            $html = !empty($extra['html']) ? $extra['html'] : '<p>'.nl2br(htmlspecialchars($text)).'</p>';
            return (bool) Mail::Send((int) Context::getContext()->language->id, 'crm_generic', $subject, array('{message}' => $html, '{title}' => $subject), $email, null, null, null, null, null, _PS_MODULE_DIR_.'pulsecrm/mails/');
        }
    }
}
