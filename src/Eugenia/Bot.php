<?php

namespace Eugenia;

use Lifo\Daemon\Daemon;
use Lifo\Daemon\Event\DaemonEvent;
use Lifo\Daemon\Event\SignalEvent;
use Lifo\Daemon\Mediator\Mediator;

use Eugenia\Workers\TgConn;
use Eugenia\Workers\TgChat;

use Eugenia\Entities;
use Eugenia\Misc;

class Bot extends Daemon
{

    private $time_start;
    private $time_frames_actions = [
        1 => [
            'getConnection',
            'processMentions',
            'processAlerts',
            'processAlertMessages',
        ],
        10 => [
            'fetchMessages',
        ],
        15 => [
            'getUpdates'
        ],
        30 => [
            'dataCleanup'
        ]
    ];

    private $update_offset = 0;
    private $time_frame_data;
    private $locale;

    private $messages = [];
    
    protected function initialize()
    {
        $this->time_start = time();



        $this->addPlugin('Lifo\Daemon\Plugin\Lock\FileLock', [
            'ttl'  => 10,
            'file' => '/tmp/quick_start_daemon.pid',
        ]);

        $this->addPlugin('Eugenia\Plugins\Config', 'config', [
            'config_path' => APP_ROOT . DIRECTORY_SEPARATOR . 'config.json',
        ]);

        $this->addPlugin('Eugenia\Plugins\Db', 'localdb', [
            'db_path' => APP_ROOT . DIRECTORY_SEPARATOR . 'localdb.json',
        ]);
        
        $this->addPlugin('Eugenia\Plugins\Config', 'locale', [
            'config_path' => APP_ROOT . DIRECTORY_SEPARATOR . 'locale.json',
            'readonly' => true
        ]);

        $config = $this->plugin('config');

        $this->locale = Misc\LangTemplate::getInstance($this->plugin('config')->getVal('locale'), $this->plugin('locale'));

        $this->addPlugin('Eugenia\Plugins\Api', [
            'config_tg_api_id'          => $config->getVal('config_tg_api_id'),
            'config_tg_api_hash'        => $config->getVal('config_tg_api_hash'),

            'config_phone_number'       => $config->getVal('config_phone_number'),
            'config_nexmo_phone_number' => $config->getVal('config_nexmo_phone_number'),
            
            'config_tg_rsa_keys'        => $config->getVal('config_tg_rsa_keys'),
            
            'config_tg_mtproto_server'  => $config->getVal('config_tg_mtproto_server'),
            
            'config_session_path'       => $config->getVal('config_session_path'),
            
            'config_twilio_sid'         => $config->getVal('config_twilio_sid'),
            'config_twilio_token'       => $config->getVal('config_twilio_token'),
            'config_twilio_speech_url'  => $config->getVal('config_twilio_speech_url'),
            
            'config_nexmo_key'          => $config->getVal('config_nexmo_key'),
            'config_nexmo_token'        => $config->getVal('config_nexmo_token'),
            'config_nexmo_answer_url'   => $config->getVal('config_nexmo_answer_url'),
            
            'config_bitly_api_key'      => $config->getVal('config_bitly_api_key'),
            'config_bitly_api_token'    => $config->getVal('config_bitly_api_token')
        ], 'api');

        $this->Tg = new TgConn($this->plugin('api'), $this);
        $this->Tg->initialize();

        $this->Chat = new TgChat($this->plugin('api'), $this, $this->Tg);
        $this->Chat->initialize();
        
        //$this->addWorker(new TgConn( $this->plugin('api')), 'Tg');

        $this->on(DaemonEvent::ON_INIT, function() {
            $this->onInit();
        });
        // when the daemon goes into shutdown mode, call our function so your daemon can clean itself up.
        // the onShutdown call is wrapped in a function so that it can remain a 'private' method 
        // (instead of using [$this, 'onShutdown'] which would require the method to be public)
        $this->on(DaemonEvent::ON_SHUTDOWN, function () {
            $this->onShutdown();
        });

        // listen for any signals caught and log it
        $this->on(DaemonEvent::ON_SIGNAL, function (SignalEvent $e) {
            $this->log("Signal %d caught!", $e->getSignal());
        });
    }

