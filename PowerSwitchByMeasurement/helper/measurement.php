<?php

// Declare
declare(strict_types=1);

trait PSBM_measurement
{
    /**
     * Triggers an action according to the current consumption value.
     *
     * @param int $Value
     */
    public function TriggerCurrentConsumptionAction(int $Value): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        if (!$this->CheckMaintenanceMode()) {
            return;
        }
        $actualSwitchingMode = $this->GetValue('SwitchingMode');
        if ($actualSwitchingMode != 1) {
            $this->SendDebug(__FUNCTION__, 'Abbruch, die Messung ist inaktiv!', 0);
            return;
        }
        $this->SendDebug(__FUNCTION__, 'Aktueller Verbrauch: ' . $Value . ' mA', 0);
        $thresholdValueSwitchingOn = $this->ReadPropertyInteger('ThresholdValueSwitchingOn');
        if ($Value > $thresholdValueSwitchingOn) {
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
        if ($Value < $thresholdValueSwitchingOff) {
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