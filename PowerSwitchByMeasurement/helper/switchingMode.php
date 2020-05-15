<?php

// Declare
declare(strict_types=1);

trait PSBM_switchingMode
{
    /**
     * Selects the switching mode.
     *
     * @param int $Mode
     * 0 = off
     * 1 = measurement
     * 2 = on
     */
    public function SelectSwitchingMode(int $Mode): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        if (!$this->CheckMaintenanceMode()) {
            return;
        }
        $actualSwitchingMode = $this->GetValue('SwitchingMode');
        $this->SetValue('SwitchingMode', $Mode);
        switch ($Mode) {
            // Off
            case 0:
                $this->SendDebug(__FUNCTION__, 'Modus: Aus', 0);
                $result = $this->ToggleActuator(false);
                if ($result) {
                    $this->DeactivateNotificationTimer();
                    $this->DeactivateSwitchingOffTimer();
                    $this->SetValue('MonitoringMode', false);
                    $this->SendDebug(__FUNCTION__, 'Die Überwachung wurde deaktiviert!', 0);
                } else {
                    $this->SetValue('SwitchingMode', $actualSwitchingMode);
                }
                break;

            // Measurement
            case 1:
                $this->SendDebug(__FUNCTION__, 'Modus: Messung', 0);
                $id = $this->ReadPropertyInteger('CurrentConsumption');
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    $consumptionValue = intval(GetValue($id));
                    $thresholdValueSwitchingOn = $this->ReadPropertyInteger('ThresholdValueSwitchingOn');
                    if ($consumptionValue > $thresholdValueSwitchingOn) {
                        $this->SetValue('MonitoringMode', true);
                        $this->SendDebug(__FUNCTION__, 'Die Überwachung wurde aktiviert!', 0);
                    }
                }
                $this->ToggleActuator(true);
                break;

            // On
            case 2:
                $this->SendDebug(__FUNCTION__, 'Modus: An', 0);
                $result = $this->ToggleActuator(true);
                if ($result) {
                    $this->DeactivateNotificationTimer();
                    $this->DeactivateSwitchingOffTimer();
                    $this->SetValue('MonitoringMode', false);
                    $this->SendDebug(__FUNCTION__, 'Die Überwachung wurde deaktiviert!', 0);
                } else {
                    $this->SetValue('SwitchingMode', $actualSwitchingMode);
                }
                break;

        }
        // Script
        $this->ExecuteScript($Mode);
        // Reset notification
        $this->ResetNotification();
    }

    /**
     * Updates the switching mode.
     *
     * @param bool $State
     * false    = off
     * true     = on
     */
    public function UpdateSwitchingMode(bool $State): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        if (!$this->CheckMaintenanceMode()) {
            return;
        }
        $this->SendDebug(__FUNCTION__, 'Neuer Wert: ' . json_encode($State), 0);
        // Off
        if (!$State) {
            $this->SendDebug(__FUNCTION__, 'Neuer Schaltaktorzustand: Aus', 0);
            $updateMode = $this->ReadPropertyInteger('ActuatorStatusOff');
            switch ($updateMode) {
                // Off
                case 1:
                    $this->SetValue('SwitchingMode', 0);
                    $this->SetValue('MonitoringMode', false);
                    $this->DeactivateNotificationTimer();
                    $this->DeactivateSwitchingOffTimer();
                    $this->ResetNotification();
                    $this->SendDebug(__FUNCTION__, 'Neuer Schaltzustand: Aus', 0);
                    break;

                default:
                    $this->SendDebug(__FUNCTION__, 'Es soll keine Aktualisierung vorgenommen werden.', 0);
                    return;
            }
        }
        // On
        if ($State) {
            $this->SendDebug(__FUNCTION__, 'Neuer Schaltaktorzustand: An', 0);
            $updateMode = $this->ReadPropertyInteger('ActuatorStatusOn');
            switch ($updateMode) {
                // Measurement
                case 1:
                    $switchingMode = 1;
                    $switchingModeName = 'Messung';
                    break;

                // On
                case 2:
                    $switchingMode = 2;
                    $switchingModeName = 'An';
                    break;

                default:
                    $this->SendDebug(__FUNCTION__, 'Es soll keine Aktualisierung vorgenommen werden.', 0);
                    return;
            }
            if ($this->GetValue('SwitchingMode') == 0) { // Off
                $this->SendDebug(__FUNCTION__, 'Neuer Schaltzustand: ' . $switchingModeName, 0);
                $this->SetValue('SwitchingMode', $switchingMode);
                $this->TriggerCurrentConsumptionAction();
                $this->DeactivateNotificationTimer();
                $this->DeactivateSwitchingOffTimer();
                $this->ResetNotification();
            } else {
                $this->SendDebug(__FUNCTION__, 'Der Schaltzustand bleibt unverändert.', 0);
            }
        }
    }

    /**
     * Triggers an automatic switch off.
     */
    public function TriggerAutomaticSwitchOff(): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        if (!$this->CheckMaintenanceMode()) {
            return;
        }
        $monitoringMode = $this->GetValue('MonitoringMode');
        if (!$monitoringMode) {
            $this->SendDebug(__FUNCTION__, 'Abbruch, die Überwachung ist inaktiv!', 0);
            return;
        }
        $id = $this->ReadPropertyInteger('CurrentConsumption');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $consumptionValue = intval(GetValue($id));
            $this->SendDebug(__FUNCTION__, 'Aktueller Verbrauch: ' . $consumptionValue . ' mA', 0);
            $thresholdValueSwitchingOff = $this->ReadPropertyInteger('ThresholdValueSwitchingOff');
            if ($consumptionValue < $thresholdValueSwitchingOff) {
                $this->SelectSwitchingMode(0);
            }
        }
    }
}