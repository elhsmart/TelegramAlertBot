<?php

namespace Eugenia\Misc;

class Parser {

    public $dict = [];

    public $commandDict = [
        'checkUser' => [],
        'helpMessage' => [],
        'disableSMS' => [],
        'disableCalls' => []
    ];

    public function __construct() {
        $this->dict = LangTemplate::getInstance()->get('parser_dict_main');

        $this->commandDict['checkUser'] = LangTemplate::getInstance()->get('parser_dict_check_user');
        $this->commandDict['helpMessage'] = LangTemplate::getInstance()->get('parser_dict_help_message');
        $this->commandDict['disableSMS'] = LangTemplate::getInstance()->get('parser_dict_disable_sms');
        $this->commandDict['disableCalls'] = LangTemplate::getInstance()->get('parser_dict_disable_calls');
    }

    public function checkPositiveAnswer($text) {

        $text = mb_trim($text);
        $text = mb_strtolower($text);
        $text = str_replace("eugenia ", "", $text);

        foreach($this->dict as $key => $val) {
            if(mb_strpos($text, $val) !== false) {
                return true;
            }
        }
        return false;
    }

    public function checkCommand($mentionMessage) {
        $resCommand = null;

        $command = explode(" ", $mentionMessage);
        $checkCommand = "";
        while(count($command) > 0) {

            $checkCommand = trim($checkCommand .= " " . array_shift($command));

            if($resCommand) {
                continue;
            }

            foreach($this->commandDict as $cmd => $words) {
                if($resCommand) {
                    continue;
                }
                foreach($words as $word) {
                    if($resCommand) {
                        continue;
                    }
                    
                    if(mb_strpos($checkCommand, $word) !== false) {
                        if(count($command) > 0) {
                            $resCommand = [
                                'command' => $cmd,
                                'entity' => implode(" ", $command)
                            ];
                        } else {
                            $resCommand = [
                                'command' => $cmd
                            ];
                        }
                    }
                }
            }
        }

        if($resCommand != null) {
            return $resCommand;
        }
        
        return false;
    }
}
