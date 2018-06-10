<?php

namespace Eugenia\Plugins;

use Lifo\Daemon\Plugin;
use Lifo\Daemon\Plugin\PluginInterface;

class Db implements PluginInterface {

    use \Lifo\Daemon\LogTrait;

    private $db_path;
    private $data;

    private $file;
    /**
     * Initial setup of plugin. Perform all one-time setup steps needed for the plugin.
     *
     * @param array $options Array of custom options
     */
    public function setup($options = []) {
        $continue = true;
        if(!isset($options['db_path'])){
            $this->db_path = dirname(__FILE__) . DIRECTORY_SEPARATOR . "db.json";
            $this->error("Db path is not set in Db plugin. Using defaults: " . $this->db_path);
            $continue = false;
        } else {
            $this->db_path = $options['db_path'];
        }
        
        if($continue && (!is_file($this->db_path) || !is_readable($this->db_path))) {
            $this->db_path = dirname(__FILE__) . DIRECTORY_SEPARATOR . "db.json";
            $this->error("Error with db file assess. Fallback to defaults: " . $this->db_path);
        }

        $this->load();
    }

    
    /**
     * Teardown the plugin. Release all resources created during the plugins lifetime.
     */
    public function teardown() {
        $daemon = \Eugenia\Bot::getInstance();
        
        if($daemon->isParent()) {
            $this->log("Saving Db on shutdown.");
            $this->data->microtime = microtime();
            $this->save();
        }
    }

    public function load() {
        if(!is_file($this->db_path) || !is_readable($this->db_path)) {
            $this->error("Db file is not readable.");
            $this->data = new \stdClass();
            return;
        }

        $this->data = json_decode(file_get_contents($this->db_path));

        if(json_last_error() != JSON_ERROR_NONE) {
            $this->error("Db JSON parsing error.");
            $this->data = new \stdClass(); 
        }
    }

    public function save() {
        file_put_contents($this->db_path, json_encode($this->data, JSON_PRETTY_PRINT | JSON_OBJECT_AS_ARRAY) . "\n");
    }    

    public function getVal($name) {
        if(isset($this->data->$name)) {
            return $this->data->$name;
        } else {
            return null;
        }
    }

    public function getNestedVal($section, $name) {
        if(isset($this->data->$section)) {
            if(isset($this->data->$section->$name)) {
                return $this->data->$section->$name;
            }
            return null;
        } 
        return null;
    }

    public function setVal($name, $value) {
        $this->data->$name = $value;
        $this->save();
        $this->load();
    }

    public function setNestedVal($section, $name, $value) {
        if(!isset($this->data->$section)) {
            $this->data->$section = new \stdClass();
        }
        $this->data->$section->$name = $value;
        $this->save();
        $this->load();
    }

    public function dropVal($name) {
        unset($this->data->$name);
        $this->save();
        $this->load();
    }

    public function dropNestedVal($section, $name) {
        if(isset($this->data->$section)) {
            unset($this->data->$section->$name);
        }
        $this->save();
        $this->load();
    }
}