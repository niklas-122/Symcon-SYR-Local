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
        
        $interval = $this->ReadPropertyInteger("UpdateInterval");
        $this->SetTimerInterval("UpdateData", $interval > 0 ? $interval * 1000 : 0);
        
        $this->MaintainVariables();
        if ($interval > 0) {
            $this->UpdateData();
        }
    }

    private function RegisterProfiles() {
        if (!IPS_VariableProfileExists("SYR.Valve.Bool")) {
            IPS_CreateVariableProfile("SYR.Valve.Bool", 0);
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
        
        if (!IPS_VariableProfileExists("SYR.RSSI")) {
            IPS_CreateVariableProfile("SYR.RSSI", 1);
            IPS_SetVariableProfileText("SYR.RSSI", "", " dBm");
            IPS_SetVariableProfileIcon("SYR.RSSI", "Network");
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

        if (!IPS_VariableProfileExists("SYR.Minutes")) {
            IPS_CreateVariableProfile("SYR.Minutes", 1);
            IPS_SetVariableProfileText("SYR.Minutes", "", " min");
            IPS_SetVariableProfileIcon("SYR.Minutes", "Clock");
        }

        if (!IPS_VariableProfileExists("SYR.Voltage")) {
            IPS_CreateVariableProfile("SYR.Voltage", 2);
            IPS_SetVariableProfileText("SYR.Voltage", "", " V");
            IPS_SetVariableProfileIcon("SYR.Voltage", "Electricity");
        }
        
        if (!IPS_VariableProfileExists("SYR.Profile")) {
            IPS_CreateVariableProfile("SYR.Profile", 1);
            IPS_SetVariableProfileAssociation("SYR.Profile", 1, "Anwesend", "House", -1);
            IPS_SetVariableProfileAssociation("SYR.Profile", 2, "Abwesend", "Car", -1);
            IPS_SetVariableProfileAssociation("SYR.Profile", 3, "Profil 3", "Suitcase", -1);
            IPS_SetVariableProfileAssociation("SYR.Profile", 4, "Profil 4", "Suitcase", -1);
        }

        if (!IPS_VariableProfileExists("SYR.MicroLeakStatus")) {
            IPS_CreateVariableProfile("SYR.MicroLeakStatus", 1);
            IPS_SetVariableProfileAssociation("SYR.MicroLeakStatus", 0, "Nicht aktiv", "Information", -1);
            IPS_SetVariableProfileAssociation("SYR.MicroLeakStatus", 1, "Test aktiv", "Clock", 0x00FF00);
            IPS_SetVariableProfileAssociation("SYR.MicroLeakStatus", 2, "Abgebrochen (Druckabfall)", "Warning", 0xFF0000);
            IPS_SetVariableProfileAssociation("SYR.MicroLeakStatus", 3, "Übersprungen", "Info", -1);
        }

        if (!IPS_VariableProfileExists("SYR.Hardness")) {
            IPS_CreateVariableProfile("SYR.Hardness", 1);
            IPS_SetVariableProfileAssociation("SYR.Hardness", 1, "Stufe 1", "Water", -1);
            IPS_SetVariableProfileAssociation("SYR.Hardness", 2, "Stufe 2", "Water", -1);
            IPS_SetVariableProfileAssociation("SYR.Hardness", 3, "Stufe 3", "Water", -1);
        }

        if (!IPS_VariableProfileExists("SYR.DisplayOrientation")) {
            IPS_CreateVariableProfile("SYR.DisplayOrientation", 1);
            IPS_SetVariableProfileAssociation("SYR.DisplayOrientation", 1, "Standard", "Information", -1);
            IPS_SetVariableProfileAssociation("SYR.DisplayOrientation", 2, "180° Gedreht", "Information", -1);
        }
    }

    private function MaintainVariables() {
        // --- 1. System & Netzwerk ---
        $this->RegisterVariableString("SerialNumber", "Seriennummer", "", 10);
        $this->RegisterVariableString("Firmware", "Firmware Version", "", 11);
        $this->RegisterVariableString("MacAddress", "MAC-Adresse", "", 12);
        $this->RegisterVariableString("IpAddress", "IP-Adresse", "", 13);
        $this->RegisterVariableString("Gateway", "Gateway IP", "", 14);
        $this->RegisterVariableString("SSID", "WLAN Name", "", 15);
        $this->RegisterVariableInteger("RSSI", "WLAN Signalstärke", "SYR.RSSI", 16);
        $this->RegisterVariableString("ConnectionStatus", "Verbindungsstatus", "", 17);
        
        // --- 2. Steuerung & Profile ---
        $this->RegisterVariableBoolean("ValveState", "Ventilzustand", "SYR.Valve.Bool", 20);
        $this->EnableAction("ValveState"); 
        $this->RegisterVariableInteger("ActiveProfile", "Aktives Profil", "SYR.Profile", 21);
        $this->EnableAction("ActiveProfile");
        $this->RegisterVariableBoolean("SleepMode", "Schlafmodus aktiv", "~Switch", 22);
        $this->RegisterVariableInteger("DisplayOrientation", "Display Ausrichtung", "SYR.DisplayOrientation", 23);
        
        // --- 3. Messwerte & Wasser ---
        $this->RegisterVariableFloat("Temperature", "Wassertemperatur", "~Temperature", 30);
        $this->RegisterVariableFloat("Pressure", "Wasserdruck", "~AirPressure.F", 40);
        $this->RegisterVariableFloat("Flow", "Aktueller Durchfluss", "SYR.Flow", 50);
        $this->RegisterVariableFloat("CurrentTapVolume", "Aktuelles Zapfvolumen", "SYR.Volume", 55);
        $this->RegisterVariableFloat("LastTapVolume", "Letztes Zapfvolumen", "SYR.Volume", 58);
        $this->RegisterVariableFloat("TotalVolume", "Gesamtwasserverbrauch", "SYR.Volume", 60);
        $this->RegisterVariableInteger("WaterHardness", "Wasserhärte", "SYR.Hardness", 65);
        
        // --- 4. Leckage-Überwachung & Profileinstellungen ---
        $this->RegisterVariableInteger("MicroLeakTestStatus", "Mikroleckage Teststatus", "SYR.MicroLeakStatus", 70);
        $this->RegisterVariableBoolean("LearningPhaseActive", "Selbstlernphase aktiv", "~Switch", 72);
        
        // Profil 1 (Anwesend)
        $this->RegisterVariableString("P1_Name", "Profil 1: Name", "", 74);
        $this->RegisterVariableFloat("P1_MaxVolume", "Profil 1: Max. Volumen", "SYR.Volume", 75);
        $this->EnableAction("P1_MaxVolume");
        $this->RegisterVariableInteger("P1_MaxTime", "Profil 1: Max. Zeit", "SYR.Minutes", 76);
        $this->EnableAction("P1_MaxTime");
        $this->RegisterVariableFloat("P1_MaxFlow", "Profil 1: Max. Durchfluss", "SYR.Flow", 77);
        $this->EnableAction("P1_MaxFlow");
        $this->RegisterVariableBoolean("P1_MicroLeak", "Profil 1: Mikroleckage aktiv", "~Switch", 78);
        $this->EnableAction("P1_MicroLeak");
        $this->RegisterVariableBoolean("P1_Buzzer", "Profil 1: Warnton", "~Switch", 79);
        $this->EnableAction("P1_Buzzer");
        $this->RegisterVariableBoolean("P1_Alarm", "Profil 1: Leckagewarnung", "~Switch", 80);
        $this->EnableAction("P1_Alarm");

        // Profil 2 (Abwesend)
        $this->RegisterVariableString("P2_Name", "Profil 2: Name", "", 84);
        $this->RegisterVariableFloat("P2_MaxVolume", "Profil 2: Max. Volumen", "SYR.Volume", 85);
        $this->EnableAction("P2_MaxVolume");
        $this->RegisterVariableInteger("P2_MaxTime", "Profil 2: Max. Zeit", "SYR.Minutes", 86);
        $this->EnableAction("P2_MaxTime");
        $this->RegisterVariableFloat("P2_MaxFlow", "Profil 2: Max. Durchfluss", "SYR.Flow", 87);
        $this->EnableAction("P2_MaxFlow");
        $this->RegisterVariableBoolean("P2_MicroLeak", "Profil 2: Mikroleckage aktiv", "~Switch", 88);
        $this->EnableAction("P2_MicroLeak");
        $this->RegisterVariableInteger("P2_ReturnTime", "Profil 2: Rückkehrzeit (Std)", "SYR.Minutes", 89);
        $this->EnableAction("P2_ReturnTime");
        $this->RegisterVariableBoolean("P2_Buzzer", "Profil 2: Warnton", "~Switch", 90);
        $this->EnableAction("P2_Buzzer");
        $this->RegisterVariableBoolean("P2_Alarm", "Profil 2: Leckagewarnung", "~Switch", 91);
        $this->EnableAction("P2_Alarm");

        // --- 5. Gerätestatus & Diagnose ---
        $this->RegisterVariableFloat("BatteryVoltage", "Batteriespannung", "SYR.Voltage", 100);
        $this->RegisterVariableFloat("MainsVoltage", "Netzspannung", "SYR.Voltage", 102);
        $this->RegisterVariableInteger("AlarmState", "Alarm Code", "SYR.Alarm", 110);
        $this->RegisterVariableString("AlarmMessage", "Aktuelle Meldung (Klartext)", "", 115);
        $this->RegisterVariableBoolean("BuzzerActive", "Summer (Buzzer) aktiv", "~Switch", 120);
    }
    
    public function UpdateData() {
        $ip = $this->ReadPropertyString("IPAddress");
        if (empty($ip)) return;

        // Admin-Modus anfordern
        $this->FetchData("/safe-tec/set/ADM/(2)f");
        usleep(200000); 
        
        $response = $this->FetchData("/safe-tec/get/all");
        if (!$response) {
            $this->SendDebug("UpdateData", "Gerät nicht erreichbar", 0);
            return;
        }
        
        $data = json_decode($response, true);
        if (is_array($data)) {
            // System & Netz
            if (isset($data['getSRN'])) $this->SetValue("SerialNumber", (string)$data['getSRN']);
            if (isset($data['getVER'])) $this->SetValue("Firmware", (string)$data['getVER']);
            if (isset($data['getMAC'])) $this->SetValue("MacAddress", (string)$data['getMAC']);
            if (isset($data['getWIP'])) $this->SetValue("IpAddress", (string)$data['getWIP']);
            if (isset($data['getWGW'])) $this->SetValue("Gateway", (string)$data['getWGW']);
            if (isset($data['getWFC'])) $this->SetValue("SSID", (string)$data['getWFC']);
            if (isset($data['getWFR'])) $this->SetValue("RSSI", -(int)$data['getWFR']);
            
            if (isset($data['getWFS'])) {
                $status = ($data['getWFS'] == 2) ? "Verbunden (Lokal)" : "Getrennt (Code: " . $data['getWFS'] . ")";
                $this->SetValue("ConnectionStatus", $status);
            }

            // Steuerung
            if (isset($data['getAB'])) {
                $this->SetValue("ValveState", ($data['getAB'] == "1"));
            }
            if (isset($data['getPRF'])) $this->SetValue("ActiveProfile", (int)$data['getPRF']);
            if (isset($data['getSLE'])) $this->SetValue("SleepMode", ((int)$data['getSLE'] === 1));
            if (isset($data['getDRP'])) $this->SetValue("DisplayOrientation", (int)$data['getDRP']);

            // Messwerte
            if (isset($data['getCEL'])) {
                $this->SetValue("Temperature", (float)$data['getCEL'] / 10);
            }
            if (isset($data['getBAR']) && $data['getBAR'] !== "-") {
                $druck = (float)str_replace([" mbar", " bar"], "", $data['getBAR']);
                if (strpos($data['getBAR'], "mbar") !== false) {
                    $druck = $druck / 1000;
                }
                $this->SetValue("Pressure", $druck);
            }
            if (isset($data['getFLO'])) $this->SetValue("Flow", (float)$data['getFLO']);
            
            if (isset($data['getAVO'])) {
                $avoVal = (float)str_replace(["mL", " "], "", $data['getAVO']);
                $this->SetValue("CurrentTapVolume", $avoVal / 1000);
            }
            if (isset($data['getLTV'])) $this->SetValue("LastTapVolume", (float)$data['getLTV']);
            
            if (isset($data['getVOL']) && $data['getVOL'] !== "ERROR: ADM" && $data['getVOL'] !== "-") {
                $volStr = str_replace(["Vol[L]", "L", " "], "", $data['getVOL']);
                $this->SetValue("TotalVolume", (float)$volStr);
            }

            if (isset($data['getDMA'])) $this->SetValue("WaterHardness", (int)$data['getDMA']);
            if (isset($data['getDSV'])) $this->SetValue("MicroLeakTestStatus", (int)$data['getDSV']);
            if (isset($data['getSLP'])) $this->SetValue("LearningPhaseActive", ((int)$data['getSLP'] === 1));
            
            // Profil 1
            if (isset($data['getPN1'])) $this->SetValue("P1_Name", (string)$data['getPN1']);
            if (isset($data['getPV1'])) $this->SetValue("P1_MaxVolume", (float)$data['getPV1']);
            if (isset($data['getPT1'])) $this->SetValue("P1_MaxTime", (int)$data['getPT1']);
            if (isset($data['getPF1'])) $this->SetValue("P1_MaxFlow", (float)$data['getPF1']);
            if (isset($data['getPM1'])) $this->SetValue("P1_MicroLeak", ((int)$data['getPM1'] === 1));
            if (isset($data['getPB1'])) $this->SetValue("P1_Buzzer", ((int)$data['getPB1'] === 1));
            if (isset($data['getPA1'])) $this->SetValue("P1_Alarm", ((int)$data['getPA1'] === 1));

            // Profil 2
            if (isset($data['getPN2'])) $this->SetValue("P2_Name", (string)$data['getPN2']);
            if (isset($data['getPV2'])) $this->SetValue("P2_MaxVolume", (float)$data['getPV2']);
            if (isset($data['getPT2'])) $this->SetValue("P2_MaxTime", (int)$data['getPT2']);
            if (isset($data['getPF2'])) $this->SetValue("P2_MaxFlow", (float)$data['getPF2']);
            if (isset($data['getPM2'])) $this->SetValue("P2_MicroLeak", ((int)$data['getPM2'] === 1));
            if (isset($data['getPR2'])) $this->SetValue("P2_ReturnTime", (int)$data['getPR2']);
            if (isset($data['getPB2'])) $this->SetValue("P2_Buzzer", ((int)$data['getPB2'] === 1));
            if (isset($data['getPA2'])) $this->SetValue("P2_Alarm", ((int)$data['getPA2'] === 1));
            
            // Spannung & Alarm
            if (isset($data['getBAT']) && $data['getBAT'] !== "ERROR: ADM" && $data['getBAT'] !== "-") {
                $batt = (float)str_replace(',', '.', $data['getBAT']);
                $this->SetValue("BatteryVoltage", $batt);
            }
            if (isset($data['getNET']) && $data['getNET'] !== "-") {
                $net = (float)str_replace(',', '.', $data['getNET']);
                $this->SetValue("MainsVoltage", $net);
            }
            if (isset($data['getALA'])) {
                $alarmCode = ($data['getALA'] == "FF") ? 0 : (int)$data['getALA'];
                $this->SetValue("AlarmState", $alarmCode);
            }
            
            if (isset($data['getALM']) && $data['getALM'] !== "ERROR: ADM") {
                $parsedMessage = $this->ParseAlarmMessage((string)$data['getALM']);
                $this->SetValue("AlarmMessage", $parsedMessage);
            }

            if (isset($data['getBUZ'])) {
                $this->SetValue("BuzzerActive", ((int)$data['getBUZ'] === 1));
            }
        }
    }

    private function ParseAlarmMessage(string $rawAlarmString): string {
        $alarmMapping = [
            'A3' => 'Leckagevolumen erreicht',
            'A4' => 'Leckagezeit erreicht',
            'A5' => 'Maximale Durchflussmenge erreicht',
            'A6' => 'Mikroleckage entdeckt',
            'A7' => 'Externer Funksensor Leckage',
            'A8' => 'Externer Kabelsensor Leckage',
            'A9' => 'Drucksensor fehlerhaft',
            'AA' => 'Temperatursensor fehlerhaft',
            'AB' => 'Batterie schwach'
        ];

        preg_match_all('/[A-F0-9]{2}/i', $rawAlarmString, $matches);
        if (empty($matches[0])) {
            return $rawAlarmString;
        }

        $translatedList = [];
        foreach ($matches[0] as $code) {
            $code = strtoupper($code);
            if ($code === 'FF') {
                continue;
            }
            $translatedList[] = isset($alarmMapping[$code]) ? $alarmMapping[$code] : "Unbekannter Fehler ({$code})";
        }

        if (empty($translatedList)) {
            return "Keine Fehler im Speicher (OK)";
        }

        $counted = array_count_values($translatedList);
        $resultParts = [];
        foreach ($counted as $msg => $count) {
            if ($count > 1) {
                $resultParts[] = "{$msg} ({$count}x)";
            } else {
                $resultParts[] = $msg;
            }
        }

        return implode(', ', $resultParts);
    }

    // --- SCHALTBEFEHLE / SETTER ---

    private function SendAdminAndCommand(string $endpoint) {
        // 1. Admin Mode aktivieren
        $this->FetchData("/safe-tec/set/ADM/(2)f");
        usleep(300000);

        // 2. Befehl senden
        $res = $this->FetchData($endpoint);
        $this->SendDebug("SendCmd", "Endpoint {$endpoint} => Antwort: " . $res, 0);

        usleep(500000);
        $this->UpdateData();
        return $res;
    }

    public function SetValveState(bool $State) {
        $endpointVal = $State ? 1 : 2; 
        $endpoint = "/safe-tec/set/ab/" . $endpointVal;
        $response = $this->SendAdminAndCommand($endpoint);

        if (empty($response) || strpos($response, "ERROR") !== false) {
            $endpointAlt = "/safe-tec/set/AB/(" . $endpointVal . ")f";
            $this->SendAdminAndCommand($endpointAlt);
        }
    }

    public function SetProfile(int $profileId) {
        $this->SendAdminAndCommand("/safe-tec/set/prf/" . $profileId);
    }

    public function SetProfileVolume(int $profileId, float $volume) {
        $this->SendAdminAndCommand("/safe-tec/set/pv" . $profileId . "/" . (int)$volume);
    }

    public function SetProfileTime(int $profileId, int $minutes) {
        $this->SendAdminAndCommand("/safe-tec/set/pt" . $profileId . "/" . $minutes);
    }

    public function SetProfileFlow(int $profileId, float $flow) {
        $this->SendAdminAndCommand("/safe-tec/set/pf" . $profileId . "/" . (int)$flow);
    }

    public function SetProfileMicroLeak(int $profileId, bool $state) {
        $val = $state ? 1 : 0;
        $this->SendAdminAndCommand("/safe-tec/set/pm" . $profileId . "/" . $val);
    }

    public function SetProfileBuzzer(int $profileId, bool $state) {
        $val = $state ? 1 : 0;
        $this->SendAdminAndCommand("/safe-tec/set/pb" . $profileId . "/" . $val);
    }

    public function SetProfileAlarm(int $profileId, bool $state) {
        $val = $state ? 1 : 0;
        $this->SendAdminAndCommand("/safe-tec/set/pa" . $profileId . "/" . $val);
    }

    public function SetProfileReturnTime(int $profileId, int $hours) {
        $this->SendAdminAndCommand("/safe-tec/set/pr" . $profileId . "/" . $hours);
    }

    public function RequestAction($Ident, $Value) {
        switch ($Ident) {
            case "ValveState":
                $this->SetValveState((bool)$Value);
                break;
            case "ActiveProfile":
                $this->SetProfile((int)$Value);
                break;
            case "P1_MaxVolume":
                $this->SetProfileVolume(1, (float)$Value);
                break;
            case "P1_MaxTime":
                $this->SetProfileTime(1, (int)$Value);
                break;
            case "P1_MaxFlow":
                $this->SetProfileFlow(1, (float)$Value);
                break;
            case "P1_MicroLeak":
                $this->SetProfileMicroLeak(1, (bool)$Value);
                break;
            case "P1_Buzzer":
                $this->SetProfileBuzzer(1, (bool)$Value);
                break;
            case "P1_Alarm":
                $this->SetProfileAlarm(1, (bool)$Value);
                break;
            case "P2_MaxVolume":
                $this->SetProfileVolume(2, (float)$Value);
                break;
            case "P2_MaxTime":
                $this->SetProfileTime(2, (int)$Value);
                break;
            case "P2_MaxFlow":
                $this->SetProfileFlow(2, (float)$Value);
                break;
            case "P2_MicroLeak":
                $this->SetProfileMicroLeak(2, (bool)$Value);
                break;
            case "P2_ReturnTime":
                $this->SetProfileReturnTime(2, (int)$Value);
                break;
            case "P2_Buzzer":
                $this->SetProfileBuzzer(2, (bool)$Value);
                break;
            case "P2_Alarm":
                $this->SetProfileAlarm(2, (bool)$Value);
                break;
            default:
                throw new Exception("Invalid Ident: " . $Ident);
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
public function GetConfigurationForm() {
    $form = json_decode(file_get_contents(__DIR__ . "/form.json"), true);

    // Aktuelle Werte aus den Modul-Variablen auslesen
    $p1Vol = $this->GetValue("P1_MaxVolume");
    $p1Time = $this->GetValue("P1_MaxTime");
    $p1Flow = $this->GetValue("P1_MaxFlow");
    $p1Micro = $this->GetValue("P1_MicroLeak");
    $p1Buzzer = $this->GetValue("P1_Buzzer");
    $p1Alarm = $this->GetValue("P1_Alarm");

    $p2Vol = $this->GetValue("P2_MaxVolume");
    $p2Time = $this->GetValue("P2_MaxTime");
    $p2Flow = $this->GetValue("P2_MaxFlow");
    $p2Micro = $this->GetValue("P2_MicroLeak");
    $p2Return = $this->GetValue("P2_ReturnTime");
    $p2Buzzer = $this->GetValue("P2_Buzzer");
    $p2Alarm = $this->GetValue("P2_Alarm");

    // Formular-Aktionen dynamisch mit den aktuellen Werten befüllen
    $form['actions'] = [
        [
            "type" => "RowLayout",
            "items" => [
                [
                    "type" => "Button",
                    "caption" => "Status jetzt aktualisieren",
                    "onClick" => "SYR_UpdateData(\$id);"
                ]
            ]
        ],
        [
            "type" => "ExpansionPanel",
            "caption" => "Absperrung & Profilauswahl",
            "items" => [
                [
                    "type" => "RowLayout",
                    "items" => [
                        [
                            "type" => "Button",
                            "caption" => "Ventil öffnen",
                            "onClick" => "SYR_SetValveState(\$id, true);"
                        ],
                        [
                            "type" => "Button",
                            "caption" => "Ventil schließen",
                            "onClick" => "SYR_SetValveState(\$id, false);"
                        ]
                    ]
                ],
                [
                    "type" => "RowLayout",
                    "items" => [
                        [
                            "type" => "Select",
                            "name" => "TargetProfile",
                            "caption" => "Aktives Profil wählen",
                            "options" => [
                                ["caption" => "Profil 1: Anwesend", "value" => 1],
                                ["caption" => "Profil 2: Abwesend", "value" => 2],
                                ["caption" => "Profil 3", "value" => 3],
                                ["caption" => "Profil 4", "value" => 4]
                            ],
                            "value" => $this->GetValue("ActiveProfile")
                        ],
                        [
                            "type" => "Button",
                            "caption" => "Profil aktivieren",
                            "onClick" => "SYR_SetProfile(\$id, \$TargetProfile);"
                        ]
                    ]
                ]
            ]
        ],
        [
            "type" => "ExpansionPanel",
            "caption" => "Profil 1 (Anwesend) - Aktuelle Werte anpassen",
            "items" => [
                [
                    "type" => "RowLayout",
                    "items" => [
                        [
                            "type" => "NumberSpinner",
                            "name" => "EditVolumeP1",
                            "caption" => "Volumenleckage (in Litern)",
                            "minimum" => 1,
                            "maximum" => 5000,
                            "value" => (int)$p1Vol
                        ],
                        [
                            "type" => "Button",
                            "caption" => "Profil 1 Volumen setzen",
                            "onClick" => "SYR_SetProfileVolume(\$id, 1, \$EditVolumeP1);"
                        ]
                    ]
                ],
                [
                    "type" => "RowLayout",
                    "items" => [
                        [
                            "type" => "NumberSpinner",
                            "name" => "EditTimeP1",
                            "caption" => "Zeitleckage (in Minuten)",
                            "minimum" => 1,
                            "maximum" => 1440,
                            "value" => (int)$p1Time
                        ],
                        [
                            "type" => "Button",
                            "caption" => "Profil 1 Zeit setzen",
                            "onClick" => "SYR_SetProfileTime(\$id, 1, \$EditTimeP1);"
                        ]
                    ]
                ],
                [
                    "type" => "RowLayout",
                    "items" => [
                        [
                            "type" => "NumberSpinner",
                            "name" => "EditFlowP1",
                            "caption" => "Durchflussleckage (in l/h)",
                            "minimum" => 100,
                            "maximum" => 10000,
                            "value" => (int)$p1Flow
                        ],
                        [
                            "type" => "Button",
                            "caption" => "Profil 1 Durchfluss setzen",
                            "onClick" => "SYR_SetProfileFlow(\$id, 1, \$EditFlowP1);"
                        ]
                    ]
                ],
                [
                    "type" => "RowLayout",
                    "items" => [
                        [
                            "type" => "CheckBox",
                            "name" => "EditMicroLeakP1",
                            "caption" => "Mikroleckage aktivieren",
                            "value" => (bool)$p1Micro
                        ],
                        [
                            "type" => "Button",
                            "caption" => "Mikroleckage speichern",
                            "onClick" => "SYR_SetProfileMicroLeak(\$id, 1, \$EditMicroLeakP1);"
                        ]
                    ]
                ],
                [
                    "type" => "RowLayout",
                    "items" => [
                        [
                            "type" => "CheckBox",
                            "name" => "EditBuzzerP1",
                            "caption" => "Warnton (Buzzer) aktivieren",
                            "value" => (bool)$p1Buzzer
                        ],
                        [
                            "type" => "Button",
                            "caption" => "Warnton speichern",
                            "onClick" => "SYR_SetProfileBuzzer(\$id, 1, \$EditBuzzerP1);"
                        ]
                    ]
                ],
                [
                    "type" => "RowLayout",
                    "items" => [
                        [
                            "type" => "CheckBox",
                            "name" => "EditAlarmP1",
                            "caption" => "Profil Leckagewarnung aktivieren",
                            "value" => (bool)$p1Alarm
                        ],
                        [
                            "type" => "Button",
                            "caption" => "Leckagewarnung speichern",
                            "onClick" => "SYR_SetProfileAlarm(\$id, 1, \$EditAlarmP1);"
                        ]
                    ]
                ]
            ]
        ],
        [
            "type" => "ExpansionPanel",
            "caption" => "Profil 2 (Abwesend) - Aktuelle Werte anpassen",
            "items" => [
                [
                    "type" => "RowLayout",
                    "items" => [
                        [
                            "type" => "NumberSpinner",
                            "name" => "EditVolumeP2",
                            "caption" => "Volumenleckage (in Litern)",
                            "minimum" => 1,
                            "maximum" => 5000,
                            "value" => (int)$p2Vol
                        ],
                        [
                            "type" => "Button",
                            "caption" => "Profil 2 Volumen setzen",
                            "onClick" => "SYR_SetProfileVolume(\$id, 2, \$EditVolumeP2);"
                        ]
                    ]
                ],
                [
                    "type" => "RowLayout",
                    "items" => [
                        [
                            "type" => "NumberSpinner",
                            "name" => "EditTimeP2",
                            "caption" => "Zeitleckage (in Minuten)",
                            "minimum" => 1,
                            "maximum" => 1440,
                            "value" => (int)$p2Time
                        ],
                        [
                            "type" => "Button",
                            "caption" => "Profil 2 Zeit setzen",
                            "onClick" => "SYR_SetProfileTime(\$id, 2, \$EditTimeP2);"
                        ]
                    ]
                ],
                [
                    "type" => "RowLayout",
                    "items" => [
                        [
                            "type" => "NumberSpinner",
                            "name" => "EditFlowP2",
                            "caption" => "Durchflussleckage (in l/h)",
                            "minimum" => 100,
                            "maximum" => 10000,
                            "value" => (int)$p2Flow
                        ],
                        [
                            "type" => "Button",
                            "caption" => "Profil 2 Durchfluss setzen",
                            "onClick" => "SYR_SetProfileFlow(\$id, 2, \$EditFlowP2);"
                        ]
                    ]
                ],
                [
                    "type" => "RowLayout",
                    "items" => [
                        [
                            "type" => "CheckBox",
                            "name" => "EditMicroLeakP2",
                            "caption" => "Mikroleckage aktivieren",
                            "value" => (bool)$p2Micro
                        ],
                        [
                            "type" => "Button",
                            "caption" => "Mikroleckage speichern",
                            "onClick" => "SYR_SetProfileMicroLeak(\$id, 2, \$EditMicroLeakP2);"
                        ]
                    ]
                ],
                [
                    "type" => "RowLayout",
                    "items" => [
                        [
                            "type" => "NumberSpinner",
                            "name" => "EditReturnTimeP2",
                            "caption" => "Rückkehrzeit zu Profil 1 (in Stunden, 0 = Aus)",
                            "minimum" => 0,
                            "maximum" => 168,
                            "value" => (int)$p2Return
                        ],
                        [
                            "type" => "Button",
                            "caption" => "Rückkehrzeit setzen",
                            "onClick" => "SYR_SetProfileReturnTime(\$id, 2, \$EditReturnTimeP2);"
                        ]
                    ]
                ],
                [
                    "type" => "RowLayout",
                    "items" => [
                        [
                            "type" => "CheckBox",
                            "name" => "EditBuzzerP2",
                            "caption" => "Warnton (Buzzer) aktivieren",
                            "value" => (bool)$p2Buzzer
                        ],
                        [
                            "type" => "Button",
                            "caption" => "Warnton speichern",
                            "onClick" => "SYR_SetProfileBuzzer(\$id, 2, \$EditBuzzerP2);"
                        ]
                    ]
                ],
                [
                    "type" => "RowLayout",
                    "items" => [
                        [
                            "type" => "CheckBox",
                            "name" => "EditAlarmP2",
                            "caption" => "Profil Leckagewarnung aktivieren",
                            "value" => (bool)$p2Alarm
                        ],
                        [
                            "type" => "Button",
                            "caption" => "Leckagewarnung speichern",
                            "onClick" => "SYR_SetProfileAlarm(\$id, 2, \$EditAlarmP2);"
                        ]
                    ]
                ]
            ]
        ]
    ];

    return json_encode($form);
}
    
}
