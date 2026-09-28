<?php
class SyrSafeTechConnect extends IPSModule {

    public function Create() {
        parent::Create();
        
        $this->RegisterPropertyString("IPAddress", "192.168.50.168");
        $this->RegisterPropertyInteger("Port", 5333);
        $this->RegisterPropertyInteger("UpdateInterval", 60);
        
        $this->RegisterTimer("UpdateData", 0, 'SYR_UpdateData($_IPS[\'TARGET\']);');
        
        $this->RegisterProfiles();
    }

    public function ApplyChanges() {
        parent::ApplyChanges();
        
        $this->SetTimerInterval("UpdateData", $this->ReadPropertyInteger("UpdateInterval") * 1000);
        $this->MaintainVariables();
        $this->UpdateData();
    }

    private function RegisterProfiles() {
        if (!IPS_VariableProfileExists("SYR.Valve.Bool")) {
            IPS_CreateVariableProfile("SYR.Valve.Bool", 0); // 0 = Boolean
            IPS_SetVariableProfileAssociation("SYR.Valve.Bool", true, "Geöffnet", "Drops", 0x00FF00);
            IPS_SetVariableProfileAssociation("SYR.Valve.Bool", false, "Geschlossen", "Lock", 0xFF0000);
        }
        
        if (!IPS_VariableProfileExists("SYR.Alarm")) {
            IPS_CreateVariableProfile("SYR.Alarm", 1);
            IPS_SetVariableProfileAssociation("SYR.Alarm", 0, "OK", "Ok", 0x00FF00);
            IPS_SetVariableProfileAssociation("SYR.Alarm", 1, "Volumen überschritten", "Warning", 0xFF0000);
            IPS_SetVariableProfileAssociation("SYR.Alarm", 2, "Zeit überschritten", "Warning", 0xFF0000);
            IPS_SetVariableProfileAssociation("SYR.Alarm", 3, "Durchfluss zu hoch", "Warning", 0xFF0000);
            IPS_SetVariableProfileAssociation("SYR.Alarm", 4, "Mikroleckage", "Warning", 0xFF0000);
        }
        
        if (!IPS_VariableProfileExists("SYR.Flow")) {
            IPS_CreateVariableProfile("SYR.Flow", 2);
            IPS_SetVariableProfileText("SYR.Flow", "", " l/h");
            IPS_SetVariableProfileIcon("SYR.Flow", "Drops");
        }

        if (!IPS_VariableProfileExists("SYR.Volume")) {
            IPS_CreateVariableProfile("SYR.Volume", 2);
            IPS_SetVariableProfileText("SYR.Volume", "", " Liter");
            IPS_SetVariableProfileIcon("SYR.Volume", "Tap");
        }

        if (!IPS_VariableProfileExists("SYR.Conductivity")) {
            IPS_CreateVariableProfile("SYR.Conductivity", 2);
            IPS_SetVariableProfileText("SYR.Conductivity", "", " µS/cm");
            IPS_SetVariableProfileIcon("SYR.Conductivity", "Lightning");
        }
        
        if (!IPS_VariableProfileExists("SYR.Profile")) {
            IPS_CreateVariableProfile("SYR.Profile", 1);
            IPS_SetVariableProfileAssociation("SYR.Profile", 1, "Anwesend", "House", -1);
            IPS_SetVariableProfileAssociation("SYR.Profile", 2, "Abwesend", "Car", -1);
            IPS_SetVariableProfileAssociation("SYR.Profile", 3, "Urlaub", "Suitcase", -1);
        }
    }

