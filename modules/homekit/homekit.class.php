<?php
/**
 * HomeKit
 * @package project
 * @author Wizard <sergejey@gmail.com>
 * @copyright http://majordomo.smartliving.ru/ (c)
 * @version 0.1 (wizard, 14:02:05 [Feb 11, 2019])
 */
//
//
class homekit extends module
{
    /**
     * homekit
     *
     * Module class constructor
     *
     * @access private
     */
    function __construct()
    {
        $this->name = "homekit";
        $this->title = "HomeKit";
        $this->module_category = "<#LANG_SECTION_DEVICES#>";
        $this->checkInstalled();
    }

    /**
     * saveParams
     *
     * Saving module parameters
     *
     * @access public
     */
    function saveParams($data = 1)
    {
        $p = array();
        if (IsSet($this->id)) {
            $p["id"] = $this->id;
        }
        if (IsSet($this->view_mode)) {
            $p["view_mode"] = $this->view_mode;
        }
        if (IsSet($this->edit_mode)) {
            $p["edit_mode"] = $this->edit_mode;
        }
        if (IsSet($this->tab)) {
            $p["tab"] = $this->tab;
        }
        return parent::saveParams($p);
    }

    /**
     * getParams
     *
     * Getting module parameters from query string
     *
     * @access public
     */
    function getParams()
    {
        global $id;
        global $mode;
        global $view_mode;
        global $edit_mode;
        global $tab;
        if (isset($id)) {
            $this->id = $id;
        }
        if (isset($mode)) {
            $this->mode = $mode;
        }
        if (isset($view_mode)) {
            $this->view_mode = $view_mode;
        }
        if (isset($edit_mode)) {
            $this->edit_mode = $edit_mode;
        }
        if (isset($tab)) {
            $this->tab = $tab;
        }
    }

    /**
     * Run
     *
     * Description
     *
     * @access public
     */
    function run()
    {
        global $session;
        $out = array();
        if ($this->action == 'admin') {
            $this->admin($out);
        } else {
            $this->usual($out);
        }
        if (IsSet($this->owner->action)) {
            $out['PARENT_ACTION'] = $this->owner->action;
        }
        if (IsSet($this->owner->name)) {
            $out['PARENT_NAME'] = $this->owner->name;
        }
        $out['VIEW_MODE'] = $this->view_mode;
        $out['EDIT_MODE'] = $this->edit_mode;
        $out['MODE'] = $this->mode;
        $out['ACTION'] = $this->action;
        $this->data = $out;
        $p = new parser(DIR_TEMPLATES . $this->name . "/" . $this->name . ".html", $this->data, $this);
        $this->result = $p->result;
    }

