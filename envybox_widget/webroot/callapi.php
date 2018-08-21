<?php
error_reporting(0);

$arList = array(
        'Внутренние номера' => array(
            '101',
            '102',
            '103',
        ),
        'Внешние номера' => array(
            '37322830304'
        ),
);

$a = $_GET['a'];
$from = trim($_GET['from']);
$to = trim($_GET['to']);


if($a == 'status') {
    echo '200 OK';
    die();
} elseif ($a == 'call' || ($from != '' && $to != '' && strlen($from) == 3)) {
        $str = "Channel: PJSIP/$from
MaxRetries: 0
RetryTime: 0
WaitTime: 300
Callerid: $to
Extension: $to
Priority: 1";
        $res = file_put_contents('/var/spool/asterisk/outgoing/'.$to.'.call', $str);
        echo "200 OK";
} elseif ($a == 'list') {
    echo json_encode($arList, JSON_UNESCAPED_UNICODE);
    die();
} elseif ($a == 'gertrecord') {
    $callid = trim($_GET['callid']);
    if($callid != '') {
        $arRecord = getrecord($callid);
        if(trim($arRecord['recordingfile']) != '') {
            $time = strtotime($arRecord['calldate']);
            $file = '/var/spool/asterisk/monitor/'.date('Y', $time).'/'.date('m', $time).'/'.date('d', $time).'/'.$arRecord['recordingfile'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file);
            finfo_close($finfo);

            header('Content-Description: File Transfer');
            header('Content-Type: '.$mime);
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            readfile($file);
            exit;
        }
    }
} elseif ($a == 'record') {
    $callid = trim($_GET['callid']);
    if($callid != '') {
        $arRecord = getrecord($callid);
        echo "<pre>"; print_r($arRecord); echo "</pre>";
        die();
    }
}

function getrecord($uid) {
    $PDO = new PDO('mysql:host=localhost;dbname=asteriskcdrdb', 'USER', 'PASS');
    $result = $PDO->query("SELECT * FROM cdr WHERE uniqueid='$uid'");
    return $result->fetch(2);
}
