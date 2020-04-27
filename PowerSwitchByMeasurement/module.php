<?php

/*
 * @module      Power Switch By Measurement
 *
 * @prefix      PSBM
 *
 * @file        module.php
 *
 * @author      Ulrich Bittner
 * @copyright   (c) 2020
 * @license     CC BY-NC-SA 4.0
 *              https://creativecommons.org/licenses/by-nc-sa/4.0/
 *
 * @version     1.00-2
 * @date        2020-04-25, 18:00, 1587834000
 * @review      2020-04-25, 18:00
 *
 * @see         https://github.com/ubittner/PowerSwitchByMeasurement
 *
 * @guids       Library
 *              {F0FA04E5-E364-F2F8-AFFC-1AA5AC8F4229}
 *
 *              Power Switch By Measurement
 *             	{7AF35886-FB4A-D44E-118D-63B5794CED66}
 */

// Declare
declare(strict_types=1);

// Include
include_once __DIR__ . '/helper/autoload.php';

class PowerSwitchByMeasurement extends IPSModule
{
    // Helper
    use PSBM_actuator;
    use PSBM_backupRestore;
    use PSBM_measurement;
    use PSBM_messageSink;
    use PSBM_notification;
    use PSBM_script;
    use PSBM_switchingMode;

    // Constants
    private const NOTIFICATION_CENTER_GUID = '{D184C522-507F-BED6-6731-728CE156D659}';

    public function Create()
    {
        // Never delete this line!
        parent::Create();
        // Register properties
        $this->RegisterProperties();
        // Create profiles
        $this->CreateProfiles();
        // Register variables
        $this->RegisterVariables();
        // Register timers
        $this->RegisterNotificationTimer();
        $this->RegisterSwitchingOffTimer();
    }

    public function ApplyChanges()
    {
        // Wait until IP-Symcon is started
        $this->RegisterMessage(0, IPS_KERNELSTARTED);
        // Never delete this line!
        parent::ApplyChanges();
        // Check runlevel
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        // Register messages
        $this->RegisterMessages();
        // Create links
        $this->CreateLinks();
        // Set options
        $this->SetOptions();
        // Deactivate timers
        $this->DeactivateNotificationTimer();
        $this->DeactivateSwitchingOffTimer();
        // Check maintenance mode
        $this->CheckMaintenanceMode();
        // Get actuator state
        $this->GetActuatorState();
    }

    public function Destroy()
    {
        // Never delete this line!
        parent::Destroy();
        // Delete profiles
        $this->DeleteProfiles();
    }

    private function KernelReady()
    {
        $this->ApplyChanges();
    }

    public function ReloadConfiguration()
    {
        $this->ReloadForm();
    }

    public function GetConfigurationForm()
    {
        $formData = json_decode(file_get_contents(__DIR__ . '/form.json'));
        // Registered messages
        $registeredVariables = $this->GetMessageList();
        foreach ($registeredVariables as $senderID => $messageID) {
            if (!IPS_ObjectExists($senderID)) {
                foreach ($messageID as $messageType) {
                    $this->UnregisterMessage($senderID, $messageType);
                }
                continue;
            } else {
                $senderName = IPS_GetName($senderID);
                $description = $senderName;
                $parentID = IPS_GetParent($senderID);
                if (is_int($parentID) && $parentID != 0 && @IPS_ObjectExists($parentID)) {
                    $description = IPS_GetName($parentID);
                }
            }
            switch ($messageID) {
                case [10001]:
                    $messageDescription = 'IPS_KERNELSTARTED';
                    break;

                case [10603]:
                    $messageDescription = 'VM_UPDATE';
                    break;

                case [10803]:
                    $messageDescription = 'EM_UPDATE';
                    break;

                default:
                    $messageDescription = 'keine Bezeichnung';
            }
            $formData->actions[1]->items[0]->values[] = [
                'Description'        => $description,
                'SenderID'           => $senderID,
                'SenderName'         => $senderName,
                'MessageID'          => $messageID,
                'MessageDescription' => $messageDescription];
        }
        return json_encode($formData);
    }

