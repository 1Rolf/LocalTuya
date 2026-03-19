<?php

declare(strict_types=1);

// Allgemeine Funktionen
require_once __DIR__ . '/../libs/_traits.php';

/**
 * CLASS VacuumCleaner
 */
class VacuumCleaner extends IPSModuleStrict
{
    use DebugHelper;
    use ProfileHelper;
    use VariableHelper;

    // Min IPS Object ID
    // private const IPS_MIN_ID = 10000;

    // Modul IDs
    private const GUID_MQTT_IO = '{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}';  // Splitter
    private const GUID_MQTT_TX = '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}';  // from module to server
    // private const GUID_MQTT_RX = '{7F7632D9-FA40-4F38-8DEA-C83CD4325A32}';  // from server to module

    // Profile "T2M.State"
    private const PROFIL_STATUS = [
        ['offline', 'Offline', 'signal-slash', 0xFF0000],
        ['online', 'Online', 'signal', 0x00FF00],
        ['undefine', 'Undefine', 'signal-slash', 0x0000FF],
    ];

    // Profile "T2MVC.Mode" (vacuum-robot)
    private const PROFIL_MODE = [
        ['standby', 'Standby', '', -1],
        ['smart', 'Smart', '', -1],
        ['wall_follow', 'Edges', '', -1],
        ['spiral', 'Spiral', '', -1],
        ['partial_bow', 'Zigzag', '', -1],
        ['chargego', 'Charge', '', -1],
    ];

    // Profile "T2MVC.Direction"
    private const PROFIL_DIRECTION = [
        ['forward', 'Forward', 'right', -1],
        ['turn_left', 'Turn left', 'turn-left', -1],
        ['turn_right', 'Turn right', 'turn-right', -1],
        ['stop', 'Stop', 'stop', -1],
        ['exit', 'Exit', 'circle-xmark', -1],
    ];

    // Profile "T2MVC.Working" (vacuum-robot)
    private const PROFIL_WORKING = [
        ['standby', 'Standby', '', -1],
        ['smart_clean', 'Smart cleaning', '', -1],
        ['wall_clean', 'Edge cleaning', '', -1], // Kantenreinigungsmodus.
        ['spot_clean', 'Spot cleaning', '', -1], // Punktuelle Reinigung
        ['mop_clean', 'Mopping and cleaning', '', -1], //Wischen und Reinigen
        ['goto_charge', 'Go charging', '', -1],
        ['charging', 'Charging', '', -1],
        ['charge_done', 'Charged', '', -1],
        ['paused', 'Paused', '', -1],
        ['cleaning', 'Cleaning', '', -1],
        ['sleep', 'Sleep', '', -1],
    ];

    // Profile "T2MVC.Suction" (vacuum)
    private const PROFIL_SUCTION = [
        ['strong', 'Strong', '', -1],
        ['normal', 'Normal', '', -1],
        ['gentle', 'Gentle', '', -1],
    ];

    // Profile "T2M.Language" (language)
    private const PROFIL_LANG = [
        ['english', 'English', '', -1],
        ['german', 'German', '', -1],
        ['french', 'French', '', -1],
        ['russian', 'Russian', '', -1],
        ['spanish', 'Spanish', '', -1],
        ['italian', 'Italian', '', -1],
    ];

    // Profile "T2MVC.Speed" (vacuum-robot)
    private const PROFIL_SPEED = [
        ['careful_clean', 'Careful clean', '', -1],
        ['speed_clean', 'Speed clean', '', -1],
    ];

    /**
     * In contrast to Construct, this function is called only once when creating the instance and starting IP-Symcon.
     * Therefore, status variables and module properties which the module requires permanently should be created here.
     *
     * @return void
     */
    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        // Device-Topic (Name)
        $this->RegisterPropertyString('MQTTBaseTopic', 'tuya2mqtt');
        $this->RegisterPropertyString('MQTTTopic', '');

        // Profiles
        $this->RegisterProfileString('T2M.Status', '', '', '', self::PROFIL_STATUS);
        $this->RegisterProfileString('T2M.Language', 'language', '', '', self::PROFIL_LANG);
        $this->RegisterProfileString('T2MVC.Mode', 'vacuum-robot', '', '', self::PROFIL_MODE);
        $this->RegisterProfileString('T2MVC.Direction', 'compass', '', '', self::PROFIL_DIRECTION);
        $this->RegisterProfileString('T2MVC.Working', 'vacuum-robot', '', '', self::PROFIL_WORKING);
        $this->RegisterProfileString('T2MVC.Suction', 'vacuum', '', '', self::PROFIL_SUCTION);
        $this->RegisterProfileString('T2MVC.Speed', 'rabbit-running', '', '', self::PROFIL_SPEED);
        $this->RegisterProfileInteger('T2MVC.Area', 'map', '', ' m³', 0, 9999, 1);
        $this->RegisterProfileInteger('T2MVC.Time', 'timer', '', ' min', 0, 9999, 1);

