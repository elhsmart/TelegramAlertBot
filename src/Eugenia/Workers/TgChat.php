<?php

    namespace Eugenia\Workers;

    use Lifo\Daemon\LogTrait;
    use Lifo\Daemon\Promise;
    use Lifo\Daemon\Worker\WorkerInterface;

    class TgChat implements WorkerInterface {

        private $time_update;
        private $conn;
        private $api;
        private $parent;

        public function __construct($api, $parent, $conn) {
            $this->parent = $parent;
            $this->api = $api;
            $this->conn = $conn;
        }

        public function initialize() {
            $this->time_update = time();
        }

        public function getSelf() {
            if(!$this->conn->getConnection()) {
                return false;
            }

            $TGClient = $this->api->getTelegramClient();
            return $TGClient->get_self();
            
        }

        public function getMentions() {
            $unreacted_mentions = [];

            if(!$this->conn->getConnection()) {
                return false;
            }
            $this->log('Fetch unread mentions from all dialogs.');

            $TGClient = $this->api->getTelegramClient();
            $dialogs = $TGClient->get_dialogs();

            foreach($dialogs as $key => $peer) {
                if($peer['_'] != 'peerChannel' && $peer['_'] != 'peerChat') {
                    //Skipping regular users
                    continue;
                }

                $mentions = $TGClient->messages->getUnreadMentions([
                    'peer' => $peer, 
                    'offset_id' => 0, 
                    'offset_date' => 0,
                    'add_offset' => 0, 
                    'limit' => 10, 
                    'max_id' => 0, 
                    'min_id' => 0
                ]);
                
                if(count($mentions['messages']) > 0) {
                    foreach($mentions['messages'] as $mentionMessage) {

                        foreach($mentions['users'] as $user) {
                            if($user['id'] == $mentionMessage['from_id']) {
                                $mention_author = $user;
                                break;
                            }
                        }

                        $au_username = $this->getSendMessageUsername($user['id']);

                        $this->log('Get new mention from ' . $au_username .  ': ' . $mentionMessage['message'] . ' .');
                        $mentionMessage['author'] = $mention_author;
                        $mentionMessage['from_username'] = $au_username ;

                        if(isset($mentionMessage['entities']) && isset($mentionMessage['entities'][0])) {
                            $mentionMessage['message'] = trim(mb_substr_replace($mentionMessage['message'], '', $mentionMessage['entities'][0]['offset'], $mentionMessage['entities'][0]['length']));
                        }
                        
                        $mentionMessage['message'] = preg_replace('/[\s]+/mu', ' ', $mentionMessage['message']);

                        array_push($unreacted_mentions, $mentionMessage);
                    }

                    $TGClient->messages->readMentions([ 'peer' => $peer]);
                }
            }

            if(count($unreacted_mentions) > 0) {

                return $unreacted_mentions;
            }

            return false;
        }

        public function markMentionRead(){
            
        }

        public function getSendMessageUsername($user_id) {
            $TGClient = $this->api->getTelegramClient();

            $from_user  = $TGClient->get_full_info($user_id);

            $from_username = isset($from_user['User']['username']) ? $from_user['User']['username'] : "";
            
            if(strlen($from_username) == 0) {
                $from_username = "[".$from_user['User']['first_name']."](tg://user?id=".$user_id.")";
            } else {
                $from_username = "@".$from_username;
            }

            return $from_username;
        }

        public function log($message) {
            $this->parent->log("TgChat: " . $message);
        }
    }