    //#################### Request action

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'SwitchingMode':
                $this->SelectSwitchingMode($Value);
                break;

        }
    }

    //#################### Private

    private function RegisterProperties(): void
    {
        // General options
        $this->RegisterPropertyBoolean('MaintenanceMode', false);
        $this->RegisterPropertyBoolean('EnableSwitchingMode', true);
        $this->RegisterPropertyBoolean('EnableMonitoringMode', true);
        $this->RegisterPropertyBoolean('EnableCurrentConsumption', true);
        $this->RegisterPropertyBoolean('EnableEnergyCounter', true);
        $this->RegisterPropertyBoolean('EnableThresholdValueOff', true);
        $this->RegisterPropertyBoolean('EnableThresholdValueOn', true);
        $this->RegisterPropertyBoolean('EnableNextNotification', true);
        $this->RegisterPropertyBoolean('EnableNextSwitchOff', true);
        $this->RegisterPropertyBoolean('UseMessageSinkDebug', false);
        // Actuator
        $this->RegisterPropertyInteger('SwitchingActuator', 0);
        // Consumption
        $this->RegisterPropertyInteger('CurrentConsumption', 0);
        $this->RegisterPropertyInteger('EnergyCounter', 0);
        $this->RegisterPropertyInteger('Archive', 0);
        $this->RegisterPropertyBoolean('UseCurrentConsumptionArchiving', false);
        // Measurement
        $this->RegisterPropertyInteger('ThresholdValueSwitchingOff', 5);
        $this->RegisterPropertyInteger('ThresholdValueSwitchingOn', 100);
        // Notification
        $this->RegisterPropertyInteger('NotificationCenter', 0);
        $this->RegisterPropertyInteger('NotificationExpirationTime', 5);
        $this->RegisterPropertyInteger('NotificationExpirationUnit', 1);
        $this->RegisterPropertyString('NotificationTitle', 'PSBM');
        $this->RegisterPropertyString('NotificationText', 'Der Grenzwert Aus wurde unterschritten.');
        // Switching off
        $this->RegisterPropertyBoolean('UseSwitchingOff', true);
        $this->RegisterPropertyInteger('SwitchingOffExpirationTime', 10);
        $this->RegisterPropertyInteger('SwitchingOffExpirationUnit', 1);
        // Script
        $this->RegisterPropertyInteger('Script', 0);
    }

    private function CreateProfiles(): void
    {
        // Switching mode
        $profile = 'PSBM.' . $this->InstanceID . '.SwitchingMode';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 1);
        }
        IPS_SetVariableProfileIcon($profile, 'Power');
        IPS_SetVariableProfileAssociation($profile, 0, 'Aus', '', 0xFF0000);
        IPS_SetVariableProfileAssociation($profile, 1, 'Messung', '', 0xFFFF00);
        IPS_SetVariableProfileAssociation($profile, 2, 'An', '', 0x00FF00);
        // Monitoring mode
        $profile = 'PSBM.' . $this->InstanceID . '.MonitoringMode';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileIcon($profile, 'Eyes');
        IPS_SetVariableProfileAssociation($profile, 0, 'Inaktiv', '', 0xFF0000);
        IPS_SetVariableProfileAssociation($profile, 1, 'Aktiv', '', 0x00FF00);
        // Below milliampere
        $profile = 'PSBM.' . $this->InstanceID . '.BelowMilliampere';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 1);
        }
        IPS_SetVariableProfileValues($profile, 0, 16000, 1);
        IPS_SetVariableProfileDigits($profile, 0);
        IPS_SetVariableProfileText($profile, '< ', ' mA');
        IPS_SetVariableProfileIcon($profile, 'Electricity');
        // Above milliampere
        $profile = 'PSBM.' . $this->InstanceID . '.AboveMilliampere';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 1);
        }
        IPS_SetVariableProfileValues($profile, 0, 16000, 1);
        IPS_SetVariableProfileDigits($profile, 0);
        IPS_SetVariableProfileText($profile, '> ', ' mA');
        IPS_SetVariableProfileIcon($profile, 'Electricity');
    }

    private function DeleteProfiles(): void
    {
        $profiles = ['SwitchingMode', 'MonitoringMode', 'BelowMilliampere', 'AboveMilliampere'];
        foreach ($profiles as $profile) {
            $profileName = 'PSBM.' . $this->InstanceID . '.' . $profile;
            if (@IPS_VariableProfileExists($profileName)) {
                IPS_DeleteVariableProfile($profileName);
            }
        }
    }

    private function RegisterVariables(): void
    {
        // Switching mode
        $profile = 'PSBM.' . $this->InstanceID . '.SwitchingMode';
        $this->RegisterVariableInteger('SwitchingMode', 'Schaltzustand', $profile, 1);
        $this->EnableAction('SwitchingMode');
        // Monitoring mode
        $profile = 'PSBM.' . $this->InstanceID . '.MonitoringMode';
        $this->RegisterVariableBoolean('MonitoringMode', 'Überwachung', $profile, 2);
        // Threshold value off
        $profile = 'PSBM.' . $this->InstanceID . '.BelowMilliampere';
        $this->RegisterVariableInteger('ThresholdValueOff', 'Schwellenwert Aus', $profile, 5);
        IPS_SetIcon($this->GetIDForIdent('ThresholdValueOff'), 'Cross');
        // Threshold value on
        $profile = 'PSBM.' . $this->InstanceID . '.AboveMilliampere';
        $this->RegisterVariableInteger('ThresholdValueOn', 'Schwellenwert An', $profile, 6);
        IPS_SetIcon($this->GetIDForIdent('ThresholdValueOn'), 'Plug');
        // Next notification
        $this->RegisterVariableString('NextNotification', 'Nächste Benachrichtigung', '', 7);
        IPS_SetIcon($this->GetIDForIdent('NextNotification'), 'Clock');
        // Next switch off
        $this->RegisterVariableString('NextSwitchOff', 'Nächste Ausschaltung', '', 8);
        IPS_SetIcon($this->GetIDForIdent('NextSwitchOff'), 'Clock');
    }

    private function CreateLinks(): void
    {
        // Current consumption
        $linkID = @IPS_GetLinkIDByName('Aktueller Verbrauch', $this->InstanceID);
        $targetID = $this->ReadPropertyInteger('CurrentConsumption');
        if ($targetID != 0 && @IPS_ObjectExists($targetID)) {
            // Check for existing link
            if ($linkID === false) {
                $linkID = IPS_CreateLink();
            }
            IPS_SetParent($linkID, $this->InstanceID);
            IPS_SetPosition($linkID, 3);
            IPS_SetName($linkID, 'Aktueller Verbrauch');
            IPS_SetIcon($linkID, 'Electricity');
            IPS_SetLinkTargetID($linkID, $targetID);
        } else {
            if ($linkID !== false) {
                IPS_SetHidden($linkID, true);
            }
        }
        // Energy counter
        $linkID = @IPS_GetLinkIDByName('Gesamtverbrauch', $this->InstanceID);
        $targetID = $this->ReadPropertyInteger('EnergyCounter');
        if ($targetID != 0 && @IPS_ObjectExists($targetID)) {
            // Check for existing link
            if ($linkID === false) {
                $linkID = IPS_CreateLink();
            }
            IPS_SetParent($linkID, $this->InstanceID);
            IPS_SetPosition($linkID, 4);
            IPS_SetName($linkID, 'Gesamtverbrauch');
            IPS_SetIcon($linkID, 'EnergyProduction');
            IPS_SetLinkTargetID($linkID, $targetID);
        } else {
            if ($linkID !== false) {
                IPS_SetHidden($linkID, true);
            }
        }
    }

    private function SetOptions(): void
    {
        // Switching mode
        IPS_SetHidden($this->GetIDForIdent('SwitchingMode'), !$this->ReadPropertyBoolean('EnableSwitchingMode'));
        // Monitoring mode
        IPS_SetHidden($this->GetIDForIdent('MonitoringMode'), !$this->ReadPropertyBoolean('EnableMonitoringMode'));
        // Current consumption
        $id = @IPS_GetLinkIDByName('Aktueller Verbrauch', $this->InstanceID);
        if ($id !== false) {
            $hide = true;
            $targetID = $this->ReadPropertyInteger('CurrentConsumption');
            if ($targetID != 0 && @IPS_ObjectExists($targetID)) {
                $archive = $this->ReadPropertyInteger('Archive');
                if ($archive != 0 && @IPS_ObjectExists($archive)) {
                    $state = $this->ReadPropertyBoolean('UseCurrentConsumptionArchiving');
                    @AC_SetLoggingStatus($archive, $targetID, $state);
                    @IPS_ApplyChanges($archive);
                }
                if ($this->ReadPropertyBoolean('EnableCurrentConsumption')) {
                    $hide = false;
                }
            }
            IPS_SetHidden($id, $hide);
        }
        // Energy counter
        $id = @IPS_GetLinkIDByName('Gesamtverbrauch', $this->InstanceID);
        if ($id !== false) {
            $hide = true;
            $targetID = $this->ReadPropertyInteger('EnergyCounter');
            if ($targetID != 0 && @IPS_ObjectExists($targetID)) {
                if ($this->ReadPropertyBoolean('EnableEnergyCounter')) {
                    $hide = false;
                }
            }
            IPS_SetHidden($id, $hide);
        }
        // Threshold value off
        $this->SetValue('ThresholdValueOff', $this->ReadPropertyInteger('ThresholdValueSwitchingOff'));
        IPS_SetHidden($this->GetIDForIdent('ThresholdValueOff'), !$this->ReadPropertyBoolean('EnableThresholdValueOff'));
        // Threshold value on
        $this->SetValue('ThresholdValueOn', $this->ReadPropertyInteger('ThresholdValueSwitchingOn'));
        IPS_SetHidden($this->GetIDForIdent('ThresholdValueOn'), !$this->ReadPropertyBoolean('EnableThresholdValueOn'));
        // Next notification
        IPS_SetHidden($this->GetIDForIdent('NextNotification'), !$this->ReadPropertyBoolean('EnableNextNotification'));
        // Next switch off
        IPS_SetHidden($this->GetIDForIdent('NextSwitchOff'), !$this->ReadPropertyBoolean('EnableNextSwitchOff'));
    }

    public function CreateScriptExample(): void
    {
        $scriptID = IPS_CreateScript(0);
        IPS_SetName($scriptID, 'Beispielskript (PSBM #' . $this->InstanceID . ')');
        $scriptContent = "<?php\n\n// Methode:\n// PSBM_SelectSwitchingMode(integer \$InstanceID, integer \$Mode);\n\n### Beispiele:\n\n// Aus:\nPSBM_SelectSwitchingMode(" . $this->InstanceID . ", 0);\n\n// Messung:\nPSBM_SelectSwitchingMode(" . $this->InstanceID . ", 1);\n\n// An:\nPSBM_SelectSwitchingMode(" . $this->InstanceID . ', 2);';
        IPS_SetScriptContent($scriptID, $scriptContent);
        IPS_SetParent($scriptID, $this->InstanceID);
        IPS_SetPosition($scriptID, 100);
        IPS_SetHidden($scriptID, true);
        if ($scriptID != 0) {
            echo 'Beispielskript wurde erfolgreich erstellt!';
        }
    }

    private function RegisterNotificationTimer(): void
    {
        $this->RegisterTimer('Notification', 0, 'PSBM_TriggerNotification(' . $this->InstanceID . ');');
    }

    private function RegisterSwitchingOffTimer(): void
    {
        $this->RegisterTimer('SwitchingOff', 0, 'PSBM_TriggerAutomaticSwitchOff(' . $this->InstanceID . ');');
    }

    private function SetNotificationTimer(): void
    {
        // Duration from minutes to seconds
        $duration = $this->ReadPropertyInteger('NotificationExpirationTime');
        $durationUnit = $this->ReadPropertyInteger('NotificationExpirationUnit');
        if ($durationUnit == 1) {
            $duration = $duration * 60;
        }
        // Set timer interval
        $this->SetTimerInterval('Notification', $duration * 1000);
        $timestamp = time() + $duration;
        $this->SetValue('NextNotification', date('d.m.Y, H:i:s', ($timestamp)));
    }

    private function SetSwitchingOffTimer(): void
    {
        // Duration from minutes to seconds
        $duration = $this->ReadPropertyInteger('SwitchingOffExpirationTime');
        $durationUnit = $this->ReadPropertyInteger('SwitchingOffExpirationUnit');
        if ($durationUnit == 1) {
            $duration = $duration * 60;
        }
        // Set timer interval
        $this->SetTimerInterval('SwitchingOff', $duration * 1000);
        $timestamp = time() + $duration;
        $this->SetValue('NextSwitchOff', date('d.m.Y, H:i:s', ($timestamp)));
    }

    public function DeactivateNotificationTimer(): void
    {
        $this->SetTimerInterval('Notification', 0);
        $this->SetValue('NextNotification', '-');
    }

    public function DeactivateSwitchingOffTimer(): void
    {
        $this->SetTimerInterval('SwitchingOff', 0);
        $this->SetValue('NextSwitchOff', '-');
    }

    private function CheckMaintenanceMode(): bool
    {
        $result = true;
        $status = 102;
        if ($this->ReadPropertyBoolean('MaintenanceMode')) {
            $result = false;
            $status = 104;
            $this->SendDebug(__FUNCTION__, 'Abbruch, der Wartungsmodus ist aktiv!', 0);
            $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', Abbruch, der Wartungsmodus ist aktiv!', KL_WARNING);
        }
        $this->SetStatus($status);
        IPS_SetDisabled($this->InstanceID, !$result);
        return $result;
    }
}