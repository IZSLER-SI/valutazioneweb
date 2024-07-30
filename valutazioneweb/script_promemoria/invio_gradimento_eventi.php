<?php

require_once ('../includes/fileinclusi/config.inc');

$connection = mysql_connect(HOST, USER, PASSWORD) or die;

mysql_select_db(DATABASE, $connection) or die;
mysql_query("SET NAMES 'utf8'");

$data_attuale = date ('Y-m-d H:i:s');


 /**
 * Funzione per l'invio dell'email CLASSICA
 */
require("./lib/PHPMailerAutoload.php");

function inviaMailCLASSICA ($evento) {
	$mail = new PHPMailer(true);

	$mail->SMTPAuth = true;
	$mail->SMTPSecure = "tls";
	$mail->Host = "MYSMTP";
	$mail->Port = MYSMTPPORT;
	$mail->Username = "MYSMTPUSERNAME";
	$mail->Password = "MYSMTPPSW";
	$mail->SMTPDebug = 0;
	$mail->Debugoutput = 'html';

	$mail->IsHTML(true);

	//mittente
	$mail->From = "MYSMTPMITTENTE";
	$mail->FromName = "VALUTAZIONE WEB";

	//Destinatario
	$mail->AddAddress(MYSMTPDESTINATARIO);

	$mail->Subject = "CHIUSURA EVENTO: ".addslashes($evento->titolo);

	$messaggio = "SI COMUNICA CHE RISULTA CHIUSO IL CORSO DAL TITOLO:<br/><b>'".addslashes($evento->titolo)."'.</b><br/><br/>I relativi dati di rendicontazione risultano trasferiti al gestionale.";

	$mail->Body = iconv("UTF-8","ISO-8859-1",$messaggio);
	if(!$mail->Send())
	{
		return false;
	} else {
		return true;
	}

}//end invia email Classica

