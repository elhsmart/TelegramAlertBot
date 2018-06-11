<?php

namespace Eugenia\Entities;

use Lifo\Daemon\Plugin;
use Lifo\Daemon\Plugin\PluginInterface;
use danog\MadelineProto\API as MadeApi;

class Message {

    public $to_id;
    public $from_id;

    public $message;
    public $status;
    public $author_id;

    public $is_tg;
    public $is_geo_tg;
    public $is_sms;
    public $is_call;

    public $viewed = false;
    public $answered = false;
    public $failed = false;

    public $is_sms_capable = false;
    public $is_call_capable = false;

    public $update_time;

    public $geo_message_id;
    public $geo_lat;
    public $geo_lng;
    public $geo_url;

    const STATUS_NEW = 1;
    const STATUS_PROCESSING = 2;
    const RETRY_COUNT = 2;

    const SMS_TIMEOUT = 180;
    const CALL_TIMEOUT = 180;
    const CHECK_TIMEOUT = 180;

    public $time_update;
    private $retry_count = 0;

    public static function createFromAlert($alert, $db) {
        $message = [
            'to_id' => $alert->to_id,
            'message' => $alert->message,
            'status' =>\Eugenia\Entities\Message::STATUS_NEW,
            'author' => $alert->author,
            'author_id' => $alert->author_id,
            'update_time' => time(),
            'retry_count' => 0,

            'viewed' => false,
            'answered' => false,
            'failed' => false
        ];        

        if($alert->geo_fwd_message_id) {
            $message['geo_message_id'] = $alert->geo_fwd_message_id;
            $message['geo_lat'] = $alert->geo_point_lat;
            $message['geo_lng'] = $alert->geo_point_lng;
            $message['from_id'] = $alert->from_id;
            $message['geo_url'] = $alert->geo_url;
        }

        return new self($message, $db);
    }

    public function __construct($message, $db) {
        $this->db = $db;

        $this->parse($message);
    }

    public function process($api) {
        if($this->viewed || $this->answered || $this->failed) {
            return true;
        }

        // Debug purposes - strict procesing only to particular user
        /* if($this->to_id->username != 'elhsmart') {
            return false;
        }*/

        $TGClient       = $api->getTelegramClient();

        // Send telegram sessage
        if($this->is_tg === null) {
            if($this->retry_count > Message::RETRY_COUNT) {
                $this->retry_count = 0;
                $this->is_tg = false;
                $this->update_time = time();
                $this->save();
            }

            $message = $this->message;

            $peer = [
                '_' => 'user',
                'id' => $this->to_id->user_id
            ];

            if(strlen($message) == 0) {
                $message = " Пожалуйста просмотрите чат";
            }

            $update = $TGClient->messages->sendMessage(['peer' => $peer, 'message' => "ALERT: " . $message]);    

            if($this->geo_message_id) {
                $geo_update = $TGClient->messages->forwardMessages([
                    'peer' => $peer,
                    'id' => [$this->geo_message_id],
                    'from_peer' => (array)$this->from_id,
                    'to_peer' => $peer
                ]);

                //geo updates is little bit different
                if($geo_update['_'] == 'updates') {
                    $geo_update = array_shift($geo_update['updates']);
                }

                $this->is_geo_tg = $geo_update['id'];
            }

            if($update['_'] == 'updateShortSentMessage' && $update['out'] == true) {
                $this->is_tg = $update['id'];
                $this->update_time = time();
                $this->save();
                return false;

            } else {
                $this->retry_count++;
                $this->save();
            }
        }

        if($this->is_sms === null && $this->is_sms_capable) {
            $message = $this->message;

            if(time() > $this->update_time + Message::SMS_TIMEOUT) {
                $this->update_time = time();
                $this->save();

                //Check if TG message is read
                if($this->viewed == false) {
                    //need to send SMS here
                    if($this->is_sms_capable) {
                        $phone = $this->to_id->phone;
                        if(strpos($this->to_id->phone, "+") === false) {
                            $phone = "+" . $this->to_id->phone;
                        }
                        $from_phone = $api->getPhoneNumber();
                        
                        if($this->geo_message_id) {
                            if(strlen($message) > 0) {
                                $message .= "\nGeo: " . $this->geo_url;
                            }
                        }

                        $TWClient = $api->getTwilioClient();
                        $smsMessage = $TWClient->messages
                            ->create($phone,
                                array(
                                    "body" => "ALERT: " . $message . "\nPlease visit AutoChat.",
                                    "from" => $from_phone
                                )
                        );
                        
                        $this->is_sms = $smsMessage->sid;
                        $this->save();
                        return false;

                    } 
                }
            }
        }

        if($this->is_call === null && $this->is_call_capable) {
            if(time() > $this->update_time + Message::CALL_TIMEOUT) {                
                $this->update_time = time();
                $this->save();

                if($this->viewed == false) {
                    
                    if($this->is_call_capable) {
                        $phone = $this->to_id->phone;
                        if(strpos($this->to_id->phone, "+") === false) {
                            $phone = "+" . $this->to_id->phone;
                        }

                        $from_phone =  $api->getNexmoPhoneNumber();

                        $NexmoClient = $api->getNexmoClient();
                        $call = $NexmoClient->calls()->create([
                            'to' => [[
                                'type' => 'phone',
                                'number' => str_replace("+", "", $phone)
                            ]],
                            'from' => [
                                'type' => 'phone',
                                'number' =>  str_replace("+", "", $from_phone)
                            ],
                            'answer_url' => [$api->getNexmoAnswerUrl()]
                        ]);
                        $this->is_call = $call->getId();
                        $this->save();
                        return false;
                    } 
                }
            }
        }

        if($this->viewed || $this->answered) {
            return true;
        }

        if(
            ($this->is_tg) &&
            (($this->is_sms_capable) ? $this->is_sms : true) && 
            (($this->is_call_capable) ? $this->is_call : true) && 
            $this->viewed === false && $this->answered === false
        ) {
            if(time() > $this->update_time + Message::CHECK_TIMEOUT) {
                $this->failed = true;

                $this->save();            
                return false;
            }
        }     
    }

