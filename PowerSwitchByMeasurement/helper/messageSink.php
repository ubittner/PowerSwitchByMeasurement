<?php

// Declare
declare(strict_types=1);

trait PSBM_messageSink
{
    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        if (!$this->CheckMaintenanceMode()) {
            return;
        }
        if ($this->ReadPropertyBoolean('UseMessageSinkDebug')) {
            $this->SendDebug(__FUNCTION__, $TimeStamp . ', SenderID: ' . $SenderID . ', Message: ' . $Message . ', Data: ' . print_r($Data, true), 0);
            if (!empty($Data)) {
                foreach ($Data as $key => $value) {
                    $this->SendDebug(__FUNCTION__, 'Data[' . $key . '] = ' . json_encode($value), 0);
                }
            }
        }
        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->KernelReady();
                break;

            // $Data[0] = actual value
            // $Data[1] = difference to last value
            // $Data[2] = last value
            case VM_UPDATE:
                // Switching actuator
                if ($SenderID == $this->ReadPropertyInteger('SwitchingActuator')) {
                    if ($Data[1]) {
                        $scriptText = 'PSBM_UpdateSwitchingMode(' . $this->InstanceID . ', ' . json_encode(boolval($Data[0])) . ');';
                        IPS_RunScriptText($scriptText);
                    }
                }
                // Current consumption
                if ($SenderID == $this->ReadPropertyInteger('CurrentConsumption')) {
                    if ($Data[1]) {
                        $scriptText = 'PSBM_TriggerCurrentConsumptionAction(' . $this->InstanceID . ', ' . json_encode(intval($Data[0])) . ');';
                        IPS_RunScriptText($scriptText);
                    }
                }
                break;

        }
    }

    //#################### Private

    private function UnregisterMessages(): void
    {
        foreach ($this->GetMessageList() as $id => $registeredMessage) {
            foreach ($registeredMessage as $messageType) {
                if ($messageType == VM_UPDATE) {
                    $this->UnregisterMessage($id, VM_UPDATE);
                }
            }
        }
    }

    private function RegisterMessages(): void
    {
        // Unregister first
        $this->UnregisterMessages();
        // Switching actuator
        $id = $this->ReadPropertyInteger('SwitchingActuator');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $this->RegisterMessage($id, VM_UPDATE);
        }
        // Current consumption
        $id = $this->ReadPropertyInteger('CurrentConsumption');
        if ($id != 0 && IPS_ObjectExists($id)) {
            $this->RegisterMessage($id, VM_UPDATE);
        }
    }
}