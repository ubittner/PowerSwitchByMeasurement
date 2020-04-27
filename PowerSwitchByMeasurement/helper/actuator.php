<?php

// Declare
declare(strict_types=1);

trait PSBM_actuator
{
    //#################### Private

    /**
     * Toggles the switching actuator off or on.
     *
     * @param bool $State
     * false    = off
     * true     = on
     *
     * @return bool
     */
    private function ToggleActuator(bool $State): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        if (!$this->CheckMaintenanceMode()) {
            return false;
        }
        $id = $this->ReadPropertyInteger('SwitchingActuator');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $stateText = 'ausgeschaltet';
            if ($State) {
                $stateText = 'eingeschaltet';
            }
            $actualActuatorStatus = boolval(GetValue($id));
            if ($actualActuatorStatus == $State) {
                $this->SendDebug(__FUNCTION__, 'Abbruch, der Schaltaktor mt der ID ' . $id . ' ist bereits ' . $stateText . '!', 0);
                return true;
            }
            $this->SendDebug(__FUNCTION__, 'Der Schaltaktor mit der ID ' . $id . ' wird ' . $stateText . '.', 0);
            $result = @RequestAction($id, $State);
            if (!$result) {
                // Try again
                $result = @RequestAction($id, $State);
                if (!$result) {
                    $this->SendDebug(__FUNCTION__, 'Fehler, der Schaltaktor mit der ID ' . $id . ' konnte nicht ' . $stateText . ' werden!', 0);
                    $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', Fehler, der Schaltaktor mit der ID ' . $id . ' konnte nicht ' . $stateText . ' werden!', KL_WARNING);
                } else {
                    $this->SendDebug(__FUNCTION__, 'Der Schaltaktor mit der ID ' . $id . ' wurde ' . $stateText . '.', 0);
                }
            }
        } else {
            $this->SendDebug(__FUNCTION__, 'Abbruch, es ist kein Schaltaktor zugewiesen!', 0);
            $result = false;
        }
        return $result;
    }

    /**
     * Gets the actual actuator state.
     */
    private function GetActuatorState(): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        if (!$this->CheckMaintenanceMode()) {
            return;
        }
        $id = $this->ReadPropertyInteger('SwitchingActuator');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $actualActuatorStatus = boolval(GetValue($id));
            $this->UpdateSwitchingMode($actualActuatorStatus);
        }
    }
}