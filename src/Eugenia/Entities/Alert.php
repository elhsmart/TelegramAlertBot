<?php

namespace Eugenia\Entities;

use Lifo\Daemon\Plugin;
use Lifo\Daemon\Plugin\PluginInterface;
use danog\MadelineProto\API as MadeApi;

class Alert {

    public $to_id;
    public $message;
    public $author;
    public $author_id;
    public $mention_id;
    public $messages = [];

    public $tg_count;
    public $sms_count;
    public $call_count;
    public $fail_count;

    public $geo_fwd_message_id;
    public $geo_point_lat;
    public $geo_point_lng;

    public static function createFromMention($mention, $db) {
        $alert = [
            'to_id' => $mention->to_id,
            'message' => str_replace('Eugenia ', '', $mention->message),
            'author' => $mention->from_username,
            'author_id' => $mention->from_id,
            'mention_id' => $mention->id,

            'tg_count' => 0,
            'call_count' => 0,
            'sms_count' => 0,
        ];        

        if($mention->media && ($mention->media->_ == 'messageMediaGeoLive' || $mention->media->_ == 'messageMediaGeo')) {
            $alert['geo_fwd_message_id'] = $mention->media->fwd_message_id;
            $alert['geo_point_lat'] = $mention->media->geo->lat;
            $alert['geo_point_lng'] = $mention->media->geo->long;
        }

        return new self($alert, $db);
    }

    public function process($api) {
        $TGClient   = $api->getTelegramClient();
        
        if(empty($this->messages)) {
            $ChatInfo = $TGClient->get_pwr_chat((array)$this->to_id);

            foreach($ChatInfo['participants'] as $key => $user) {

                $UserInfo = $TGClient->get_full_info($user['user']['id']);
                $me = $TGClient->get_self();
                
                if(getenv('DEBUG') == 'true') {
                    if($this->author_id != $UserInfo['User']['id']) {
                        continue;
                    }
                } else {
                    // drop bot
                    if($UserInfo['User']['self'] == true) {
                        continue;
                    }                
                    
                    // drop author
                    if($this->author_id == $UserInfo['User']['id']) {
                        continue;
                    }
                }
                
                $bitly = new \Hpatoio\Bitly\Client($api->getBitlyApiToken());
                $response = $bitly->shorten(array("longUrl" => 'http://www.google.com/maps/place/'.$this->geo_point_lat.",".$this->geo_point_lng));
                
                $alert = $this->serialize();
                $alert['from_id'] = $this->to_id;

                $alert['to_id'] = [
                    '_' => 'user',
                    'user_id' =>  $UserInfo['User']['id'],
                    'username' => isset($UserInfo['User']['username']) ? $UserInfo['User']['username'] : false,
                    'phone' => isset($UserInfo['User']['phone']) ? $UserInfo['User']['phone'] : false,
                ];

                if($this->geo_fwd_message_id) {
                    $alert['geo_url'] = $response['url'];
                }

                $Message = Message::createFromAlert((object)$alert, $this->db);

                if($alert['to_id']['phone'] !== false) {
                    $Message->is_sms_capable = true;
                    $Message->is_call_capable = true;
                }

                $Message->save();

                $this->messages[] = $Message->getHash();
                $this->save();
            }

            return false;
        }

        $exclude_messages = [];
        foreach($this->messages as $key => $message) {
            $messageObj = new Message((array)$this->db->getNestedVal('messages', $message), $this->db);
            if($messageObj->viewed) {
                if($messageObj->is_tg) {
                    $this->tg_count++;
                }

                if($messageObj->is_sms) {
                    $this->sms_count++;
                }

                if($messageObj->is_call) {
                    $this->call_count++;
                }       

                $exclude_messages[] = $message;
                continue;
            }

            if($messageObj->answered) {
                $exclude_messages[] = $message;

                if($messageObj->is_sms) {
                    $this->sms_count++;
                }

                if($messageObj->is_call) {
                    $this->call_count++;
                }       
                
                continue;
            }

            if($messageObj->failed) {
                $exclude_messages[] = $message;
                $this->fail_count++;

                if($messageObj->is_tg) {
                    $this->tg_count++;
                }

                if($messageObj->is_sms) {
                    $this->sms_count++;
                }

                if($messageObj->is_call) {
                    $this->call_count++;
                }                       
                continue;
            }
        }
        
        $messDiff= array_diff($this->messages, $exclude_messages);
        $this->messages = [];

        foreach($messDiff as $key => $val) {
            $this->messages[] = $val;
        }

        $this->save();

        foreach($exclude_messages as $key => $hash) {
            $this->db->dropNestedVal('messages', $hash);
        }

        if(empty($this->messages)) {
            return true;
        }
    }

    public function __construct($alert, $db) {
        $this->db = $db;

        $this->parse($alert);
    }

    public function save() {
        $this->db->setNestedVal('alerts', $this->getHash(), $this->serialize());
    }

    public function serialize() {
        $alert = [
            'to_id' => $this->to_id,
            'message' => $this->message,
            'author' => $this->author,
            'author_id' => $this->author_id,
            'mention_id' => $this->mention_id,
            'messages' => $this->messages,

            'call_count' => $this->call_count,
            'sms_count' => $this->sms_count,
            'tg_count' => $this->tg_count,

            'geo_fwd_message_id' => $this->geo_fwd_message_id,
            'geo_point_lat' => $this->geo_point_lat,
            'geo_point_lng' => $this->geo_point_lng
        ];

        return $alert;
    }

    public function parse($alert) {
        $alert = (array)$alert;
        $this->to_id = (array)$alert['to_id'];
        $this->message = $alert['message'];
        $this->author = $alert['author'];
        $this->author_id = $alert['author_id'];        
        $this->mention_id = $alert['mention_id'];      

        if(isset($alert['messages'])) {
            $this->messages = $alert['messages'];      
        }
        if(isset($alert['call_count'])) {
            $this->call_count = $alert['call_count'];      
        }
        if(isset($alert['sms_count'])) {
            $this->sms_count = $alert['sms_count'];      
        }
        if(isset($alert['tg_count'])) {
            $this->tg_count = $alert['tg_count'];      
        }

        if(isset($alert['geo_fwd_message_id'])) {
            $this->geo_fwd_message_id = $alert['geo_fwd_message_id'];      
        }
        if(isset($alert['geo_point_lat'])) {
            $this->geo_point_lat = $alert['geo_point_lat'];      
        }
        if(isset($alert['geo_point_lng'])) {
            $this->geo_point_lng = $alert['geo_point_lng'];      
        }

    }

    public function getHash() {
        $to_id = $this->to_id;
        if(is_object($to_id)) {
            $to_id = (array)$to_id;
        }
        return md5($this->mention_id . $this->author_id . $to_id['channel_id']);
    }

    public function load() {
        $this->parse($this->db->getNestedVal('alerts', $this->getHash()));
    }

    public function drop() {
        $this->db->dropNestedVal('alerts', $this->getHash());
        $this->db->save();
    }
}
