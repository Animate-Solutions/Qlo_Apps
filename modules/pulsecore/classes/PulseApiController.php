<?php
/**
 * Base JSON API controller. Subclasses declare $resources = array('name' => 'method').
 * Auth: header  Authorization: Bearer <token>  (token in pulse_api_token) or a valid guest portal session.
 */
abstract class PulseApiController extends ModuleFrontController
{
    protected $resources = array();
    protected $token;

    public function init()
    {
        $this->ajax = true;
        parent::init();
    }

    public function postProcess()
    {
        header('Content-Type: application/json');
        try {
            $this->authenticate();
            $resource = Tools::getValue('resource', 'ping');
            if (!isset($this->resources[$resource])) {
                throw new PrestaShopException('Unknown resource', 404);
            }
            $method = $this->resources[$resource];
            $body = json_decode(Tools::file_get_contents('php://input'), true);
            $out = $this->$method((int) Tools::getValue('id'), is_array($body) ? $body : array());
            $this->respond(array('ok' => true, 'data' => $out));
        } catch (Exception $e) {
            $this->respond(array('ok' => false, 'error' => $e->getMessage()), $e->getCode() ?: 400);
        }
    }

    protected function authenticate()
    {
        $lic = _PS_MODULE_DIR_.'pulselicense/classes/PulseLicenseService.php';
        if (file_exists($lic) && Module::isEnabled('pulselicense')) { require_once $lic; PulseLicenseService::assertApi(); }
        $hdr = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
        if (!preg_match('/^Bearer\s+([A-Za-z0-9]{64})$/', $hdr, $m)) {
            throw new PrestaShopException('Unauthorized', 401);
        }
        $this->token = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_api_token` WHERE `token`="'.pSQL($m[1]).'" AND `active`=1');
        if (!$this->token) {
            throw new PrestaShopException('Unauthorized', 401);
        }
    }

    protected function requireScope($scope)
    {
        if (!in_array($scope, explode(',', $this->token['scopes']))) {
            throw new PrestaShopException('Forbidden: scope '.$scope, 403);
        }
    }

    protected function respond(array $payload, $status = 200)
    {
        http_response_code($status);
        die(json_encode($payload));
    }
}
