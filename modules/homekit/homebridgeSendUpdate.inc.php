<?php

/*if (!isset($params['NEW_VALUE']) or !isset($params['OLD_VALUE'])){
	registerError('homebridge', 'Отсутствуют необходимые переменные');
	DebMes($params, 'homebridge');
}*/
if ($params['NEW_VALUE'] == $params['OLD_VALUE']) return;

$payload = array();
$payload['name'] = $device1['LINKED_OBJECT'];
$payload['service_name'] = $device1['TITLE'];

$payload2 = array();
$payload2['name'] = $device1['LINKED_OBJECT'];
$payload2['service_name'] = $device1['TITLE'];

//DebMes("Homebridge Update ".$device1['LINKED_OBJECT']." (".$device1['TYPE']."): ".gg($device1['LINKED_OBJECT'] . '.status')." / ".gg($device1['LINKED_OBJECT'] . '.value'),'homebridge');

switch ($device1['TYPE']) {
    case 'relay':
        $load_type = gg($device1['LINKED_OBJECT'] . '.loadType');
        if ($load_type == 'light') $payload['service'] = 'Lightbulb';
        elseif ($load_type == 'vent') $payload['service'] = 'Fan';
        elseif ($load_type == 'switch') $payload['service'] = 'Switch';
        else                          $payload['service'] = 'Outlet';
        $payload['characteristic'] = 'On';
        if ($params['NEW_VALUE']) {
            $payload['value'] = 1;
        } else {
            $payload['value'] = 0;
        }
        break;
    case 'sensor_temp':
        $payload['service'] = 'TemperatureSensor';
        $payload['characteristic'] = 'CurrentTemperature';
        $payload['value'] = $params['NEW_VALUE'];
        break;
    case 'sensor_co2':
        $payload['service'] = 'CarbonDioxideSensor';
        $payload['characteristic'] = 'CarbonDioxideLevel';
        $payload['value'] = $params['NEW_VALUE'];

        $max_level = gg($device1['LINKED_OBJECT'] . '.maxValue');
        if (!$max_level) {
            $max_level = 1200;
        }
        $payload2['service'] = 'CarbonDioxideSensor';
        $payload2['characteristic'] = 'CarbonDioxideDetected';
        if ($payload['value'] >= $max_level) {
            $payload2['value'] = "1";
        } else {
            $payload2['value'] = "0";
        }
        break;
    case 'sensor_humidity':
        $payload['service'] = 'HumiditySensor';
        $payload['characteristic'] = 'CurrentRelativeHumidity';
        $payload['value'] = $params['NEW_VALUE'];
        break;
    case 'motion':
        $payload['service'] = 'MotionSensor';
        $payload['characteristic'] = 'MotionDetected';
        if ($params['NEW_VALUE']) {
            $payload['value'] = 1;
        } else {
            $payload['value'] = 0;
        }
        break;
    case 'smoke':
        $payload['service'] = 'SmokeSensor';
        $payload['characteristic'] = 'SmokeDetected';
        if ($params['NEW_VALUE']) {
            $payload['value'] = 1;
        } else {
            $payload['value'] = 0;
        }
        break;
    case 'leak':
        $payload['service'] = 'LeakSensor';
        $payload['characteristic'] = 'LeakDetected';
        if ($params['NEW_VALUE']) {
            $payload['value'] = 1;
        } else {
            $payload['value'] = 0;
        }
        break;
    case 'sensor_light':
        $payload['service'] = 'LightSensor';
        $payload['characteristic'] = 'CurrentAmbientLightLevel';
        $payload['value'] = $params['NEW_VALUE'];
        break;
    case 'openclose':
        $payload['service'] = 'ContactSensor';
        $payload['characteristic'] = 'ContactSensorState';
        $nc = gg($device1['LINKED_OBJECT'] . '.ncno') == 'nc';
        $payload['value'] = $nc ? 1 - $params['NEW_VALUE'] : $params['NEW_VALUE'];
        break;
    case 'openable':
        $open_type = gg($device1['LINKED_OBJECT'] . '.openType');
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
            if ($open_type == 'gates') {
                if (gg($device1['LINKED_OBJECT'] . '.status')) {
                    $payload['value'] = "1";
                } else {
                    $payload['value'] = "0";
                }

                $payload['characteristic'] = 'CurrentDoorState';
                if ($debug_sync) {
                    DebMes("MQTT to_set : " . json_encode($payload), 'homebridge');
                }
            } elseif ($open_type == 'door' || $open_type == 'window' || $open_type == 'curtains' || $open_type == 'shutters') {
                $payload['characteristic'] = 'CurrentPosition';
                if (gg($device1['LINKED_OBJECT'] . '.status')) {
                    $payload['value'] = "0";
                } else {
                    $payload['value'] = "100";
                }
                if ($debug_sync) {
                    DebMes("MQTT to_set : " . json_encode($payload, JSON_UNESCAPED_UNICODE), 'homebridge');
                }
				addToOperationsQueue("homekit_queue", "set", json_encode($payload, JSON_UNESCAPED_UNICODE));
                $payload['characteristic'] = 'TargetPosition';
            }
        }
        break;
    case 'rgb':
        $payload['service'] = 'Lightbulb';

        $payload['characteristic'] = 'On';
        if (gg($device1['LINKED_OBJECT'] . '.status')) {
            $payload['value'] = 1;
        } else {
            $payload['value'] = 0;
        }
		addToOperationsQueue("homekit_queue", "set", json_encode($payload, JSON_UNESCAPED_UNICODE));

        $payload['characteristic'] = 'Hue';
        $payload['value'] = gg($device1['LINKED_OBJECT'] . '.hue');
		addToOperationsQueue("homekit_queue", "set", json_encode($payload, JSON_UNESCAPED_UNICODE));

        $payload['characteristic'] = 'Saturation';
        $payload['value'] = gg($device1['LINKED_OBJECT'] . '.saturation');
        addToOperationsQueue("homekit_queue", "set", json_encode($payload, JSON_UNESCAPED_UNICODE));

        $payload['characteristic'] = 'Brightness';
        $payload['value'] = gg($device1['LINKED_OBJECT'] . '.brightness');
        break;
    case 'ledlamp':
        $payload['service'] = 'Lightbulb';

        $payload['characteristic'] = 'On';
        if (gg($device1['LINKED_OBJECT'] . '.status')) {
            $payload['value'] = true;
        } else {
            $payload['value'] = false;
        }
        addToOperationsQueue("homekit_queue", "set", json_encode($payload, JSON_UNESCAPED_UNICODE));


        $payload['characteristic'] = 'Brightness';
        $payload['value'] = gg($device1['LINKED_OBJECT'] . '.brightness');
        break;

    case 'thermostat':
		$payload['service'] = 'Thermostat';
        $payload['characteristic'] = 'CurrentTemperature';
        $payload['value'] = gg($device1['LINKED_OBJECT'] . '.value');
        addToOperationsQueue("homekit_queue", "set", json_encode($payload, JSON_UNESCAPED_UNICODE));

        $payload['characteristic'] = 'TargetTemperature';
        $payload['value'] = gg($device1['LINKED_OBJECT'] . '.currentTargetValue');
        addToOperationsQueue("homekit_queue", "set", json_encode($payload, JSON_UNESCAPED_UNICODE));
        $payload['characteristic'] = 'CurrentHeatingCoolingState';
		$disabled = gg($device1['LINKED_OBJECT'] . '.disabled');
        if (!$disabled) {
            $payload['value'] = gg($device1['LINKED_OBJECT'] . '.relay_status'); //off = 0, heat = 1, cool = 2
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
        break;
		
	case 'ac':
		$payload['service'] = 'Thermostat';
        $payload['characteristic'] = 'CurrentTemperature';
        $payload['value'] = gg($device1['LINKED_OBJECT'] . '.value');
        addToOperationsQueue("homekit_queue", "set", json_encode($payload, JSON_UNESCAPED_UNICODE));

        $payload['characteristic'] = 'TargetTemperature';
        $payload['value'] = gg($device1['LINKED_OBJECT'] . '.currentTargetValue');
        addToOperationsQueue("homekit_queue", "set", json_encode($payload, JSON_UNESCAPED_UNICODE));
		$ac_mode = gg($device1['LINKED_OBJECT'] . '.thermostat');
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
        break;
    /*
    case 'sensor_battery':
       $payload['service']='BatteryService';
       addToOperationsQueue("homekit_queue", "add", json_encode($payload, JSON_UNESCAPED_UNICODE));
       // Characteristic.BatteryLevel;
       // Characteristic.ChargingState; 0 - NOT_CHARGING, 1 - CHARGING, 2 - NOT_CHARGEABLE
       // Characteristic.StatusLowBattery;
       $payload['characteristic'] = 'BatteryLevel';
       $payload['value']=gg($device1['LINKED_OBJECT'].'.value');
       addToOperationsQueue("homekit_queue", "set", json_encode($payload, JSON_UNESCAPED_UNICODE));

       $payload['characteristic'] = 'ChargingState';
       $payload['value']=2;
       addToOperationsQueue("homekit_queue", "set", json_encode($payload, JSON_UNESCAPED_UNICODE));

       $payload['characteristic'] = 'StatusLowBattery';
       $payload['value']=gg($device1['LINKED_OBJECT'].'.normalValue') ? 0 : 1;
       addToOperationsQueue("homekit_queue", "set", json_encode($payload, JSON_UNESCAPED_UNICODE));

       break;
    */
    default:
        $addon_path = DIR_MODULES . 'devices/addons/' . $device1['TYPE'] . '_homebridgeSendUpdate.php';
        if (file_exists($addon_path)) {
            require($addon_path);
        }
		else if(isset($batteryWarning)){
			unset($batteryWarning);
		}
}
if (isset($payload['service']) && !isset($batteryWarning)) {
    addToOperationsQueue("homekit_queue", "set", json_encode($payload, JSON_UNESCAPED_UNICODE));
	
}
if (isset($payload2['service']) && !isset($batteryWarning)) {
    addToOperationsQueue("homekit_queue", "set", json_encode($payload2, JSON_UNESCAPED_UNICODE));
}
if (isset($batteryWarning)){
	$payload['characteristic'] = 'StatusLowBattery';
	$payload['value'] = $batteryWarning == 1 ? 1 : 0;
	addToOperationsQueue("homekit_queue", "set", json_encode($payload, JSON_UNESCAPED_UNICODE));
}

