# Symcon-SYR-Local
IP-Symcon Module to control SYR Devices using the local Port 5333

## Dokumentation SYR SafeTech Connect (Lokale API)

**Inhaltsverzeichnis**[cite: 2]

1. [Funktionsumfang](https://www.google.com/search?q=%231-funktionsumfang)[cite: 2]
2. [Voraussetzungen](https://www.google.com/search?q=%232-voraussetzungen)[cite: 2]
3. [Installation](https://www.google.com/search?q=%233-installation)[cite: 2]
4. [Funktionsreferenz](https://www.google.com/search?q=%234-funktionsreferenz)[cite: 2]
5. [Konfiguration](https://www.google.com/search?q=%235-konfiguration)[cite: 2]
6. [Versions-Historie](https://www.google.com/search?q=%236-versions-historie)[cite: 2]

## 1. Funktionsumfang[cite: 2]

Dieses Modul ermöglicht die lokale Anbindung der SYR SafeTech Connect Leckageschutz-Geräte an IP-Symcon. Die Kommunikation erfolgt **ausschließlich über die lokale HTTP-API** des Geräts, wodurch keine Internetverbindung oder Hersteller-Cloud notwendig ist.

Besondere Merkmale:

* **Lokale Steuerung:** Ventilsteuerung, Profilwahl (Anwesend/Abwesend) und Display-Ausrichtung (4 Stufen) direkt im eigenen Netzwerk konfigurierbar.
* **Live-Leckageüberwachung:** Auslesen der aktiven Limits (Begrenzung) und Berechnung der Live-Ausnutzung (Durchfluss, Volumen, Zeit) während eines Zapfvorgangs, inklusive eigenem Timer zur Berechnung der Zapfzeit.
* **Tagesverbrauch:** Eigenständige Berechnung des täglichen Wasserverbrauchs mit automatischer Rücksetzung um Mitternacht.
* **Wasserwerte:** Kontinuierliche Ermittlung von Leitfähigkeit und automatisierte Schätzung der Wasserhärte (°dH).

## 2. Voraussetzungen[cite: 2]

* IP-Symcon ab Version 6.0[cite: 2]
* Ein SYR SafeTech / SafeTech+ Gerät, das im lokalen WLAN/Netzwerk eingebunden ist[cite: 2].

## 3. Installation[cite: 2]

Das Modul kann über das **Module Control** in IP-Symcon installiert werden.

1. Module Control öffnen.
2. URL des GitHub-Repositories hinzufügen.
3. Instanz `SyrSafeTechConnect` hinzufügen.

## 4. Funktionsreferenz[cite: 2]

Die Steuerung des Moduls erfolgt standardisiert über die IP-Symcon Funktion `RequestAction($VariablenID, $Wert)`.

Folgende Aktionen (Variablen) sind schaltbar:

* **Ventil schalten (`ValveAction`)**
```php
RequestAction($ID_ValveAction, true);  // Öffnen
RequestAction($ID_ValveAction, false); // Schließen

```


* **Profil wechseln (`ActiveProfile`)**
```php
RequestAction($ID_ActiveProfile, 1); // 1 = Anwesend, 2 = Abwesend, 3 = Profil 3, 4 = Profil 4

```


* **Display-Ausrichtung (`DisplayOrientation`)**
```php
RequestAction($ID_DisplayOrientation, 1); // 1 = Standard (0°), 2 = 90°, 3 = 180°, 4 = 270°

```


* **Selbstlernphase (`LearningPhaseActive` & `LearningPhaseDays`)**
Ein- und Ausschalten der Lernphase sowie Festlegen der Lerntage direkt über das WebFront möglich.

## 5. Konfiguration[cite: 2]

### Instanz-Eigenschaften

| Eigenschaft | Typ | Standardwert | Beschreibung |
| --- | --- | --- | --- |
| IPAddress | string | 192.168.50.168 | IP-Adresse des SYR Geräts |
| Port | integer | 5333 | HTTP Port der lokalen API |
| UpdateInterval | integer | 60 | Abrufintervall in Sekunden (0 = deaktiviert) |
| WebFrontID | integer | 0 | Ziel-WebFront für Benachrichtigungen (optional) |
| EnableCloseNotification | boolean | true | Benachrichtigung bei Ventilschließung |
| EnableBatteryNotification | boolean | true | Benachrichtigung bei niedrigem Batteriestand |

### Variablen & Profile

Das Modul legt beim Start automatisch alle benötigten Profiltypen (z.B. `SYR.Valve.Bool`, `SYR.DisplayOrientation`, `SYR.Alarm`) und die entsprechenden Variablen an.
Unter anderem stehen folgende Werte strukturiert zur Verfügung:

* **Sensorik (Pos 10-17):** Wasserdruck, Temperatur, aktueller Durchfluss, Zapfvolumen, Tagesverbrauch, Gesamtwasserverbrauch, Wasserhärte, Leitfähigkeit.
* **Leckage-Ausnutzung (Pos 20-25):** Begrenzung und aktuelle Ausnutzung für Durchfluss, Volumen und Zeit.
* **Gerätestatus (Pos 50+):** Batteriespannung, Netzspannung, Alarm-Code, Buzzer-Status, Mikroleckage-Teststatus.
* **Netzwerk (Pos 70+):** MAC-Adresse, IP, Gateway, SSID, Signalstärke (RSSI).

## 6. Versions-Historie[cite: 2]

* **1.0**
* Initiale Version
* Lokale API-Anbindung mit `Get` und `Set` Befehlen
* Berechnung Tagesverbrauch
* Timer-Logik für Zeitleckage-Ausnutzung integriert
* 4-stufige Display-Drehung integriert