    /**
     * BackEnd
     *
     * Module backend
     *
     * @access public
     */
    function admin(&$out)
    {

        if (gr('ok_msg')) {
            $out['OK_MSG'] = gr('ok_msg');
        }

        $this->getConfig();
        $out['HOMEBRIDGE_HOME'] = $this->config['HOMEBRIDGE_HOME'];
        if (!$out['HOMEBRIDGE_HOME']) {
            $out['HOMEBRIDGE_HOME'] = '/home/pi/.homebridge';
        }

        $homekit_config_file = $out['HOMEBRIDGE_HOME'] . '/config.json';

        if (is_file($homekit_config_file)) {
            $homekit_data = json_decode(LoadFile($homekit_config_file), true);
            //dprint($data);
            $out['HOMEBRIDGE_ID'] = $homekit_data['bridge']['username'];
        }

        if ($this->mode == 'service_stop') {
            safe_exec('sudo service homebridge stop', 1);
            sleep(2);
            @unlink($homebridge_home . '/cached/cachedAccessories');
            $this->redirect("?ok_msg=" . urlencode('Stop command sent'));
        }
        if ($this->mode == 'service_start') {
            safe_exec('sudo service homebridge start', 1);
            $this->redirect("?ok_msg=" . urlencode('Start command sent'));
        }

        if ($this->mode == 'service_restart') {
            $this->restartHomebridge();
        }

        if ($this->mode == 'service_disable') {
            safe_exec('sudo systemctl stop homebridge.service', 1);
            sleep(3);
            safe_exec('sudo systemctl disable homebridge.service', 1);
            $this->redirect("?ok_msg=" . urlencode('Disable command sent'));
        }

        if ($this->mode == 'service_enable') {
            safe_exec('sudo systemctl enable homebridge.service', 1);
            sleep(3);
            $this->mode='sync';
        }

        if ($this->mode == 'sync') {

            addClass('HomeBridgeClass');
            $properties = array('from_get' => 'homebridge/from/get',
                'from_identify' => 'homebridge/from/identify',
                'from_response' => 'homebridge/from/response',
                'from_set' => 'homebridge/from/set',
                'to_add' => 'homebridge/to/add',
                'to_add_service' => 'homebridge/to/add/service',
                'to_get' => 'homebridge/to/get',
                'to_remove' => 'homebridge/to/remove',
                'to_remove_service' => 'homebridge/to/remove/service',
                'to_set' => 'homebridge/to/set',
                'to_set_accessoryinformation' => 'homebridge/to/set/accessoryinformation',
                'to_set_reachability' => 'homebridge/to/set/reachability');
            foreach ($properties as $p => $path) {
                addClassProperty('HomeBridgeClass', $p);
            }
            addClassObject('HomeBridgeClass', 'HomeBridge');


            SQLExec("DELETE FROM mqtt WHERE PATH LIKE 'homebridge%'");
            foreach ($properties as $p => $path) {
                addLinkedProperty('HomeBridge', $p, 'mqtt');
                //SQLExec("DELETE FROM mqtt WHERE PATH='".$path."'");
                $mqtt_rec=array();
                $mqtt_rec['TITLE']=$path;
                $mqtt_rec['PATH']=$path;
                $mqtt_rec['LINKED_OBJECT']='HomeBridge';
                $mqtt_rec['LINKED_PROPERTY']=$p;
                SQLInsert('mqtt',$mqtt_rec);
            }

            include_once (DIR_MODULES.'mqtt/mqtt.class.php');
            $mqtt=new mqtt();
            $mqtt->getConfig();
            $tmp=explode(',',$mqtt->config['MQTT_QUERY']);
            $tmp=array_map('trim',$tmp);
            if (!in_array('homebridge/from/#',$tmp)) {
                $tmp[]='homebridge/from/#';
                $mqtt->config['MQTT_QUERY']=implode(', ',$tmp);
                $mqtt->saveConfig();
                setGlobal('cycle_mqttControl', 'restart');
            }

            //sync cameras
            if ($this->config['HOMEBRIDGE_HOME']) {
                $homekit_config_file = $this->config['HOMEBRIDGE_HOME'] . '/config.json';
            } else {
                $homekit_config_file = '/home/pi/.homebridge/config.json';
            }
            if (is_file($homekit_config_file)) {
                $homekit_data = json_decode(LoadFile($homekit_config_file), true);
                if (is_array($homekit_data) && is_array($homekit_data['platforms'])) {
                    $config_updated=0;
                    $found_platforms=array();
                    $ip_cameras=SQLSelect("SELECT * FROM devices WHERE TYPE='camera'");
                    if (count($ip_cameras)>0) {
                        foreach($homekit_data['platforms'] as &$platform) {
                            $found_platforms[strtolower($platform['platform'])]=1;
                        }
                        if (!$found_platforms['camera-ffmpeg']) {
                            $homekit_data['platforms'][]=array('platform'=>'Camera-ffmpeg');
                        }
                        foreach($homekit_data['platforms'] as &$platform) {
                            if (strtolower($platform['platform'])=='camera-ffmpeg') {
                                $cams=array();
                                foreach($ip_cameras as $camera) {
                                    $cam_rec=array();
                                    $cam_rec['name']=$camera['TITLE'];
                                    $source = '';

                                    $cameraUsername = gg($camera['LINKED_OBJECT'].'.cameraUsername');
                                    $cameraPassword = gg($camera['LINKED_OBJECT'].'.cameraPassword');
                                    $snapshot_url = gg($camera['LINKED_OBJECT'].'.snapshotURL');
                                    $stream_url = gg($camera['LINKED_OBJECT'].'.streamURL');
                                    $stream_url_hq = gg($camera['LINKED_OBJECT'].'.streamURL_HQ');
                                    $streamTransport = gg($camera['LINKED_OBJECT'].'.streamTransport');

                                    if ($stream_url_hq) {
                                        $source=$stream_url_hq;
                                    } elseif ($stream_url) {
                                        $source=$stream_url;
                                    }

                                    if ($source!='') {
                                        if ($cameraUsername && $cameraPassword) {
                                            $source = str_replace('://','://'.$cameraUsername.':'.$cameraPassword.'@',$source);
                                            $snapshot_url = str_replace('://','://'.$cameraUsername.':'.$cameraPassword.'@',$snapshot_url);
                                        }
                                        $source='-i '.$source;
                                        if ($streamTransport!='') {
                                            $source='-rtsp_transport '.$streamTransport.' '.$source;
                                        }
                                        $source='-re '.$source;
                                        $cam_rec['videoConfig']=array('source'=>$source,'maxStreams'=>2,'maxWidth'=>1280,'maxHeight'=>720,'maxFPS'=>30);
                                        if ($snapshot_url!='') {
                                            $cam_rec['stillImageSource']='-i '.$snapshot_url;
                                        }
                                        $cams[]=$cam_rec;
                                    }
                                }
                                if (json_encode($platform['cameras'])!=json_encode($cams)) {
                                    $platform['cameras']=$cams;
                                    $config_updated=1;
                                }
                            }
                        }
                    }
                    if ($config_updated) {
                        SaveFile($homekit_config_file,json_encode($homekit_data,JSON_PRETTY_PRINT));
                    }
                }
            }

            $old_id = $out['HOMEBRIDGE_ID'];
            $filename = $out['HOMEBRIDGE_HOME'] . '/persist/AccessoryInfo.' . str_replace(':', '', $old_id) . '.json';
            @unlink($filename);
            $filename = $out['HOMEBRIDGE_HOME'] . '/persist/IdentifierCache.' . str_replace(':', '', $old_id) . '.json';
            @unlink($filename);
            $this->restartHomebridge(1);
            $this->redirect("?ok_msg=" . urlencode('SYNC command sent. Restarting.'));
        }

        if ($this->view_mode == 'update_settings') {
            $new_home = gr('homebridge_home', 'trim');
            $new_home = preg_replace('/[\/]$/is', '', $new_home);
            if (is_dir($new_home)) {
                $this->config['HOMEBRIDGE_HOME'] = $new_home;
            }
            $this->saveConfig();

            $homebridge_id = strtoupper(gr('homebridge_id', 'trim'));
            if (is_file($homekit_config_file) && is_array($homekit_data) && $homebridge_id != $out['HOMEBRIDGE_ID'] && preg_match('/^\w{2}:\w{2}:\w{2}:\w{2}:\w{2}:\w{2}$/', $homebridge_id)) {
                $old_id = $out['HOMEBRIDGE_ID'];
                $homekit_data['bridge']['username'] = $homebridge_id;
                @unlink($out['HOMEBRIDGE_HOME'] . '/persist/AccessoryInfo.' . str_replace(':', '', $old_id) . '.json');
                @unlink($out['HOMEBRIDGE_HOME'] . '/persist/IdentifierCache.' . str_replace(':', '', $old_id) . '.json');
                SaveFile($homekit_config_file, json_encode($homekit_data, JSON_PRETTY_PRINT));
                $this->restartHomebridge();
            }
            $this->redirect("?ok_msg=".urldecode('Settings have been saved.'));
        }

    }

