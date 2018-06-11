<?php

    namespace Eugenia\Workers;
    use Eugenia\Misc;

    use Lifo\Daemon\LogTrait;
    use Lifo\Daemon\Promise;
    use Lifo\Daemon\Worker\WorkerInterface;

    class TgConn implements WorkerInterface {

        use LogTrait;

        const STATUS_OFFLINE = 1;
        const STATUS_PENDING_SMS = 2;
        const STATUS_PENDING_CALL = 3;
        const STATUS_ONLINE = 4;
        const STATUS_ONLINE_LOGGEDIN = 5;
        const STATUS_ONLINE_STARTED = 6;

        const STARTUP_PAUSE = 5;
        const SMS_UPDATE_PAUSE = 125;
        const CALL_UPDATE_PAUSE = 60;
        const ONLINE_UPDATE_PAUSE = 60;
        
        private $conn_status;
        private $time_update;
        private $api;
        private $phone_data;

        public function __construct($api, $parent) {
            $this->parent = $parent;
            $this->api = $api;
        }

        public function initialize() {
            $this->time_update = time();
            $this->conn_status = TgConn::STATUS_OFFLINE;
        }

        public function log($message) {
            $this->parent->log($message);
        }

        public function getConnection() {
            $TGClient = $this->api->getTelegramClient();
            if($TGClient->get_self() && 
                $this->conn_status == TgConn::STATUS_OFFLINE) {
                $TGClient->start();
                $this->conn_status = TgConn::STATUS_ONLINE_STARTED;
                return $TGClient;                
            }

            switch($this->conn_status) {
                case TgConn::STATUS_ONLINE_STARTED: {
                    $this->conn_status = TgConn::STATUS_ONLINE;
                    $users = $this->parent->plugin('config')->getVal('users');
                    $admin = $TGClient->get_full_info($users->admin);

                    $peer = [
                        '_' => 'user',
                        'id' => $admin['User']['id']
                    ];

                    $TGClient->messages->sendMessage([
                        'peer' => $peer, 
                        'message' => Misc\LangTemplate::getInstance()->get('conn_going_online')
                    ]);
                    return true;
                }

                case TgConn::STATUS_ONLINE_LOGGEDIN: {
                    $this->conn_status = TgConn::STATUS_ONLINE;
                    $users = $this->parent->plugin('config')->getVal('users');
                    $admin = $TGClient->get_full_info($users->admin);

                    $peer = [
                        '_' => 'user',
                        'id' => $admin['User']['id']
                    ];

                    $TGClient->messages->sendMessage([
                        'peer' => $peer, 
                        'message' => Misc\LangTemplate::getInstance()->get('conn_logged_in')
                    ]);
                    return true;
                }

                case TgConn::STATUS_OFFLINE:{
                    if(time() > $this->time_update + TgConn::STARTUP_PAUSE) {
                        $phone = $this->api->getPhoneNumber();
                        $this->phone_data = $TGClient->phone_login($phone);

                        $this->time_update = time();

                        if($this->phone_data) {
                            $this->log('Call result:' . print_r($this->phone_data, true));                            
                            $this->conn_status = TgConn::STATUS_PENDING_SMS;
                        } else {
                            $this->log('Pending SMS failed. Will try in ' . TgConn::STARTUP_PAUSE . ' seconds.');
                        }
                    }
                    return false;
                }

                case TgConn::STATUS_PENDING_SMS:{
                    if(time() > $this->time_update + TgConn::SMS_UPDATE_PAUSE) {
                        $phone = $this->api->getPhoneNumber(); 

                        $result = $TGClient->method_call('auth.sendCall', [
                            'phone_number' => $this->phone_data['phone_number'], 
                            'phone_code_hash' => $this->phone_data['phone_code_hash']
                        ], ['datacenter' => $TGClient->API->authorized_dc]);

                        if($result) {
                            $this->log('Call result:' . print_r($result, true));                            
                            $this->conn_status = TgConn::STATUS_PENDING_CALL;
                        } else {
                            $this->log('Call failed. Will try in ' . TgConn::SMS_UPDATE_PAUSE . ' seconds.');                            
                        }
                        $this->time_update = time();
                    }
                    return false;
                }

                case TgConn::STATUS_PENDING_CALL:{
                    if(time() > $this->time_update + TgConn::CALL_UPDATE_PAUSE) {
                        if(is_file(getcwd() . "/logs/login_cdde")) {
                            $code = file_get_contents(getcwd() . "/logs/login_cdde");
                            $code = trim($code);

                            unlink(getcwd() . "/logs/login_cdde");
                            $result = $TGClient->complete_phone_login($code);

                            if ($result['_'] === 'account.needSignup') {
                                $result = $TGClient->complete_signup('Eugenia', '');
                            }

                            if(is_object($result)) {
                                $this->conn_status = TgConn::STATUS_ONLINE_LOGGEDIN;
                                $this->time_update = time();
                                return true;
                            }
                        } else {
                            $this->log("Seems like call not made. Switch back to SMS");
                            $this->conn_status = TgConn::STATUS_OFFLINE;
                            $this->time_update = time();                            
                        }
                    } 
                    return false;
                }                   

                case TgConn::STATUS_ONLINE: {
                    return true;
                }                  
            }
        }

        public function teardown() {
            $TGClient   = $this->api->getTelegramClient();      
            $users      = $this->parent->plugin('config')->getVal('users');
            $admin      = $TGClient->get_full_info($users->admin);

            $peer = [
                '_' => 'user',
                'id' => $admin['User']['id']
            ];
    
            $TGClient->messages->sendMessage([
                'peer' => $peer, 
                'message' => Misc\LangTemplate::getInstance()->get('conn_going_offline')
            ]);      
        }
    }
