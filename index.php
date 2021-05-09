<?php

require 'classes\server.php';
use Server\Server;

$server = new Server('127.0.0.1', 1111);
$socket = $server->connectSocket();
$server->answer($socket);