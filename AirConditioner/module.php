<?php

declare(strict_types=1);

// Allgemeine Funktionen (Traits der LocalTuya-Bibliothek)
require_once __DIR__ . '/../libs/_traits.php';

/**
 * CLASS AirConditioner
 *
 * Steuert eine Tuya-Klimaanlage lokal ueber tuya2mqtt.
 * Anders als CeilingFan (Friendly Topics) arbeitet dieses Modul mit den ROHEN DPS,
 * die tuya2mqtt als JSON-Objekt auf ".../dps/state" veroeffentlicht, z.B.:
 *   {"1":true,"5":"4","6":24,"8":"2","10":false,"16":false,"17":false}
 * Kommandos werden als Tuya-JSON an ".../dps/command" gesendet: {"dps":<n>,"set":<wert>}
 */
class AirConditioner extends IPSModuleStrict
{
    use DebugHelper;
    use ProfileHelper;
    use VariableHelper;

    // Modul IDs (identisch zur uebrigen LocalTuya-Bibliothek)
    private const GUID_MQTT_IO = '{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}';  // Splitter (MQTT Server/Client)
    private const GUID_MQTT_TX = '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}';  // Modul -> Server

    // Profil "T2M.Status" (bibliotheksweit geteilt)
    private const PROFIL_STATUS = [
        ['offline', 'Offline', 'signal-slash', 0xFF0000],
        ['online', 'Online', 'signal', 0x00FF00],
        ['undefine', 'Undefine', 'signal-slash', 0x0000FF],
    ];

    // Profil "T2MAC.Mode" (DPS 5 - kommt als String "0".."4")
    private const PROFIL_MODE = [
        [0, 'Auto', 'a', -1],
        [1, 'Heat', 'fire', -1],
        [2, 'Dry', 'droplet', -1],
        [3, 'Cool', 'snowflake', -1],
        [4, 'Fan', 'fan', -1],
    ];

    // Profil "T2MAC.FanSpeed" (DPS 8 - kommt als String "0".."3")
    private const PROFIL_FAN = [
        [0, 'Auto', 'a', -1],
        [1, 'Low', 'signal-bars-weak', -1],
        [2, 'Medium', 'signal-bars-fair', -1],
        [3, 'High', 'signal-bars-good', -1],
    ];

    /**
     * Wird einmalig beim Erstellen der Instanz und beim Start von IP-Symcon aufgerufen.
     */
    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        // Properties
        $this->RegisterPropertyString('MQTTBaseTopic', 'tuya2mqtt');
        $this->RegisterPropertyString('MQTTTopic', '');
        $this->RegisterPropertyBoolean('PollActive', false);
        $this->RegisterPropertyInteger('PollInterval', 30);

        // Timer fuer die zyklische Aktualisierung (get-states)
        $this->RegisterTimer('Poll', 0, "T2MAC_Poll(\$_IPS['TARGET']);");

        // Profile
        $this->RegisterProfileString('T2M.Status', 'cloud-question', '', '', self::PROFIL_STATUS);
        $this->RegisterProfileInteger('T2MAC.Mode', 'sliders', '', '', 0, 4, 1, self::PROFIL_MODE);
        $this->RegisterProfileInteger('T2MAC.FanSpeed', 'fan', '', '', 0, 3, 1, self::PROFIL_FAN);
        $this->RegisterProfileInteger('T2MAC.TempC', 'temperature-half', '', ' °C', 16, 31, 1);
        $this->RegisterProfileInteger('T2MAC.TempF', 'temperature-half', '', ' °F', 61, 88, 1);

