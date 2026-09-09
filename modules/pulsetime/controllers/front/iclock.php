<?php
require_once _PS_MODULE_DIR_.'pulsetime/classes/autoload.php';

/**
 * /iclock/{action} — the ADMS push endpoint ZKTeco firmware dials into, plus the Hikvision HTTP event
 * listener. The path is NOT a design choice: the firmware has /iclock/cdata, /iclock/getrequest and
 * /iclock/devicecmd compiled in and will call nothing else. See the README for the rewrite a property needs
 * when friendly URLs are off.
 *
 * This is a public, unauthenticated-by-default surface, so everything defensive lives in PulseTaAdms::admit():
 * an unknown serial registers as `pending` and is inert until an administrator claims it; a claimed device is
 * identified by its registered serial plus an optional shared key and an optional IP allowlist; every request
 * is rate-limited per serial and the body is capped before it is read.
 *
 * Replies are the terse plain-text strings the firmware parses. Even a refusal answers 200 with a body the
 * device understands, because a device that gets an error page retries in a tight loop and floods the link.
 */
class PulseTimeIclockModuleFrontController extends ModuleFrontController
{
    public $ssl = false;

    public function init()
    {
        $this->ajax = true;
        parent::init();
    }

    public function postProcess()
    {
        $action = Tools::strtolower(preg_replace('/[^a-zA-Z_]/', '', (string) Tools::getValue('action', 'cdata')));
        $serial = PulseTaAdms::cleanSerial(Tools::getValue('SN', Tools::getValue('sn', '')));
        $method = isset($_SERVER['REQUEST_METHOD']) ? Tools::strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';
        $declared = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
        $max = PulseTaAdms::maxBytes();

        // The declared length is handed to admit() rather than short-circuited here, so an oversize body is
        // logged and counted by the rate limiter instead of being answered for free.
        $admit = PulseTaAdms::admit($serial, $action, $declared);
        if (empty($admit['ok'])) {
            // A pending or refused device still gets a syntactically valid answer: it keeps buffering its
            // punches locally and re-sends them the moment an administrator claims it.
            if ($action === 'getrequest') { $this->reply('OK'); }
            if ($action === 'cdata' && $method === 'GET') { $this->reply(PulseTaAdms::handshake($admit['device'])); }
            $this->reply('OK: 0');
        }
        $dev = $admit['device'];

        if ($action === 'ping') { $this->reply(PulseTaAdms::ping($dev)); }
        if ($action === 'getrequest') { $this->reply(PulseTaAdms::getrequest($dev)); }

        $body = $this->body($max);
        if ($action === 'devicecmd') { $this->reply(PulseTaAdms::devicecmd($dev, $body)); }
        if ($action === 'event') { $this->reply(PulseTaAdms::hikEvent($dev, $body, isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '')); }

        if ($action === 'cdata') {
            if ($method !== 'POST' || $body === '') { PulseTaAdms::logHandshake($dev, $serial, strlen($body)); $this->reply(PulseTaAdms::handshake($dev)); }
            $table = Tools::strtoupper(preg_replace('/[^A-Za-z0-9_]/', '', (string) Tools::getValue('table', 'ATTLOG')));
            if ($table === 'ATTLOG') { $this->reply(PulseTaAdms::attlog($dev, $body)); }
            if ($table === 'OPERLOG') { $this->reply(PulseTaAdms::operlog($dev, $body)); }
            $this->reply(PulseTaAdms::otherTable($dev, $table, $body));
        }

        // fdata (fingerprint templates), querydata and anything a future firmware invents: logged, acknowledged.
        PulseTaAdms::log($serial, $action, '', 'ok', 'unhandled path acknowledged', strlen($body), 0, 0, (int) $dev['id_pulse_ta_device'], substr($body, 0, 200));
        $this->reply('OK');
    }

    /**
     * Read at most $max BYTES of the request body — never the whole stream unbounded.
     * strlen/substr here, not Tools::strlen/Tools::substr: this is a byte cap on a raw request body, and the
     * multibyte versions would count characters and could let a large body through.
     */
    protected function body($max)
    {
        $raw = @file_get_contents('php://input', false, null, 0, $max + 1);
        if ($raw === false) { return ''; }
        return strlen($raw) > $max ? substr($raw, 0, $max) : $raw;
    }

    /** Plain text, no theme, no cookie, end of request. */
    protected function reply($body, $status = 200)
    {
        http_response_code((int) $status);
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        die((string) $body);
    }

    public function initContent() { $this->postProcess(); }
    public function setMedia() { return true; }
}
