<?php
/**
 * Room-control adapter contract (lights, air conditioning, curtains, scenes).
 *
 * Pulse does not ship a BMS. An adapter translates one portal action into whatever the property's controller
 * speaks, and reports honestly whether it reached the hardware. Implement these four methods, drop the class
 * in this folder and pick it in Portal Settings — nothing else in the module needs to change.
 *
 *   apply()   perform the action, return array('ok'=>bool,'state'=>string,'value'=>float,'message'=>string)
 *   read()    read one point back from the controller (or the last known state when the bus is quiet)
 *   discover() list the points a room exposes, used to provision pulse_gp_control_point
 *   test()    connectivity probe for the settings screen
 */
interface PulseGpControlInterface
{
    public function __construct(array $config);
    public function apply($idRoom, array $point, $action, $value = null);
    public function read($idRoom, array $point);
    public function discover($idRoom);
    public function test();
}
