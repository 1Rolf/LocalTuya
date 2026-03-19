# 🗂️ Saugroboter (Vacuum Cleaner)

[![Version](https://img.shields.io/badge/Symcon-PHP--Modul-red.svg?style=flat-square)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Product](https://img.shields.io/badge/Symcon%20Version-8.1-blue.svg?style=flat-square)](https://www.symcon.de/produkt/)
[![Version](https://img.shields.io/badge/Modul%20Version-2.2.20260319-orange.svg?style=flat-square)](https://github.com/Wilkware/LocalTuya)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg?style=flat-square)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![Actions](https://img.shields.io/github/actions/workflow/status/wilkware/LocalTuya/ci.yml?branch=main&label=CI&style=flat-square)](https://github.com/Wilkware/LocalTuya/actions)

Das Modul bietet die Möglichkeit, mit einem kombatiblen Saugroboter über das lokale Netzwerk zu kommunizieren.

## Inhaltverzeichnis

1. [Funktionsumfang](#user-content-1-funktionsumfang)
2. [Voraussetzungen](#user-content-2-voraussetzungen)
3. [Installation](#user-content-3-installation)
4. [Einrichten der Instanzen in IP-Symcon](#user-content-4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#user-content-5-statusvariablen-und-profile)
6. [Visualisierung](#user-content-6-visualisierung)
7. [PHP-Befehlsreferenz](#user-content-7-php-befehlsreferenz)
8. [Versionshistorie](#user-content-8-versionshistorie)

### 1. Funktionsumfang

Das Modules kommuniziert via MQTT mit dem Saugroboter und bietet neben dem Auslesen aller Geräteinformationen auch das
Steuern des Roboters über die Statusvariablen.  
Eine genaue Beschreibung der für den Saugroporter verfügbaren Befehlsumfang kann man im [Tuya Developer Portal](https://developer.tuya.com/en/) einsehen.

### 2. Voraussetzungen

* IP-Symcon ab Version 7.2

Notwendige Voraussetzung ist eine funktionsfähige und laufende Installation von [Tuya2Mqtt](https://github.com/Wilkware/tuya2mqtt). Dessen Installation, Konfiguration und der Betrieb ist hier beschrieben: [README](https://github.com/Wilkware/tuya2mqtt/blob/main/README.md).  
Dort findet man ebenfalls die unterstützten Tuya Geräte.

Getestet mit meinem Saugroboter P20 von AIRROBO.

### 3. Installation

* Über den Modul Store die Bibliothek _LocalTuya_ installieren.
* Alternativ Über das Modul-Control folgende URL hinzufügen.  
`https://github.com/Wilkware/LocalTuya` oder `git://github.com/Wilkware/LocalTuya.git`

### 4. Einrichten der Instanzen in IP-Symcon

* Unter "Instanz hinzufügen" ist das _'Tuya Saugroboter'_-Modul unter dem Hersteller _'(Geräte)'_ aufgeführt.

__Konfigurationsseite__:

Einstellungsbereich:

> 📳 Geräteinformationen …

Name                        | Beschreibung
--------------------------- | ----------------------------------
MQTT Base Topic             | Ist das grundlegende Themenpräfix, unter dem alle spezifischen Subtopics für Nachrichten in einem MQTT-System organisiert werden. Standardmäßig ist der Präfix auf _'tuya2mqtt'_ vorbelegt.
MQTT Topic                  | Ist der eindeutige Geräte-Pfad, der zum Veröffentlichen und Abonnieren von Nachrichten verwendet wird. __HINWEIS:__ Immer in Kleinbuchstaben angeben!


_Aktionsbereich:_

Aktion                  | Beschreibung
----------------------- | ---------------------------------
AKTUALISIEREN           | Löst eine Nachricht aus, welche versucht alle Status(Geräte)informationen vom Gerät abzurufen.

### 5. Statusvariablen und Profile

Die Statusvariablen werden automatisch angelegt. Das Löschen einzelner kann hilfreich sein, z.B. wenn entsprechender Befehl/Status nicht vom Roboter unterstützt wird.

Name                        | Typ       | Beschreibung
--------------------------- | --------- | ----------------
Status                      | String    | Verfügbarkeitsstaus (siehe T2M.Status)
Betrieb                     | Boolean   | Betriebszustand (AN, AUS)
Modus                       | String    | Aktueller Gerätemodus (siehe T2MVC.Mode)
Zustand                     | String    | Aktueller Betriebsstatus (siehe T2MVC.Working)
Richtungssteuerung          | String    | Steuerung der Richtungsbewegung (siehe T2MVC.Direction)
Batterieladung              | Integer   | Aktueller Ladezustand (0-100%)
Kantenbürste                | Integer   | Lebensdauer der Seitenbürste (0-100%)
Walzenürste                 | Integer   | Lebensdauer der Hauptbürste (0-100%)
Filer                       | Integer   | Lebensdauer des Filters (0-100%)
Saugstufe                   | String    | Stärke der Saugstufe (siehe T2MVC.Suction)
Reinigungsbereich           | Integer   | Quadratmeter der gereinigten Fläche (0-9999m³)
Reinigungszeit              | Integer   | Verbrauchte Zeit für Reinigung (0-9999min)
Reinigiungsgeschwindigkeit  | String    | Schnelles oder sorgfälltiges Reinigen (siehe T2MVC.Speed)
Lautsärke                   | Integer   | Lautsärker der Sprachausgabe des Gerätes (0-100%)
Sprache                     | String    | Verwendete Sprache für Amsagen (siehe T2M.Language)

Folgendes Profil wird angelegt:

Name                 | Typ       | Beschreibung
-------------------- | --------- | ----------------
T2M.Status           | String    | Online (online), Offline (offline) oder Undefinierd (undefined)
T2M.Language         | String    | Englisch, Deutsch, Französisch, Russisch, Spanisch, Italienisch
T2MVC.Mode           | String    | Bereit, Intilligent, Kanten, Spiral, Zickzack, Laden
T2MVC.Direction      | String    | Vorwärts, Links drehen, Rechts drehen, Stop, Exit
T2MVC.Working        | String    | Intilligente Reingung, Kantenreinigung, Punktuelle Reinigung, Wischen und Reinigen, Zum aufladen, Aufladen, Voll aufgeladen, Pausiert, Reinigen, Schlafen
T2MVC.Suction        | String    | Stark, Normal, Leise
T2MVC.Speed          | String    | Gründliches reinigen, Schnell reinigen
T2MVC.Area           | Integer   | 0 bis 9999 Quadratmeter (m³)
T2MVC.Time           | Integer   | 0 bis 9999 Minuten (min)

### 6. Visualisierung

Man kann die Instanz bzw. Statusvariablen direkt in die Visualisierung verlinken.

### 7. PHP-Befehlsreferenz

Das Modul stellt keine direkten Funktionsaufrufe zur Verfügung.

### 8. Versionshistorie

v2.2.20260319

* _FIX_: Kompatibilität für IPS größer 8.2 hergestellt

v2.1.20250926

* _FIX_: Abruf aller Daten korrigiert (get-states)
* _FIX_: Fehler bei Verarbeitung des Payloads durch Umstellung auf IPSModuleStrict korrigiert

v2.0.20250916

* _NEU_: Projektumstrukturierung hin zu einer globalen CI/CD-Pipeline
* _NEU_: Kompatibilität auf IPS 8.1 hoch gesetzt
* _NEU_: Umstellung auf IPSModuleStrict
* _FIX_: Bibliotheksfunktionen angeglichen

v1.1.20250802

* _NEU_: Konfigurationsformular überarbeitet
* _NEU_: Continuous Integration mit Check Style, Static Code Analysis und Unit Tests eingeführt
* _NEU_: Debugging Funktionen komplett überarbeitet
* _FIX_: Mqtt Topic test korrigiert
* _FIX_: Dokumentation für PHP Static Analysis komplett überarbeitet
* _FIX_: Bibliotheksfunktionen überarbeitet in Vorbereitung auf IPSModuleStrict

v1.0.20250125

* _NEU_: Initialversion

## Entwickler

Seit nunmehr über 10 Jahren fasziniert mich das Thema Haussteuerung. In den letzten Jahren betätige ich mich auch intensiv in der IP-Symcon Community und steuere dort verschiedenste Skript und Module bei. Ihr findet mich dort unter dem Namen @pitti ;-)

[![GitHub](https://img.shields.io/badge/GitHub-@wilkware-181717.svg?style=for-the-badge&logo=github)](https://wilkware.github.io/)

## Spenden

Die Software ist für die nicht kommerzielle Nutzung kostenlos, über eine Spende bei Gefallen des Moduls würde ich mich freuen.

[![PayPal](https://img.shields.io/badge/PayPal-spenden-00457C.svg?style=for-the-badge&logo=paypal)](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=8816166)

## Lizenz

Namensnennung - Nicht-kommerziell - Weitergabe unter gleichen Bedingungen 4.0 International

[![Licence](https://img.shields.io/badge/License-CC_BY--NC--SA_4.0-EF9421.svg?style=for-the-badge&logo=creativecommons)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