    private function MaintainVariables() {
        // System- & Netzwerk-Informationen
        $this->RegisterVariableString("SerialNumber", "Seriennummer", "", 10);
        $this->RegisterVariableString("Firmware", "Firmware Version", "", 11);
        $this->RegisterVariableString("SSID", "WLAN Name", "", 12);
        $this->RegisterVariableInteger("RSSI", "WLAN Signalstärke", "~Intensity.100", 13);
        $this->RegisterVariableString("ConnectionStatus", "Verbindungsstatus", "", 14);
        
        // Steuerung
        $this->RegisterVariableBoolean("ValveState", "Ventilzustand", "SYR.Valve.Bool", 20);
        $this->EnableAction("ValveState"); 
        
        // Messwerte
        $this->RegisterVariableFloat("Temperature", "Wassertemperatur", "~Temperature", 30);
        $this->RegisterVariableFloat("Pressure", "Wasserdruck", "~AirPressure.F", 40);
        $this->RegisterVariableFloat("Flow", "Aktueller Durchfluss", "SYR.Flow", 50);
        $this->RegisterVariableFloat("TotalVolume", "Gesamtwasserverbrauch", "SYR.Volume", 60);
        $this->RegisterVariableFloat("Conductivity", "Leitfähigkeit", "SYR.Conductivity", 65);
        
        // Status & Alarme
        $this->RegisterVariableInteger("AlarmState", "Alarm Code", "SYR.Alarm", 70);
        $this->RegisterVariableString("AlarmMessage", "Aktuelle Meldung (Klartext)", "", 75);
        $this->RegisterVariableInteger("ActiveProfile", "Aktives Profil", "SYR.Profile", 80);
    }
    
    public function UpdateData() {
        $ip = $this->ReadPropertyString("IPAddress");
        if (empty($ip)) return;

        // 1. Admin-Modus aktivieren
        $this->FetchData("/safe-tec/set/ADM/(2)f");
        usleep(200000); 
        
        // 2. Daten abrufen
        $response = $this->FetchData("/safe-tec/get/all");
        if (!$response) {
            $this->SendDebug("UpdateData", "Gerät nicht erreichbar", 0);
            return;
        }
        
        $data = json_decode($response, true);
        if (is_array($data)) {
            // System
            if (isset($data['getSRN'])) $this->SetValue("SerialNumber", (string)$data['getSRN']);
            if (isset($data['getVER'])) $this->SetValue("Firmware", (string)$data['getVER']);
            if (isset($data['getWFC'])) $this->SetValue("SSID", (string)$data['getWFC']);
            if (isset($data['getWFR'])) $this->SetValue("RSSI", (int)$data['getWFR']);
            
            if (isset($data['getWFS'])) {
                $status = ($data['getWFS'] == 2) ? "Verbunden" : "Getrennt (Code: " . $data['getWFS'] . ")";
                $this->SetValue("ConnectionStatus", $status);
            }

            // Ventil (1 = Offen/true, Alles andere = Zu/false)
            if (isset($data['getAB'])) {
                $isOpen = ($data['getAB'] == "1");
                $this->SetValue("ValveState", $isOpen);
            }
            
            // Messwerte
            if (isset($data['getTMP'])) $this->SetValue("Temperature", (float)$data['getTMP']);
            if (isset($data['getBAR'])) {
                $druck = (float)str_replace(" mbar", "", $data['getBAR']);
                $this->SetValue("Pressure", $druck);
            }
            if (isset($data['getFLO'])) $this->SetValue("Flow", (float)$data['getFLO']);
            if (isset($data['getCEL'])) $this->SetValue("Conductivity", (float)$data['getCEL']);
            if (isset($data['getVOL']) && $data['getVOL'] !== "ERROR: ADM") {
                $this->SetValue("TotalVolume", (float)$data['getVOL']);
            }
            
            // Alarme & Profile
            if (isset($data['getALA'])) {
                $alarmCode = ($data['getALA'] == "FF") ? 0 : (int)$data['getALA'];
                $this->SetValue("AlarmState", $alarmCode);
            }
            if (isset($data['getALM']) && $data['getALM'] !== "ERROR: ADM") {
                $this->SetValue("AlarmMessage", (string)$data['getALM']);
            }
            if (isset($data['getPRF'])) $this->SetValue("ActiveProfile", (int)$data['getPRF']);
        }
    }

    public function SetValveState(bool $State) {
        $endpointVal = $State ? 1 : 2; 
        $endpoint = "/safe-tec/set/AB/(" . $endpointVal . ")f";
        
        $this->FetchData($endpoint);
        
        // 1 Sekunde warten, damit das physische Ventil umschalten kann
        usleep(1000000); 
        $this->UpdateData();
    }

    public function RequestAction($Ident, $Value) {
        switch ($Ident) {
            case "ValveState":
                $this->SetValveState($Value);
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
