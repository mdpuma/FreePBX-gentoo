<?php

function store_missed_call($o, $file) {
    if (is_file($file)) $file_exists = 1;
    $fp = fopen($file, 'a+');
    if ($file_exists == 0) fwrite($fp, '"ora-data";"numar client";"numar apelat";"dispozitia"' . "\n");
    fwrite($fp, '"' . date("d.m.Y H:i:s") . '";"' . $o['src'] . '";"' . $o['did'] . '";"' . $o['disposition'] . "\"\n");
    fclose($fp);
}

function send_missed_call_email($destination, $config, $attachment) {
    $mail = new PHPMailer();
    if ($config['email']['smtp'] == 1) {
        $mail->isSMTP();
        $mail->Host = $config['email']['smtphost'];
        $mail->Port = 587;
        $mail->SMTPSecure = '';
        $mail->SMTPAuth = true;
        $mail->Username = $config['email']['username'];
        $mail->Password = $config['email']['password'];
        // $mail->SMTPDebug = 2;
        $mail->SMTPOptions = array('ssl' => array('verify_peer' => false));
    }

    $mail->From = $config['email']['from'];
    $mail->FromName = 'FreePBX';
    $mail->Subject = 'PBX: apeluri pierdute';
    $mail->AddAddress($destination);

    if (!is_file($attachment)) {
        $mail->Body = 'Nu sunt inregistrate apeluri pierdute';
    }
    else {
        $mail->Body = 'Gasiti atasament';
        $mail->AddAttachment($attachment, basename($attachment));
    }
    return $mail->send();
}

function check_is_missing($o) {
    global $config;
    if ($o['dst'] != '' && ($o['disposition'] == 'ANSWERED' || $o['disposition'] == 'BUSY' || $o['disposition'] == 'ANSWER')) {
        if($config['debug']==1) debug("apel cu raspuns");
        exit;
    }
    if (!isset($o['srcname']) || empty($o['srcname'])) {
        $o['srcname'] = $o['src'];
    }
    if (preg_match("/^[0-9]{1,3}$/", $o['src'])) {
        if($config['debug']==1) debug("apel de iesire");
        exit;
    }
}

function send_telegram_msg($chat, $message) {
    global $config;
    
    // using telegram BotApi
    system($config['telegram']['script'].' --action=sendmessage --chat="'.$chat.'" --msg="'.$message.'"');
    
    // using telegram-cli
//     system('echo "'.$message.'" | '.$config['telegram']['script'].' "'.$chat.'"');
}

function get_telegram_chat($department=null) {
    global $o, $config;
    if(isset($o['department']) && !empty($o['department']) && isset($config['telegram']['departments'][$o['department']])) {
        return $config['telegram']['departments'][$o['department']].'"';
    } else {
        return $config['telegram']['default_destination'];
    }
}

function send_email() {
    global $mail, $config, $o;
    $mail = new PHPMailer();
    if ($config['email']['use_smtp'] == 1) {
        $mail->isSMTP();
        $mail->Host = $config['email']['smtphost'];
        $mail->Port = 587;
        $mail->SMTPSecure = '';
        $mail->SMTPAuth = true;
        $mail->Username = $config['email']['username'];
        $mail->Password = $config['email']['password'];
        // $mail->SMTPDebug = 2;
        $mail->SMTPOptions = array('ssl' => array('verify_peer' => false));
    }

    $o['manager'] = get_manager_by_callerid($o['srcname']);
    
    $mail->From = $config['email']['from'];
    $mail->FromName = 'FreePBX';
    $mail->Subject = 'PBX: apel pierdut: ' . $o['src'];
    $mail->AddAddress(get_email_by_csv($o['manager'], $config['managers_file']));
    $mail->Body = get_missedcall_template();
    

    if (!$mail->send()) {
        if($config['debug']==1) debug("Mailer Error: " . $mail->ErrorInfo);
    }
    else {
        if($config['debug']==1) debug("Message sent!");
    }
}

function get_email_by_csv($extension, $csvfile) {
    global $config;
    $result = '';
    if (($handle = @fopen($csvfile, "r")) !== false) {
        while (($data = fgetcsv($handle, 100, ",")) !== false) {
            if (!empty($data[0]) && !empty($extension) && $data[0] == $extension) {
                // found required extension
                return $data[1];
            }
        }
        fclose($handle);
    }
    return $config['email']['to'];
}

function get_manager_by_callerid($callerid) {
    if(preg_match("/[^|]+ \| ([0-9]+)/", $callerid, $matches)) {
        return $matches[1];
    }
    return '';
}

function get_missedcall_template() {
    global $o;
    return <<<EOF
Apel pierdut, receptionat pe numarul ${o['did']} de la ${o['src']} (${o['srcname']}) 
EOF;
}

function debug($msg) {
    $date = date('d.m.Y H:i:s');
    echo "$date: $msg\n";
}

// +------------------------------+//

?>