        // Automatically connect to the MQTT server/splitter instance
        if ((float) IPS_GetKernelVersion() < 8.2) {
            $this->ConnectParent(self::GUID_MQTT_IO);
        }
    }

    /**
     * This function is called when deleting the instance during operation and when updating via "Module Control".
     * The function is not called when exiting IP-Symcon.
     *
     * @return void
     */
    public function Destroy(): void
    {
        //Never delete this line!
        parent::Destroy();
    }

    /**
     * The content can be overwritten in order to transfer a self-created configuration page.
     * This way, content can be generated dynamically.
     * In this case, the "form.json" on the file system is completely ignored.
     *
     * @return string Content of the configuration page.
     */
    public function GetConfigurationForm(): string
    {
        // Get Form
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        //$this->LogDebug(__FUNCTION__, $form);
        return json_encode($form);
    }

    /**
     * Is executed when "Apply" is pressed on the configuration page and immediately after the instance has been created.
     *
     * @return void
     */
    public function ApplyChanges(): void
    {
        //Never delete this line!
        parent::ApplyChanges();

        $base = $this->ReadPropertyString('MQTTBaseTopic');
        $topic = $this->ReadPropertyString('MQTTTopic');

        // Check setup
        if (empty($base) || empty($topic)) {
            $this->SetStatus(201);
            return;
        } else {
            // Set filter
            $filter = preg_quote($this->ReadPropertyString('MQTTBaseTopic') . '/' . $this->ReadPropertyString('MQTTTopic'));
            $this->LogDebug(__FUNCTION__, 'Filter: .*' . $filter . '.*');
            $this->SetReceiveDataFilter('.*' . $filter . '.*');
        }

        // Initialize
        // Statusvariable (SyncProfile)
        $es = @$this->GetIDForIdent('status');

        // Maintain variables
        $pos = 0;
        $this->MaintainVariable('power', $this->Translate('Power'), 0, '~Switch', $pos++, true);
        $this->MaintainVariable('mode', $this->Translate('Mode'), 3, 'T2MVC.Mode', $pos++, true);
        $this->MaintainVariable('direction_control', $this->Translate('Direction control'), 3, 'T2MVC.Direction', $pos++, true);
        $this->MaintainVariable('working_status', $this->Translate('Working status'), 3, 'T2MVC.Working', $pos++, true);
        $this->MaintainVariable('battery_left', $this->Translate('Battery left'), 1, '~Battery.100', $pos++, true);
        $this->MaintainVariable('edge_brush', $this->Translate('Edge brush'), 1, '~Valve', $pos++, true);
        $this->MaintainVariable('roll_brush', $this->Translate('Roll brush'), 1, '~Valve', $pos++, true);
        $this->MaintainVariable('filter', $this->Translate('Filter'), 1, '~Valve', $pos++, true);
        $this->MaintainVariable('suction', $this->Translate('Suction'), 3, 'T2MVC.Suction', $pos++, true);
        $this->MaintainVariable('volume_set', $this->Translate('Volume'), 1, '~Volume', $pos++, true);
        $this->MaintainVariable('clean_speed', $this->Translate('Clean speed'), 3, 'T2MVC.Speed', $pos++, true);
        $this->MaintainVariable('clean_area', $this->Translate('Clean area'), 1, 'T2MVC.Area', $pos++, true);
        $this->MaintainVariable('clean_time', $this->Translate('Clean time'), 1, 'T2MVC.Time', $pos++, true);
        $this->MaintainVariable('status', $this->Translate('Status'), 3, 'T2M.Status', $pos++, true);
        $this->MaintainVariable('language', $this->Translate('Language'), 3, 'T2M.Language', $pos++, true);

        // Maintain actions
        $this->MaintainAction('language', true);
        $this->MaintainAction('power', true);
        $this->MaintainAction('mode', true);
        $this->MaintainAction('direction_control', true);
        $this->MaintainAction('suction', true);
        $this->MaintainAction('volume_set', true);
        $this->MaintainAction('clean_speed', true);

        // Init on first time
        if (!$es) {
            $this->SetValueString('status', 'undefine');
        }

        // All ready
        $this->SetStatus(102);
    }

    /**
     * Is called when, for example, a button is clicked in the visualization.
     *
     * @param string $ident Ident of the variable
     * @param mixed $value The value to be set
     * @return void
     */
    public function RequestAction(string $ident, mixed $value): void
    {
        // Debug output
        $this->LogDebug(__FUNCTION__, $ident . ' => ' . $value);
        switch ($ident) {
            case 'get-states':
                $this->SendMQTT('command', $ident);
                break;
            case 'power':
                // boolean
                $this->SendMQTT($ident . '/command', $value ? 'true' : 'false');
                break;
            case 'volume_set':
                // integer
                $this->SendMQTT($ident . '/command', strval($value));
                break;
            case 'mode':
            case 'direction_control':
            case 'suction':
            case 'clean_speed':
            case 'language':
                // string
                $this->SendMQTT($ident . '/command', strval($value));
                break;
            default:
                $this->LogDebug(__FUNCTION__, 'ERROR!!!');
                break;
        }
        //$this->SetValue($ident, $value);
    }

    /**
     * This function is called by IP-Symcon and processes sent data and, if necessary, forwards it to
     * all child instances. Data can be sent using the SendDataToChildren function.
     *
     * @param string $json Data package in JSON format
     *
     * @return string Optional response to the parent instance
     */
    public function ReceiveData(string $json): string
    {
        $data = json_decode($json);

        $topic = $data->Topic;
        $payload = hex2bin($data->Payload);
        $this->LogDebug(__FUNCTION__, 'Received Topic: ' . $topic . ' Payload: ' . $payload);

        if (fnmatch('*/status', $topic)) {
            $this->SetValueString('status', strval($payload));
        }
        if (fnmatch('*/power', $topic)) {
            $this->SetValueBoolean('power', $payload == 'on' ? true : false);
        }
        if (fnmatch('*/mode', $topic)) {
            $this->SetValueString('mode', strval($payload));
        }
        if (fnmatch('*/direction_control', $topic)) {
            $this->SetValueString('direction_control', strval($payload));
        }
        if (fnmatch('*/working_status', $topic)) {
            $this->SetValueString('working_status', strval($payload));
        }
        if (fnmatch('*/battery_left', $topic)) {
            $this->SetValueInteger('battery_left', intval($payload));
        }
        if (fnmatch('*/edge_brush', $topic)) {
            $this->SetValueInteger('edge_brush', intval($payload));
        }
        if (fnmatch('*/roll_brush', $topic)) {
            $this->SetValueInteger('roll_brush', intval($payload));
        }
        if (fnmatch('*/filter', $topic)) {
            $this->SetValueInteger('filter', intval($payload));
        }
        if (fnmatch('*/suction', $topic)) {
            $this->SetValueString('suction', strval($payload));
        }
        if (fnmatch('*/clean_area', $topic)) {
            $this->SetValueInteger('clean_area', intval($payload));
        }
        if (fnmatch('*/clean_time', $topic)) {
            $this->SetValueInteger('clean_time', intval($payload));
        }
        if (fnmatch('*/clean_speed', $topic)) {
            $this->SetValueString('clean_speed', strval($payload));
        }
        if (fnmatch('*/volume_set', $topic)) {
            $this->SetValueInteger('volume_set', intval($payload));
        }
        if (fnmatch('*/language', $topic)) {
            $this->SetValueString('language', strval($payload));
        }
        return '';
    }

    /**
     * Send command to MQTT server.
     *
     * @param string $topic Topic name
     * @param string $payload Payload data
     *
     * @return bool True if send successful, otherwise false.
     */
    protected function SendMQTT(string $topic, string $payload): bool
    {
        $resultServer = true;
        // MQTT Server
        $server['DataID'] = self::GUID_MQTT_TX;
        $server['PacketType'] = 3;
        $server['QualityOfService'] = 0;
        $server['Retain'] = false;
        $server['Topic'] = $this->ReadPropertyString('MQTTBaseTopic') . '/' . $this->ReadPropertyString('MQTTTopic') . '/' . $topic;
        $server['Payload'] = bin2hex($payload);
        $json = json_encode($server, JSON_UNESCAPED_SLASHES);
        $this->LogDebug(__FUNCTION__ . 'MQTT Server', $json);
        $resultServer = @$this->SendDataToParent($json);
        return $resultServer !== '';
    }
}