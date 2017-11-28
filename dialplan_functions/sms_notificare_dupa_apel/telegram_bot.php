#!/usr/bin/env php
<?php
// https://github.com/Eleirbag89/TelegramBotPHP/blob/master/Telegram.php
ini_set('display_errors', 'Off');
include 'TelegramBotPHP/Telegram.php';


$token    = '468030648:AAEZ06d_DHbQ1udUH51TmK3fnJ2cLtPzhBo'; //iphostmd_bot
$o        = getopt('', array(
    'action:',
    'msg:',
    'chat:',
    'debug:'
));

$chats    = array(
    'sales' => -285995895
);

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
        if (isset($o['chat']) && isset($chats[$o['chat']])) {
            $chat_id = $chats[$o['chat']];
        } else {
            die("Please complete chats list\n");
        }
        $content = array(
            'chat_id' => $chat_id,
            'text' => $o['msg']
        );
        $return  = $telegram->sendMessage($content);
        var_dump($return);
        break;
    }
    default: {
        echo "Usage: $argv[0] --action=(getupdates|sendmessage) --chat='chat_name' --msg='text'\n";
        break;
    }
}