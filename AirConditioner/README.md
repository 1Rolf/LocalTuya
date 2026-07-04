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

## Einrichtung in IP-Symcon
1. Bibliothek im **Modul-Control** aktualisieren, damit das Modul „Air Conditioner" erkannt wird.
2. **Instanz hinzufügen** → nach „Air Conditioner" suchen und auswählen (Alias: Tuya Klimaanlage).
3. Als **Parent** den **MQTT-Server** wählen – deinen Symcon-MQTT-Broker, auf den tuya2mqtt publiziert.
4. **MQTT Base Topic** = `tuya2mqtt`, **MQTT Topic** = `portable_air_conditioner`.
5. Optional **Periodic refresh** aktivieren und Intervall (5–600 s) setzen.
6. **Übernehmen**. Mit „Aktualisieren" werden einmalig alle aktuellen Werte geholt.

## Zyklische Aktualisierung (optional)
Tuya-Geräte senden DPs nur bei Änderung – kein periodischer Vollstatus. Fällt ein
Delta aus (Verbindungsabbruch, verworfener MQTT-Publish), bleibt eine Variable stehen.
Dagegen gibt es im Konfigformular unter **Periodic refresh**:
- **Enable periodic refresh (get-states)** – Ein/Aus
- **Interval** – 5 bis 600 Sekunden

Ist es aktiv, sendet das Modul im gewählten Takt `get-states` an `.../command` und liest
so alle aktuellen Werte neu ein (Re-Sync). Standard: aus, 30 s. Konservativer Richtwert
30–60 s; sehr kurze Intervalle erzeugen unnötig Last.

## Lizenz / Attribution
Abgeleitet von der LocalTuya-Bibliothek von Heiko Wilknitz (@pitti, https://wilkware.de).
Lizenziert unter **CC BY-NC-SA 4.0**.