    function restartHomebridge($force_refresh = 0)
    {
        $this->getConfig();
        $homebridge_home = $this->config['HOMEBRIDGE_HOME'];
        if (!$homebridge_home) {
            $homebridge_home = '/home/pi/.homebridge';
        }
        @unlink($homebridge_home . '/cached/cachedAccessories');
        safe_exec('sudo service homebridge stop', 1);
        safe_exec('sudo service homebridge start', 1);
        sleep(5);
        include_once(DIR_MODULES . 'devices/devices.class.php');
        $dv = new devices();
        $dv->homebridgeSync(0, $force_refresh);
    }

    /**
     * FrontEnd
     *
     * Module frontend
     *
     * @access public
     */
    function usual(&$out)
    {
        if ($this->ajax) {
            $op = gr('op');
            if ($op == 'status') {
                $output = array();
                exec('sudo service homebridge status', $output);
                $result = implode("\n", $output);
                $result = str_replace('active (running)','<font color="green"><b>active (running)</b></font>',$result);
                $result = str_replace('inactive (dead)','<font color="red"><b>inactive (dead)</b></font>',$result);
                echo '<pre>' . $result . '</pre>';
                exit;
            }
        }
        $this->admin($out);
    }

    /**
     * Install
     *
     * Module installation routine
     *
     * @access private
     */
    function install($data = '')
    {
        parent::install();
    }
// --------------------------------------------------------------------
}
/*
*
* TW9kdWxlIGNyZWF0ZWQgRmViIDExLCAyMDE5IHVzaW5nIFNlcmdlIEouIHdpemFyZCAoQWN0aXZlVW5pdCBJbmMgd3d3LmFjdGl2ZXVuaXQuY29tKQ==
*
*/
