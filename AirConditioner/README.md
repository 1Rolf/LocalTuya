# Air Conditioner (Tuya Klimaanlage)

Drittes Geräte-Modul für die LocalTuya-Bibliothek. Steuert eine Tuya-Klimaanlage
lokal über **tuya2mqtt** – ohne Cloud.

## Besonderheit gegenüber CeilingFan / VacuumCleaner
Diese beiden Module nutzen **Friendly Topics**. Für die Klimaanlage existiert kein
tuya2mqtt-Template, daher verarbeitet dieses Modul die **rohen DPS**, die tuya2mqtt
als JSON auf `.../dps/state` veröffentlicht, z. B.:

    {"1":true,"5":"4","6":24,"8":"2","10":false,"16":false,"17":false}

Kommandos gehen als Tuya-JSON an `.../dps/command`: `{"dps":<n>,"set":<wert>}`.

## DPS-Zuordnung
| DPS | Variable            | Typ     | Werte                                            |
|-----|---------------------|---------|--------------------------------------------------|
| 1   | Ein/Aus             | Boolean | true/false                                       |
| 5   | Modus               | String  | 0 Auto, 1 Heizen, 2 Entfeuchten, 3 Kühlen, 4 Lüften |
| 6   | Solltemperatur °C   | Integer | °C                                               |
| 8   | Lüfterstufe         | String  | 0 Auto, 1 Niedrig, 2 Mittel, 3 Hoch              |
| 10  | Einheit °F an/aus   | Boolean | true = °F-Modus                                  |
| 16  | Luftklappe (Swing)  | Boolean | true/false                                       |
| 17  | Schlafmodus         | Boolean | true/false                                       |
| 18  | Solltemperatur °F   | Integer | °F (nur wenn DPS 10 an)                          |

DPS 4/13/14/15/19 sind derzeit nicht zugeordnet (unbekannt).

## Installation
1. Diesen Ordner `AirConditioner` in dein LocalTuya-Repo legen (neben `CeilingFan`,
   `VacuumCleaner` und `libs`), committen/pushen.
2. In IP-Symcon die Bibliothek über das Modul-Control aktualisieren.
3. Instanz hinzufügen → Hersteller „(Geräte)" → **Air Conditioner** (Alias: Tuya Klimaanlage).
4. **MQTT Base Topic** = `tuya2mqtt`, **MQTT Topic** = `portable_air_conditioner`.
5. Als Parent den vorhandenen MQTT-Client/-Server wählen. „Aktualisieren" klicken.

## Kommando-Topic verifizieren (wichtig!)
Getestet werden konnte der Schreibpfad nicht. Prüfe ihn einmal manuell:

    mosquitto_pub -h <SYMCON_IP> -t 'tuya2mqtt/portable_air_conditioner/dps/command' -m '{"dps":1,"set":false}'

Schaltet die AC ab → passt. Falls nicht, erwartet dein tuya2mqtt Einzel-Topics:

    mosquitto_pub -h <SYMCON_IP> -t 'tuya2mqtt/portable_air_conditioner/dps/1/command' -m 'false'

In diesem Fall in `module.php` die Methode `SendDps()` auf die dort auskommentierte
Alternative umstellen.

## Lizenz / Attribution
Abgeleitet von der LocalTuya-Bibliothek von Heiko Wilknitz (@pitti, https://wilkware.de).
Lizenziert unter **CC BY-NC-SA 4.0**.
