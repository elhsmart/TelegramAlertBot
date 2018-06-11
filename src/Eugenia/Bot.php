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
        ]
    ];

    private $update_offset = 0;
    private $time_frame_data;

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

        $this->addPlugin('Eugenia\Plugins\Api', [
            'api_key' => 'test',
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
            
            $this->update_offset = $update['update_id'];
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
            case 'helpMessage': {
                $TGClient->messages->sendMessage([
                    'peer' => $mention['to_id'], 
                    'parse_mode' => 'Markdown',
                    'message' => 
                        "Бот предназначен для оповещения участников чата о неотложных событиях и просьбах помощи." . "\n"  .
                        "Оповещение может присылаться ботом как в телеграм напрямую в личную беседу, так и через SMS с телефонным звонком." . "\n\n" . 
                        "Чтобы создать сообщение нужно обратиться к боту через @ с текстом сообщения, которое вы желаете разослать. После этого бот попросит подтверждение, и если вы ему ответите 'да' или '+' - будет создан новый список рассылки вашего сообщения.\n\n" .
                        "Если вместо 'да' или '+' вы ответите геопозицией (как одиночной, так и лайвом) - это расценится как положительный ответ и геолокация будет добавлена к сообщению. \n\n" .
                        "Если вы сразу напишете боту геолокацию или аудио вместо сообщения - это будет расценено как суперсрочное сообщение и бот не потребует подтверждения, создав рассылку без него. \n\n" .
                        "Так же суперсрочным сообщением будет воспринято обращение без текста (ситуация, когда нет времени писать)\n\n" .
                        "Другие типы медиа в сообщениях (изображения, gif-файлы, видео и пр.) будут проигнорированы."
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
                                'Username: ' . ( isset($user['User']['username']) ? $user['User']['username'] : 'Not Set' ) . "\n" . 
                                'First Name: ' . ( isset($user['User']['first_name']) ? $user['User']['first_name'] : 'Not Set' ) . "\n" . 
                                'Last Name: ' . ( isset($user['User']['last_name']) ? $user['User']['last_name'] : 'Not Set' ) . "\n" . 
                                'Phone: '. ( isset($user['User']['phone']) ? $user['User']['phone'] : 'Not Acessible' ) 
                            ]);
                    } else {
                        $TGClient->messages->sendMessage([
                            'peer' => $mention['to_id'], 
                            'parse_mode' => 'Markdown',
                            'message' =>  'Юзера "' . $command['entity'] . '" нету.'
                        ]);
                    }
                } catch (\danog\MadelineProto\Exception $e) {
                    $TGClient->messages->sendMessage([
                        'peer' => $mention['to_id'], 
                        'parse_mode' => 'Markdown',
                        'message' => 'Юзера "' . $command['entity'] . '" нету.'
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
                $TGClient->messages->sendMessage(['peer' => (array)$alertObj->to_id, 'message' => "Рассылка завершена. Telegram: ".$alertObj->tg_count.", SMS: ".$alertObj->sms_count.", Звонки: ".$alertObj->call_count.", Не просмотрено: ".$alertObj->fail_count]);                
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
                        'message' => "" . $from_username . " Извини, могу ответить только на последнее сообщение c текстом '" . $mentionObj->message . "' . Уверен?"]);
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
                                $TGClient->messages->sendMessage(['peer' => (array)$mention->to_id, 'message' => "" . $from_username . " Ок. Создаю рассылку."]);
                                $alert = Entities\Alert::createFromMention($in_mention, $this->plugin('localdb'));
                                $alert->save();

                                $in_mention->drop();
                                $mention->drop();
                            } else if($location = (new Misc\Location())->checkLocationMessage($mention)) {
                                $from_username = $this->Chat->getSendMessageUsername($mention->from_id);
                                $this->log("Positive answer Detected with Geolocation. Creating new alert.");                                
                                $TGClient->messages->sendMessage(['peer' => (array)$mention->to_id, 'message' => "" . $from_username . " Ок. Создаю рассылку. Добавлю к ней геолокацию."]);
                                $in_mention->media = $mention->media;
                                $in_mention->media->fwd_message_id = $mention->id;

                                $alert = Entities\Alert::createFromMention($in_mention, $this->plugin('localdb'));
                                $alert->save();

                                $in_mention->drop();
                                $mention->drop();
                            } else {
                                $from_username = $this->Chat->getSendMessageUsername($mention->from_id);
                                $TGClient->messages->sendMessage(['peer' => (array)$mention->to_id, 'message' => "" . $from_username . " Не уверен - не создавай алерты."]);

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
                                'message' => "" . $from_username . " Для создания рассылки напиши мне сообщение напрямую."
                            ]);
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

    protected function onInit() {
        $dbdata = (array)$this->plugin('localdb')->getVal('chats');
    }

    protected function onShutdown() {
        $this->log("OH NO!!! we're shutting down! I better do some clean up.");
        $this->Tg->teardown();
        // do stuff here...
    }
    
}