        // Automatisch mit dem MQTT-Server/-Splitter verbinden
        if ((float) IPS_GetKernelVersion() < 8.2) {
            $this->ConnectParent(self::GUID_MQTT_IO);
        }
    }

    public function Destroy(): void
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function GetConfigurationForm(): string
    {
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        return json_encode($form);
    }

    /**
     * Wird bei "Uebernehmen" und direkt nach dem Erstellen der Instanz ausgefuehrt.
     */
    public function ApplyChanges(): void
    {
        //Never delete this line!
        parent::ApplyChanges();

        $base = $this->ReadPropertyString('MQTTBaseTopic');
        $topic = $this->ReadPropertyString('MQTTTopic');

        // Setup pruefen
        if (empty($base) || empty($topic)) {
            $this->SetTimerInterval('Poll', 0);
            $this->SetStatus(201);
            return;
        } else {
            // Empfangsfilter auf das Geraet setzen
            $filter = preg_quote($base . '/' . $topic);
            $this->LogDebug(__FUNCTION__, 'Filter: .*' . $filter . '.*');
            $this->SetReceiveDataFilter('.*' . $filter . '.*');
        }

        // Erstinitialisierung erkennen
        $es = @$this->GetIDForIdent('status');

        // Variablen anlegen/pflegen
        $pos = 0;
        $this->MaintainVariable('power', $this->Translate('Power'), 0, '~Switch', $pos++, true);
        $this->MaintainVariable('mode', $this->Translate('Mode'), 1, 'T2MAC.Mode', $pos++, true);
        $this->MaintainVariable('setpoint_c', $this->Translate('Setpoint (°C)'), 1, 'T2MAC.TempC', $pos++, true);
        $this->MaintainVariable('fan_speed', $this->Translate('Fan speed'), 1, 'T2MAC.FanSpeed', $pos++, true);
        $this->MaintainVariable('swing', $this->Translate('Swing'), 0, '~Switch', $pos++, true);
        $this->MaintainVariable('sleep', $this->Translate('Sleep'), 0, '~Switch', $pos++, true);
        $this->MaintainVariable('fahrenheit', $this->Translate('Fahrenheit'), 0, '~Switch', $pos++, true);
        $this->MaintainVariable('setpoint_f', $this->Translate('Setpoint (°F)'), 1, 'T2MAC.TempF', $pos++, true);
        $this->MaintainVariable('status', $this->Translate('Status'), 3, 'T2M.Status', $pos++, true);

        // Aktionen (bedienbare Variablen)
        $this->MaintainAction('power', true);
        $this->MaintainAction('mode', true);
        $this->MaintainAction('setpoint_c', true);
        $this->MaintainAction('fan_speed', true);
        $this->MaintainAction('swing', true);
        $this->MaintainAction('sleep', true);
        $this->MaintainAction('fahrenheit', true);
        $this->MaintainAction('setpoint_f', true);

        // Zyklische Aktualisierung (Timer) konfigurieren
        $active = $this->ReadPropertyBoolean('PollActive');
        $interval = $this->ReadPropertyInteger('PollInterval');
        if ($interval < 5) {
            $interval = 5;
        }
        if ($interval > 600) {
            $interval = 600;
        }
        $this->SetTimerInterval('Poll', $active ? $interval * 1000 : 0);

        // Beim ersten Mal initialisieren
        if (!$es) {
            $this->SetValueString('status', 'undefine');
        }

        // Alles bereit
        $this->SetStatus(102);
    }

    /**
     * Wird aufgerufen, wenn in der Visualisierung eine Variable bedient wird.
     */
    public function RequestAction(string $ident, mixed $value): void
    {
        $this->LogDebug(__FUNCTION__, $ident . ' => ' . var_export($value, true));
        switch ($ident) {
            case 'get-states':
                // Geraet zwingen, alle DPS-Werte zu melden (Geraete-Ebene, nicht dps/command)
                $this->SendMQTT('command', 'get-states');
                return;
            case 'power':
                $this->SendDps(1, (bool) $value);
                break;
            case 'mode':
                // DPS 5 erwartet einen String ("0".."4")
                $this->SendDps(5, strval((int) $value));
                break;
            case 'setpoint_c':
                $this->SendDps(6, (int) $value);
                break;
            case 'fan_speed':
                // DPS 8 erwartet einen String ("0".."3")
                $this->SendDps(8, strval((int) $value));
                break;
            case 'swing':
                $this->SendDps(16, (bool) $value);
                break;
            case 'sleep':
                $this->SendDps(17, (bool) $value);
                break;
            case 'fahrenheit':
                $this->SendDps(10, (bool) $value);
                break;
            case 'setpoint_f':
                $this->SendDps(18, (int) $value);
                break;
            default:
                $this->LogDebug(__FUNCTION__, 'ERROR: unbekannter Ident ' . $ident);
                return;
        }
        // Optimistisch sofort setzen; der Echo auf .../dps/state korrigiert bei Bedarf.
        $this->SetValue($ident, $value);
    }

    /**
     * Timer-Callback: erzwingt eine frische Vollausgabe aller Werte (Re-Sync).
     */
    public function Poll(): void
    {
        $this->SendMQTT('command', 'get-states');
    }

    /**
     * Verarbeitet vom MQTT-Server empfangene Daten.
     */
    public function ReceiveData(string $json): string
    {
        $data = json_decode($json);
        $topic = $data->Topic;
        $payload = hex2bin($data->Payload);
        $this->LogDebug(__FUNCTION__, 'Topic: ' . $topic . ' Payload: ' . $payload);

        // Online-/Offline-Status
        if (fnmatch('*/status', $topic)) {
            $this->SetValueString('status', strval($payload));
            return '';
        }

        // Rohe DPS als JSON-Objekt
        if (fnmatch('*/dps/state', $topic)) {
            $dps = json_decode($payload, true);
            if (!is_array($dps)) {
                return '';
            }
            if (array_key_exists('1', $dps)) {
                $this->SetValueBoolean('power', filter_var($dps['1'], FILTER_VALIDATE_BOOLEAN));
            }
            if (array_key_exists('5', $dps)) {
                $this->SetValueInteger('mode', (int) $dps['5']);
            }
            if (array_key_exists('6', $dps)) {
                $this->SetValueInteger('setpoint_c', (int) $dps['6']);
            }
            if (array_key_exists('8', $dps)) {
                $this->SetValueInteger('fan_speed', (int) $dps['8']);
            }
            if (array_key_exists('16', $dps)) {
                $this->SetValueBoolean('swing', filter_var($dps['16'], FILTER_VALIDATE_BOOLEAN));
            }
            if (array_key_exists('17', $dps)) {
                $this->SetValueBoolean('sleep', filter_var($dps['17'], FILTER_VALIDATE_BOOLEAN));
            }
            if (array_key_exists('10', $dps)) {
                $this->SetValueBoolean('fahrenheit', filter_var($dps['10'], FILTER_VALIDATE_BOOLEAN));
            }
            if (array_key_exists('18', $dps)) {
                $this->SetValueInteger('setpoint_f', (int) $dps['18']);
            }
        }
        return '';
    }

    /**
     * Sendet einen einzelnen Datenpunkt als Tuya-JSON an .../dps/command.
     * Format entspricht tuyapi: {"dps": <n>, "set": <wert>}
     */
    protected function SendDps(int $dp, mixed $value): bool
    {
        $payload = json_encode(['dps' => $dp, 'set' => $value], JSON_UNESCAPED_SLASHES);
        return $this->SendMQTT('dps/command', $payload);

        // --- ALTERNATIVE, falls dein tuya2mqtt Einzel-Topics erwartet ---
        // (dann obige Zeile auskommentieren und diese verwenden)
        // $val = is_bool($value) ? ($value ? 'true' : 'false') : strval($value);
        // return $this->SendMQTT('dps/' . $dp . '/command', $val);
    }

    /**
     * Sendet ein Kommando an den MQTT-Server.
     */
    protected function SendMQTT(string $topic, string $payload): bool
    {
        $server = [];
        $server['DataID'] = self::GUID_MQTT_TX;
        $server['PacketType'] = 3;
        $server['QualityOfService'] = 0;
        $server['Retain'] = false;
        $server['Topic'] = $this->ReadPropertyString('MQTTBaseTopic') . '/' . $this->ReadPropertyString('MQTTTopic') . '/' . $topic;
        $server['Payload'] = bin2hex($payload);
        $json = json_encode($server, JSON_UNESCAPED_SLASHES);
        $this->LogDebug(__FUNCTION__, $json);
        $result = @$this->SendDataToParent($json);
        return $result !== '';
    }
}
