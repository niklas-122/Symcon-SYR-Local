<?php
class SyrSafeTechConnect extends IPSModule {

    public function Create() {
        parent::Create();
        
        // Grundeinstellungen
        $this->RegisterPropertyString("IPAddress", "192.168.50.168");
        $this->RegisterPropertyInteger("Port", 5333);
        $this->RegisterPropertyInteger("UpdateInterval", 60);
        
        // Timer für die automatische Abfrage
        $this->RegisterTimer("UpdateData", 0, 'SYR_UpdateData($_IPS[\'TARGET\']);');
        
        // Profile registrieren
        $this->RegisterProfiles();
    }

    public function ApplyChanges() {
        parent::ApplyChanges();
        
        // Timer setzen
        $this->SetTimerInterval("UpdateData", $this->ReadPropertyInteger("UpdateInterval") * 1000);
        
        // Variablenstruktur im Objektbaum aufbauen
        $this->MaintainVariables();
        
        // Direkter Initialabruf
        $this->UpdateData();
    }

    private function RegisterProfiles() {
        // Ventilsteuerung (1 = Geöffnet, 2 = Geschlossen)
        if (!IPS_VariableProfileExists("SYR.Valve")) {
            IPS_CreateVariableProfile("SYR.Valve", 1);
            IPS_SetVariableProfileAssociation("SYR.Valve", 1, "Geöffnet", "Drops", 0x00FF00);
            IPS_SetVariableProfileAssociation("SYR.Valve", 2, "Geschlossen", "Lock", 0xFF0000);
            IPS_SetVariableProfileAction("SYR.Valve", $this->InstanceID); 
        }
        
        // Alarmstatus (FF wird zu 0, ansonsten Alarmcodes)
        if (!IPS_VariableProfileExists("SYR.Alarm")) {
            IPS_CreateVariableProfile("SYR.Alarm", 1);
            IPS_SetVariableProfileAssociation("SYR.Alarm", 0, "OK", "Ok", 0x00FF00);
            IPS_SetVariableProfileAssociation("SYR.Alarm", 1, "Volumen überschritten", "Warning", 0xFF0000);
            IPS_SetVariableProfileAssociation("SYR.Alarm", 2, "Zeit überschritten", "Warning", 0xFF0000);
            IPS_SetVariableProfileAssociation("SYR.Alarm", 3, "Durchfluss zu hoch", "Warning", 0xFF0000);
            IPS_SetVariableProfileAssociation("SYR.Alarm", 4, "Mikroleckage", "Warning", 0xFF0000);
        }
        
        // Profile
        if (!IPS_VariableProfileExists("SYR.Profile")) {
            IPS_CreateVariableProfile("SYR.Profile", 1);
            IPS_SetVariableProfileAssociation("SYR.Profile", 1, "Anwesend", "House", -1);
            IPS_SetVariableProfileAssociation("SYR.Profile", 2, "Abwesend", "Car", -1);
            IPS_SetVariableProfileAssociation("SYR.Profile", 3, "Urlaub", "Suitcase", -1);
        }
    }

    private function MaintainVariables() {
        $this->RegisterVariableString("Firmware", "Firmware Version", "", 10);
        $this->RegisterVariableInteger("ValveState", "Ventilzustand", "SYR.Valve", 20);
        $this->EnableAction("ValveState"); // Bedienung im WebFront erlauben
        
        $this->RegisterVariableFloat("Temperature", "Wassertemperatur", "~Temperature", 30);
        $this->RegisterVariableFloat("Pressure", "Wasserdruck", "~AirPressure.F", 40);
        $this->RegisterVariableFloat("Flow", "Aktueller Durchfluss", "~Water.Volume", 50);
        $this->RegisterVariableFloat("TotalVolume", "Gesamtwasserverbrauch", "~Water", 60);
        
        $this->RegisterVariableInteger("AlarmState", "Alarmstatus", "SYR.Alarm", 70);
        $this->RegisterVariableInteger("ActiveProfile", "Aktives Profil", "SYR.Profile", 80);
    }
    
    public function UpdateData() {
        $ip = $this->ReadPropertyString("IPAddress");
        
        if (empty($ip)) {
            $this->SendDebug("UpdateData", "Keine IP-Adresse konfiguriert", 0);
            return;
        }

        // 1. Admin-Modus aktivieren (schaltet Werte wie getVOL frei)
        $this->FetchData("/safe-tec/set/ADM/(2)f");
        
        // Kurzer Delay, damit das Gerät den Status verarbeitet (Vermeidet Blockieren von IPS)
        usleep(200000); // 200 ms
        
        // 2. Daten abrufen
        $response = $this->FetchData("/safe-tec/get/all");
        if (!$response) {
            $this->SendDebug("UpdateData", "Gerät nicht erreichbar", 0);
            return;
        }
        
        $data = json_decode($response, true);
        if (is_array($data)) {
            if (isset($data['getVER'])) $this->SetValue("Firmware", $data['getVER']);
            if (isset($data['getAB']))  $this->SetValue("ValveState", (int)$data['getAB']);
            if (isset($data['getTMP'])) $this->SetValue("Temperature", (float)$data['getTMP']);
            
            // Druckwert bereinigen (Entfernt " mbar" und formatiert zu Float)
            if (isset($data['getBAR'])) {
                $druck = (float)str_replace(" mbar", "", $data['getBAR']);
                $this->SetValue("Pressure", $druck);
            }
            
            if (isset($data['getFLO'])) $this->SetValue("Flow", (float)$data['getFLO']);
            
            // Prüfen ob Admin-Modus erfolgreich war
            if (isset($data['getVOL']) && $data['getVOL'] !== "ERROR: ADM") {
                $this->SetValue("TotalVolume", (float)$data['getVOL']);
            }
            
            // Alarmstatus auf Integer mappen
            if (isset($data['getALA'])) {
                $alarmCode = ($data['getALA'] == "FF") ? 0 : (int)$data['getALA'];
                $this->SetValue("AlarmState", $alarmCode);
            }
            
            if (isset($data['getPRF'])) $this->SetValue("ActiveProfile", (int)$data['getPRF']);
        }
    }

    // Erlaubt das Schalten des Ventils aus dem WebFront oder per Skript
    public function RequestAction($Ident, $Value) {
        switch ($Ident) {
            case "ValveState":
                // Value: 1 = Öffnen, 2 = Schließen
                $endpoint = "/safe-tec/set/AB/(" . $Value . ")f";
                $this->FetchData($endpoint);
                $this->SetValue($Ident, $Value);
                
                // Kurze Pause, dann zur Sicherheit re-syncen
                usleep(200000); 
                $this->UpdateData();
                break;
            default:
                throw new Exception("Invalid Ident");
        }
    }

    private function FetchData($endpoint) {
        $ip = $this->ReadPropertyString("IPAddress");
        $port = $this->ReadPropertyInteger("Port");
        $url = "http://{$ip}:{$port}{$endpoint}";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        $result = curl_exec($ch);
        curl_close($ch);
        
        return $result;
    }
}
?>