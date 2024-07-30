<?php
 /**
 * Funzione per l'invio dell'email CLASSICA
 */
require("./lib/PHPMailerAutoload.php");

function inviaMailCLASSICA ($utente, $evento) {
	$mail = new PHPMailer(true);

	$mail->SMTPAuth = true;
	$mail->SMTPSecure = "tls";
	$mail->Host = "MYSMTP";
	$mail->Port = MYSMTPPORT;
	$mail->Username = "MYSMTPUSERNAME";
	$mail->Password = "MYSMTPPSW";

	$mail->IsHTML(true);
	
	//mittente
	$mail->From = "MYSMTPMITTENTE";
	$mail->FromName = "FORMAZIONE";

	//Destinatario
	$mail->AddAddress($utente->email);
	$mail->AddBCC("MYSMTPDESTINATARIOBCC");

	$mail->Subject = "SCADENZA COMPILAZIONE EVENTO: ".$evento->titolo;

	$messaggio = "<font face='Arial' size='2' color='#330000'>Salve,<br>
		Le ricordiamo che in data '".Date('d-m-Y H:i',strtotime($evento->data_termine))."' si chiude la fase di compilazione dei questionari e/o test previsti per l'evento dal titolo '".addslashes($evento->titolo)."'. <br>
Successivamente a tale data NON sarà più possibile accedere alla procedura di compilazione.<br>
<br><br>
SI INFORMA CHE LA COMPILAZIONE DEI MODULI PREVISTI, E' INDISPENSABILE PER CONSENTIRE ALLO SCRIVENTE UFFICIO DI PROCEDERE CON LA CHIUSURA E RENDICONTAZIONE DEL CORSO.
<br><br>
La ringraziamo per la sua collaborazione.<br><br>
				Di seguito il link di accesso:
				<br><br>
				FRONTOFFICEURL
				";

	$mail->Body = $messaggio; 

	if(!$mail->Send())
	{
		return false;
	} else {
		return true;
	}

}//end invia email Classica

//INIZIO SCRIPT
require './config.inc';

//Connetto il DB valutazione_web
$connection = mysql_connect(HOST, USER, PASSWORD) or die;

mysql_select_db(DATABASE, $connection) or die;
mysql_query("SET NAMES 'utf8'");

$data_attuale = date ('Y-m-d H:i:s');

//Seleziono gli eventi che sacdono tra ORE_PREAVVISO ore o meno che siano presenti nella tabella mail_avvisi_eventi con secondo_avviso NULL
$sql_eventi=" 
	SELECT 
		ma.id_evento AS id_evento_avviso,
		ev.*,
		TIMESTAMPDIFF(HOUR, '".$data_attuale."', ev.data_termine) AS ore_mancanti
	FROM eventi ev
	INNER JOIN mail_avvisi_eventi ma ON (ev.id_evento=ma.id_evento AND ma.secondo_avviso IS NULL)
	WHERE  ev.record_attivo=1 AND TIMESTAMPDIFF(HOUR, '".$data_attuale."', ev.data_termine)<=".ORE_PREAVVISO; 

$result_eventi = mysql_query($sql_eventi, $connection);
$num_rows = mysql_num_rows($result_eventi);

$array_eventi=array();
if ($num_rows>0){//C'è almeno un evento
	while($row = mysql_fetch_object($result_eventi) ){
		$array_eventi[] = $row;
	}
}