    protected function execute() {
        foreach($this->time_frames_actions as $key => $frame_funcs) {
            if(!isset($this->time_frame_data[$key])) {
                // Init start data mark here
                $this->time_frame_data[$key] = new \stdClass();
                $this->time_frame_data[$key]->time = time();
                continue;
            }

            if(time() - $this->time_frame_data[$key]->time >= $key) {
                // Evaluate action
                $this->time_frame_data[$key]->time = time();

                foreach($frame_funcs as $in_key => $func) {
                    if(is_callable([$this, $func])) {
                        $this->{$func}($key);
                    }
                }                
            }
        }

        $this->log("Loop %d", $this->getLoopIterations());
    }

    public function getConnection() {
        /*$this->worker('Tg')->getConnection()->then(function($val){
            $this->log("Worker returned %s via Promise", $val);
        });*/

        $this->Tg->getConnection();
    }

    public function getUpdates() {
        $TGClient   = $this->plugin('api')->getTelegramClient();

        $updates    = $TGClient->get_updates(['offset' => $this->update_offset, 'limit' => 50, 'timeout' => 0]);
        foreach($updates as $key => $update) {

            if($update['update']['_'] == 'updateNewMessage') {
                // Mark message as read
                $TGClient->messages->readHistory(['peer' => $update['update']['message']['from_id'], 'max_id' => $update['update_id'] ]);

                $this->processDirectMessage($update);
            }

            if($update['update']['_'] == 'updateReadHistoryOutbox') {
                $messages = $this->plugin('localdb')->getVal('messages');
                if($messages) {
                    foreach($messages as $key => $message) {
                        $message = new Entities\Message((array)$message, $this->plugin('localdb'));
                        if($update['update']['max_id'] >= $message->is_tg) {
                            $message->viewed = true;
                            $message->save();
                        }
                    }
                }
            }
            
            $this->update_offset = $update['update_id'] + 1;
        }
    }

    public function processDirectMessage($update) {
        $TGClient = $this->plugin('api')->getTelegramClient();
        $me = $this->Chat->getSelf();
        $message = $update['update']['message'];
        if($message['to_id']['user_id'] == $me['id']) {
            // Processing audio message
            if( (new Misc\Media())->checkMediaAudio($message) ) {
                // Here we will go with processing Audio
            }

            if( (new Misc\Media())->checkMediaVideo($message) ) {
                // Here we will go with processing Audio
            }

            $authorPeer = [
                '_' => 'user',
                'id' => $message['from_id']
            ];

            $user = $TGClient->get_full_info($authorPeer);
            $authorPeer['phone'] = $user['User']['phone'];

            $settingsCommands = [
                'disableSMS',
                'enableSMS',
                'disableCalls',
                'enableCalls',
                'reviewSettings'
            ];
            $checkSettingsCommand = true;

            if(strlen($message['message']) > 0) {
                $command = (new Misc\Parser())->checkCommand($message['message']);
                if($command) {
                    if(in_array($command['command'], $settingsCommands)) {
                        if(!$authorPeer['phone']) {
                            $TGClient->messages->sendMessage([
                                'peer' => $authorPeer, 
                                'parse_mode' => 'Markdown',
                                'message' => 
                                    Misc\LangTemplate::getInstance()->get('bot_settings_not_applicable')
                            ]);       
                            $checkSettingsCommand = false;
                        }                             
                    }
                    if($checkSettingsCommand) {
                        switch($command['command']) {
                            case 'disableSMS': {
                                $userSettings = Entities\Settings::getAndCheck($authorPeer['id'], $this->plugin('localdb'), false, true);
                                $userSettings->sms_enabled = false;
                                $userSettings->save();

                                $TGClient->messages->sendMessage([
                                    'peer' => $authorPeer, 
                                    'parse_mode' => 'Markdown',
                                    'message' => 
                                        Misc\LangTemplate::getInstance()->get('bot_settings_sms_disabled')
                                ]);    
                                break;
                            }
                            case 'enableSMS': {
                                $userSettings = Entities\Settings::getAndCheck($authorPeer['id'], $this->plugin('localdb'), true, true);
                                $userSettings->sms_enabled = true;
                                $userSettings->save();

                                $TGClient->messages->sendMessage([
                                    'peer' => $authorPeer, 
                                    'parse_mode' => 'Markdown',
                                    'message' => 
                                        Misc\LangTemplate::getInstance()->get('bot_settings_sms_enabled')
                                ]);   
                                break;
                            }
                            case 'disableCalls': {
                                $userSettings = Entities\Settings::getAndCheck($authorPeer['id'], $this->plugin('localdb'), true, false);
                                $userSettings->calls_enabled = false;
                                $userSettings->save();

                                $TGClient->messages->sendMessage([
                                    'peer' => $authorPeer, 
                                    'parse_mode' => 'Markdown',
                                    'message' => 
                                        Misc\LangTemplate::getInstance()->get('bot_settings_calls_disabled')
                                ]);                                
                                break;
                            }
                            case 'enableCalls': {
                                $userSettings = Entities\Settings::getAndCheck($authorPeer['id'], $this->plugin('localdb'), true, true);
                                $userSettings->calls_enabled = true;
                                $userSettings->save();

                                $TGClient->messages->sendMessage([
                                    'peer' => $authorPeer, 
                                    'parse_mode' => 'Markdown',
                                    'message' => 
                                        Misc\LangTemplate::getInstance()->get('bot_settings_calls_enabled')
                                ]);                                
                                break;
                            }
                            case 'reviewSettings': {
                                $userSettings = Entities\Settings::getAndCheck($authorPeer['id'], $this->plugin('localdb'), false, false);

                                $TGClient->messages->sendMessage([
                                    'peer' => $authorPeer, 
                                    'parse_mode' => 'Markdown',
                                    'message' => 
                                        Misc\LangTemplate::getInstance()->get('bot_settings', 
                                        ($userSettings->calls_enabled) ? 'On' : 'Off',
                                        ($userSettings->sms_enabled) ? 'On' : 'Off'
                                    )
                                ]);                                        
                                break;
                            }
                        }
                    }
                }
            }

            //var_dump($update['update']['message']);
            // Check media
        }
    }

