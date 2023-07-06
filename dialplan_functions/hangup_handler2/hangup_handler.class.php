<?php

require_once 'PHPMailer/class.phpmailer.php';
require_once 'PHPMailer/class.smtp.php';

class hangup_handler {
	private $config = null;
	private $default_teams_card = '{"summary":"Apel pierdut","themeColor":"0078D7","@type":"MessageCard","sections":[{"facts":[{"name":"Numar client:","value":"null"},{"name":"Nume client:","value":"null"},{"name":"Numar companie:","value":"022011011"},{"name":"Departament selectat:","value":"Oh yaya"}],"text":"Apel pierdut, trebuie retelefonat"}]}';
	
	function __construct($config) {
		$this->config = $config;
	}
	
	public function check_is_missing($o) {
		if ($o['dst'] != '' && ($o['disposition'] == 'ANSWERED' || $o['disposition'] == 'BUSY' || $o['disposition'] == 'ANSWER')) {
			$this->debug("apel cu raspuns");
			exit;
		}
		if (!isset($o['srcname']) || empty($o['srcname'])) {
			$o['srcname'] = $o['src'];
		}
		if (preg_match("/^[0-9]{1,3}$/", $o['src'])) {
			$this->debug("apel de iesire");
			exit;
		}
	}

	public function store_missed_call($o, $file) {
		if (is_file($file)) $file_exists = 1;
		$fp = fopen($file, 'a+');
		if ($file_exists == 0) fwrite($fp, '"ora-data";"numar client";"numar apelat";"dispozitia"' . "\n");
		fwrite($fp, '"' . date("d.m.Y H:i:s") . '";"' . $o['src'] . '";"' . $o['did'] . '";"' . $o['disposition'] . "\"\n");
		fclose($fp);
	}

	public function send_missed_call_email_report($destination, $attachment) {
		$mail = new PHPMailer();
		if ($this->config['email']['use_smtp'] == 1) {
			$mail->isSMTP();
			$mail->Host = $this->config['email']['smtphost'];
			$mail->Port = 587;
			$mail->SMTPSecure = '';
			$mail->SMTPAuth = true;
			$mail->Username = $this->config['email']['username'];
			$mail->Password = $this->config['email']['password'];
			// $mail->SMTPDebug = 2;
			$mail->SMTPOptions = array('ssl' => array('verify_peer' => false));
		}

		$mail->From = $this->config['email']['from'];
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

	public function send_missed_call_email($caller, $destination) {
		global $mail;
		$mail = new PHPMailer();
		if ($this->config['email']['use_smtp'] == 1) {
			$mail->isSMTP();
			$mail->Host = $this->config['email']['smtphost'];
			$mail->Port = 587;
			$mail->SMTPSecure = '';
			$mail->SMTPAuth = true;
			$mail->Username = $this->config['email']['username'];
			$mail->Password = $this->config['email']['password'];
			// $mail->SMTPDebug = 2;
			$mail->SMTPOptions = array('ssl' => array('verify_peer' => false));
		}
		
		$list = explode(",", $destination);
		foreach($list as $email) {
			$mail->AddAddress($email);
		}
		
		$mail->From = $this->config['email']['from'];
		$mail->FromName = 'FreePBX';
		$mail->Subject = 'PBX: apel pierdut: ' . $o['src'];
		$mail->Body = get_missedcall_template();
		

		if (!$mail->send()) {
			$this->debug("Mailer Error: " . $mail->ErrorInfo);
		}
		else {
			$this->debug("Message sent!");
		}
	}
	
	public function send_telegram_msg($department = '', $message) {
		$chat = $this->get_destination('telegram', $department);
		$post_data = json_encode(array('chat_id' => $chat, 'message' => $message));
		
		$ch = curl_init(); 
		curl_setopt($ch, CURLOPT_URL, $this->config['notification_server_url'].'/telegram_message'); 
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

		$result = curl_exec($ch); 
		var_dump(curl_error($ch));
		curl_close($ch); 
	}
	
	public function send_slack_msg($department = '', $message) {
		$chat = $this->get_destination('slack', $department);
		$post_data = json_encode(array('channel_id' => $chat, 'message' => $message));
		
		$ch = curl_init(); 
		curl_setopt($ch, CURLOPT_URL, $this->config['notification_server_url'].'/slack_message'); 
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

		$result = curl_exec($ch); 
		var_dump(curl_error($ch));
		curl_close($ch); 
	}
	
	public function send_mteams_message($department = 'neselectat', $number, $name, $did, $mesaj=null) {
		$webhook_url = $this->get_destination('teams', $department);
		
		$txt = json_decode($this->default_teams_card);
		$txt->sections[0]->facts[0]->value = $number;
		$txt->sections[0]->facts[1]->value = $name;
		$txt->sections[0]->facts[2]->value = $did;
		$txt->sections[0]->facts[3]->value = $department;
		if($mesaj !== null) {
			$txt->summary = $mesaj;
			$txt->sections[0]->text = $mesaj;
		}
		$json_encoded = json_encode($txt);
		var_dump($json_encoded);
		
		$ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
		curl_setopt($ch, CURLOPT_URL, $webhook_url); 
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $json_encoded);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

		$result = curl_exec($ch); 
		var_dump(curl_error($ch));
		curl_close($ch); 
	}
	
	public function get_destination($type = 'email', $department=null) {
		if(!empty($department) && isset($this->config[$type]['departments'][$department])) {
			return $this->config[$type]['departments'][$department];
		}
		return $this->config[$type]['default_destination'];
	}
	
	public function get_email_by_csv($extension, $csvfile) {
		if (($handle = @fopen($csvfile, "r")) !== false) {
			while (($data = fgetcsv($handle, 100, ",")) !== false) {
				if (!empty($data[0]) && !empty($extension) && $data[0] == $extension) {
					// found required extension
					return $data[1];
				}
			}
			fclose($handle);
		}
		return $this->config['email']['default_destination'];
	}

	public function get_manager_by_callerid($callerid) {
		if(preg_match("/[^|]+ \| ([0-9]+)/", $callerid, $matches)) {
			return $matches[1];
		}
		return '';
	}

	public function get_missedcall_template($o) {
		return <<<EOF
Apel pierdut, receptionat pe numarul ${o['did']} de la ${o['src']} (${o['srcname']}) 
EOF;
	}

	public function debug($msg) {
		if($this->config['debug']!==1) return;
		$date = date('d.m.Y H:i:s');
		echo "$date: $msg\n";
	}

}
// +------------------------------+//

?>
