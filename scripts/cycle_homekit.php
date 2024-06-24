<?php
chdir(dirname(__FILE__) . '/../');
include_once("./config.php");
include_once("./lib/loader.php");
include_once("./lib/threads.php");
set_time_limit(0);
include_once("./load_settings.php");
include_once(DIR_MODULES . "control_modules/control_modules.class.php");
$ctl = new control_modules();

include_once(ROOT . "3rdparty/phpmqtt/phpMQTT.php");
include_once(DIR_MODULES . 'homekit/homekit.class.php');
$homekit_module = new homekit();
$homekit_module->getConfig();

echo date("H:i:s") . " running " . basename(__FILE__) . PHP_EOL;

$client_name = "MajorDoMo HomeKit";
$client_name = $client_name . ' (#' . uniqid() . ')';

if ($homekit_module->config['MQTT_AUTH']) {
    $username = $homekit_module->config['MQTT_USERNAME'];
    $password = $homekit_module->config['MQTT_PASSWORD'];
}

$host = 'localhost';

if (isset($homekit_module->config['MQTT_HOST'])) {
    $host = $homekit_module->config['MQTT_HOST'];
}

if (isset($homekit_module->config['MQTT_PORT'])) {
    $port = $homekit_module->config['MQTT_PORT'];
} else {
    $port = 1883;
}

if (isset($homekit_module->config['MQTT_QUERY'])) {
    $query = $homekit_module->config['MQTT_QUERY'];
} else {
    $query = 'homebridge';
}

$mqtt_client = new Bluerhinos\phpMQTT($host, $port, $client_name);

if ($homekit_module->config['MQTT_AUTH']) {
    $connect = $mqtt_client->connect(true, NULL, $username, $password);
    if (!$connect) {
        exit(1);
    }
} else {
    $connect = $mqtt_client->connect();
    if (!$connect) {
        exit(1);
    }
}
echo "Subscribe to ".$query."/from/#".PHP_EOL;
$topic[$query."/from/#"] = array("qos"=>0, "function"=>"procmsg"); 
$mqtt_client->subscribe($topic, 0);
$previousMillis = 0;
$modehomebridge = false;
$modetime = 0;

$force_refresh = 0;
include(DIR_MODULES . 'homekit/homebridgeSync.inc.php');

while ($mqtt_client->proc()) {
    $queue = checkOperationsQueue('homekit_queue');
    foreach ($queue as $mqtt_data) {
		if($mqtt_data['DATANAME'] == 'get' && $mqtt_data['DATAVALUE'] == '{"name": "*"}'){
			$modehomebridge = true;
			$modetime = round(microtime(true) * 10000);
		}
        $topic = $query."/to/".$mqtt_data['DATANAME'];
        $value = $mqtt_data['DATAVALUE'];
        $qos = 0;
        $retain = 0;
        if ($topic != '') {
			echo date("H:i:s")." Send data to ".$mqtt_data['DATANAME'].": ".$value.PHP_EOL;
            $mqtt_client->publish($topic, $value, $qos, $retain);
        }
    }

    $currentMillis = round(microtime(true) * 10000);
	
	if($modehomebridge && $currentMillis - $modetime > 150000){
		$modehomebridge = false;
	}

    if ($currentMillis - $previousMillis > 100000) {
        $previousMillis = $currentMillis;
        setGlobal((str_replace('.php', '', basename(__FILE__))) . 'Run', time(), 1);
        if (file_exists('./reboot') || isset($_GET['onetime'])) {
            $mqtt_client->close();
            exit;
        }
    }
}

$mqtt_client->close();

function procmsg($topic, $msg)
{
	global $query;
	global $modehomebridge;
	$length = strlen($query."/from/");
	$topic = substr($topic, $length);
	echo date("H:i:s")." Receive data from ". $topic.": ".$msg.PHP_EOL;
	require(DIR_MODULES.'homekit/processHomebridgeMQTT.inc.php');
}

DebMes("Unexpected close of cycle: " . basename(__FILE__));
