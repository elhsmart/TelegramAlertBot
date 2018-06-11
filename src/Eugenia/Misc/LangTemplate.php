<?php

    namespace Eugenia\Misc;

    class LangTemplate {
        
         private static $instance = null;
        private $langValues;

        private function __clone() {}
        private function __construct() {}

        public static function getInstance($locale = null, $config = null) {

            if (null === self::$instance) {
                self::$instance = new self();
                self::$instance->langValues = $config->getVal($locale);
            
                if(self::$instance->langValues == null) {
                    throw new \Exception('Locale file not found');
                }
            }

            return self::$instance;
        }

        public function get() {
            $arguments = func_get_args();

            if(count($arguments) == 0) {
                throw new \Exception('Please provide valid string for language value');
            }

            if(!is_string($arguments[0])) {
                throw new \Exception('Language value must be string');
            }

            $stringCode = array_shift($arguments);
            $resString = $this->langValues->{$stringCode};

            if(is_array($resString)) {
                $resString = implode("", $resString);
            }

            if(is_object($resString)) {
                return (array)$resString;
            }

            return mb_vsprintf($resString, $arguments);
        }
    }