if (count($array_eventi)>0)
foreach ($array_eventi as $evento){
	if ($evento->gradimento=='1' && $evento->apprendimento=='1'){//Se entrambi i test erano da fare, trovo gli utenti a cui ne manca anche solo uno(grad o appr)
		$sql_utenti=" 
			SELECT *
			FROM utenti
			WHERE 
				id_utente IN (
					SELECT ut.id_utente
					FROM utenti ut
						INNER JOIN utenti_associazione_eventi ass ON (ass.id_utente=ut.id_utente AND ass.record_attivo=1)
						INNER JOIN eventi ev ON (ass.id_evento=ev.id_evento AND ev.record_attivo=1 AND ev.gradimento=1)
						INNER JOIN gradimento gr ON (gr.id_evento=ev.id_evento AND gr.record_attivo=1)
						LEFT JOIN gradimento_partecipazione gp ON (gp.id_gradimento=gr.id_gradimento AND gp.id_utente=ass.id_utente  AND gp.record_attivo=1)
					WHERE  ut.record_attivo=1 AND ut.email<>'' AND ut.email IS NOT NULL AND ass.id_evento=".$evento->id_evento." AND gp.id_utente IS NULL
					GROUP BY ut.id_utente
				)
				OR id_utente IN(
					SELECT ut.id_utente
					FROM utenti ut
						INNER JOIN utenti_associazione_eventi ass ON (ass.id_utente=ut.id_utente AND ass.record_attivo=1)
						INNER JOIN eventi ev ON (ass.id_evento=ev.id_evento AND ev.record_attivo=1 AND ev.apprendimento=1)
						INNER JOIN test ts ON (ts.id_evento=ev.id_evento AND ts.record_attivo=1)
						LEFT JOIN test_risultato tr ON (tr.id_test=ts.id_test AND tr.id_utente=ass.id_utente AND tr.record_attivo=1)
					WHERE  ut.record_attivo=1 AND ut.email<>'' AND ut.email IS NOT NULL AND ass.id_evento=".$evento->id_evento." AND (tr.data_termine IS NULL OR tr.id_utente IS NULL)
					GROUP BY ut.id_utente
				)
		"; 
	}
	elseif ($evento->gradimento=='1' && $evento->apprendimento=='0'){//Trovo gli utenti che non hanno effettuato il gradimento
		$sql_utenti=" 
			SELECT ut.*
			FROM utenti ut
				INNER JOIN utenti_associazione_eventi ass ON (ass.id_utente=ut.id_utente AND ass.record_attivo=1)
				INNER JOIN eventi ev ON (ass.id_evento=ev.id_evento AND ev.record_attivo=1 AND ev.gradimento=1)
				INNER JOIN gradimento gr ON (gr.id_evento=ev.id_evento AND gr.record_attivo=1)
				LEFT JOIN gradimento_partecipazione gp ON (gp.id_gradimento=gr.id_gradimento AND gp.id_utente=ass.id_utente  AND gp.record_attivo=1)
			WHERE  ut.record_attivo=1 AND ut.email<>'' AND ut.email IS NOT NULL AND ass.id_evento=".$evento->id_evento." AND gp.id_utente IS NULL
			GROUP BY ut.id_utente
		"; 
	}
	elseif ($evento->gradimento=='0' && $evento->apprendimento=='1'){//Trovo gli utenti che non hanno effettuato l'apprendimento o lo hanno solo iniziato
		$sql_utenti=" 
			SELECT ut.*
			FROM utenti ut
				INNER JOIN utenti_associazione_eventi ass ON (ass.id_utente=ut.id_utente AND ass.record_attivo=1)
				INNER JOIN eventi ev ON (ass.id_evento=ev.id_evento AND ev.record_attivo=1 AND ev.apprendimento=1)
				INNER JOIN test ts ON (ts.id_evento=ev.id_evento AND ts.record_attivo=1)
				LEFT JOIN test_risultato tr ON (tr.id_test=ts.id_test AND tr.id_utente=ass.id_utente AND tr.record_attivo=1)
			WHERE  ut.record_attivo=1 AND ut.email<>'' AND ut.email IS NOT NULL AND ass.id_evento=".$evento->id_evento." AND (tr.data_termine IS NULL OR tr.id_utente IS NULL)
			GROUP BY ut.id_utente
		"; 
	}
	else{
		$sql_utenti="";
	}

	$result_utenti = mysql_query($sql_utenti, $connection);
	$array_utenti=array();
	while($row = mysql_fetch_object($result_utenti) ){
		$array_utenti[]=$row;
	}
	
	$n=0;
	foreach ($array_utenti as $utente){
		$invio = inviaMailCLASSICA($utente,$evento);
		sleep(2);//aspetto 2 secondi
		if ($invio==false){
			//echo "<script type=\"text/javascript\">alert('ERRORE INVIO EMAIL.');</script>"; //exit();
			$evento->utenti_not_ok[]=$utente;
		} else {
			$n++;
			$evento->utenti_ok[]=$utente;
			mysql_query("INSERT INTO mail_avvisi_utenti (id_evento, id_utente, email, data_invio)
						VALUES(".$evento->id_evento.", ".$utente->id_utente.", '".Addslashes($utente->email)."', NOW());");
		}
	}
	$evento->n_invii=$n;
	
	if ($n>0){
		 mysql_query("UPDATE mail_avvisi_eventi SET secondo_avviso=NOW() WHERE id_evento=".$evento->id_evento);
	}
}

?>
