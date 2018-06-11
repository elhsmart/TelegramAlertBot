<?php

namespace Eugenia\Misc;

class Location {

    public function checkLocationMessage($mention) {
        if(strlen($mention->message) != 0) {
            return false;
        }

        if($mention->media == null) {
            return false;
        }

        if($mention->media->_ != 'messageMediaGeo' && $mention->media->_ != 'messageMediaGeoLive') {
            return false;
        }

        return true;
    }

}