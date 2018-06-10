<?php

namespace Eugenia\Entities;

use Lifo\Daemon\Plugin;
use Lifo\Daemon\Plugin\PluginInterface;
use danog\MadelineProto\API as MadeApi;

class Mention {

    public $is_answered = false;
    public $is_answered_timestamp;
    
    public $id;

    public $to_id;
    public $from_id;
    public $from_username;
    public $message;
    public $reply_to_msg_id;

    private $db;

    public function __construct($mention, $db) {
        $this->db = $db;

        $this->parse($mention);
    }

    public function isAnswered() {
        return (bool)$this->is_answered;
    }

    public function silentAnswer() {
        $this->is_answered = true;
        $this->is_answered_timestamp = time();
    }
    
    public function answer($api) {
        $TGClient   = $api->getTelegramClient();
        $from_user  = $TGClient->get_full_info($this->from_id);

        $from_username = $from_user['User']['username'];
        if(strlen($from_username) == 0) {
            $from_username = "[".$from_user['User']['first_name']."](tg://user?id=".$this->from_id.")";
        } else {
            $from_username = "@".$from_username;
        }

        // Check dupes
        $alerts = (array)$this->db->getVal("alerts");
        
        foreach($alerts as $key => $alert) {
            if($alert->author_id == $this->from_id) {
                $TGClient->messages->sendMessage([
                    'peer' => (array)$this->to_id, 
                    'parse_mode' => 'Markdown',
                    'message' => "" . $from_username . " от тебя уже есть рассылка. Подожди, пожалуйта, ока она закончмтся"]);
                $this->drop();
            }
        }

        $TGClient->messages->sendMessage([
            'peer' => (array)$this->to_id, 
            'parse_mode' => 'Markdown',
            'message' => "" . $from_username . " уверен?"]);

        $this->is_answered = true;
        $this->is_answered_timestamp = time();
    }

    public function save() {
        $this->db->setNestedVal('mentions', $this->getHash(), $this->serialize());
    }

    public function serialize() {
        $mention = [];

        $mention['to_id'] = $this->to_id;
        $mention['from_id'] = $this->from_id;
        $mention['from_username'] = $this->from_username;
        $mention['id'] = $this->id;
        $mention['message'] = $this->message;
        $mention['is_answered'] = $this->is_answered;
        $mention['is_answered_timestamp'] = $this->is_answered_timestamp;
        $mention['reply_to_msg_id'] = $this->reply_to_msg_id;

        return $mention;
    }

    public function parse($mention) {
        $mention = (array)$mention;

        $this->to_id = $mention['to_id'];
        $this->from_id = $mention['from_id'];
        $this->from_username = $mention['from_username'];
        $this->id = $mention['id'];
        $this->message = $mention['message'];
        if(isset($mention['is_answered'])) {
            $this->is_answered = $mention['is_answered'];
        }

        if(isset($mention['is_answered_timestamp'])) {        
            $this->is_answered_timestamp = $mention['is_answered_timestamp'];
        }
        
        if(isset($mention['reply_to_msg_id'])) {        
            $this->reply_to_msg_id = $mention['reply_to_msg_id'];
        }
    }

    public function getHash() {
        $to_id = $this->to_id;
        if(is_object($to_id)) {
            $to_id = (array)$to_id;
        }
        return md5($this->id . $this->from_id . $to_id['channel_id']);
    }

    public function load() {
        $this->parse($this->db->getNestedVal('mentions', $this->getHash()));
    }

    public function drop() {
        $this->db->dropNestedVal('mentions', $this->getHash());
        $this->db->save();
    }
}
