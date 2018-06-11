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
    private $nexmo_answer_url;
    private $nexmo;

    private $bitly_api_token;
    private $bitly;

    public function setup($options = []) {
        $this->tg_api_id            = $options['config_tg_api_id'];
        $this->tg_api_hash          = $options['config_tg_api_hash'];
        $this->tg_rsa_keys          = $options['config_tg_rsa_keys'];
        $this->tg_mtproto_server    = $options['config_tg_mtproto_server'];
        $this->session_path         = $options['config_session_path'];

        $this->twilio_sid           = $options['config_twilio_sid'];
        $this->twilio_token         = $options['config_twilio_token'];

        $this->nexmo_key            = $options['config_nexmo_key'];
        $this->nexmo_token          = $options['config_nexmo_token'];

        $this->phone_number         = $options['config_phone_number'];
        $this->nexmo_phone_number   = $options['config_nexmo_phone_number'];
        $this->nexmo_answer_url     = $options['config_nexmo_answer_url'];

        $this->bitly_api_token      = $options['config_bitly_api_token'];
    }

    public function teardown() { 
        /* haven't any shared data */ 

    }

    public function getNexmoClient() {
        $this->log('Nexmo client requested.');

        $basic  = new \Nexmo\Client\Credentials\Basic($this->nexmo_key, $this->nexmo_token);
        $keypair = new \Nexmo\Client\Credentials\Keypair(file_get_contents(getcwd() . '/data/nexmo_private.key'), 'c957da8b-58f4-4af0-a458-74c941e0cea1');
        $this->nexmo = new \Nexmo\Client(new \Nexmo\Client\Credentials\Container($basic, $keypair));

        return $this->nexmo;
    }

    public function getTwilioClient() {
        $this->log('Twilio client requested.');
        if(!$this->twilio) {
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

    public function getNexmoAnswerUrl() {
        return $this->nexmo_answer_url;
    }

    public function getBitlyApiToken() {
        return $this->bitly_api_token;
    }
}