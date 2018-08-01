#!/usr/bin/env php
<?php
// https://github.com/Eleirbag89/TelegramBotPHP/blob/master/Telegram.php
ini_set('display_errors', 'On');
include 'TelegramBotPHP/Telegram.php';


$token    = 'API'; //iphostmd_bot
$o        = getopt('', array(
    'action:',
    'msg:',
    'fileurl:',
    'chat:',
    'debug:'
));

$telegram = new Telegram($token);
switch (@$o['action']) {
    case 'getupdates': {
        $return = $telegram->getUpdates(0, 100, 0, true);
        if ($return['ok'] == true) {
            print_r($return['result']);
        } else {
            die("return is not OK\n");
        }
        break;
    }
    case 'sendmessage': {
        if (!isset($o['msg']) || empty($o['msg'])) {
            die("empty msg argument\n");
        }
        if (isset($o['chat']) && is_numeric($o['chat'])) {
            $chat_id = $o['chat'];
        } else {
            die("Please complete chat argument with chat_id\n");
        }
        $content = array(
            'chat_id' => $chat_id,
            'text' => $o['msg']
        );
        $return  = $telegram->sendMessage($content);
        var_dump($return);
        break;
    }
    case 'sendaudio': {
        if (!isset($o['fileurl'])) {
            die("empty file argument\n");
        }
        if (isset($o['chat']) && is_numeric($o['chat'])) {
            $chat_id = $o['chat'];
        } else {
            die("Please complete chat argument with chat_id\n");
        }
        $content = array(
            'chat_id' => $chat_id,
            'audio' => $o['fileurl'],
        );
        $return  = $telegram->sendAudio($content);
        var_dump($return);
        break;
    }
    default: {
        echo "Usage: $argv[0] --action=(getupdates|sendmessage) --chat='chat_name' --msg='text'\n";
        break;
    }
}
