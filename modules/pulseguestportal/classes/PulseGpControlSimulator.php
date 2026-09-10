<?php
/**
 * Simulator adapter — the default. It talks to no hardware and says so: state lives in
 * pulse_gp_control_point, every action succeeds, and the log records adapter=simulator so nobody mistakes a
 * demo for a wired room. A property with no BMS can still ship the section as a room-preference panel that
 * housekeeping reads (set the AC to 22° before I return).
 */
class PulseGpControlSimulator implements PulseGpControlInterface
{
    protected $cfg;
    public function __construct(array $config) { $this->cfg = $config; }

    public function apply($idRoom, array $point, $action, $value = null)
    {
        $state = $point['state']; $val = (float) $point['value'];
        switch ($action) {
            case 'on': $state = 'on'; break;
            case 'off': $state = 'off'; break;
            case 'toggle': $state = $point['state'] === 'on' ? 'off' : 'on'; break;
            case 'open': $state = 'open'; break;
            case 'close': $state = 'closed'; break;
            case 'set':
                $val = round((float) $value, 1);
                if ($val < (float) $point['min_value'] || $val > (float) $point['max_value']) { return array('ok' => false, 'state' => $state, 'value' => (float) $point['value'], 'message' => 'Value out of range ('.(float) $point['min_value'].'–'.(float) $point['max_value'].')'); }
                if ($point['type'] === 'ac' || $point['type'] === 'light') { $state = $val > 0 ? 'on' : 'off'; }
                break;
            case 'scene': $state = 'on'; break;
            default: return array('ok' => false, 'state' => $state, 'value' => $val, 'message' => 'Unsupported action '.$action);
        }
        return array('ok' => true, 'state' => $state, 'value' => $val, 'message' => 'simulated');
    }
    public function read($idRoom, array $point) { return array('ok' => true, 'state' => $point['state'], 'value' => (float) $point['value'], 'message' => 'simulated'); }
    /** The layout a standard Carvington room has: two light circuits, the AC, the curtain and two scenes. */
    public function discover($idRoom)
    {
        return array(
            array('code' => 'light_main', 'type' => 'light', 'label' => 'Main light', 'state' => 'off', 'value' => 0, 'min_value' => 0, 'max_value' => 100, 'sort' => 1),
            array('code' => 'light_bed', 'type' => 'light', 'label' => 'Bedside light', 'state' => 'off', 'value' => 0, 'min_value' => 0, 'max_value' => 100, 'sort' => 2),
            array('code' => 'ac', 'type' => 'ac', 'label' => 'Air conditioning', 'state' => 'on', 'value' => 24, 'min_value' => 16, 'max_value' => 30, 'sort' => 3),
            array('code' => 'curtain', 'type' => 'curtain', 'label' => 'Curtains', 'state' => 'closed', 'value' => 0, 'min_value' => 0, 'max_value' => 100, 'sort' => 4),
            array('code' => 'scene_night', 'type' => 'scene', 'label' => 'Good night', 'state' => 'off', 'value' => 0, 'min_value' => 0, 'max_value' => 1, 'sort' => 5),
            array('code' => 'scene_welcome', 'type' => 'scene', 'label' => 'Welcome', 'state' => 'off', 'value' => 0, 'min_value' => 0, 'max_value' => 1, 'sort' => 6),
        );
    }
    public function test() { return array('ok' => true, 'message' => 'Simulator adapter — no hardware is contacted, states are stored in Pulse only'); }
}
