<?php

// Declare
declare(strict_types=1);

trait PSBM_notification
{
    /**
     * Triggers a notification.
     */
    public function TriggerNotification(): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        if (!$this->CheckMaintenanceMode()) {
            return;
        }
        $id = $this->ReadPropertyInteger('CurrentConsumption');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $consumptionValue = intval(GetValue($id));
            $this->SendDebug(__FUNCTION__, 'Aktueller Verbrauch: ' . $consumptionValue . ' mA', 0);
            $thresholdValueSwitchingOff = $this->ReadPropertyInteger('ThresholdValueSwitchingOff');
            if ($consumptionValue < $thresholdValueSwitchingOff) {
                $this->DeactivateNotificationTimer();
                $this->SendNotification();
            }
        }
    }

    //##################### Private

    /**
     * Sends a notification.
     */
    private function SendNotification(): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        if (!$this->CheckMaintenanceMode()) {
            return;
        }
        if (!$this->ValidateNotificationCenter()) {
            return;
        }
        $notify = $this->ReadAttributeBoolean('SendNotification');
        if ($notify) {
            $this->SendDebug(__FUNCTION__, 'Die Benachrichtigung wird versendet.', 0);
            $id = $this->ReadPropertyInteger('NotificationCenter');
            $title = substr($this->ReadPropertyString('NotificationTitle'), 0, 32);
            $text = $this->ReadPropertyString('NotificationText');
            $pushTitle = $title;
            $pushText = $text;
            $emailSubject = $title;
            $emailText = $text;
            $smsText = $title . ', ' . $text;
            $messageType = 0;
            @BENA_SendNotification($id, $pushTitle, $pushText, $emailSubject, $emailText, $smsText, $messageType);
            $this->WriteAttributeBoolean('SendNotification', false);
            $this->SendDebug(__FUNCTION__, 'Attribute: ' . json_encode(false), 0);
        }
    }

    /**
     * Validates the notification center.
     *
     * @return bool
     * false    = an error occurred
     * true     = notification center is valid
     */
    private function ValidateNotificationCenter(): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $result = true;
        $id = $this->ReadPropertyInteger('NotificationCenter');
        if ($id == 0 || !@IPS_ObjectExists($id)) {
            $this->SendDebug(__FUNCTION__, 'Abbruch, es ist keine Benachrichtigungszentrale zugewiesen!', 0);
            return false;
        }
        if ($id != 0 && IPS_ObjectExists($id)) {
            $instance = IPS_GetInstance($id);
            $moduleID = $instance['ModuleInfo']['ModuleID'];
            if ($moduleID !== self::NOTIFICATION_CENTER_GUID) {
                $this->SendDebug(__FUNCTION__, 'Abbruch, die GUID der Benachrichtigungszentrale ist ungültig!', 0);
                return false;
            }
        }
        if ($result) {
            $this->SendDebug(__FUNCTION__, 'Die Benachrichtigungszentrale kann verwendet werden.', 0);
        }
        return $result;
    }
}