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

    public function checkCommand($mentionMessage) {
        $resCommand = null;

        $command = explode(" ", $mentionMessage);
        var_dump($command);
        foreach($this->commandDict as $cmd => $words) {
            var_dump($cmd);
            foreach($words as $word) {
                var_dump($word);
                if(mb_strpos($command[0], $word) !== false) {
                    array_shift($command);
                    $resCommand = [
                        'command' => $cmd,
                        'entity' => implode(" ", $command)
                    ];
                }
            }
        }

        if($resCommand != null) {
            return $resCommand;
        }
        
        return false;
    }
}
