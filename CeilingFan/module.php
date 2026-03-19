<?php

declare(strict_types=1);

// Allgemeine Funktionen
require_once __DIR__ . '/../libs/_traits.php';

/**
 * CLASS CeilingFan
 */
class CeilingFan extends IPSModuleStrict
{
    use DebugHelper;
    use ProfileHelper;
    use VariableHelper;

    // Min IPS Object ID
    // private const IPS_MIN_ID = 10000;

    // Modul IDs
    private const GUID_MQTT_IO = '{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}';  // Splitter
    private const GUID_MQTT_TX = '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}';  // from module to server
    //private const GUID_MQTT_RX = '{7F7632D9-FA40-4F38-8DEA-C83CD4325A32}';  // from server to module

    // Profile "T2M.State"
    private const PROFIL_STATUS = [
        ['offline', 'Offline', 'signal-slash', 0xFF0000],
        ['online', 'Online', 'signal', 0x00FF00],
        ['undefine', 'Undefine', 'signal-slash', 0x0000FF],
    ];

    // Profile "T2MVC.ColorTemp" (vacuum-robot)
    private const PROFIL_TEMP = [
        [0, 'Warm', 'dial-min', -1],
        [500, 'Neutral', 'dial-med', -1],
        [1000, 'Cool', 'dial-max', -1],
    ];

    // Profile "T2MCF.Direction"
    private const PROFIL_DIRECTION = [
        ['forward', 'Forward', 'arrows-rotate', -1],
        ['reverse', 'Reverse', 'arrows-rotate-reverse', -1],
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
        $this->RegisterProfileString('T2M.Status', 'cloud-question', '', '', self::PROFIL_STATUS);
        $this->RegisterProfileString('T2MCF.Direction', 'compass', '', '', self::PROFIL_DIRECTION);
        $this->RegisterProfileInteger('T2MCF.ColorTemp', 'sliders', '', '', 0, 1000, 500, self::PROFIL_TEMP);
        $this->RegisterProfileInteger('T2MCF.Speed', 'rabbit-running', 'Level ', '', 1, 6, 1);
        $this->RegisterProfileInteger('T2MCF.Countdown', 'timer', '', ' min', 0, 540, 1);

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
        $this->MaintainVariable('light', $this->Translate('Light'), 0, '~Switch', $pos++, true);
        $this->MaintainVariable('color_temp', $this->Translate('Color temp'), 1, 'T2MCF.ColorTemp', $pos++, true);
        $this->MaintainVariable('fan', $this->Translate('Fan'), 0, '~Switch', $pos++, true);
        $this->MaintainVariable('speed', $this->Translate('Speed'), 1, 'T2MCF.Speed', $pos++, true);
        $this->MaintainVariable('direction', $this->Translate('Direction'), 3, 'T2MCF.Direction', $pos++, true);
        $this->MaintainVariable('countdown_left', $this->Translate('Countdown left'), 1, 'T2MCF.Countdown', $pos++, true);
        $this->MaintainVariable('beep', $this->Translate('Beep'), 0, '~Switch', $pos++, true);
        $this->MaintainVariable('status', $this->Translate('Status'), 3, 'T2M.Status', $pos++, true);

        // Maintain actions
        $this->MaintainAction('light', true);
        $this->MaintainAction('color_temp', true);
        $this->MaintainAction('fan', true);
        $this->MaintainAction('speed', true);
        $this->MaintainAction('direction', true);
        $this->MaintainAction('countdown_left', true);
        $this->MaintainAction('beep', true);

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
            case 'light':
            case 'fan':
            case 'beep':
                // boolean
                $this->SendMQTT($ident . '/command', $value ? 'true' : 'false');
                break;
            case 'color_temp':
            case 'speed':
            case 'countdown_left':
                // integer
                $this->SendMQTT($ident . '/command', strval($value));
                break;
            case 'direction':
                // string
                $this->SendMQTT($ident . '/command', $value);
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
        if (fnmatch('*/light', $topic)) {
            $this->SetValueBoolean('light', $payload == 'on' ? true : false);
        }
        if (fnmatch('*/color_temp', $topic)) {
            $this->SetValueInteger('color_temp', intval($payload));
        }
        if (fnmatch('*/fan', $topic)) {
            $this->SetValueBoolean('fan', $payload == 'on' ? true : false);
        }
        if (fnmatch('*/speed', $topic)) {
            $this->SetValueInteger('speed', intval($payload));
        }
        if (fnmatch('*/direction', $topic)) {
            $this->SetValueString('direction', strval($payload));
        }
        if (fnmatch('*/countdown_left', $topic)) {
            $this->SetValueInteger('countdown_left', intval($payload));
        }
        if (fnmatch('*/beep', $topic)) {
            $this->SetValueBoolean('beep', $payload == 'on' ? true : false);
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
        $this->LogDebug(__FUNCTION__, $json);
        $resultServer = @$this->SendDataToParent($json);
        return $resultServer !== '';
    }
}