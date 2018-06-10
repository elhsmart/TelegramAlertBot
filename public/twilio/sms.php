<?php
require_once '../../vendor/autoload.php';
require_once './libs/dumprequest.php';

use Twilio\Twiml;

$response   = new Twiml();
$response->hangup();

$date = new DateTime();

(new DumpHTTPRequestToFile)->execute("../../logs/sms-".$date->format('Y-m-d H:i:sP') . ".txt");

header('Content-Type:text/xml');
echo $response;