<?php

// Declare
declare(strict_types=1);

trait PSBM_script
{
    /**
     * Executes a script.
     *
     * @param int $Mode
     * 0    = off
     * 1    = measurement
     * 2    = on
     *
     * @return bool
     */
    public function ExecuteScript(int $Mode): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        if (!$this->CheckMaintenanceMode()) {
            return false;
        }
        $id = $this->ReadPropertyInteger('Script');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $this->SendDebug(__FUNCTION__, 'Das Skript mit der ID ' . $id . ' wird ausgeführt.', 0);
            $this->SendDebug(__FUNCTION__, 'Parameter Mode: ' . json_encode($Mode), 0);
            $consumptionValue = 0;
            $currentConsumption = $this->ReadPropertyInteger('CurrentConsumption');
            if ($currentConsumption != 0 && @IPS_ObjectExists($currentConsumption)) {
                $consumptionValue = intval(GetValue($currentConsumption));
            }
            $this->SendDebug(__FUNCTION__, 'Aktueller Verbrauch: ' . json_encode($consumptionValue), 0);
            $result = IPS_RunScriptEx($id, ['Mode' => $Mode, 'CurrentConsumption' => $consumptionValue]);
            if (!$result) {
                $this->SendDebug(__FUNCTION__, 'Fehler, das Skript mit der ID ' . $id . ' konnte nicht ausgeführt werden!', 0);
                $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', Fehler, das Skript mit der ID ' . $id . ' konnte nicht ausgeführt werden!', KL_WARNING);
            } else {
                $this->SendDebug(__FUNCTION__, 'Das Skript mit der ID ' . $id . ' wurde ausgeführt.', 0);
            }
        } else {
            $this->SendDebug(__FUNCTION__, 'Abbruch, es ist kein Skript zugewiesen!', 0);
            $result = false;
        }
        return $result;
    }
}