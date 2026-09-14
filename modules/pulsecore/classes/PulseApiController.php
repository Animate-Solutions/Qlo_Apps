<?php
/**
 * Base JSON API controller. Subclasses declare $resources = array('name' => 'method').
 * Auth: header  Authorization: Bearer <token>  (token in pulse_api_token) or a valid guest portal session.
 */
abstract class PulseApiController extends ModuleFrontController
{
    protected $resources = array();
    protected $token;
    /** The hotel this request acts for, taken from the token or the X-Pulse-Hotel header. */
    protected $idHotel = 0;

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
        $this->token = PulseDb::getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_api_token` WHERE `token`="'.pSQL($m[1]).'" AND `active`=1');
        if (!$this->token) {
            throw new PrestaShopException('Unauthorized', 401);
        }
        $this->enterHotel();
    }

    /**
     * An API request has no back-office session, so the hotel comes from the token: each token
     * belongs to one property, and everything the request reads or writes is scoped to it.
     *
     * A token may also be issued for the whole group (id_hotel 0), for an integration that has to
     * reach several properties; such a request must name the property it means in the X-Pulse-Hotel
     * header, and is refused if it does not, because "no hotel" must never silently mean "all".
     */
    protected function enterHotel()
    {
        if (!class_exists('PulseHotelContext')) { return; }
        $idHotel = isset($this->token['id_hotel']) ? (int) $this->token['id_hotel'] : 0;
        $asked = (int) Tools::getValue('id_hotel', isset($_SERVER['HTTP_X_PULSE_HOTEL']) ? $_SERVER['HTTP_X_PULSE_HOTEL'] : 0);

        if ($idHotel > 0) {
            // A property token may not be pointed at another property.
            if ($asked && $asked !== $idHotel) {
                throw new PrestaShopException('This token belongs to another hotel', 403);
            }
        } else {
            if (!$asked) {
                throw new PrestaShopException('Specify the hotel: X-Pulse-Hotel header or id_hotel', 400);
            }
            if (!PulseDb::unscoped(function () use ($asked) {
                return (int) PulseDb::getValue('SELECT id FROM `'._DB_PREFIX_.'htl_branch_info` WHERE id = '.(int) $asked.' AND active = 1');
            })) {
                throw new PrestaShopException('Unknown hotel', 404);
            }
            $idHotel = $asked;
        }
        PulseHotelContext::assume($idHotel);
        $this->idHotel = $idHotel;
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
