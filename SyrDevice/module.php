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
            IPS_SetVariableProfileIcon("SYR.Volume", "Tap");
        }

        if (!IPS_VariableProfileExists("SYR.Minutes")) {
            IPS_CreateVariableProfile("SYR.Minutes", 2); // Geändert auf Float für genauere Timer-Anzeige
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

        // Display Ausrichtung mit 4 Optionen
        if (!IPS_VariableProfileExists("SYR.DisplayOrientation")) {
            IPS_CreateVariableProfile("SYR.DisplayOrientation", 1);
        }
        IPS_SetVariableProfileAssociation("SYR.DisplayOrientation", 1, "Standard (0°)", "Information", -1);
        IPS_SetVariableProfileAssociation("SYR.DisplayOrientation", 2, "90° Gedreht", "Information", -1);
        IPS_SetVariableProfileAssociation("SYR.DisplayOrientation", 3, "180° Gedreht", "Information", -1);
        IPS_SetVariableProfileAssociation("SYR.DisplayOrientation", 4, "270° Gedreht", "Information", -1);
    }

    private function MaintainVariables() {
        // --- 1. Messwerte & Sensoren (Pos 10 - 17) ---
        $v10 = $this->RegisterVariableInteger("Pressure", "Wasserdruck", "SYR.Pressure.mBar", 10);
        $v11 = $this->RegisterVariableFloat("Temperature", "Wassertemperatur", "~Temperature", 11);
        $v12 = $this->RegisterVariableFloat("Flow", "Aktueller Durchfluss", "SYR.Flow", 12);
        $v13 = $this->RegisterVariableFloat("CurrentTapVolume", "Aktuelles Zapfvolumen", "SYR.Volume", 13);
        $v14 = $this->RegisterVariableFloat("DailyVolume", "Tagesverbrauch", "SYR.Volume", 14); // Neu hinzugefügt
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

        // --- 2. Aktive Limits & Ausnutzung (Pos 20 - 25) ---
        $l1 = $this->RegisterVariableFloat("FlowleakageLimit", "Durchflussleckage-Begrenzung", "SYR.Flow", 20);
        $l2 = $this->RegisterVariableFloat("FlowleakageUtilization", "Durchflussleckage-Ausnutzung", "SYR.Flow", 21);
        $l3 = $this->RegisterVariableFloat("VolumeleakageLimit", "Volumenleckage-Begrenzung", "SYR.Volume", 22);
        $l4 = $this->RegisterVariableFloat("VolumeleakageUtilization", "Volumenleckage-Ausnutzung", "SYR.Volume", 23);
        $l5 = $this->RegisterVariableFloat("TimeleakageLimit", "Zeitleckage-Begrenzung", "SYR.Minutes", 24);
        $l6 = $this->RegisterVariableFloat("TimeleakageUtilization", "Zeitleckage-Ausnutzung", "SYR.Minutes", 25);

        IPS_SetPosition($l1, 20);
        IPS_SetPosition($l2, 21);
        IPS_SetPosition($l3, 22);
        IPS_SetPosition($l4, 23);
        IPS_SetPosition($l5, 24);
        IPS_SetPosition($l6, 25);

        // --- 3. Steuerung & Hauptzustand (Pos 30 - 39) ---
        $v30 = $this->RegisterVariableBoolean("ValveAction", "Ventilschalter (Fahrbefehl)", "SYR.Valve.Bool", 30);
        $this->EnableAction("ValveAction"); 
        $v31 = $this->RegisterVariableInteger("ValveState", "Ventilzustand (Status)", "SYR.Valve.Int", 31);
        $v32 = $this->RegisterVariableInteger("ActiveProfile", "Aktives Profil", "SYR.Profile", 32);
        $this->EnableAction("ActiveProfile");
        $v33 = $this->RegisterVariableBoolean("SleepMode", "Schlafmodus aktiv", "~Switch", 33);
        $v34 = $this->RegisterVariableInteger("DisplayOrientation", "Display Ausrichtung", "SYR.DisplayOrientation", 34);
        $this->EnableAction("DisplayOrientation");

        // ... restliche Gerätestatus-Variablen ab Position 50 bleiben erhalten
        $this->RegisterVariableFloat("BatteryVoltage", "Batteriespannung", "SYR.Voltage", 50);
        $this->RegisterVariableFloat("MainsVoltage", "Netzspannung", "SYR.Voltage", 51);
        $this->RegisterVariableInteger("AlarmState", "Alarm Code", "SYR.Alarm", 52);
        $this->RegisterVariableString("AlarmMessage", "Aktuelle Meldung (Klartext)", "", 53);
        
        // ... System, Netz und Profileinstellungen ab Pos 70 bzw. 90 (wie im vorigen Code)
        // (Zur besseren Übersichtlichkeit hier im Beispielblock weggelassen, 
        // müssen im echten Code aber natürlich nicht gelöscht werden).
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
            // Messwerte (wie bisher)
            $temp = 20.0;
            if (isset($data['getCEL'])) {
                $temp = (float)$data['getCEL'] / 10;
                $this->SetValue("Temperature", $temp);
            }
            if (isset($data['getBAR']) && $data['getBAR'] !== "-") {
                $druck = (float)str_replace([" mbar", " bar"], "", $data['getBAR']);
                if (strpos($data['getBAR'], "mbar") === false && $druck < 50) $druck = $druck * 1000;
                $this->SetValue("Pressure", (int)round($druck));
            }
            
            $currentFlow = isset($data['getFLO']) ? (float)$data['getFLO'] : 0;
            $this->SetValue("Flow", $currentFlow);
            
            $currentTapVol = 0;
            if (isset($data['getAVO'])) {
                $currentTapVol = ((float)str_replace(["mL", " "], "", $data['getAVO'])) / 1000;
                $this->SetValue("CurrentTapVolume", $currentTapVol);
            }
            
            // Tagesverbrauch & Gesamtwasserverbrauch Logik
            if (isset($data['getVOL']) && $data['getVOL'] !== "ERROR: ADM" && $data['getVOL'] !== "-") {
                $totalVolume = (float)str_replace(["Vol[L]", "L", " "], "", $data['getVOL']);
                $this->SetValue("TotalVolume", $totalVolume);

                $today = date("Y-m-d");
                $lastDay = $this->ReadAttributeString("LastMidnightDate");
                
                // Tageswechsel erkennen
                if ($lastDay !== $today) {
                    $this->WriteAttributeString("LastMidnightDate", $today);
                    $this->WriteAttributeFloat("VolumeAtMidnight", $totalVolume);
                }
                
                $midnightVolume = $this->ReadAttributeFloat("VolumeAtMidnight");
                if ($midnightVolume == 0 && $totalVolume > 0) {
                    // Fallback beim ersten Start des Moduls
                    $this->WriteAttributeFloat("VolumeAtMidnight", $totalVolume);
                    $midnightVolume = $totalVolume;
                }
                
                // Tagesverbrauch in Liter setzen
                $dailyVolume = max(0, $totalVolume - $midnightVolume);
                $this->SetValue("DailyVolume", $dailyVolume);
            }
            
            // ... Leitfähigkeit und Härte (wie bisher)

            // Steuerung & Status
            $activeProfile = 1;
            if (isset($data['getPRF'])) {
                $activeProfile = (int)$data['getPRF'];
                $this->SetValue("ActiveProfile", $activeProfile);
            }
            
            if (isset($data['getDRP'])) {
                $this->SetValue("DisplayOrientation", (int)$data['getDRP']);
            }

            // Auslesen der Profil-Limits je nach aktivem Profil
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
            
            // Setzen der Limit-Variablen (Begrenzung)
            $this->SetValue("FlowleakageLimit", $limitFlow);
            $this->SetValue("VolumeleakageLimit", $limitVol);
            $this->SetValue("TimeleakageLimit", $limitTime);

            // Setzen der Utilization-Variablen (Ausnutzung)
            $this->SetValue("FlowleakageUtilization", $currentFlow);
            $this->SetValue("VolumeleakageUtilization", $currentTapVol);
            
            // Eigene Timer-Logik für Zeitleckage-Ausnutzung (da die API die Zapfzeit meist nicht direkt liefert)
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

            // ... (Restlicher Parsing-Code für Batterie, Alarm, Netz etc. analog zum vorigen Code)
        }
    }

    public function SetDisplayOrientation(int $orientation) {
        // Fallback-Mechanismus, da manche SYR Firmwares unterschiedliche Endpoints für das Display nutzen
        $response = $this->SendAdminAndCommand("/safe-tec/set/drp/" . $orientation);
        
        if (empty($response) || strpos($response, "ERROR") !== false) {
            // Alternativer Command-Style, der bei hartnäckigen Einstellungen oft erzwingt, dass der Wert angenommen wird
            $this->SendAdminAndCommand("/safe-tec/set/DRP/(" . $orientation . ")f");
        }
        $this->UpdateData();
    }

    // ... (Weitere Set-Funktionen und RequestAction wie im vorigen Code)

}
