<?php
namespace Server;

class Server {

    private $address;
    private $port;

    public function __construct(?string $address, ?string $port) {
        $this->address = $address;
        $this->port = $port;
    }
    
    public function answer($sock) {
        $clients = array();
        do {
            $read = array();
            $read[] = $sock;
            $read = array_merge($read,$clients);
        
            $write = NULL;
            $except = NULL;
            $tv_sec = 5;

            if(socket_select($read, $write, $except, $tv_sec) < 1)
            {
                continue;
            }
        
            if (in_array($sock, $read)) {       
            
                if (($msgsock = socket_accept($sock)) === false) {
                    echo "socket_accept() failed: reason: " . socket_strerror(socket_last_error($sock)) . "\n";
                    break;
                }
                $clients[] = $msgsock;
                $key = array_keys($clients, $msgsock);
                $msg = $this->createMenu($key[0]);
                socket_write($msgsock, $msg, strlen($msg));
            }
        
            foreach ($clients as $key => $client) {      
                if (in_array($client, $read)) {
                    if (false === ($buf = socket_read($client, 2048, PHP_NORMAL_READ))) {
                        echo "socket_read() failed: reason: " . socket_strerror(socket_last_error($client)) . "\n";
                        break 2;
                    }

                    if ( 1 == $buf ) {
                        $talkback = $this->getDiskSpace() . "\r\n";
                        socket_write($client, $talkback, strlen($talkback));
                    } elseif ( 2 == $buf ) {
                        $talkback = $this->getAveragePing('8.8.8.8')."\r\n";
                        socket_write($client, $talkback, strlen($talkback));
                    } elseif ( 3 == $buf ) {
                        unset($clients[$key]);
                        socket_close($client);
                        break;
                    }
                }
            
            }       
        } while (true);
        socket_close($sock);
    }

    public function connectSocket() {
        $this->errorFlush();

        if (($sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false) {
            echo "socket_create() failed: reason: " . socket_strerror(socket_last_error()) . "\n";
        }

        if (socket_bind($sock, $this->address, $this->port) === false) {
            echo "socket_bind() failed: reason: " . socket_strerror(socket_last_error($sock)) . "\n";
        }

        if (socket_listen($sock, 5) === false) {
            echo "socket_listen() failed: reason: " . socket_strerror(socket_last_error($sock)) . "\n";
        }
        return $sock;
    }

    private function closeSocket($client) {
        $linger = array ('l_linger' => 0, 'l_onoff' => 1);
        socket_set_option($socket, SOL_SOCKET, SO_LINGER, $linger);
        socket_close($client);
    }

    private function createMenu($user) {
        $msg = "\nWelcome Client: '$user' \r\n" .
        "Menu: \r\n".
        "1. Get Disk space (total on the server)\r\n".
        "2. Get ping average to 8.8.8.8\r\n".
        "3. To quit\r\n";
        return $msg;
    }

    private function getAveragePing(?string $ip) {
        $result = exec("ping $ip");
        preg_match('/Average = (.*)ms/', $result, $matches, PREG_OFFSET_CAPTURE);
        return $matches[0][0];
    }

    private function errorFlush() {
        error_reporting(E_ALL);
        set_time_limit(0);
        ob_implicit_flush();
    }

    private function getDiskSpace() {
        $bytes = disk_free_space(".");
        $si_prefix = array( 'B', 'KB', 'MB', 'GB', 'TB', 'EB', 'ZB', 'YB' );
        $base = 1024;
        $class = min((int)log($bytes , $base) , count($si_prefix) - 1);
        $talkback = $bytes . '<br />';
        $talkback .= sprintf('%1.2f' , $bytes / pow($base,$class)) . ' ' . $si_prefix[$class] . '<br />';
        return $talkback;
    }
}

$server = new Server('127.0.0.1', 1111);
$socket = $server->connectSocket();
$server->answer($socket);