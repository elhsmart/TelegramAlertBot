<?php
require_once '../../vendor/autoload.php';
require_once './libs/dumprequest.php';

use Twilio\Twiml;

$response   = new Twiml();
$response->record();
$response->hangup();

$date = new DateTime();

(new DumpHTTPRequestToFile)->execute("../../logs/speech-".$date->format('Y-m-d H:i:sP') . ".txt");

$WordToDigitsMap = [
    'one'   => 1,
    'two'   => 2,
    'too'   => 2,
    'three' => 3,
    'four'  => 4,
    'five'  => 5,
    'six'   => 6,
    'seven' => 7,
    'eight' => 8,
    'nine'  => 9,
    'zero'  => 0
];

$code = [];

$SpeechResult   = $_POST['SpeechResult'];

$SpeechResult   = strtolower($SpeechResult);
$codeStart      = @array_shift(explode("once again", $SpeechResult));

$codeString     = @trim(array_pop(explode("your code is ", $codeStart)));
$codeArray      = explode(" ", $codeString);


foreach($codeArray as $key => $val) {
    $val = preg_replace('/[^A-Za-z0-9\-]/', '', $val);
    if(empty($val) && strlen($val) == 0) {
        continue;
    }
    if(!is_numeric($val)) {
        array_push($code, intval($WordToDigitsMap[$val]));
    } else {
        array_push($code, intval($val));
    }
}

file_put_contents("../../logs/login_cdde", implode("", $code));

header('Content-Type:text/xml');
echo $response;