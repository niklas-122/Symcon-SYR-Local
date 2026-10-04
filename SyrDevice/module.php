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
        
        // Interne Attribute als Benachrichtigungs-Sperre & Timer
        $this->RegisterAttributeBoolean("CloseNotified", false);
        $this->RegisterAttributeBoolean("BatteryNotified", false);
        
        // Attribute für Tagesverbrauch und Zeitleckage-Ausnutzung
        $this->RegisterAttributeFloat("VolumeAtMidnight", 0.0);
        $this->RegisterAttributeString("LastMidnightDate", "");
        $this->RegisterAttributeInteger("TapStartTime", 0);
        
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
            IPS_SetVariableProfileDigits("SYR.Volume", 1);
            IPS_SetVariableProfileIcon("SYR.Volume", "Tap");
        }

        if (!IPS_VariableProfileExists("SYR.Volume.Tap")) {
            IPS_CreateVariableProfile("SYR.Volume.Tap", 2);
            IPS_SetVariableProfileText("SYR.Volume.Tap", "", " l");
            IPS_SetVariableProfileDigits("SYR.Volume.Tap", 3);
            IPS_SetVariableProfileIcon("SYR.Volume.Tap", "Tap");
        }

        if (!IPS_VariableProfileExists("SYR.Minutes")) {
            IPS_CreateVariableProfile("SYR.Minutes", 2);
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

        if (!IPS_VariableProfileExists("SYR.Pressure.Bar")) {
            IPS_CreateVariableProfile("SYR.Pressure.Bar", 2);
            IPS_SetVariableProfileText("SYR.Pressure.Bar", "", " bar");
            IPS_SetVariableProfileDigits("SYR.Pressure.Bar", 2);
            IPS_SetVariableProfileIcon("SYR.Pressure.Bar", "Gauge");
        }

        if (!IPS_VariableProfileExists("SYR.Conductivity")) {
            IPS_CreateVariableProfile("SYR.Conductivity", 2);
            IPS_SetVariableProfileText("SYR.Conductivity", "", " µS/cm");
            IPS_SetVariableProfileDigits("SYR.Conductivity", 0);
            IPS_SetVariableProfileIcon("SYR.Conductivity", "Electricity");
        }

        if (!IPS_VariableProfileExists("SYR.Hardness.Estimated")) {
            IPS_CreateVariableProfile("SYR.Hardness.Estimated", 2);
            IPS_SetVariableProfileText("SYR.Hardness.Estimated", "", " °dH (ca.)");
            IPS_SetVariableProfileDigits("SYR.Hardness.Estimated", 1);
            IPS_SetVariableProfileIcon("SYR.Hardness.Estimated", "Water");
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
            IPS_SetVariableProfileAssociation("SYR.DisplayOrientation", 2, "90° Gedreht", "Information", -1);
            IPS_SetVariableProfileAssociation("SYR.DisplayOrientation", 3, "180° Gedreht", "Information", -1);
            IPS_SetVariableProfileAssociation("SYR.DisplayOrientation", 4, "270° Gedreht", "Information", -1);
        }
    }

    private function MaintainVariables() {
        // --- 1. Messwerte & Sensoren ---
        $v10 = $this->RegisterVariableFloat("Pressure", "Wasserdruck", "SYR.Pressure.Bar", 10);
        $v11 = $this->RegisterVariableFloat("Temperature", "Wassertemperatur", "~Temperature", 11);
        $v12 = $this->RegisterVariableFloat("Flow", "Aktueller Durchfluss", "SYR.Flow", 12);
        $v13 = $this->RegisterVariableFloat("CurrentTapVolume", "Aktuelles Zapfvolumen", "SYR.Volume.Tap", 13);
        $v14 = $this->RegisterVariableFloat("DailyVolume", "Tagesverbrauch", "SYR.Volume", 14);
        $v15 = $this->RegisterVariableFloat("TotalVolume", "Gesamtwasserverbrauch", "SYR.Volume", 15);
        $v16 = $this->RegisterVariableFloat("WaterHardness", "Wasserhärte (geschätzt)", "SYR.Hardness.Estimated", 16);
        $v17 = $this->RegisterVariableFloat("Conductivity", "Leitfähigkeit", "SYR.Conductivity", 17);

        IPS_SetPosition($v10, 10);
        IPS_SetPosition($v11, 11);
        IPS_SetPosition($v12, 12);
        IPS_SetPosition($v13, 13);
        IPS_SetPosition($v14, 14);
        IPS_SetPosition($v15, 15);
        IPS_SetPosition($v16, 16);
        IPS_SetPosition($v17, 17);

        // --- 2. Aktive Limits & Ausnutzung ---
        $l1 = $this->RegisterVariableFloat("FlowleakageLimit", "Durchflussleckage-Begrenzung", "SYR.Flow", 20);
        $l2 = $this->RegisterVariableFloat("FlowleakageUtilization", "Durchflussleckage-Ausnutzung", "SYR.Flow", 21);
        $l3 = $this->RegisterVariableFloat("VolumeleakageLimit", "Volumenleckage-Begrenzung", "SYR.Volume", 22);
        $l4 = $this->RegisterVariableFloat("VolumeleakageUtilization", "Volumenleckage-Ausnutzung", "SYR.Volume.Tap", 23);
        $l5 = $this->RegisterVariableInteger("TimeleakageLimit", "Zeitleckage-Begrenzung", "SYR.Minutes", 24);
        $l6 = $this->RegisterVariableInteger("TimeleakageUtilization", "Zeitleckage-Ausnutzung", "SYR.Minutes", 25);

        IPS_SetPosition($l1, 20);
        IPS_SetPosition($l2, 21);
        IPS_SetPosition($l3, 22);
        IPS_SetPosition($l4, 23);
        IPS_SetPosition($l5, 24);
        IPS_SetPosition($l6, 25);

        // --- 3. Steuerung & Hauptzustand ---
        $v30 = $this->RegisterVariableBoolean("ValveAction", "Ventilschalter (Fahrbefehl)", "SYR.Valve.Bool", 30);
        $this->EnableAction("ValveAction"); 
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

        // --- 4. Gerätestatus & Diagnose ---
        $this->RegisterVariableFloat("BatteryVoltage", "Batteriespannung", "SYR.Voltage", 50);
        $this->RegisterVariableFloat("MainsVoltage", "Netzspannung", "SYR.Voltage", 51);
        $this->RegisterVariableInteger("AlarmState", "Alarm Code", "SYR.Alarm", 52);
        $this->RegisterVariableString("AlarmMessage", "Aktuelle Meldung (Klartext)", "", 53);
        $this->RegisterVariableBoolean("BuzzerActive", "Summer (Buzzer) aktiv", "~Switch", 54);
        $this->RegisterVariableInteger("MicroLeakTestStatus", "Mikroleckage Teststatus", "SYR.MicroLeakStatus", 55);
        $this->RegisterVariableBoolean("LearningPhaseActive", "Selbstlernphase aktiv", "~Switch", 56);
        $this->EnableAction("LearningPhaseActive");
        $this->RegisterVariableInteger("LearningPhaseDays", "Selbstlernphase Dauer", "SYR.Days", 57);
        $this->EnableAction("LearningPhaseDays");

        // --- 5. System & Netzwerkinformationen ---
        $this->RegisterVariableString("SerialNumber", "Seriennummer", "", 70);
        $this->RegisterVariableString("Firmware", "Firmware Version", "", 71);
        $this->RegisterVariableString("MacAddress", "MAC-Adresse", "", 72);
        $this->RegisterVariableString("IpAddress", "IP-Adresse", "", 73);
        $this->RegisterVariableString("Gateway", "Gateway IP", "", 74);
        $this->RegisterVariableString("SSID", "WLAN Name", "", 75);
        $this->RegisterVariableInteger("RSSI", "WLAN Signalstärke", "SYR.RSSI", 76);
        $this->RegisterVariableString("ConnectionStatus", "Verbindungsstatus", "", 77);

        // --- 6. Profileinstellungen ---
        // Profil 1 (Anwesend)
        $this->RegisterVariableString("P1_Name", "Profil 1: Name", "", 90);
        $this->RegisterVariableFloat("P1_MaxVolume", "Profil 1: Max. Volumen", "SYR.Volume", 91);
        $this->EnableAction("P1_MaxVolume");
        $this->RegisterVariableInteger("P1_MaxTime", "Profil 1: Max. Zeit", "SYR.Minutes", 92);
        $this->EnableAction("P1_MaxTime");
        $this->RegisterVariableFloat("P1_MaxFlow", "Profil 1: Max. Durchfluss", "SYR.Flow", 93);
        $this->EnableAction("P1_MaxFlow");
        $this->RegisterVariableBoolean("P1_MicroLeak", "Profil 1: Mikroleckage aktiv", "~Switch", 94);
        $this->EnableAction("P1_MicroLeak");
        $this->RegisterVariableBoolean("P1_Buzzer", "Profil 1: Warnton", "~Switch", 95);
        $this->EnableAction("P1_Buzzer");
        $this->RegisterVariableBoolean("P1_Alarm", "Profil 1: Leckagewarnung", "~Switch", 96);
        $this->EnableAction("P1_Alarm");

        // Profil 2 (Abwesend)
        $this->RegisterVariableString("P2_Name", "Profil 2: Name", "", 100);
        $this->RegisterVariableFloat("P2_MaxVolume", "Profil 2: Max. Volumen", "SYR.Volume", 101);
        $this->EnableAction("P2_MaxVolume");
        $this->RegisterVariableInteger("P2_MaxTime", "Profil 2: Max. Zeit", "SYR.Minutes", 102);
        $this->EnableAction("P2_MaxTime");
        $this->RegisterVariableFloat("P2_MaxFlow", "Profil 2: Max. Durchfluss", "SYR.Flow", 103);
        $this->EnableAction("P2_MaxFlow");
        $this->RegisterVariableBoolean("P2_MicroLeak", "Profil 2: Mikroleckage aktiv", "~Switch", 104);
        $this->EnableAction("P2_MicroLeak");
        $this->RegisterVariableInteger("P2_ReturnTime", "Profil 2: Rückkehrzeit (Std)", "SYR.Minutes", 105);
        $this->EnableAction("P2_ReturnTime");
        $this->RegisterVariableBoolean("P2_Buzzer", "Profil 2: Warnton", "~Switch", 106);
        $this->EnableAction("P2_Buzzer");
        $this->RegisterVariableBoolean("P2_Alarm", "Profil 2: Leckagewarnung", "~Switch", 107);
        $this->EnableAction("P2_Alarm");
    }
    
    public function UpdateData() {
        $ip = $this->ReadPropertyString("IPAddress");
        if (empty($ip)) return;

        $this->MaintainVariables();

        $this->FetchData("/safe-tec/set/ADM/(2)f");
        usleep(200000); 
        
        $response = $this->FetchData("/safe-tec/get/all");
        if (!$response) return;
        
        $data = json_decode($response, true);
        if (is_array($data)) {
            // Wassertemperatur
            $temp = 20.0;
            if (isset($data['getCEL'])) {
                $temp = (float)$data['getCEL'] / 10;
                $this->SetValue("Temperature", $temp);
            }

            // Wasserdruck in bar umrechnen
            if (isset($data['getBAR']) && $data['getBAR'] !== "-") {
                $rawBar = (float)str_replace([" mbar", " bar", " "], "", $data['getBAR']);
                if (strpos($data['getBAR'], "mbar") !== false || $rawBar > 50) {
                    $pressureBar = $rawBar / 1000.0;
                } else {
                    $pressureBar = $rawBar;
                }
                $this->SetValue("Pressure", round($pressureBar, 2));
            }
            
            // Aktueller Durchfluss
            $currentFlow = isset($data['getFLO']) ? (float)$data['getFLO'] : 0;
            $this->SetValue("Flow", $currentFlow);
            
            // Aktuelles Zapfvolumen: Rohwert in mL -> Umrechnung in Liter
            $currentTapVol = 0.0;
            if (isset($data['getAVO'])) {
                $rawAvo = (float)str_replace(["mL", "L", " "], "", $data['getAVO']);
                $currentTapVol = $rawAvo / 1000.0;
                $this->SetValue("CurrentTapVolume", $currentTapVol);
            }
            
            // Gesamtwasserverbrauch & Tagesverbrauch
            if (isset($data['getVOL']) && $data['getVOL'] !== "ERROR: ADM" && $data['getVOL'] !== "-") {
                $totalVolume = (float)str_replace(["Vol[L]", "L", " "], "", $data['getVOL']);
                $this->SetValue("TotalVolume", $totalVolume);

                $today = date("Y-m-d");
                $lastDay = $this->ReadAttributeString("LastMidnightDate");
                
                if ($lastDay !== $today) {
                    $this->WriteAttributeString("LastMidnightDate", $today);
                    $this->WriteAttributeFloat("VolumeAtMidnight", $totalVolume);
                }
                
                $midnightVolume = $this->ReadAttributeFloat("VolumeAtMidnight");
                if ($midnightVolume == 0 && $totalVolume > 0) {
                    $this->WriteAttributeFloat("VolumeAtMidnight", $totalVolume);
                    $midnightVolume = $totalVolume;
                }
                
                $dailyVolume = max(0, $totalVolume - $midnightVolume);
                $this->SetValue("DailyVolume", $dailyVolume);
            }
            
            // Leitfähigkeit
            $conductivity = 0.0;
            if (isset($data['getCND'])) {
                $conductivity = (float)$data['getCND'];
                $this->SetValue("Conductivity", $conductivity);
            } elseif (isset($data['getCON'])) {
                $conductivity = (float)$data['getCON'];
                $this->SetValue("Conductivity", $conductivity);
            }

            // Geschätzte Wasserhärte (°dH)
            if ($conductivity > 0) {
                $ec25 = $conductivity / (1 + 0.02 * ($temp - 25));
                $estimateddH = $ec25 / 33.0;
                $this->SetValue("WaterHardness", round($estimateddH, 1));
            }

            // Ventilzustand
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

            // Aktives Profil
            $activeProfile = 1;
            if (isset($data['getPRF'])) {
                $activeProfile = (int)$data['getPRF'];
                $this->SetValue("ActiveProfile", $activeProfile);
            }
            
            if (isset($data['getDRP'])) {
                $this->SetValue("DisplayOrientation", (int)$data['getDRP']);
            }

            // Profil-Limits auslesen
            $limitFlow = 0; $limitVol = 0; $limitTime = 0;
            if ($activeProfile == 1) {
                if (isset($data['getPF1'])) $limitFlow = (float)$data['getPF1'];
                if (isset($data['getPV1'])) $limitVol = (float)$data['getPV1'];
                if (isset($data['getPT1'])) $limitTime = (float)$data['getPT1'];
            } elseif ($activeProfile == 2) {
                if (isset($data['getPF2'])) $limitFlow = (float)$data['getPF2'];
                if (isset($data['getPV2'])) $limitVol = (float)$data['getPV2'];
                if (isset($data['getPT2'])) $limitTime = (float)$data['getPT2'];
            }
            
            $this->SetValue("FlowleakageLimit", $limitFlow);
            $this->SetValue("VolumeleakageLimit", $limitVol);
            $this->SetValue("TimeleakageLimit", $limitTime);

            // Ausnutzungs-Werte
            $this->SetValue("FlowleakageUtilization", $currentFlow);
            $this->SetValue("VolumeleakageUtilization", $currentTapVol);
            
            if ($currentFlow > 0) {
                $tapStart = $this->ReadAttributeInteger("TapStartTime");
                if ($tapStart === 0) {
                    $this->WriteAttributeInteger("TapStartTime", time());
                    $this->SetValue("TimeleakageUtilization", 0.0);
                } else {
                    $elapsedMinutes = round((time() - $tapStart) / 60, 1);
                    $this->SetValue("TimeleakageUtilization", $elapsedMinutes);
                }
            } else {
                $this->WriteAttributeInteger("TapStartTime", 0);
                $this->SetValue("TimeleakageUtilization", 0.0);
            }

            // Batteriespannung & Netzspannung
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

            // Alarme & Klartext-Meldungen
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

            // Profil 1 Werte auslesen
            if (isset($data['getPN1'])) $this->SetValue("P1_Name", (string)$data['getPN1']);
            if (isset($data['getPV1'])) $this->SetValue("P1_MaxVolume", (float)$data['getPV1']);
            if (isset($data['getPT1'])) $this->SetValue("P1_MaxTime", (int)$data['getPT1']);
            if (isset($data['getPF1'])) $this->SetValue("P1_MaxFlow", (float)$data['getPF1']);
            if (isset($data['getPM1'])) $this->SetValue("P1_MicroLeak", ((int)$data['getPM1'] === 1));
            if (isset($data['getPB1'])) $this->SetValue("P1_Buzzer", ((int)$data['getPB1'] === 1));
            if (isset($data['getPA1'])) $this->SetValue("P1_Alarm", ((int)$data['getPA1'] === 1));

            // Profil 2 Werte auslesen
            if (isset($data['getPN2'])) $this->SetValue("P2_Name", (string)$data['getPN2']);
            if (isset($data['getPV2'])) $this->SetValue("P2_MaxVolume", (float)$data['getPV2']);
            if (isset($data['getPT2'])) $this->SetValue("P2_MaxTime", (int)$data['getPT2']);
            if (isset($data['getPF2'])) $this->SetValue("P2_MaxFlow", (float)$data['getPF2']);
            if (isset($data['getPM2'])) $this->SetValue("P2_MicroLeak", ((int)$data['getPM2'] === 1));
            if (isset($data['getPR2'])) $this->SetValue("P2_ReturnTime", (int)$data['getPR2']);
            if (isset($data['getPB2'])) $this->SetValue("P2_Buzzer", ((int)$data['getPB2'] === 1));
            if (isset($data['getPA2'])) $this->SetValue("P2_Alarm", ((int)$data['getPA2'] === 1));

            // Benachrichtigungen auslösen
            $this->CheckAndSendNotifications($currentValveState, $currentAlarmMessage, $battVal);
        }
    }

    private function CheckAndSendNotifications(int $valveState, string $alarmMessage, float $batteryVoltage) {
        $webFrontID = $this->ReadPropertyInteger("WebFrontID");
        if ($webFrontID <= 0 || !IPS_InstanceExists($webFrontID)) return;

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
        $form = [
            "elements" => [
                ["type" => "ValidationTextBox", "name" => "IPAddress", "caption" => "IP-Adresse"],
                ["type" => "NumberSpinner", "name" => "Port", "caption" => "Port"],
                ["type" => "NumberSpinner", "name" => "UpdateInterval", "caption" => "Update Intervall (Sekunden)"],
                ["type" => "SelectInstance", "name" => "WebFrontID", "caption" => "WebFront Instanz (für Benachrichtigungen)"],
                ["type" => "CheckBox", "name" => "EnableCloseNotification", "caption" => "Benachrichtigung bei Absperrung aktivieren"],
                ["type" => "CheckBox", "name" => "EnableBatteryNotification", "caption" => "Benachrichtigung bei schwacher Batterie aktivieren"]
            ]
        ];

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
                                "onClick" => "SYR_RequestAction(\$id, 'ValveAction', true);"
                            ],
                            [
                                "type" => "Button",
                                "caption" => "Ventil schließen",
                                "onClick" => "SYR_RequestAction(\$id, 'ValveAction', false);"
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
                                "onClick" => "SYR_RequestAction(\$id, 'ActiveProfile', \$TargetProfile);"
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
                                    ["caption" => "90° Gedreht", "value" => 2],
                                    ["caption" => "180° Gedreht", "value" => 3],
                                    ["caption" => "270° Gedreht", "value" => 4]
                                ],
                                "value" => (int)$dispOrientation
                            ],
                            [
                                "type" => "Button",
                                "caption" => "Display Ausrichtung speichern",
                                "onClick" => "SYR_RequestAction(\$id, 'DisplayOrientation', \$EditDisplayOrientation);"
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
                                "onClick" => "SYR_RequestAction(\$id, 'LearningPhaseActive', \$EditSLPActive); SYR_RequestAction(\$id, 'LearningPhaseDays', \$EditSLPDays);"
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
                                "onClick" => "SYR_RequestAction(\$id, 'P1_MaxVolume', \$EditVolumeP1);"
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
                                "onClick" => "SYR_RequestAction(\$id, 'P1_MaxTime', \$EditTimeP1);"
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
                                "onClick" => "SYR_RequestAction(\$id, 'P1_MaxFlow', \$EditFlowP1);"
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
                                "onClick" => "SYR_RequestAction(\$id, 'P1_MicroLeak', \$EditMicroLeakP1);"
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
                                "onClick" => "SYR_RequestAction(\$id, 'P1_Buzzer', \$EditBuzzerP1);"
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
                                "onClick" => "SYR_RequestAction(\$id, 'P1_Alarm', \$EditAlarmP1);"
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
                                "onClick" => "SYR_RequestAction(\$id, 'P2_MaxVolume', \$EditVolumeP2);"
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
                                "onClick" => "SYR_RequestAction(\$id, 'P2_MaxTime', \$EditTimeP2);"
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
                                "onClick" => "SYR_RequestAction(\$id, 'P2_MaxFlow', \$EditFlowP2);"
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
                                "onClick" => "SYR_RequestAction(\$id, 'P2_MicroLeak', \$EditMicroLeakP2);"
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
                                "onClick" => "SYR_RequestAction(\$id, 'P2_ReturnTime', \$EditReturnTimeP2);"
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
                                "onClick" => "SYR_RequestAction(\$id, 'P2_Buzzer', \$EditBuzzerP2);"
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
                                "onClick" => "SYR_RequestAction(\$id, 'P2_Alarm', \$EditAlarmP2);"
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
        if (empty($matches[0])) return $rawAlarmString;

        $translatedList = [];
        foreach ($matches[0] as $code) {
            $code = strtoupper($code);
            if ($code === 'FF') continue;
            $translatedList[] = isset($alarmMapping[$code]) ? $alarmMapping[$code] : "Unbekannter Fehler ({$code})";
        }

        if (empty($translatedList)) return "Keine Fehler im Speicher (OK)";

        $counted = array_count_values($translatedList);
        $resultParts = [];
        foreach ($counted as $msg => $count) {
            $resultParts[] = ($count > 1) ? "{$msg} ({$count}x)" : $msg;
        }

        return implode(', ', $resultParts);
    }

    public function SetDisplayOrientation(int $orientation) {
        $response = $this->SendAdminAndCommand("/safe-tec/set/drp/" . $orientation);
        if (empty($response) || strpos($response, "ERROR") !== false) {
            $this->SendAdminAndCommand("/safe-tec/set/DRP/(" . $orientation . ")f");
        }
    }

    private function SendAdminAndCommand(string $endpoint) {
        $this->FetchData("/safe-tec/set/ADM/(2)f");
        usleep(300000);

        $res = $this->FetchData($endpoint);
        $this->SendDebug("SendCmd", "Endpoint {$endpoint} => Antwort: " . $res, 0);
        usleep(300000);

        $this->FetchData("/safe-tec/set/ADM/(0)f");
        usleep(400000);
        $this->UpdateData();
        return $res;
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

    public function RequestAction($Ident, $Value) {
        switch ($Ident) {
            case "ValveAction":
                $targetVal = ((bool)$Value) ? 1 : 2;
                $this->SendAdminAndCommand("/safe-tec/set/ab/" . $targetVal);
                break;
            case "ActiveProfile":
                $this->SendAdminAndCommand("/safe-tec/set/prf/" . (int)$Value);
                break;
            case "DisplayOrientation":
                $this->SetDisplayOrientation((int)$Value);
                break;
            case "LearningPhaseActive":
                $this->SendAdminAndCommand("/safe-tec/set/slp/" . (((bool)$Value) ? 1 : 0));
                break;
            case "LearningPhaseDays":
                $this->SendAdminAndCommand("/safe-tec/set/slt/" . (int)$Value);
                break;
            case "P1_MaxVolume":
                $this->SendAdminAndCommand("/safe-tec/set/pv1/" . (int)$Value);
                break;
            case "P1_MaxTime":
                $this->SendAdminAndCommand("/safe-tec/set/pt1/" . (int)$Value);
                break;
            case "P1_MaxFlow":
                $this->SendAdminAndCommand("/safe-tec/set/pf1/" . (int)$Value);
                break;
            case "P1_MicroLeak":
                $this->SendAdminAndCommand("/safe-tec/set/pm1/" . (((bool)$Value) ? 1 : 0));
                break;
            case "P1_Buzzer":
                $this->SendAdminAndCommand("/safe-tec/set/pb1/" . (((bool)$Value) ? 1 : 0));
                break;
            case "P1_Alarm":
                $this->SendAdminAndCommand("/safe-tec/set/pa1/" . (((bool)$Value) ? 1 : 0));
                break;
            case "P2_MaxVolume":
                $this->SendAdminAndCommand("/safe-tec/set/pv2/" . (int)$Value);
                break;
            case "P2_MaxTime":
                $this->SendAdminAndCommand("/safe-tec/set/pt2/" . (int)$Value);
                break;
            case "P2_MaxFlow":
                $this->SendAdminAndCommand("/safe-tec/set/pf2/" . (int)$Value);
                break;
            case "P2_MicroLeak":
                $this->SendAdminAndCommand("/safe-tec/set/pm2/" . (((bool)$Value) ? 1 : 0));
                break;
            case "P2_ReturnTime":
                $this->SendAdminAndCommand("/safe-tec/set/pr2/" . (int)$Value);
                break;
            case "P2_Buzzer":
                $this->SendAdminAndCommand("/safe-tec/set/pb2/" . (((bool)$Value) ? 1 : 0));
                break;
            case "P2_Alarm":
                $this->SendAdminAndCommand("/safe-tec/set/pa2/" . (((bool)$Value) ? 1 : 0));
                break;
            default:
                throw new Exception("Invalid Ident: " . $Ident);
        }
    }
}
