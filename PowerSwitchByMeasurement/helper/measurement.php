<?php

// Declare
declare(strict_types=1);

trait PSBM_measurement
{
    /**
     * Triggers an action according to the current consumption value.
     */
    public function TriggerCurrentConsumptionAction(): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        if (!$this->CheckMaintenanceMode()) {
            return;
        }
        $actualSwitchingMode = $this->GetValue('SwitchingMode');
        if ($actualSwitchingMode != 1) {
            $this->SendDebug(__FUNCTION__, 'Abbruch, die Messung ist inaktiv!', 0);
            $this->SetValue('MonitoringMode', false);
            return;
        }
        $id = $this->ReadPropertyInteger('CurrentConsumption');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $value = intval(GetValue($id));
            $this->SendDebug(__FUNCTION__, 'Aktueller Verbrauch: ' . $value . ' mA', 0);
            $thresholdValueSwitchingOn = $this->ReadPropertyInteger('ThresholdValueSwitchingOn');
            if ($value > $thresholdValueSwitchingOn) {
                $this->SetValue('MonitoringMode', true);
                $this->SendDebug(__FUNCTION__, 'Die Überwachung wurde aktiviert!', 0);
                $this->DeactivateNotificationTimer();
                $this->DeactivateSwitchingOffTimer();
            }
            $monitoringMode = $this->GetValue('MonitoringMode');
            if (!$monitoringMode) {
                $this->SendDebug(__FUNCTION__, 'Abbruch, die Überwachung ist inaktiv!', 0);
                return;
            }
            $thresholdValueSwitchingOff = $this->ReadPropertyInteger('ThresholdValueSwitchingOff');
            if ($value < $thresholdValueSwitchingOff) {
                if ($this->ValidateNotificationCenter()) {
                    $duration = $this->ReadPropertyInteger('NotificationExpirationTime');
                    if ($duration == 0) {
                        $this->TriggerNotification();
                    } else {
                        $this->SetNotificationTimer();
                    }
                }
                $useSwitchingOff = $this->ReadPropertyBoolean('UseSwitchingOff');
                if ($useSwitchingOff) {
                    $duration = $this->ReadPropertyInteger('SwitchingOffExpirationTime');
                    if ($duration == 0) {
                        $this->TriggerAutomaticSwitchOff();
                    } else {
                        $this->SetSwitchingOffTimer();
                    }
                }
            }
        }
    }
}