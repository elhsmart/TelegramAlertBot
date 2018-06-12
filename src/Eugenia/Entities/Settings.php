<?php

namespace Eugenia\Entities;
use Eugenia\Misc;

use Lifo\Daemon\Plugin;
use Lifo\Daemon\Plugin\PluginInterface;
use danog\MadelineProto\API as MadeApi;

class Settings {
    
    public $user_id;
    public $calls_enabled;
    public $sms_enabled;

    public $update_time;

    private $db;

    public static function getAndCheck($user_id, $db, $sms_enabled = true, $calls_enabled = true) {
        $userSettings = self::getById($user_id, $db);
        if(!$userSettings) {
            $dumbSettings = [
                'user_id' => $user_id,
                'calls_enabled' => $sms_enabled,
                'sms_enabled' => $calls_enabled
            ];
            $userSettings = new self($dumbSettings,  $db);
            $userSettings->save();
        }

        return $userSettings;
    }

    public static function getById($id, $db) {
        $entity = new self(false, $db);
        $entity->user_id = $id;
        $hash = $entity->getHash();

        $entityData = $db->getNestedVal('settings', $hash);

        if($entityData) {
            $entity->load();
            return $entity;
        }

        return false;
    }

    public function __construct($settings = false, $db) {
        $this->db = $db;
        if($settings) {
            $this->parse($settings);
        }
    }

    public function save() {
        $this->db->setNestedVal('settings', $this->getHash(), $this->serialize());
    }

    public function serialize() {
        $settings = [];

        $settings['user_id'] = $this->user_id;
        $settings['calls_enabled'] = $this->calls_enabled;
        $settings['sms_enabled'] = $this->sms_enabled;
        $settings['update_time'] = $this->update_time;

        return $settings;
    }

    public function parse($user) {
        $user = (array)$user;

        $this->user_id = $user['user_id'];

        if(isset($user['calls_enabled'])) {
            $this->calls_enabled = $user['calls_enabled'];
        }

        if(isset($user['sms_enabled'])) {        
            $this->sms_enabled = $user['sms_enabled'];
        }

        if(isset($user['update_time'])) {        
            $this->update_time = $user['update_time'];
        } else {
            $this->update_time = time();
        }
    }

    public function getHash() {
        $user_id = $this->user_id;
        return md5("settings_" . $user_id);
    }

    public function load() {
        $this->parse($this->db->getNestedVal('settings', $this->getHash()));
    }

    public function drop() {
        $this->db->dropNestedVal('settings', $this->getHash());
    }
}
