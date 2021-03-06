<?php 

namespace Eugenia\Misc;

class Media {
    
    public function checkMediaLocation($update) {
        if(isset($update['media']) && count($update['media']) > 0) {
            if((new Location())->checkLocationMessage($update)) {
                return true;
            }
        }

        return false;
    }

    public function checkMediaAudio($message) {
        if(isset($message['media']) && count($message['media']) > 0) {
            $type = array_shift($message['media']);
            $doc  = array_shift($message['media']);

            if($type == 'messageMediaDocument' && isset($doc['attributes'])) {
                foreach($doc['attributes'] as $attribute) {
                    if($attribute['_'] == 'documentAttributeAudio' 
                        // Support only voice messages.
                        && $attribute['voice'] == 'true') {
                            return true;
                        }
                }
            }
        }

        return false;
    }

    public function checkMediaVideo($message) {
        if(isset($message['media']) && count($message['media']) > 0) {
            $type = array_shift($message['media']);
            $doc  = array_shift($message['media']);

            if($type == 'messageMediaDocument' && isset($doc['attributes'])) {
                foreach($doc['attributes'] as $attribute) {
                    // We support both round and telescope video types.
                    if($attribute['_'] == 'documentAttributeVideo') {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}