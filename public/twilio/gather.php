<?php
require_once '../../vendor/autoload.php';
require_once '../../src/Eugenia/Config.php';

use Twilio\Twiml;

$response   = new Twiml();
$gather     = $response->gather([
    'action' => $config_twilio_speech_url,
    'method' => 'POST',
    'input' => 'speech',
    'timeout' => 30
]);
$gather->say('Okay.');
$response->record();

header('Content-Type:text/xml');

echo $response;