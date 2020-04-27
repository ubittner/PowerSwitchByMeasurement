<?php

// Declare
declare(strict_types=1);

trait PSBM_backupRestore
{
    //#################### Backup

    /**
     * Creates a backup of the actual configuration into a script.
     *
     * @param int $BackupCategory
     */
    public function CreateBackup(int $BackupCategory): void
    {
        if (IPS_GetInstance($this->InstanceID)['InstanceStatus'] == 102) {
            $name = 'Konfiguration (' . IPS_GetName($this->InstanceID) . ' #' . $this->InstanceID . ') ' . date('d.m.Y H:i:s');
            $config = IPS_GetConfiguration($this->InstanceID);
            // Create backup
            $content = "<?php\n// Backup " . date('d.m.Y, H:i:s') . "\n// " . $this->InstanceID . "\n$" . "config = '" . $config . "';";
            $backupScript = IPS_CreateScript(0);
            IPS_SetParent($backupScript, $BackupCategory);
            IPS_SetName($backupScript, $name);
            IPS_SetHidden($backupScript, true);
            IPS_SetScriptContent($backupScript, $content);
            echo 'Die Konfiguration wurde erfolgreich gesichert!';
        }
    }

    //#################### Restore

    /**
     * Restores a configuration form selected script.
     *
     * @param int $ConfigurationScript
     */
    public function RestoreConfiguration(int $ConfigurationScript): void
    {
        if ($ConfigurationScript != 0 && IPS_ObjectExists($ConfigurationScript)) {
            $object = IPS_GetObject($ConfigurationScript);
            if ($object['ObjectType'] == 3) {
                $content = IPS_GetScriptContent($ConfigurationScript);
                preg_match_all('/\'([^\']+)\'/', $content, $matches);
                $config = $matches[1][0];
                IPS_SetConfiguration($this->InstanceID, $config);
                if (IPS_HasChanges($this->InstanceID)) {
                    IPS_ApplyChanges($this->InstanceID);
                }
            }
            echo 'Die Konfiguration wurde erfolgreich wiederhergestellt!';
        }
    }
}
