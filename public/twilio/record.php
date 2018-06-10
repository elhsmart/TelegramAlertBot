<?php
require_once '../../vendor/autoload.php';

use Twilio\Twiml;

$res = new \Twilio\Twiml();
    $res->say('Hello.');
    $res->record();
    $res->hangup();

$input = date('Y-m-d H:i:s ', time()) . json_encode($request->getParsedBody()) . "\n";
file_put_contents("/tmp/ltl_ping", file_get_contents('/tmp/ltl_ping') . $input);

header('Content-Type:text/xml');

echo $response;