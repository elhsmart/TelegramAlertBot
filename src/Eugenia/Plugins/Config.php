<?php

namespace Eugenia\Plugins;

use Lifo\Daemon\Plugin;
use Lifo\Daemon\Plugin\PluginInterface;

class Config implements PluginInterface {

    use \Lifo\Daemon\LogTrait;

    private $config_path;
    private $readonly;
    private $data;

    private $file;
    /**
     * Initial setup of plugin. Perform all one-time setup steps needed for the plugin.
     *
     * @param array $options Array of custom options
     */
    public function setup($options = []) {
        $continue = true;
        if(!isset($options['config_path'])){
            $this->config_path = dirname(__FILE__) . DIRECTORY_SEPARATOR . "config.json";
            $this->error("Configuration path is not set in Config plugin. Using defaults: " . $this->config_path);
            $continue = false;
        } else {
            $this->config_path = $options['config_path'];
        }
        
        if($continue && (!is_file($this->config_path) || !is_readable($this->config_path))) {
            $this->config_path = dirname(__FILE__) . DIRECTORY_SEPARATOR . "config.json";
            $this->error("Error with config file assess. Fallback to defaults: " . $this->config_path);
        }

        if(isset($options['readonly'])) {
            $this->readonly = $options['readonly'];
        }

        $this->load();
    }

    
    /**
     * Teardown the plugin. Release all resources created during the plugins lifetime.
     */
    public function teardown() {
        $daemon = \Eugenia\Bot::getInstance();
        
        if($daemon->isParent()) {
            if($this->readonly) {
                $this->log("Readonly config. Sorry, no save.");
            } else {
                $this->log("Saving config on shutdown.");
                $this->data->microtime = microtime();
                $this->save();
            }
        }
    }

    public function load() {
        if(!is_file($this->config_path) || !is_readable($this->config_path)) {
            $this->error("Config file is not readable.");
            $this->data = new \stdClass();
            return;
        }

        $this->data = json_decode(file_get_contents($this->config_path));

        if(json_last_error() != JSON_ERROR_NONE) {
            $this->error("Config JSON parsing error.");
            $this->data = new \stdClass(); 
        }
    }

    public function save() {
        if($this->readonly) {
            $this->log("Cannot save readonly config.");
        } else {
            file_put_contents($this->config_path, json_encode($this->data, JSON_PRETTY_PRINT) . "\n");
        }
    }    

    public function getVal($name) {
        return $this->data->$name;
    }

    public function setVal($name, $value) {
        $this->data->$name = $value;
    }
}