    public function save() {
        $this->db->setNestedVal('messages', $this->getHash(), $this->serialize());
    }

    public function serialize() {
        $message = [
            'to_id' => $this->to_id,
            'message' => $this->message,
            'status' => $this->status,
            'author_id' => $this->author_id,
            'author' => $this->author,

            'is_tg' => $this->is_tg,
            'is_geo_tg' => $this->is_geo_tg,
            'is_sms' => $this->is_sms,
            'is_call' => $this->is_call,
            
            'is_sms_capable' => $this->is_sms_capable,
            'is_call_capable' => $this->is_call_capable,

            'update_time' => $this->update_time,
            'retry_count' => $this->retry_count,

            'viewed' => $this->viewed,
            'answered' => $this->answered,
            'failed' => $this->failed,

            'geo_message_id' => $this->geo_message_id,
            'geo_lat' => $this->geo_lat,
            'geo_lng' => $this->geo_lng,
            'geo_url' => $this->geo_url,
            'from_id' => $this->from_id
        ];

        return $message;
    }

    public function parse($message) {
        $this->to_id = $message['to_id'];
        $this->message = $message['message'];
        $this->author = $message['author'];
        $this->author_id = $message['author_id'];        
        $this->status = $message['status'];        

        if(isset($message['is_tg'])) {
            $this->is_tg = $message['is_tg'];  
        }

        if(isset($message['is_geo_tg'])) {
            $this->is_geo_tg = $message['is_geo_tg'];  
        }

        if(isset($message['is_sms'])) {
            $this->is_sms = $message['is_sms'];  
        }

        if(isset($message['is_call'])) {
            $this->is_call = $message['is_call'];  
        }

        if(isset($message['is_sms_capable'])) {
            $this->is_sms_capable = $message['is_sms_capable'];  
        }

        if(isset($message['is_call_capable'])) {
            $this->is_call_capable = $message['is_call_capable'];
        }

        if(isset($message['update_time'])) {
            $this->update_time = $message['update_time'];
        }

        if(isset($message['retry_count'])) {
            $this->retry_count = $message['retry_count'];
        }

        if(isset($message['viewed'])) {
            $this->viewed = $message['viewed'];
        }

        if(isset($message['answered'])) {
            $this->answered = $message['answered'];
        }

        if(isset($message['failed'])) {
            $this->failed = $message['failed'];
        }

        if(isset($message['from_id'])) {
            $this->from_id = $message['from_id'];
        }

        if(isset($message['geo_message_id'])) {
            $this->geo_message_id = $message['geo_message_id'];
        }

        if(isset($message['geo_lat'])) {
            $this->geo_lat = $message['geo_lat'];
        }

        if(isset($message['geo_lng'])) {
            $this->geo_lng = $message['geo_lng'];
        }

        if(isset($message['geo_url'])) {
            $this->geo_url = $message['geo_url'];
        }
    }

    public function getHash() {
        $to_id = $this->to_id;
        if(is_object($to_id)) {
            $to_id = (array)$to_id;
        }
        return md5($this->author_id . $to_id['user_id']);
    }

    public function drop() {
        $this->db->dropNestedVal('messages', $this->getHash());
        $this->db->save();
    }

    public function load() {
        $this->parse($this->db->getNestedVal('messages', $this->getHash()));
    }
}