    public function fetchMessages() {
        if($this->Tg->getConnection()) {
            $this->log("Fetch last Mentions from assigned chats");
            $mentions = $this->Chat->getMentions();

            if($mentions && count($mentions) > 0) {
                foreach($mentions as $mention) {
                    $command = (new Misc\Parser())->checkCommand($mention['message']);

                    if($command) {
                        $this->processCommandMention($command, $mention);
                        continue;
                    }

                    $entity = new Entities\Mention($mention, $this->plugin('localdb'));
                    $entity->save();
                }
            }
        }
    }
 
    public function processCommandMention($command, $mention) {
        $TGClient   = $this->plugin('api')->getTelegramClient();

        switch($command['command']) {
            case 'disableSMS': {
                $alerts = $messages = $this->plugin('localdb')->getVal('alerts');
                $userAlerts = [];
                foreach($alerts as $key => $alert) {
                    if($alert->author_id == $mention['author']['id']) {
                        $userAlerts[] = $alert;
                    }
                }
                
                $from_username = $this->Chat->getSendMessageUsername($mention['author']);            

                if(count($userAlerts) > 0) {
                    foreach($userAlerts as $key => $alert) {
                        foreach($alert->messages as $mes_key => $hash) {
                            $message = $this->plugin('localdb')->getNestedVal('messages', $hash);
                            $message->is_sms_capable = false;
                            $this->plugin('localdb')->setNestedVal('messages', $hash, $message);
                        }

                        $TGClient->messages->sendMessage([
                            'peer' => $mention['to_id'], 
                            'parse_mode' => 'Markdown',
                            'message' => 
                                Misc\LangTemplate::getInstance()->get('bot_sms_disabled', $from_username)
                                //$from_username . ", SMS для твоих текущих рассылок выключены."
                            ]);
                    }
                } else {
                    $TGClient->messages->sendMessage([
                        'peer' => $mention['to_id'], 
                        'parse_mode' => 'Markdown',
                        'message' => 
                            Misc\LangTemplate::getInstance()->get('bot_havent_active_alerts_for_sms', $from_username)
                            //$from_username . ", у тебя нет активных рассылок, чтобы отключать SMS."
                        ]);
                }
                break;
            }
            case 'disableCalls': {
                $alerts = $messages = $this->plugin('localdb')->getVal('alerts');
                $userAlerts = [];
                foreach($alerts as $key => $alert) {
                    if($alert->author_id == $mention['author']['id']) {
                        $userAlerts[] = $alert;
                    }
                }
                
                $from_username = $this->Chat->getSendMessageUsername($mention['author']);            

                if(count($userAlerts) > 0) {
                    foreach($userAlerts as $key => $alert) {
                        foreach($alert->messages as $mes_key => $hash) {
                            $message = $this->plugin('localdb')->getNestedVal('messages', $hash);
                            $message->is_call_capable = false;
                            $this->plugin('localdb')->setNestedVal('messages', $hash, $message);
                        }

                        $TGClient->messages->sendMessage([
                            'peer' => $mention['to_id'], 
                            'parse_mode' => 'Markdown',
                            'message' => 
                                Misc\LangTemplate::getInstance()->get('bot_calls_disabled', $from_username)
                                //$from_username . ", звонки для твоих текущих рассылок выключены."
                            ]);
                    }
                } else {
                    $TGClient->messages->sendMessage([
                        'peer' => $mention['to_id'], 
                        'parse_mode' => 'Markdown',
                        'message' => 
                            Misc\LangTemplate::getInstance()->get('bot_havent_active_alerts_for_calls', $from_username)
                            //$from_username . ", у тебя нет активных рассылок, чтобы отключать звонки."
                        ]);
                }
                break;
            }
            case 'helpMessage': {
                $TGClient->messages->sendMessage([
                    'peer' => $mention['to_id'], 
                    'parse_mode' => 'Markdown',
                    'message' => Misc\LangTemplate::getInstance()->get('bot_help')
                ]);   

                break;             
            }

            case "checkUser": {
                $peer = [
                    '_' => 'user',
                    'id' => $command['entity']
                ];
                try {
                    $user = $TGClient->get_full_info($peer);

                    if($user['User'] && $user['User']['_']) {
                        $TGClient->messages->sendMessage([
                            'peer' => $mention['to_id'], 
                            'parse_mode' => 'Markdown',
                            'message' => 
                                'Username: ' . ( isset($user['User']['username']) ? $user['User']['username'] : Misc\LangTemplate::getInstance()->get('bot_not_set') ) . "\n" . 
                                'First Name: ' . ( isset($user['User']['first_name']) ? $user['User']['first_name'] : Misc\LangTemplate::getInstance()->get('bot_not_set') ) . "\n" . 
                                'Last Name: ' . ( isset($user['User']['last_name']) ? $user['User']['last_name'] : Misc\LangTemplate::getInstance()->get('bot_not_set') ) . "\n" . 
                                'Phone: '. ( isset($user['User']['phone']) ? $user['User']['phone'] : Misc\LangTemplate::getInstance()->get('bot_not_accessible') ) 
                            ]);
                    } else {
                        $TGClient->messages->sendMessage([
                            'peer' => $mention['to_id'], 
                            'parse_mode' => 'Markdown',
                            'message' => Misc\LangTemplate::getInstance()->get('bot_username_missed', $command['entity'])
                            // 'Юзера "' . $command['entity'] . '" нету.'
                        ]);
                    }
                } catch (\danog\MadelineProto\Exception $e) {
                    $TGClient->messages->sendMessage([
                        'peer' => $mention['to_id'], 
                        'parse_mode' => 'Markdown',
                        'message' => Misc\LangTemplate::getInstance()->get('bot_username_missed', $command['entity'])
                        //'Юзера "' . $command['entity'] . '" нету.'
                    ]);                    
                }
                break;
            }
        }
    }

