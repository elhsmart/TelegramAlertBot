<?php

namespace Eugenia\Plugins;

use Lifo\Daemon\Plugin;
use Lifo\Daemon\Plugin\PluginInterface;
use danog\MadelineProto\API as MadeApi;
use Twilio\Rest\Client;

class Api implements PluginInterface {

    use \Lifo\Daemon\LogTrait;

    private $config;

    private $tg_api_id;
    private $tg_api_hash;
    private $phone_number;
    private $nexmo_phone_nmber;

    private $tg_rsa_keys;

    private $tg_mtproto_server;

    private $session_path;
    private $madeline;

    private $twilio_sid;
    private $twilio_token;
    private $twilio;

    private $nexmo_key;
    private $nexmo_token;
    private $nexmo;

    public function setup($options = []) {
        include(__DIR__ . DIRECTORY_SEPARATOR .  ".."  . DIRECTORY_SEPARATOR . "Config.php");

        $this->tg_api_id            = $config_tg_api_id;
        $this->tg_api_hash          = $config_tg_api_hash;
        $this->tg_rsa_keys          = $config_tg_rsa_keys;
        $this->tg_mtproto_server    = $config_tg_mtproto_server;
        $this->session_path         = $config_session_path;

        $this->twilio_sid           = $config_twilio_sid;
        $this->twilio_token         = $config_twilio_token;

        $this->nexmo_key            = $config_nexmo_key;
        $this->nexmo_token          = $config_nexmo_token;

        $this->phone_number         = $config_phone_number;
        $this->nexmo_phone_number   = $config_nexmo_phone_number;
    }

    public function teardown() { 
        /* haven't any shared data */ 

    }

    public function getNexmoClient() {
        $basic  = new \Nexmo\Client\Credentials\Basic($this->nexmo_key, $this->nexmo_token);
        $keypair = new \Nexmo\Client\Credentials\Keypair(file_get_contents(getcwd() . '/data/nexmo_private.key'), 'c957da8b-58f4-4af0-a458-74c941e0cea1');
        $this->nexmo = new \Nexmo\Client(new \Nexmo\Client\Credentials\Container($basic, $keypair));

        return $this->nexmo;
    }

    public function getTwilioClient() {
        if(!$this->twilio) {
            var_dump($this->twilio_sid, $this->twilio_token);
            $this->twilio = new Client($this->twilio_sid, $this->twilio_token);            
        }

        return $this->twilio;
    }

    public function getTelegramClient() {
        if(!$this->madeline) {
            $settings = [
                'updates' => [
                    'handle_updates' => true
                ],
                'authorization' => [
                    'rsa_keys' => $this->tg_rsa_keys
                ],
                'connection_settings' => [
                    'all' => [
                        'test_mode' => false
                    ]
                ],
                'app_info' => [
                    'api_id' => $this->tg_api_id,
                    'api_hash' => $this->tg_api_hash
                ],
                'peer' => [
                    'full_fetch' => true,
                    'cache_all_peers_on_startup' => true
                ]
            ];

            $this->madeline = new MadeApi($this->session_path, $settings);
        } 

        return $this->madeline;
    }

    public function getPhoneNumber() {
        return $this->phone_number;
    }

    public function getNexmoPhoneNumber() {
        return $this->nexmo_phone_number;
    }

    public function getRSAKeys() {
        return $this->tg_rsa_keys;
    }
    public function getMTProtoServer() {
        return $this->tg_mtproto_server;
    }
}