//Seleziono gli eventi che hanno gradimento, chiusi da un minuto o più e che non siano presenti nella tabella invio_risultati_eventi o presenti, ma con con invio_gradimento NULL
$sql_eventi=" 
	SELECT 
		ev.*,
		u_ws.username,
		u_ws.password
	FROM eventi ev
		LEFT JOIN invio_risultati_eventi ire ON (ev.id_evento=ire.id_evento)
		INNER JOIN utenti_ws u_ws ON (u_ws.id_amministratore=ev.id_amministratore)
	WHERE  (ev.record_attivo=1 
		AND TIMESTAMPDIFF(MINUTE, ev.data_termine, '".$data_attuale."')>=1
		AND ev.gradimento=1 AND ire.invio_gradimento IS NULL )";

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
	//Trovo gli utenti che hanno effettuato il gradimento
	$sql_utenti=" 
		SELECT 
			ut.*,
			gp.risposte,
			DATE_FORMAT(gp.data_compilazione, '%Y%m%d%H%i%s') AS data_compilazione
		FROM utenti ut
			INNER JOIN utenti_associazione_eventi ass ON (ass.id_utente=ut.id_utente AND ass.record_attivo=1)
			INNER JOIN eventi ev ON (ass.id_evento=ev.id_evento AND ev.record_attivo=1 AND ev.gradimento=1)
			INNER JOIN gradimento gr ON (gr.id_evento=ev.id_evento AND gr.record_attivo=1)
			INNER JOIN gradimento_partecipazione gp ON (gp.id_gradimento=gr.id_gradimento AND gp.id_utente=ass.id_utente  AND gp.record_attivo=1)
		WHERE  ut.record_attivo=1 AND ass.id_evento=".$evento->id_evento."
		GROUP BY ev.id_evento, ut.id_utente
		ORDER BY ut.cognome, ut.nome
	";

	$result_utenti = mysql_query($sql_utenti, $connection);
	$array_utenti=array();
	while($row = mysql_fetch_object($result_utenti) ){
		$array_utenti[]=$row;
	}
	
	$n=0;
	foreach ($array_utenti as $utente){
		//WEB SERVICES///
		ini_set('soap.wsdl_cache_enabled', '0');
		$client = new SoapClient(WSDL, array('encoding'  =>'UTF-8'));

		$obj_input = new stdClass();
		$obj_input->username = $evento->username;
		$obj_input->password = $evento->password;
		$obj_input->id_evento = $evento->codice_evento; //codice_evento
		$obj_input->id_iscritto = $utente->codice_utente; //codice_utente
		$obj_input->answersSequence = $utente->risposte;
		$obj_input->dataOraCompilazioneYYYYMMDDHHMMSS = $utente->data_compilazione;
		
		$result = $client->StoreParticipantQualitySurvey($obj_input);
		if ($result==false){
			$evento->utenti_not_ok[]=$utente;
		} else {
			$n++;
			$evento->utenti_ok[]=$utente;
		}
	}
	$evento->n_invii=$n;

	include_once "Risposte_Testuali.php";

    require_once ('../includes/fileinclusi/config.inc');

    $connection = mysqli_connect(HOST, USER, PASSWORD) or die;
    mysqli_select_db($connection,DATABASE) or die;
    mysqli_query($connection,"SET NAMES 'utf8'");

    $query ="
		SELECT
			GR.id_utente AS id_utente,
			GD.id_domanda AS id_domanda,
				GR.id_risposta as id_risposta,
				ev.codice_evento AS codice_evento,
				G.id_gradimento AS id_gradimento,
				DATE_FORMAT(GP.data_compilazione, '%Y%m%d%H%i%s') AS dt_compilazione,
				GR.risposta_testuale AS tx_risposta,
				GAD.ordinamento as ordinamento,
				GD.testo as testo_domanda
		FROM eventi ev
			LEFT JOIN invio_risultati_eventi ire ON (ev.id_evento=ire.id_evento)
			INNER JOIN utenti_ws u_ws ON (u_ws.id_amministratore=ev.id_amministratore)
			JOIN gradimento G ON (G.id_evento=ev.id_evento)
			JOIN gradimento_associazione_domande GAD ON (GAD.id_gradimento=G.id_gradimento)
			JOIN gradimento_domande GD ON (GD.id_domanda=GAD.id_domanda)
			JOIN gradimento_risposte GR ON (GR.id_domanda=GD.id_domanda)
			JOIN gradimento_partecipazione GP ON (GP.id_utente=GR.id_utente)
		WHERE
			ev.record_attivo=1
			AND TIMESTAMPDIFF(MINUTE, ev.data_termine, '".date ('Y-m-d H:i:s')."')>=1
			AND ev.gradimento=1
			AND ire.invio_gradimento IS NULL
			AND GD.tipologia_domanda=1
			and GR.risposta_testuale != ''
			AND ev.id_evento = {$evento->id_evento}
		GROUP BY CONCAT (GD.id_domanda, '-',GR.id_utente, '-', GR.id_risposta)
		ORDER BY ev.id_evento,GP.id_utente, GR.id_risposta
	";

    $result = mysqli_query($connection,$query);
    $array_utenti=array();
    while($row = mysqli_fetch_object($result) ){
        $array_risposte[]=$row;
    }

    /** Recupero le credenziali dell'utente del DB */

    $query = "select * from utenti_ws;";
    $result = mysqli_query($connection,$query);
    while($row = mysqli_fetch_object($result) ){
        $ws_username = $row->username;
        $ws_password = $row->password;
    }

    /** Richiamo la funzione per l'invio al WS delle risposte testuali */
    inviaRisposteTestuali($array_risposte, $ws_username, $ws_password);

	if ($n>0){	 
		//Vedo se già esiste una riga con l'evento
		$sql_ev="SELECT	* FROM  invio_risultati_eventi WHERE id_evento=".$evento->id_evento; 
		$result_ev = mysqli_query($connection, $sql_ev);
		$num_ev = mysqli_num_rows($result_ev);
		if ($num_ev==0)
			mysqli_query($connection, "INSERT INTO invio_risultati_eventi (id_evento, invio_gradimento) VALUES(".$evento->id_evento.", NOW()); ");
		else{
			mysqli_query($connection, "UPDATE invio_risultati_eventi SET invio_gradimento=NOW() WHERE id_evento=".$evento->id_evento);
		echo"UPDATE invio_risultati_eventi SET invio_gradimento=NOW() WHERE id_evento=".$evento->id_evento;
		}	
	}
    $invio = inviaMailCLASSICA($evento);
    unset($array_risposte);
}

?>
