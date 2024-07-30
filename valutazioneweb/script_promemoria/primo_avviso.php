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
	$mail->SMTPDebug = 2;
	$mail->Debugoutput = 'html';

	$mail->IsHTML(true);
	
	//mittente
	$mail->From = "MYSMTPMITTENTE";
	$mail->FromName = "FORMAZIONE";

	//Destinatario
	$mail->AddAddress($utente->email);
	$mail->AddBCC("MYSMTPDESTINATARIOBCC");

	$mail->Subject = "VALUTAZIONE EVENTO: ".$evento->titolo;

	$messaggio = "<font face='Arial' size='2' color='#330000'>Salve,<br>
		La informiamo che in riferimento all'evento formativo dal titolo '".addslashes($evento->titolo)."' risulta possibile accedere sino al ".Date('d-m-Y H:i',strtotime($evento->data_termine)).", al portale web per la compilazione dei questionari e/o test previsti.<br/><br/>
				Per accedere alla compilazione del questionario:
				<br/><br/>
				1) Collegati al sito FRONTOFFICEURL
                                <br/><br/>
                                2) effettua l'accesso mediante il link 'ACCEDI AL PORTALE' in alto a destra
                                <br/><br/>
                                3) dopo aver effettuato il login, comparir&#224; un promemoria ed un link per accedere alla compilazione dei questionari e/o dei test
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

$connection = mysql_connect(HOST, USER, PASSWORD) or die;

mysql_select_db(DATABASE, $connection) or die;
mysql_query("SET NAMES 'utf8'");

$data_attuale = date ('Y-m-d H:i:s');

//Seleziono nel range di date gli eventi
$sql_eventi=" 
	SELECT ma.id_evento AS id_evento_avviso, ev.*
	FROM eventi ev
		LEFT JOIN mail_avvisi_eventi ma ON (ev.id_evento=ma.id_evento)
	WHERE  ev.record_attivo=1 AND '".$data_attuale."' BETWEEN ev.data_inizio AND ev.data_termine
"; 

$result_eventi = mysql_query($sql_eventi, $connection);
$num_rows = mysql_num_rows($result_eventi);

$array_eventi=array();
if ($num_rows>0){//C'è almeno un evento nel DB in quel range date
	while($row = mysql_fetch_object($result_eventi) ){
		if ($row->id_evento_avviso==''){ //Se nella tabella avvisi non ho questo id_evento lo aggiungo alla lista degli eventio da inviare
			$array_eventi[] = $row;
		}
	}
}

if (count($array_eventi)>0)
foreach ($array_eventi as $evento){
	//Cerco gli utenti associati a questo evento e che hanno email
	$sql_utenti=" 
		SELECT ut.*
		FROM utenti ut
			INNER JOIN utenti_associazione_eventi ass ON (ass.id_utente=ut.id_utente AND ass.record_attivo=1)
		WHERE  ut.record_attivo=1 AND ut.email<>'' AND ut.email IS NOT NULL AND ass.id_evento=".$evento->id_evento; 

	$result_utenti = mysql_query($sql_utenti, $connection);
	$array_utenti=array();
	while($row = mysql_fetch_object($result_utenti) ){
		$array_utenti[]=$row;
	}
    	
	$n=0;
	foreach ($array_utenti as $utente){
		$invio = inviaMailCLASSICA($utente,$evento);
		sleep(2);//aspetto 5 secondi
		if ($invio==false){
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
		 mysql_query("INSERT INTO mail_avvisi_eventi (id_evento, primo_avviso)
					VALUES(".$evento->id_evento.", NOW());");
	}
}

?>