    public function processAlertMessages() {
        $TGClient   = $this->plugin('api')->getTelegramClient();

        if(!$this->Tg->getConnection()) {
            return;
        }

        $alerts = (array)$this->plugin('localdb')->getVal('alerts');
        foreach($alerts as $key => $alert) {
            $alertObj = new Entities\Alert($alert, $this->plugin('localdb'));       
            
            // Alerts cleanup
            if(time() > $alertObj->time_update + $this->plugin('config')->getVal('alerts_cleanup_timeout')) {
                $this->log('Stalled alert found. Cleanup.');

                foreach($alertObj->messages as $key => $messageHash) {
                    $message =  (array)$this->plugin('localdb')->getNestedVal('messages', $messageHash);
                    $messageObj = new Entities\Message($message, $this->plugin('localdb'));      
                    $messageObj->drop();
                }

                $alertObj->drop();
                continue;
            }
            
            foreach($alertObj->messages as $key => $messageHash) {
                $message =  (array)$this->plugin('localdb')->getNestedVal('messages', $messageHash);
                $messageObj = new Entities\Message($message, $this->plugin('localdb'));      
                $messageObj->process($this->plugin('api'));
            }
        }
    }

    public function processAlerts() {
        $TGClient   = $this->plugin('api')->getTelegramClient();

        if(!$this->Tg->getConnection()) {
            return;
        }

        $alerts = (array)$this->plugin('localdb')->getVal('alerts');
        foreach($alerts as $key => $alert) {
            $alertObj = new Entities\Alert($alert, $this->plugin('localdb'));            
            if($alertObj->process($this->plugin('api'))) {
                $TGClient->messages->sendMessage([
                    'peer' => (array)$alertObj->to_id, 
                    'message' => Misc\LangTemplate::getInstance()->get('bot_alert_ended_report', (string)$alertObj->tg_count, (string)$alertObj->sms_count, (string)$alertObj->call_count, (string)$alertObj->fail_count)
                    //"Рассылка завершена. Telegram: ".$alertObj->tg_count.", SMS: ".$alertObj->sms_count.", Звонки: ".$alertObj->call_count.", Не просмотрено: ".$alertObj->fail_count
                ]);                
                $alertObj->drop();
            }
        }
    }

