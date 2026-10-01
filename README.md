## Dokumentation SYR SafeTech Connect (Lokale API)

**Inhaltsverzeichnis**

1. [Funktionsumfang]
2. [Voraussetzungen]
3. [Installation]
4. [Funktionsreferenz]
5. [Konfiguration]
6. [Versions-Historie]

## 1. Funktionsumfang

Dieses Modul ermöglicht die lokale Anbindung der SYR SafeTech Connect Leckageschutz-Geräte an IP-Symcon. Die Kommunikation erfolgt **ausschließlich über die lokale HTTP-API** des Geräts, wodurch keine Internetverbindung oder Hersteller-Cloud notwendig ist.

Besondere Merkmale:

* **Lokale Steuerung:** Ventilsteuerung, Profilwahl (Anwesend/Abwesend) und Display-Ausrichtung (4 Stufen) direkt im eigenen Netzwerk konfigurierbar.
* **Live-Leckageüberwachung:** Auslesen der aktiven Limits (Begrenzung) und Berechnung der Live-Ausnutzung (Durchfluss, Volumen, Zeit) während eines Zapfvorgangs, inklusive eigenem Timer zur Berechnung der Zapfzeit.
* **Tagesverbrauch:** Eigenständige Berechnung des täglichen Wasserverbrauchs mit automatischer Rücksetzung um Mitternacht.
* **Wasserwerte:** Kontinuierliche Ermittlung von Leitfähigkeit und automatisierte Schätzung der Wasserhärte (°dH).

## 2. Voraussetzungen

* IP-Symcon ab Version 6.0
* Ein SYR SafeTech / SafeTech+ Gerät, das im lokalen WLAN/Netzwerk eingebunden ist.

## 3. Installation

Das Modul kann über das **Module Control** in IP-Symcon installiert werden.

1. Module Control öffnen.
2. URL des GitHub-Repositories hinzufügen.
3. Instanz `SyrSafeTechConnect` hinzufügen.

## 4. Funktionsreferenz

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

## 5. Konfiguration

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

## 6. Versions-Historie

* **1.0**
* Initiale Version
* Lokale API-Anbindung mit `Get` und `Set` Befehlen
* Berechnung Tagesverbrauch
* Timer-Logik für Zeitleckage-Ausnutzung integriert
* 4-stufige Display-Drehung integriert
