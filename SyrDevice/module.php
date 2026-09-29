<?php

class SyrSafeTechConnect extends IPSModule {

    public function Create() {
        parent::Create();
        
        $this->RegisterPropertyString("IPAddress", "192.168.50.168");
        $this->RegisterPropertyInteger("Port", 5333);
        $this->RegisterPropertyInteger("UpdateInterval", 60);
        
        // Benachrichtigungseigenschaften
        $this->RegisterPropertyInteger("WebFrontID", 0);
        $this->RegisterPropertyBoolean("EnableCloseNotification", true);
        $this->RegisterPropertyBoolean("EnableBatteryNotification", true);
        
        // Interne Attribute als Benachrichtigungs-Sperre
        $this->RegisterAttributeBoolean("CloseNotified", false);
        $this->RegisterAttributeBoolean("BatteryNotified", false);
        
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
        if (!IPS_VariableProfileExists("SYR.Valve.Int")) {
            IPS_CreateVariableProfile("SYR.Valve.Int", 1);
            IPS_SetVariableProfileAssociation("SYR.Valve.Int", 10, "geschlossen", "Lock", 0xFF0000);
            IPS_SetVariableProfileAssociation("SYR.Valve.Int", 11, "schliesst", "Clock", 0xFFA500);
            IPS_SetVariableProfileAssociation("SYR.Valve.Int", 20, "geöffnet", "Drops", 0x00FF00);
            IPS_SetVariableProfileAssociation("SYR.Valve.Int", 21, "öffnet", "Clock", 0x00FF00);
        }

        if (!IPS_VariableProfileExists("SYR.Valve.Bool")) {
            IPS_CreateVariableProfile("SYR.Valve.Bool", 0);
            IPS_SetVariableProfileAssociation("SYR.Valve.Bool", true, "Öffnen", "Drops", 0x00FF00);
            IPS_SetVariableProfileAssociation("SYR.Valve.Bool", false, "Schließen", "Lock", 0xFF0000);
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
            IPS_SetVariableProfileText("SYR.Volume", "", " l");
            IPS_SetVariableProfileIcon("SYR.Volume", "Tap");
        }

        if (!IPS_VariableProfileExists("SYR.Minutes")) {
            IPS_CreateVariableProfile("SYR.Minutes", 1);
            IPS_SetVariableProfileText("SYR.Minutes", "", " min");
            IPS_SetVariableProfileIcon("SYR.Minutes", "Clock");
        }

        if (!IPS_VariableProfileExists("SYR.Days")) {
            IPS_CreateVariableProfile("SYR.Days", 1);
            IPS_SetVariableProfileText("SYR.Days", "", " Tage");
            IPS_SetVariableProfileIcon("SYR.Days", "Calendar");
        }

        if (!IPS_VariableProfileExists("SYR.Voltage")) {
            IPS_CreateVariableProfile("SYR.Voltage", 2);
            IPS_SetVariableProfileText("SYR.Voltage", "", " V");
            IPS_SetVariableProfileDigits("SYR.Voltage", 1);
            IPS_SetVariableProfileIcon("SYR.Voltage", "Electricity");
        }

        if (!IPS_VariableProfileExists("SYR.Pressure.mBar")) {
            IPS_CreateVariableProfile("SYR.Pressure.mBar", 1);
            IPS_SetVariableProfileText("SYR.Pressure.mBar", "", " mbar");
            IPS_SetVariableProfileIcon("SYR.Pressure.mBar", "Gauge");
        }

        // Korrigiert auf Typ 2 (Float), damit Integer-Variablen mit Nachkommastellen oder Text-Suffix im WebFront fehlerfrei laufen
        if (!IPS_VariableProfileExists("SYR.Conductivity")) {
            IPS_CreateVariableProfile("SYR.Conductivity", 2);
            IPS_SetVariableProfileText("SYR.Conductivity", "", " µS/cm");
            IPS_SetVariableProfileIcon("SYR.Conductivity", "Electricity");
        }

        // Wiederhergestellt: Wasserhärte in Stufen (1-3)
        if (!IPS_VariableProfileExists("SYR.Hardness")) {
            IPS_CreateVariableProfile("SYR.Hardness", 1);
            IPS_SetVariableProfileAssociation("SYR.Hardness", 1, "Stufe 1", "Water", -1);
            IPS_SetVariableProfileAssociation("SYR.Hardness", 2, "Stufe 2", "Water", -1);
            IPS_SetVariableProfileAssociation("SYR.Hardness", 3, "Stufe 3", "Water", -1);
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

        if (!IPS_VariableProfileExists("SYR.DisplayOrientation")) {
            IPS_CreateVariableProfile("SYR.DisplayOrientation", 1);
            IPS_SetVariableProfileAssociation("SYR.DisplayOrientation", 1, "Standard (0°)", "Information", -1);
            IPS_SetVariableProfileAssociation("SYR.DisplayOrientation", 2, "180° Gedreht", "Information", -1);
        }
    }

    private function MaintainVariables() {
        // --- 1. Messwerte & Sensoren (Pos 10 - 29) ---
        $v10 = $this->RegisterVariableInteger("Pressure", "Wasserdruck", "SYR.Pressure.mBar", 10);
        $v11 = $this->RegisterVariableFloat("Temperature", "Wassertemperatur", "~Temperature", 11);
        $v12 = $this->RegisterVariableFloat("Flow", "Aktueller Durchfluss", "SYR.Flow", 12);
        $v13 = $this->RegisterVariableFloat("CurrentTapVolume", "Aktuelles Zapfvolumen", "SYR.Volume", 13);
        $v14 = $this->RegisterVariableFloat("LastTapVolume", "Letztes Zapfvolumen", "SYR.Volume", 14);
        $v15 = $this->RegisterVariableFloat("TotalVolume", "Gesamtwasserverbrauch", "SYR.Volume", 15);
        $v16 = $this->RegisterVariableInteger("WaterHardness", "Wasserhärte", "SYR.Hardness", 16); // Zurück auf Stufen-Profil
        $v17 = $this->RegisterVariableInteger("Conductivity", "Leitfähigkeit", "SYR.Conductivity", 17);

        IPS_SetPosition($v10, 10);
        IPS_SetPosition($v11, 11);
        IPS_SetPosition($v12, 12);
        IPS_SetPosition($v13, 13);
        IPS_SetPosition($v14, 14);
        IPS_SetPosition($v15, 15);
        IPS_SetPosition($v16, 16);
        IPS_SetPosition($v17, 17);

        // --- 2. Steuerung & Hauptzustand (Pos 30 - 49) ---
        $v30 = $this->RegisterVariableBoolean("ValveAction", "Ventilschalter (Fahrbefehl)", "SYR.Valve.Bool", 30);
        $this->EnableAction("ValveAction"); 
        
        // ValveState ist rein informativ (KEIN EnableAction!)
        $v31 = $this->RegisterVariableInteger("ValveState", "Ventilzustand (Status)", "SYR.Valve.Int", 31);
        
        $v32 = $this->RegisterVariableInteger("ActiveProfile", "Aktives Profil", "SYR.Profile", 32);
        $this->EnableAction("ActiveProfile");
        $v33 = $this->RegisterVariableBoolean("SleepMode", "Schlafmodus aktiv", "~Switch", 33);
        $v34 = $this->RegisterVariableInteger("DisplayOrientation", "Display Ausrichtung", "SYR.DisplayOrientation", 34);
        $this->EnableAction("DisplayOrientation");

        IPS_SetPosition($v30, 30);
        IPS_SetPosition($v31, 31);
        IPS_SetPosition($v32, 32);
        IPS_SetPosition($v33, 33);
        IPS_SetPosition($v34, 34);

        // --- 3. Gerätestatus & Diagnose (Pos 50 - 69) ---
        $v50 = $this->RegisterVariableFloat("BatteryVoltage", "Batteriespannung", "SYR.Voltage", 50);
        $v51 = $this->RegisterVariableFloat("MainsVoltage", "Netzspannung", "SYR.Voltage", 51);
        $v52 = $this->RegisterVariableInteger("AlarmState", "Alarm Code", "SYR.Alarm", 52);
        $v53 = $this->RegisterVariableString("AlarmMessage", "Aktuelle Meldung (Klartext)", "", 53);
        $v54 = $this->RegisterVariableBoolean("BuzzerActive", "Summer (Buzzer) aktiv", "~Switch", 54);
        $v55 = $this->RegisterVariableInteger("MicroLeakTestStatus", "Mikroleckage Teststatus", "SYR.MicroLeakStatus", 55);
        $v56 = $this->RegisterVariableBoolean("LearningPhaseActive", "Selbstlernphase aktiv", "~Switch", 56);
        $this->EnableAction("LearningPhaseActive");
        $v57 = $this->RegisterVariableInteger("LearningPhaseDays", "Selbstlernphase Dauer", "SYR.Days", 57);
        $this->EnableAction("LearningPhaseDays");

        IPS_SetPosition($v50, 50);
        IPS_SetPosition($v51, 51);
        IPS_SetPosition($v52, 52);
        IPS_SetPosition($v53, 53);
        IPS_SetPosition($v54, 54);
        IPS_SetPosition($v55, 55);
        IPS_SetPosition($v56, 56);
        IPS_SetPosition($v57, 57);

        // --- 4. System & Netzwerkinformationen (Pos 70 - 89) ---
        $v70 = $this->RegisterVariableString("SerialNumber", "Seriennummer", "", 70);
        $v71 = $this->RegisterVariableString("Firmware", "Firmware Version", "", 71);
        $v72 = $this->RegisterVariableString("MacAddress", "MAC-Adresse", "", 72);
        $v73 = $this->RegisterVariableString("IpAddress", "IP-Adresse", "", 73);
        $v74 = $this->RegisterVariableString("Gateway", "Gateway IP", "", 74);
        $v75 = $this->RegisterVariableString("SSID", "WLAN Name", "", 75);
        $v76 = $this->RegisterVariableInteger("RSSI", "WLAN Signalstärke", "SYR.RSSI", 76);
        $v77 = $this->RegisterVariableString("ConnectionStatus", "Verbindungsstatus", "", 77);

        IPS_SetPosition($v70, 70);
        IPS_SetPosition($v71, 71);
        IPS_SetPosition($v72, 72);
        IPS_SetPosition($v73, 73);
        IPS_SetPosition($v74, 74);
        IPS_SetPosition($v75, 75);
        IPS_SetPosition($v76, 76);
        IPS_SetPosition($v77, 77);

        // --- 5. Profileinstellungen (Pos 90+) ---
        // Profil 1 (Anwesend)
        $v90 = $this->RegisterVariableString("P1_Name", "Profil 1: Name", "", 90);
        $v91 = $this->RegisterVariableFloat("P1_MaxVolume", "Profil 1: Max. Volumen", "SYR.Volume", 91);
        $this->EnableAction("P1_MaxVolume");
        $v92 = $this->RegisterVariableInteger("P1_MaxTime", "Profil 1: Max. Zeit", "SYR.Minutes", 92);
        $this->EnableAction("P1_MaxTime");
        $v93 = $this->RegisterVariableFloat("P1_MaxFlow", "Profil 1: Max. Durchfluss", "SYR.Flow", 93);
        $this->EnableAction("P1_MaxFlow");
        $v94 = $this->RegisterVariableBoolean("P1_MicroLeak", "Profil 1: Mikroleckage aktiv", "~Switch", 94);
        $this->EnableAction("P1_MicroLeak");
        $v95 = $this->RegisterVariableBoolean("P1_Buzzer", "Profil 1: Warnton", "~Switch", 95);
        $this->EnableAction("P1_Buzzer");
        $v96 = $this->RegisterVariableBoolean("P1_Alarm", "Profil 1: Leckagewarnung", "~Switch", 96);
        $this->EnableAction("P1_Alarm");

        IPS_SetPosition($v90, 90);
        IPS_SetPosition($v91, 91);
        IPS_SetPosition($v92, 92);
        IPS_SetPosition($v93, 93);
        IPS_SetPosition($v94, 94);
        IPS_SetPosition($v95, 95);
        IPS_SetPosition($v96, 96);

        // Profil 2 (Abwesend)
        $v100 = $this->RegisterVariableString("P2_Name", "Profil 2: Name", "", 100);
        $v101 = $this->RegisterVariableFloat("P2_MaxVolume", "Profil 2: Max. Volumen", "SYR.Volume", 101);
        $this->EnableAction("P2_MaxVolume");
        $v102 = $this->RegisterVariableInteger("P2_MaxTime", "Profil 2: Max. Zeit", "SYR.Minutes", 102);
        $this->EnableAction("P2_MaxTime");
        $v103 = $this->RegisterVariableFloat("P2_MaxFlow", "Profil 2: Max. Durchfluss", "SYR.Flow", 103);
        $this->EnableAction("P2_MaxFlow");
        $v104 = $this->RegisterVariableBoolean("P2_MicroLeak", "Profil 2: Mikroleckage aktiv", "~Switch", 104);
        $this->EnableAction("P2_MicroLeak");
        $v105 = $this->RegisterVariableInteger("P2_ReturnTime", "Profil 2: Rückkehrzeit (Std)", "SYR.Minutes", 105);
        $this->EnableAction("P2_ReturnTime");
        $v106 = $this->RegisterVariableBoolean("P2_Buzzer", "Profil 2: Warnton", "~Switch", 106);
        $this->EnableAction("P2_Buzzer");
        $v107 = $this->RegisterVariableBoolean("P2_Alarm", "Profil 2: Leckagewarnung", "~Switch", 107);
        $this->EnableAction("P2_Alarm");

        IPS_SetPosition($v100, 100);
        IPS_SetPosition($v101, 101);
        IPS_SetPosition($v102, 102);
        IPS_SetPosition($v103, 103);
        IPS_SetPosition($v104, 104);
        IPS_SetPosition($v105, 105);
        IPS_SetPosition($v106, 106);
        IPS_SetPosition($v107, 107);
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
            // Messwerte
            if (isset($data['getCEL'])) {
                $this->SetValue("Temperature", (float)$data['getCEL'] / 10);
            }
            if (isset($data['getBAR']) && $data['getBAR'] !== "-") {
                $druck = (float)str_replace([" mbar", " bar"], "", $data['getBAR']);
                if (strpos($data['getBAR'], "mbar") === false && $druck < 50) {
                    $druck = $druck * 1000;
                }
                $this->SetValue("Pressure", (int)round($druck));
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
            
            // Leitfähigkeit
            if (isset($data['getCND'])) {
                $this->SetValue("Conductivity", (int)$data['getCND']);
            } elseif (isset($data['getCON'])) {
                $this->SetValue("Conductivity", (int)$data['getCON']);
            }

            // Steuerung & Status
            $currentValveState = 20;
            if (isset($data['getVLV'])) {
                $currentValveState = (int)$data['getVLV'];
                $this->SetValue("ValveState", $currentValveState);
                if ($currentValveState === 20) $this->SetValue("ValveAction", true);
                if ($currentValveState === 10) $this->SetValue("ValveAction", false);
            } elseif (isset($data['getAB'])) {
                $isOpen = ($data['getAB'] == "1");
                $currentValveState = $isOpen ? 20 : 10;
                $this->SetValue("ValveState", $currentValveState);
                $this->SetValue("ValveAction", $isOpen);
            }

            if (isset($data['getPRF'])) $this->SetValue("ActiveProfile", (int)$data['getPRF']);
            if (isset($data['getSLE'])) $this->SetValue("SleepMode", ((int)$data['getSLE'] === 1));
            if (isset($data['getDRP'])) $this->SetValue("DisplayOrientation", (int)$data['getDRP']);

            // Gerätestatus & Spannung exakt mit 1 Nachkommastelle verarbeiten
            $battVal = 0.0;
            if (isset($data['getBAT']) && $data['getBAT'] !== "ERROR: ADM" && $data['getBAT'] !== "-") {
                $rawBat = str_replace(',', '.', (string)$data['getBAT']);
                $battVal = round((float)$rawBat, 1);
                $this->SetValue("BatteryVoltage", $battVal);
            }
            if (isset($data['getNET']) && $data['getNET'] !== "-") {
                $rawNet = str_replace(',', '.', (string)$data['getNET']);
                $netVal = round((float)$rawNet, 1);
                $this->SetValue("MainsVoltage", $netVal);
            }

            if (isset($data['getDSV'])) $this->SetValue("MicroLeakTestStatus", (int)$data['getDSV']);
            if (isset($data['getSLP'])) $this->SetValue("LearningPhaseActive", ((int)$data['getSLP'] === 1));
            if (isset($data['getSLT'])) $this->SetValue("LearningPhaseDays", (int)$data['getSLT']);

            // Alarm-Parsing
            if (isset($data['getALA'])) {
                $alarmCode = ($data['getALA'] == "FF") ? 0 : (int)$data['getALA'];
                $this->SetValue("AlarmState", $alarmCode);
            }
            
            $currentAlarmMessage = "Keine Fehler im Speicher (OK)";
            if (isset($data['getALM']) && $data['getALM'] !== "ERROR: ADM") {
                $currentAlarmMessage = $this->ParseAlarmMessage((string)$data['getALM']);
                $this->SetValue("AlarmMessage", $currentAlarmMessage);
            }

            if (isset($data['getBUZ'])) {
                $this->SetValue("BuzzerActive", ((int)$data['getBUZ'] === 1));
            }

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

            // --- BENACHRICHTIGUNGS-LOGIK ---
            $this->CheckAndSendNotifications($currentValveState, $currentAlarmMessage, $battVal);
        }
    }

    private function CheckAndSendNotifications(int $valveState, string $alarmMessage, float $batteryVoltage) {
        $webFrontID = $this->ReadPropertyInteger("WebFrontID");
        if ($webFrontID <= 0 || !IPS_InstanceExists($webFrontID)) {
            return;
        }

        // 1. Benachrichtigung bei Ventilschließung / Leckage
        if ($this->ReadPropertyBoolean("EnableCloseNotification")) {
            if ($valveState === 10) { 
                if (!$this->ReadAttributeBoolean("CloseNotified")) {
                    $reason = ($alarmMessage !== "Keine Fehler im Speicher (OK)") ? $alarmMessage : "Ventil wurde geschlossen / Leckageschutz ausgelöst";
                    WFC_SendNotification($webFrontID, "SYR SafeTech: Absperrung geschlossen!", "Grund: " . $reason, "Warning", 10);
                    $this->WriteAttributeBoolean("CloseNotified", true);
                }
            } else {
                $this->WriteAttributeBoolean("CloseNotified", false);
            }
        }

        // 2. Benachrichtigung bei schwacher 9V Batterie (Bat Low)
        if ($this->ReadPropertyBoolean("EnableBatteryNotification")) {
            $isBatLow = ($batteryVoltage > 0.0 && $batteryVoltage < 7.5) || (strpos($alarmMessage, "Batterie schwach") !== false);
            if ($isBatLow) {
                if (!$this->ReadAttributeBoolean("BatteryNotified")) {
                    $batText = ($batteryVoltage > 0) ? sprintf("Spannung: %.1f V", $batteryVoltage) : "Batterie schwach";
                    WFC_SendNotification($webFrontID, "SYR SafeTech: Batteriewechsel erforderlich!", "Die 9V-Pufferbatterie muss getauscht werden ({$batText}).", "Alert", 10);
                    $this->WriteAttributeBoolean("BatteryNotified", true);
                }
            } else {
                $this->WriteAttributeBoolean("BatteryNotified", false);
            }
        }
    }

    private function GetVolumeOptions() {
        $options = [["caption" => "Aus", "value" => 0]];
        for ($v = 10; $v <= 100; $v += 10) {
            $options[] = ["caption" => "{$v} Liter", "value" => $v];
        }
        for ($v = 150; $v <= 1000; $v += 50) {
            $options[] = ["caption" => "{$v} Liter", "value" => $v];
        }
        for ($v = 1100; $v <= 9000; $v += 100) {
            $options[] = ["caption" => "{$v} Liter", "value" => $v];
        }
        return $options;
    }

    private function GetTimeOptions() {
        $options = [["caption" => "Aus", "value" => 0]];
        for ($m = 30; $m <= 1500; $m += 30) {
            $hours = $m / 60;
            $options[] = ["caption" => "{$hours} Std ({$m} min)", "value" => $m];
        }
        return $options;
    }

    private function GetFlowOptions() {
        $options = [
            ["caption" => "Aus", "value" => 0],
            ["caption" => "3500 l/h", "value" => 3500],
            ["caption" => "3600 l/h", "value" => 3600]
        ];
        for ($f = 3700; $f <= 5000; $f += 100) {
            $options[] = ["caption" => "{$f} l/h", "value" => $f];
        }
        return $options;
    }

    public function GetConfigurationForm() {
        $form = json_decode(file_get_contents(__DIR__ . "/form.json"), true);

        // Ist-Werte auslesen
        $slpActive = $this->GetValue("LearningPhaseActive");
        $slpDays = $this->GetValue("LearningPhaseDays");
        if ($slpDays < 7) $slpDays = 7;

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

        $dispOrientation = $this->GetValue("DisplayOrientation");
        if ($dispOrientation < 1) $dispOrientation = 1;

        $volOptions = $this->GetVolumeOptions();
        $timeOptions = $this->GetTimeOptions();
        $flowOptions = $this->GetFlowOptions();

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
                "caption" => "Absperrung, Profil & Displaysteuerung",
                "items" => [
                    [
                        "type" => "RowLayout",
                        "items" => [
                            [
                                "type" => "Button",
                                "caption" => "Ventil öffnen",
                                "onClick" => "SYR_SetValveAction(\$id, true);"
                            ],
                            [
                                "type" => "Button",
                                "caption" => "Ventil schließen",
                                "onClick" => "SYR_SetValveAction(\$id, false);"
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
                    ],
                    [
                        "type" => "RowLayout",
                        "items" => [
                            [
                                "type" => "Select",
                                "name" => "EditDisplayOrientation",
                                "caption" => "Display drehen",
                                "options" => [
                                    ["caption" => "Standard (0°)", "value" => 1],
                                    ["caption" => "180° Gedreht", "value" => 2]
                                ],
                                "value" => (int)$dispOrientation
                            ],
                            [
                                "type" => "Button",
                                "caption" => "Display Ausrichtung speichern",
                                "onClick" => "SYR_SetDisplayOrientation(\$id, \$EditDisplayOrientation);"
                            ]
                        ]
                    ]
                ]
            ],
            [
                "type" => "ExpansionPanel",
                "caption" => "Selbstlernphase",
                "items" => [
                    [
                        "type" => "RowLayout",
                        "items" => [
                            [
                                "type" => "CheckBox",
                                "name" => "EditSLPActive",
                                "caption" => "Selbstlernphase aktiv",
                                "value" => (bool)$slpActive
                            ],
                            [
                                "type" => "NumberSpinner",
                                "name" => "EditSLPDays",
                                "caption" => "Dauer (7 bis 28 Tage)",
                                "minimum" => 7,
                                "maximum" => 28,
                                "step" => 1,
                                "value" => (int)$slpDays
                            ],
                            [
                                "type" => "Button",
                                "caption" => "Selbstlernphase speichern",
                                "onClick" => "SYR_SetLearningPhase(\$id, \$EditSLPActive, \$EditSLPDays);"
                            ]
                        ]
                    ]
                ]
            ],
            [
                "type" => "ExpansionPanel",
                "caption" => "Profil 1 (Anwesend) - Einstellungen",
                "items" => [
                    [
                        "type" => "RowLayout",
                        "items" => [
                            [
                                "type" => "Select",
                                "name" => "EditVolumeP1",
                                "caption" => "Volumenleckage",
                                "options" => $volOptions,
                                "value" => (int)$p1Vol
                            ],
                            [
                                "type" => "Button",
                                "caption" => "Volumen setzen",
                                "onClick" => "SYR_SetProfileVolume(\$id, 1, \$EditVolumeP1);"
                            ]
                        ]
                    ],
                    [
                        "type" => "RowLayout",
                        "items" => [
                            [
                                "type" => "Select",
                                "name" => "EditTimeP1",
                                "caption" => "Zeitleckage",
                                "options" => $timeOptions,
                                "value" => (int)$p1Time
                            ],
                            [
                                "type" => "Button",
                                "caption" => "Zeit setzen",
                                "onClick" => "SYR_SetProfileTime(\$id, 1, \$EditTimeP1);"
                            ]
                        ]
                    ],
                    [
                        "type" => "RowLayout",
                        "items" => [
                            [
                                "type" => "Select",
                                "name" => "EditFlowP1",
                                "caption" => "Durchflussleckage",
                                "options" => $flowOptions,
                                "value" => (int)$p1Flow
                            ],
                            [
                                "type" => "Button",
                                "caption" => "Durchfluss setzen",
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
                "caption" => "Profil 2 (Abwesend) - Einstellungen",
                "items" => [
                    [
                        "type" => "RowLayout",
                        "items" => [
                            [
                                "type" => "Select",
                                "name" => "EditVolumeP2",
                                "caption" => "Volumenleckage",
                                "options" => $volOptions,
                                "value" => (int)$p2Vol
                            ],
                            [
                                "type" => "Button",
                                "caption" => "Volumen setzen",
                                "onClick" => "SYR_SetProfileVolume(\$id, 2, \$EditVolumeP2);"
                            ]
                        ]
                    ],
                    [
                        "type" => "RowLayout",
                        "items" => [
                            [
                                "type" => "Select",
                                "name" => "EditTimeP2",
                                "caption" => "Zeitleckage",
                                "options" => $timeOptions,
                                "value" => (int)$p2Time
                            ],
                            [
                                "type" => "Button",
                                "caption" => "Zeit setzen",
                                "onClick" => "SYR_SetProfileTime(\$id, 2, \$EditTimeP2);"
                            ]
                        ]
                    ],
                    [
                        "type" => "RowLayout",
                        "items" => [
                            [
                                "type" => "Select",
                                "name" => "EditFlowP2",
                                "caption" => "Durchflussleckage",
                                "options" => $flowOptions,
                                "value" => (int)$p2Flow
                            ],
                            [
                                "type" => "Button",
                                "caption" => "Durchfluss setzen",
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

    private function SendAdminAndCommand(string $endpoint) {
        // 1. Admin Mode aktivieren
        $this->FetchData("/safe-tec/set/ADM/(2)f");
        usleep(300000);

        // 2. Ziel-Befehl senden
        $res = $this->FetchData($endpoint);
        $this->SendDebug("SendCmd", "Endpoint {$endpoint} => Antwort: " . $res, 0);
        usleep(300000);

        // 3. Admin Mode deaktivieren
        $this->FetchData("/safe-tec/set/ADM/(0)f");

        usleep(400000);
        $this->UpdateData();
        return $res;
    }

    // --- PUBLIC SETTER & ACTION HANDLER ---

    public function SetValveAction(bool $open) {
        $targetVal = $open ? 1 : 2;
        $endpoint = "/safe-tec/set/ab/" . $targetVal;
        
        $response = $this->SendAdminAndCommand($endpoint);

        if (empty($response) || strpos($response, "ERROR") !== false) {
            $endpointAlt = "/safe-tec/set/AB/(" . $targetVal . ")f";
            $this->SendAdminAndCommand($endpointAlt);
        }
    }

    public function SetProfile(int $profileId) {
        $this->SendAdminAndCommand("/safe-tec/set/prf/" . $profileId);
    }

    public function SetDisplayOrientation(int $orientation) {
        $response = $this->SendAdminAndCommand("/safe-tec/set/drp/" . $orientation);
        if (empty($response) || strpos($response, "ERROR") !== false) {
            $this->SendAdminAndCommand("/safe-tec/set/DRP/(" . $orientation . ")f");
        }
    }

    public function SetLearningPhase(bool $active, int $days) {
        $actVal = $active ? 1 : 0;
        $this->SendAdminAndCommand("/safe-tec/set/slt/" . $days);
        $this->SendAdminAndCommand("/safe-tec/set/slp/" . $actVal);
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
            case "ValveAction":
                $this->SetValveAction((bool)$Value);
                break;
            case "ActiveProfile":
                $this->SetProfile((int)$Value);
                break;
            case "DisplayOrientation":
                $this->SetDisplayOrientation((int)$Value);
                break;
            case "LearningPhaseActive":
                $days = $this->GetValue("LearningPhaseDays");
                $this->SetLearningPhase((bool)$Value, $days > 0 ? $days : 14);
                break;
            case "LearningPhaseDays":
                $active = $this->GetValue("LearningPhaseActive");
                $this->SetLearningPhase((bool)$active, (int)$Value);
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
}