    public function processMentions() {
        $TGClient   = $this->plugin('api')->getTelegramClient();

        if(!$this->Tg->getConnection()) {
            return;
        }

        $mentions = (array)$this->plugin('localdb')->getVal('mentions');

        //Check stalled mentions
        foreach($mentions as $key => $mention) {
            $mentionObj = new Entities\Mention($mention, $this->plugin('localdb'));
            if(time() > $mentionObj->update_time + $this->plugin('config')->getVal('mentions_cleanup_timeout')) {
                $this->log('Stalled mention found. Cleaning');
                unset($mentions[$key]);
                $mentionObj->drop();
            }
        }

        if(is_array($mentions) && count($mentions) > 0) {
            $this->log('Check duplicate mentions.');        

            $mentionAuthors = [];
            foreach($mentions as $key => $mention) {
                if(!isset($mentionAuthors[$mention->from_id])) {
                    $mentionAuthors[$mention->from_id] = [];
                }

                if($mention->is_answered == false) {
                    $mentionAuthors[$mention->from_id][] = $mention;
                }
            }
            foreach($mentionAuthors as $author => $mentions) {
                if(count($mentions) > 1) {
                    $x = 0;
                    foreach($mentions as $key => $mention) {
                        $mentionObj = new Entities\Mention($mention, $this->plugin('localdb'));
                        if($x == 0) {                                
                            $mentionObj->silentAnswer();
                            $mentionObj->save();
                            $x++;
                        } else {
                            $this->plugin('localdb')->dropNestedVal('mentions', $mentionObj->getHash());
                        }
                    }

                    $from_username = $this->Chat->getSendMessageUsername($mention->from_id);            
                    $TGClient->messages->sendMessage([
                        'peer' => (array)$mention->to_id, 
                        'parse_mode' => 'Markdown',
                        'message' => Misc\LangTemplate::getInstance()->get('bot_only_last_mention_applicable', [$from_username, $mentionObj->message])
                    ]);
                    //"" . $from_username . " Извини, могу ответить только на последнее сообщение c текстом '" . $mentionObj->message . "' . Уверен?"]);
                }
            }

            $this->log('Unreaded mentions processing.');

            $this->plugin('localdb')->load();
            $mentions = (array)$this->plugin('localdb')->getVal('mentions');

            foreach($mentions as $key => $mention) {
                $mention = new Entities\Mention($mention, $this->plugin('localdb'));

                if(!$mention->is_answered) {
                    // Check if this mention is parent or child
                    $checkIfAnswer = false;
                    foreach($mentions as $in_key => $in_mention) {
                        $in_mention = new Entities\Mention($in_mention, $this->plugin('localdb'));
                        if (
                            (
                                $mention->from_id == $in_mention->from_id &&
                                $mention->to_id == $in_mention->to_id &&
                                $mention != $in_mention
                                && $in_mention->is_answered == true
                                ) ||
                            (
                                isset($in_mention->reply_to_msg_id) && 
                                $in_mention->reply_to_msg_id == $mention->id
                            )
                        ) {
                            // Seems like answer found
                            $this->log("Found possible mention answer. Lets process it.");
                            $answer = (new Misc\Parser())->checkPositiveAnswer($mention->message);

                            if($answer) {
                                // Here we go wit new alert

                                $from_username = $this->Chat->getSendMessageUsername($mention->from_id);
                                $this->log("Positive answer Detected. Creating new alert.");                                
                                $TGClient->messages->sendMessage([
                                    'peer' => (array)$mention->to_id, 
                                    'message' => Misc\LangTemplate::getInstance()->get('bot_creating_alert', $from_username)
                                    //"" . $from_username . " Ок. Создаю рассылку."
                                ]);

                                $alert = Entities\Alert::createFromMention($in_mention, $this->plugin('localdb'));
                                $alert->save();

                                $in_mention->drop();
                                $mention->drop();

                            } else if($location = (new Misc\Location())->checkLocationMessage($mention)) {
                                $from_username = $this->Chat->getSendMessageUsername($mention->from_id);
                                $this->log("Positive answer Detected with Geolocation. Creating new alert.");                                
                                $TGClient->messages->sendMessage([
                                    'peer' => (array)$mention->to_id, 
                                    'message' => 
                                    Misc\LangTemplate::getInstance()->get('bot_creating_alert_with_geo', $from_username)
                                    //"" . $from_username . " Ок. Создаю рассылку. Добавлю к ней геолокацию."
                                ]);

                                $in_mention->media = $mention->media;
                                $in_mention->media->fwd_message_id = $mention->id;

                                $alert = Entities\Alert::createFromMention($in_mention, $this->plugin('localdb'));
                                $alert->save();

                                $in_mention->drop();
                                $mention->drop();
                            } else {
                                $from_username = $this->Chat->getSendMessageUsername($mention->from_id);
                                $TGClient->messages->sendMessage([
                                    'peer' => (array)$mention->to_id, 
                                    'message' =>  Misc\LangTemplate::getInstance()->get('bot_rejecting_alert', $from_username)
                                    //"" . $from_username . " Не уверен - не создавай алерты."
                                ]);

                                $in_mention->drop();
                                $mention->drop();
                            }
                            $checkIfAnswer = true;
                        }
                    }
                    if(!$checkIfAnswer) {
                        if($mention->reply_to_msg_id && $mention->reply_to_msg_id) {
                            $from_username = $this->Chat->getSendMessageUsername($mention->from_id);

                            $TGClient->messages->sendMessage([
                                'peer' => (array)$mention->to_id, 
                                'parse_mode' => 'Markdown',
                                'message' =>  Misc\LangTemplate::getInstance()->get('bot_rejecting_reply_alert', $from_username)
                                //"" . $from_username . " Для создания рассылки напиши мне сообщение напрямую."
                            ]);
                            //$mention->drop();

                            $mention->silentAnswer();
                            $mention->save();

                        } else {
                            if($mention->answer($this->plugin('api'))) {
                                $mention->save();
                            }
                        }
                    }
                } else {
                    // Check alerts stamps 
                }
            }
        }
    }

    public function dataCleanup() {
        
    }

    protected function onInit() {
        $dbdata = (array)$this->plugin('localdb')->getVal('chats');
    }

    protected function onShutdown() {
        $this->log("OH NO!!! we're shutting down! I better do some clean up.");
        $this->Tg->teardown();
        // do stuff here...
    }
    
}