<?php

define('DS', DIRECTORY_SEPARATOR);
define('ROOT', dirname(__FILE__).DS.'..'.DS.'web');

define('DEBUG', true);

error_reporting(E_ALL || ~E_NOTICE);
ini_set('display_errors', 1);

include_once(ROOT.DS.'inc'.DS.'core.php');

$settings = loadSettings();
if(!$settings['POP3PORT'])
    $pop3port = $settings['POP3PORT']?:110;

echo "[i] Server started\n";
flush();


while(1)
{
    // listen for incoming connection
    $listen_socket = socket_create_listen($pop3port, 1);
    $r = $w = $e = array($listen_socket);
    $n = socket_select($r, $w, $e, 120);
    $client_socket = ($n == 1) ? socket_accept($listen_socket) : null;
    socket_close($listen_socket);

    if(DEBUG===true)
        echo("[+] New client connected").PHP_EOL;

    if(!$client_socket) {
        // timed out
        exit;
    }

    // start handling the session
    $read_buffer = "";
    $write_buffer = "+OK POP3 server ready\r\n";
    $active = true;

    $messages = [];


    $idle_start = time();
    while(true) {
        $r = $w = $e = array($client_socket);
        $n = socket_select($r, $w, $e, 60);
        if($n) {
            if($r) {
                // read from the socket
                //error_log("reading from socket");
                $read_buffer .= socket_read($client_socket, 128);
                $idle_start = time();
            }
            if($w) {
                if($write_buffer) {
                    // write to the socket
                    //error_log("writing to socket");
                    $written = socket_write($client_socket, $write_buffer);
                    $write_buffer = substr($write_buffer, $written);
                    $idle_start = time();
                } else if($active) {
                    $now = time();
                    $idle_time = $now - $idle_start;
                    if($idle_time > 10) {
                        // exit if nothing happened for 10 seconds
                        error_log("timeout 1");
                        break;
                    } else if($idle_time > 2) {
                        // start napping when the client is too slow
                        error_log("timeout 2");
                        sleep(1);
                    }
                } else {
                    break;
                }
            }
            if($e) {
                break;
            }
            if($read_buffer) {
                
                if(preg_match('/(.*?)(?:\s+(.*?))?[\r\n]+/', $read_buffer, $matches)) {
                    $read_buffer = substr($read_buffer, strlen($matches[0]));
                    $tmatches = array_map('trim', $matches);

                    if(DEBUG===true)
                        echo("  [READING FROM CLIENT] ".$tmatches[0]).PHP_EOL;
                    
                    $command = $matches[1];
                    $argument = $matches[2];
                    switch($command) {
                        case 'CAPA':
                            $write_buffer = "+OK Capability list follows\r\n";
                            $write_buffer.= implode("\r\n",["USER","PASS","UIDL"]);
                            $write_buffer .= "\r\n.\r\n";
                            break;
                        break;
                        case 'USER':
                            $username = $argument;
                            $write_buffer .= "+OK $username is welcome here\r\n";

                            $messages = getEmailsOfEmail($username);

                            if(DEBUG===true)
                                echo("  [+] Loaded ".count($messages)." for email $username").PHP_EOL;

                            break;
                        case 'PASS':
                            // we'll accept any password
                            $message_count = count($messages);
                            $write_buffer .= "+OK mailbox has $message_count message(s)\r\n";
                            break;
                        case 'QUIT': 
                            $write_buffer .= "+OK POP3 server signing off\r\n";
                            $active = false;
                            break;
                        case 'STAT':
                            $message_count = count($messages);
                            $mailbox_size = 0;
                            foreach($messages as $message) {
                                $mailbox_size += $message['maillen'];
                            }
                            $write_buffer .= "+OK $message_count $mailbox_size\r\n";
                            break;
                        case 'LIST':
                            $start_index = (int) $argument;
                            $message_count = count($messages) - $start_index;
                            $total_size = 0;
                            for($i = $start_index; $i < count($messages); $i++) {
                                $msg = array_values(array_slice($messages, $i, 1, true))[0];
                                $total_size += $msg['maillen'];
                            }
                            $write_buffer .= "+OK $message_count messages ($total_size octets)\r\n";
                            for($i = $start_index; $i < count($messages); $i++) {
                                $msg = array_values(array_slice($messages, $i, 1, true))[0];
                                $message_id = $i + 1;
                                $message_size = $msg['maillen'];
                                $write_buffer .= "$message_id $message_size\r\n";
                            }
                            $write_buffer .= ".\r\n";
                            break;

                        case 'UIDL':
                            $start_index = (int) $argument;
                            $message_count = count($messages) - $start_index;
                            $write_buffer .= "+OK $message_count messages\r\n";
                            for($i = $start_index; $i < count($messages); $i++) {
                                $message_id = $i + 1;
                                $msg = array_values(array_slice($messages, $i, 1, true))[0];
                                $write_buffer .= "$message_id ".$msg['md5']."\r\n";
                            }
                            $write_buffer .= ".\r\n";

                            break;
                        case 'RETR':
                            $message_id = (int) $argument;
                            $message = array_values(array_slice($messages, ($message_id-1), 1, true))[0];
                            $message_size = $message['maillen'];
                            $write_buffer .= "+OK $message_size octets\r\n";
                            $write_buffer .= getRawEmail($message['email'],$message['id'])."\r\n";
                            $write_buffer .= ".\r\n";
                            break;
                        case 'DELE':
                            $message_id = (int) $argument;
                            $msg = array_values(array_slice($messages, ($message_id-1), 1, true))[0];
                            deleteEmail($msg['email'],$msg['id']);
                            $write_buffer .= "+OK\r\n";
                            break;
                        case 'NOOP':
                            $write_buffer .= "+OK\r\n";
                            break;
                        case 'LAST':
                            $message_count = count($messages) - $start_index;
                            $write_buffer .= "+OK $message_count\r\n";
                            break;
                        case 'RSET':
                            $write_buffer .= "+OK\r\n";
                            break;
                        default:
                            $write_buffer .= "-ERR Unknown command '$command'\r\n";
                    }

                    if(DEBUG===true)
                        echo("  [MY ANSWER] ".trim($write_buffer)).PHP_EOL;

                    if(!$active && DEBUG===true)
                        echo("[-] Client disconnected").PHP_EOL;

                }
            }
        } else {
            break;
        }
    }
}