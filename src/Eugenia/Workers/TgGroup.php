<?php

    namespace Eugenia\Workers;

    use Lifo\Daemon\LogTrait;
    use Lifo\Daemon\Promise;
    use Lifo\Daemon\Worker\WorkerInterface;

    class TgGroup implements WorkerInterface {

        private $time_update;
        private $conn;
        private $api;
        private $parent;

        public function __construct($api, $parent, $conn) {
            $this->parent = $parent;
            $this->api = $api;
            $this->conn = $conn;
        }

        public function initialize() {
            $this->time_update = time();
        }
    }