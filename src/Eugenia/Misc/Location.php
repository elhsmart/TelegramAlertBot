<?php

namespace Eugenia\Misc;

class Location {

    public function checkLocationMessage($mention) {
        if(is_array($mention)) {
            if(strlen($mention['message']) != 0) {
                return false;
            }

            if($mention['media'] == null) {
                return false;
            }
    
            if($mention['media']['_'] != 'messageMediaGeo' && $mention['media']['_'] != 'messageMediaGeoLive') {
                return false;
            }            
        } else if(is_object($mention)) {
            if(strlen((object)$mention->message) != 0) {
                return false;
            }

            if($mention->media == null) {
                return false;
            }
    
            if($mention->media->_ != 'messageMediaGeo' && $mention->media->_ != 'messageMediaGeoLive') {
                return false;
            }
        }
        return true;
    }

}