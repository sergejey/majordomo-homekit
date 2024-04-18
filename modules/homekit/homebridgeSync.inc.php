<?php
// thanks to https://github.com/cflurin/homebridge-mqtt

if (defined('DISABLE_SIMPLE_DEVICES') && DISABLE_SIMPLE_DEVICES == 1) return;

$debug_sync = 0;

$qry = "1";

if (isset($device_id) && $device_id != 0) {
    $qry .= " AND ID=" . $device_id;
}
$devices = SQLSelect("SELECT * FROM devices WHERE $qry");
$total = count($devices);
DebMes("Syncing devices (total: $total)", 'homebridge');
for ($i = 0; $i < $total; $i++) {

    if ($devices[$i]['LINKED_OBJECT'] == '') {
        continue;
    }
    $payload = array();
    $payload['name'] = $devices[$i]['LINKED_OBJECT'];


    if ($devices[$i]['SYSTEM_DEVICE'] || $devices[$i]['ARCHIVED']) {
        if ($debug_sync) {
            DebMes("HomeBridge.to_remove: " . json_encode($payload), 'homebridge');
        }
		addToOperationsQueue("homekit_queue", "remove", json_encode($payload, JSON_UNESCAPED_UNICODE));
        continue;
    }

    if ($force_refresh) {
        if ($debug_sync) {
            DebMes("HomeBridge.to_remove: " . json_encode($payload), 'homebridge');
        }
		addToOperationsQueue("homekit_queue", "remove", json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    $payload['service_name'] = processTitle($devices[$i]['TITLE']);
	$frombattery = false;

    switch ($devices[$i]['TYPE']) {
        case 'relay':
            $load_type = gg($devices[$i]['LINKED_OBJECT'] . '.loadType');
            if ($load_type == 'light') $payload['service'] = 'Lightbulb';
            elseif ($load_type == 'vent') $payload['service'] = 'Fan';
            elseif ($load_type == 'switch') $payload['service'] = 'Switch';
            else                          $payload['service'] = 'Outlet';
            addToOperationsQueue("homekit_queue", "add", json_encode($payload, JSON_UNESCAPED_UNICODE));

            $payload['characteristic'] = 'On';
            if (gg($devices[$i]['LINKED_OBJECT'] . '.status')) {
                $payload['value'] = 1;
            } else {
                $payload['value'] = 0;
            }
            addToOperationsQueue("homekit_queue", "set", json_encode($payload, JSON_UNESCAPED_UNICODE));
            break;
        case 'openable':
            $open_type = gg($devices[$i]['LINKED_OBJECT'] . '.openType');
            if ($open_type == 'gates') {
                $payload['service'] = 'GarageDoorOpener';
            } elseif ($open_type == 'door') {
                $payload['service'] = 'Door';
            } elseif ($open_type == 'window') {
                $payload['service'] = 'Window';
            } elseif ($open_type == 'curtains') {
                $payload['service'] = 'WindowCovering';
            } elseif ($open_type == 'shutters') {
                $payload['service'] = 'WindowCovering';
            }
            if (isset($payload['service'])) {
                addToOperationsQueue("homekit_queue", "add", json_encode($payload, JSON_UNESCAPED_UNICODE));
                if ($open_type == 'gates') {
                    $payload['characteristic'] = 'CurrentDoorState';
                    if (gg($devices[$i]['LINKED_OBJECT'] . '.status')) {
                        $payload['value'] = 1;
                    } else {
                        $payload['value'] = 0;
                    }
                    addToOperationsQueue("homekit_queue", "set", json_encode($payload, JSON_UNESCAPED_UNICODE));
                    $payload['characteristic'] = 'TargetDoorState';
                    $payload['value'] = "1";
                    addToOperationsQueue("homekit_queue", "set", json_encode($payload, JSON_UNESCAPED_UNICODE));
                } elseif ($open_type == 'door' || $open_type == 'window' || $open_type == 'curtains' || $open_type == 'shutters') {
                    $payload['characteristic'] = 'CurrentPosition';
                    if (gg($devices[$i]['LINKED_OBJECT'] . '.status')) {
                        $payload['value'] = "1"; // открыто на 0% (закрыто)
                    } else {
                        $payload['value'] = "100"; // открыто на 100% (открыто)
                    }
                    addToOperationsQueue("homekit_queue", "set", json_encode($payload, JSON_UNESCAPED_UNICODE));
                    $payload['characteristic'] = 'TargetPosition';
                    addToOperationsQueue("homekit_queue", "set", json_encode($payload, JSON_UNESCAPED_UNICODE));
                    $payload['characteristic'] = 'PositionState';
                    $payload['value'] = "2"; //0 - "Закрывается" 1 - "Открывается" 2 - нет отображения
                    addToOperationsQueue("homekit_queue", "set", json_encode($payload, JSON_UNESCAPED_UNICODE));
                }
            }
            break;
        case 'sensor_temp':
            $payload['service'] = 'TemperatureSensor';
            addToOperationsQueue("homekit_queue", "add", json_encode($payload, JSON_UNESCAPED_UNICODE));

            $payload['characteristic'] = 'CurrentTemperature';
            $payload['value'] = gg($devices[$i]['LINKED_OBJECT'] . '.value');
            addToOperationsQueue("homekit_queue", "set", json_encode($payload, JSON_UNESCAPED_UNICODE));
			$frombattery = true;
            break;
        case 'sensor_humidity':
            $payload['service'] = 'HumiditySensor';
            addToOperationsQueue("homekit_queue", "add", json_encode($payload, JSON_UNESCAPED_UNICODE));

            $payload['characteristic'] = 'CurrentRelativeHumidity';
            $payload['value'] = gg($devices[$i]['LINKED_OBJECT'] . '.value');
            addToOperationsQueue("homekit_queue", "set", json_encode($payload, JSON_UNESCAPED_UNICODE));
			$frombattery = true;
            break;
        case 'sensor_co2':
            $payload['service'] = 'CarbonDioxideSensor';
            addToOperationsQueue("homekit_queue", "add", json_encode($payload, JSON_UNESCAPED_UNICODE));

            $payload['characteristic'] = 'CarbonDioxideLevel';
            $payload['value'] = gg($devices[$i]['LINKED_OBJECT'] . '.value');
            addToOperationsQueue("homekit_queue", "set", json_encode($payload, JSON_UNESCAPED_UNICODE));

            $payload['characteristic'] = 'CarbonDioxideDetected';
            $payload['value'] = "0";
            addToOperationsQueue("homekit_queue", "set", json_encode($payload, JSON_UNESCAPED_UNICODE));
			$frombattery = true;
            break;

        case 'sensor_moisture':
            //todo
            break;

        case 'sensor_radiation':
            //todo
            break;

        case 'vacuum':
            //todo
            break;

        case 'media':
            //todo
            break;

        case 'tv':
            //todo
            break;

        case 'motion':
            $payload['service'] = 'MotionSensor';
            addToOperationsQueue("homekit_queue", "add", json_encode($payload, JSON_UNESCAPED_UNICODE));

            $payload['characteristic'] = 'MotionDetected';
            $payload['value'] = (int)gg($devices[$i]['LINKED_OBJECT'] . '.status');
            addToOperationsQueue("homekit_queue", "set", json_encode($payload, JSON_UNESCAPED_UNICODE));
			$frombattery = true;
            break;
        case 'smoke':
            $payload['service'] = 'SmokeSensor';
            addToOperationsQueue("homekit_queue", "add", json_encode($payload, JSON_UNESCAPED_UNICODE));

            $payload['characteristic'] = 'SmokeDetected';
            $payload['value'] = (int)gg($devices[$i]['LINKED_OBJECT'] . '.status');
            addToOperationsQueue("homekit_queue", "set", json_encode($payload, JSON_UNESCAPED_UNICODE));
			$frombattery = true;
            break;
        case 'leak':
            $payload['service'] = 'LeakSensor';
            addToOperationsQueue("homekit_queue", "add", json_encode($payload, JSON_UNESCAPED_UNICODE));

            $payload['characteristic'] = 'LeakDetected';
            $payload['value'] = (int)gg($devices[$i]['LINKED_OBJECT'] . '.status');
            addToOperationsQueue("homekit_queue", "set", json_encode($payload, JSON_UNESCAPED_UNICODE));
			$frombattery = true;
            break;
        case 'button':
            $payload['service'] = 'Switch';
            addToOperationsQueue("homekit_queue", "add", json_encode($payload, JSON_UNESCAPED_UNICODE));
            break;
        case 'sensor_light':
            $payload['service'] = 'LightSensor';
            addToOperationsQueue("homekit_queue", "add", json_encode($payload, JSON_UNESCAPED_UNICODE));

            $payload['characteristic'] = 'CurrentAmbientLightLevel';
            $payload['value'] = (int)gg($devices[$i]['LINKED_OBJECT'] . '.value');
            addToOperationsQueue("homekit_queue", "set", json_encode($payload, JSON_UNESCAPED_UNICODE));
			$frombattery = true;
            break;
        case 'openclose':
            $payload['service'] = 'ContactSensor';
            addToOperationsQueue("homekit_queue", "add", json_encode($payload, JSON_UNESCAPED_UNICODE));

            $payload['characteristic'] = 'ContactSensorState';
            $payload['value'] = (int)gg($devices[$i]['LINKED_OBJECT'] . '.ncno') == 'nc' ? 1 - gg($devices[$i]['LINKED_OBJECT'] . '.status') : gg($devices[$i]['LINKED_OBJECT'] . '.status');
            addToOperationsQueue("homekit_queue", "set", json_encode($payload, JSON_UNESCAPED_UNICODE));
			$frombattery = true;		           
			break;
        case 'dimmer':
            $payload['service'] = 'Lightbulb';
            addToOperationsQueue("homekit_queue", "add", json_encode($payload, JSON_UNESCAPED_UNICODE));
            $payload['characteristic'] = 'On';
            if (gg($devices[$i]['LINKED_OBJECT'] . '.status')) {
                $payload['value'] = 1;
            } else {
                $payload['value'] = 0;
            }
            addToOperationsQueue("homekit_queue", "set", json_encode($payload, JSON_UNESCAPED_UNICODE));
            $payload['characteristic'] = 'Brightness';
            $payload['value'] = (int)gg($devices[$i]['LINKED_OBJECT'] . '.level');
            addToOperationsQueue("homekit_queue", "set", json_encode($payload, JSON_UNESCAPED_UNICODE));
            break;
        case 'rgb':
            $payload['service'] = 'Lightbulb';

            addToOperationsQueue("homekit_queue", "add", json_encode($payload, JSON_UNESCAPED_UNICODE));

            $payload['characteristic'] = 'On';
            if (gg($devices[$i]['LINKED_OBJECT'] . '.status')) {
                $payload['value'] = 1;
            } else {
                $payload['value'] = 0;
            }
            addToOperationsQueue("homekit_queue", "set", json_encode($payload, JSON_UNESCAPED_UNICODE));

            $payload['characteristic'] = 'Hue';
            $payload['value'] = gg($devices[$i]['LINKED_OBJECT'] . '.hue');
            addToOperationsQueue("homekit_queue", "set", json_encode($payload, JSON_UNESCAPED_UNICODE));

            $payload['characteristic'] = 'Saturation';
            $payload['value'] = gg($devices[$i]['LINKED_OBJECT'] . '.saturation');
            addToOperationsQueue("homekit_queue", "set", json_encode($payload, JSON_UNESCAPED_UNICODE));

            $payload['characteristic'] = 'Brightness';
            $payload['value'] = gg($devices[$i]['LINKED_OBJECT'] . '.brightness');
            addToOperationsQueue("homekit_queue", "set", json_encode($payload, JSON_UNESCAPED_UNICODE));
            break;
        case 'ledlamp':
            $payload['service'] = 'Lightbulb';
            addToOperationsQueue("homekit_queue", "add", json_encode($payload, JSON_UNESCAPED_UNICODE));

            $payload['characteristic'] = 'On';
            if (gg($devices[$i]['LINKED_OBJECT'] . '.status')) {
                $payload['value'] = 1;
            } else {
                $payload['value'] = 0;
            }
            addToOperationsQueue("homekit_queue", "set", json_encode($payload, JSON_UNESCAPED_UNICODE));
            $payload['characteristic'] = 'Brightness';
            $payload['value'] = gg($devices[$i]['LINKED_OBJECT'] . '.brightness');
            addToOperationsQueue("homekit_queue", "set", json_encode($payload, JSON_UNESCAPED_UNICODE));
            break;

        case 'thermostat':
            $payload['service'] = 'Thermostat';
			$payload['TargetTemperature']['minStep'] = 0.5;
            addToOperationsQueue("homekit_queue", "add", json_encode($payload, JSON_UNESCAPED_UNICODE));
			unset($payload['TargetTemperature']);

            $payload['characteristic'] = 'CurrentTemperature';
            $payload['value'] = gg($devices[$i]['LINKED_OBJECT'] . '.value');;
            addToOperationsQueue("homekit_queue", "set", json_encode($payload, JSON_UNESCAPED_UNICODE));

            $payload['characteristic'] = 'TargetTemperature';
            $payload['value'] = gg($devices[$i]['LINKED_OBJECT'] . '.currentTargetValue');
            addToOperationsQueue("homekit_queue", "set", json_encode($payload, JSON_UNESCAPED_UNICODE));

			$payload['characteristic'] = 'CurrentHeatingCoolingState';
			$disabled = gg($$devices[$i]['LINKED_OBJECT'] . '.disabled');
			if (!$disabled) {
				$payload['value'] = gg($devices[$i]['LINKED_OBJECT'] . '.relay_status'); //off = 0, heat = 1, cool = 2
			} else {
				$payload['value'] = 0;
			}
			addToOperationsQueue("homekit_queue", "set", json_encode($payload, JSON_UNESCAPED_UNICODE));
			$payload['characteristic'] = 'TargetHeatingCoolingState';
			if (!$disabled) {
				$payload['value'] = 3; //off = 0, heat = 1, and cool = 2, auto = 3
			} else {
				$payload['value'] = 0;
			}
            addToOperationsQueue("homekit_queue", "set", json_encode($payload, JSON_UNESCAPED_UNICODE));
            //TargetHeatingCoolingState
            //CoolingThresholdTemperature
            //HeatingThresholdTemperature


            break;
			
		case 'ac':
            $payload['service'] = 'Thermostat';
			$payload['TargetTemperature']['minStep'] = 0.5;
            addToOperationsQueue("homekit_queue", "add", json_encode($payload, JSON_UNESCAPED_UNICODE));
			unset($payload['TargetTemperature']);

            $payload['characteristic'] = 'CurrentTemperature';
            $payload['value'] = gg($devices[$i]['LINKED_OBJECT'] . '.value');
            addToOperationsQueue("homekit_queue", "set", json_encode($payload, JSON_UNESCAPED_UNICODE));

            $payload['characteristic'] = 'TargetTemperature';
            $payload['value'] = gg($devices[$i]['LINKED_OBJECT'] . '.currentTargetValue');
            addToOperationsQueue("homekit_queue", "set", json_encode($payload, JSON_UNESCAPED_UNICODE));

			$ac_mode = gg($devices[$i]['LINKED_OBJECT'] . '.thermostat');
			$payload['characteristic'] = 'CurrentHeatingCoolingState';
			switch ($ac_mode) {//off = 0, heat = 1, cool = 2
				case 'off':
					$payload['value'] = 0;
					break;
				case 'heat':
					$payload['value'] = 1;
					break;
				case 'cool':
					$payload['value'] = 2;
					break;
				default:
					$payload['value'] = 0;
					break;
			}
			addToOperationsQueue("homekit_queue", "set", json_encode($payload, JSON_UNESCAPED_UNICODE));
			$payload['characteristic'] = 'TargetHeatingCoolingState';
			switch ($ac_mode) {//off = 0, heat = 1, cool = 2, auto = 3
				case 'off':
					$payload['value'] = 0;
					break;
				case 'heat':
					$payload['value'] = 1;
					break;
				case 'cool':
					$payload['value'] = 2;
					break;
				case 'auto':
					$payload['value'] = 3;
					break;
				default:
					$payload['value'] = 3;
					break;
			}
            addToOperationsQueue("homekit_queue", "set", json_encode($payload, JSON_UNESCAPED_UNICODE));



            break;
			
        case 'camera':
            /*
            $cameraUsername = gg($devices[$i]['LINKED_OBJECT'].'.cameraUsername');
            $cameraPassword = gg($devices[$i]['LINKED_OBJECT'].'.cameraPassword');
            $snapshot_url = gg($devices[$i]['LINKED_OBJECT'].'.snapshotURL');
            $stream_url = gg($devices[$i]['LINKED_OBJECT'].'.streamURL');
            $stream_url_hq = gg($devices[$i]['LINKED_OBJECT'].'.streamURL_HQ');
            if ($snapshot_url) {
               $stream_url=$snapshot_url;
            } elseif (!$stream_url && $stream_url_hq) {
               $stream_url = $stream_url_hq;
            }
            $thumb_params ='';
            $thumb_params.= 'username="' . $cameraUsername . '" password="' . $cameraPassword . '"';
            $thumb_params.= ' width="1024"';
            $thumb_params.= ' url="' . $stream_url . '"';
            $streamTransport = gg($devices[$i]['LINKED_OBJECT'].'.streamTransport');
            if ($streamTransport!='auto' && $streamTransport!='') {
               $thumb_params.= ' transport="'.$streamTransport.'"';
            }
            $body = '[#module name="thumb" '. $thumb_params. '#]';
            $body = processTitle($body, $this);
            if (preg_match('/img src="(.+?)"/is',$body,$m)) {
               $snapshotPreviewURL=$m[1];
               $snapshotPreviewURL = preg_replace('/&w=(\d+?)/','', $snapshotPreviewURL);
               $snapshotPreviewURL = preg_replace('/&h=(\d+?)/','', $snapshotPreviewURL);
            } else {
               $snapshotPreviewURL='';
            }
            $snapshotPreviewURL='http://'.getLocalIP().$snapshotPreviewURL;

            $payload['service']='CameraRTPStreamManagement';
            sg('HomeBridge.to_add',json_encode($payload));

            $payload['characteristic'] = 'SupportedVideoStreamConfiguration';
            $payload['value']='';
            addToOperationsQueue("homekit_queue", "set", json_encode($payload, JSON_UNESCAPED_UNICODE));

            $payload['characteristic'] = 'SupportedAudioStreamConfiguration';
            $payload['value']='';
            addToOperationsQueue("homekit_queue", "set", json_encode($payload, JSON_UNESCAPED_UNICODE));

            $payload['characteristic'] = 'SupportedRTPConfiguration';
            $payload['value']='';
            addToOperationsQueue("homekit_queue", "set", json_encode($payload, JSON_UNESCAPED_UNICODE));

            $payload['characteristic'] = 'SelectedRTPStreamConfiguration';
            $payload['value']='';
            addToOperationsQueue("homekit_queue", "set", json_encode($payload, JSON_UNESCAPED_UNICODE));

            $payload['characteristic'] = 'StreamingStatus';
            $payload['value']='';
            addToOperationsQueue("homekit_queue", "set", json_encode($payload, JSON_UNESCAPED_UNICODE));

            $payload['characteristic'] = 'SetupEndpoints';
            $payload['value']='';
            addToOperationsQueue("homekit_queue", "set", json_encode($payload, JSON_UNESCAPED_UNICODE));
   */
            break;
        /*
        case 'sensor_battery':
           $payload['service']='BatteryService';
           sg('HomeBridge.to_add',json_encode($payload));
           // Characteristic.BatteryLevel;
           // Characteristic.ChargingState; 0 - NOT_CHARGING, 1 - CHARGING, 2 - NOT_CHARGEABLE
           // Characteristic.StatusLowBattery;
           $payload['characteristic'] = 'BatteryLevel';
           $payload['value']=gg($devices[$i]['LINKED_OBJECT'].'.value');
           addToOperationsQueue("homekit_queue", "set", json_encode($payload, JSON_UNESCAPED_UNICODE));

           $payload['characteristic'] = 'ChargingState';
           $payload['value']=2;
           addToOperationsQueue("homekit_queue", "set", json_encode($payload, JSON_UNESCAPED_UNICODE));

           $payload['characteristic'] = 'StatusLowBattery';
           $payload['value']=gg($devices[$i]['LINKED_OBJECT'].'.normalValue') ? 0 : 1;
           addToOperationsQueue("homekit_queue", "set", json_encode($payload, JSON_UNESCAPED_UNICODE));
           break;
        */
        default:
            $addon_path = DIR_MODULES . '/devices/addons/' . $devices[$i]['TYPE'] . '_homebridgeSync.php';
            if (file_exists($addon_path)) {
                require($addon_path);
            }
    }
	if ($frombattery && $devices[$i]['LINKED_OBJECT'] . '.batteryOperated'){
		$payload['characteristic'] = 'StatusLowBattery';
		$payload['value'] = gg($devices[$i]['LINKED_OBJECT'] . '.batteryWarning') == 1 ? 1 : 0;
		addToOperationsQueue("homekit_queue", "set", json_encode($payload, JSON_UNESCAPED_UNICODE));
	}
}
addToOperationsQueue("homekit_queue", "get", '{"name": "*"}');
