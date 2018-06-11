<?php

namespace Eugenia\Misc;

class Parser {

    public $dict = [
        "да",
        "+",
        "конечно",
        "yes",
        "так",
        "валяй",
        "создавай",
        "вперед",
        "ага",
        "ну да",
        "lf"
    ];

    public $commandDict = [
        'checkUser' => [
            'check',
            'проверь'
        ],

        'helpMessage' => [
            'help',
            'помощь',
            'рудз',
            'gjvjom'
        ],

        'disableSMS' => [
            'no sms',
            'без смс',
            'не писать',
            'ты',
            'ns'
        ],

        'disableCalls' => [
            'no calls',
            'без звонка',
            'без звонков',
            'не звонить',
            'nc',
            'тс'
        ]
    ];

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

    public function checkCommand($mentionMessage, $mention = false) {
        $resCommand = null;

        $command = explode(" ", $mentionMessage);

        foreach($this->commandDict as $cmd => $words) {
            if($resCommand) {
                continue;
            }
            foreach($words as $word) {
                if($resCommand) {
                    continue;
                }
                
                if(mb_strpos($command[0], $word) !== false) {
                    array_shift($command);

                    if(count($command) > 0) {
                        $resCommand = [
                            'command' => $cmd,
                            'entity' => implode(" ", $command)
                        ];
                        if($mention) {
                            $resCommand['mention'] = $mention;
                        }
                    } else {
                        $resCommand = [
                            'command' => $cmd
                        ];                        
                        if($mention) {
                            $resCommand['mention'] = $mention;
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
