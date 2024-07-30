<?php
//INIZIO SCRIPT
require_once ('../includes/fileinclusi/config.inc');

//Connetto il DB valutazione_web
$connection = mysql_connect(HOST, USER, PASSWORD) or die;

mysql_select_db(DATABASE, $connection) or die;
mysql_query("SET NAMES 'utf8'");

$data_attuale = date ('Y-m-d H:i:s');
	
function lettera_da_numero($num){
	switch ($num) {
		case 1: return 'a';
		case 2: return 'b';
		case 3: return 'c';
		case 4: return 'd';
		case 5: return 'e';
		case 6: return 'f';
		case 7: return 'g';
		case 8: return 'h';
		case 9: return 'i';
		case 10: return 'l';
		default: return ' '; //spazio
	}
}

//Seleziono gli eventi che hanno apprendimento, chiusi da un minuto o pi� e che non siano presenti nella tabella invio_risultati_eventi o presenti, ma con con invio_apprendimento NULL
$sql_eventi="
	SELECT
		ev.*,
		u_ws.username,
		u_ws.password
	FROM eventi ev
	INNER JOIN utenti_ws u_ws ON (u_ws.id_amministratore=ev.id_amministratore)
	LEFT JOIN invio_risultati_eventi ire ON (ev.id_evento=ire.id_evento)
	WHERE  ev.record_attivo=1
		AND TIMESTAMPDIFF(MINUTE, ev.data_termine, '".$data_attuale."')>=1
		AND ev.apprendimento=1 AND ire.invio_apprendimento IS NULL"; 

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
	
	//Trovo la struttura dell'evento
	$sql_struttura=" 
		SELECT 
			dom.risp_corretta,
			ts.soglia_superamento
		FROM eventi ev
			INNER JOIN test ts ON (ts.id_evento=ev.id_evento AND ts.record_attivo=1)
			INNER JOIN test_associazione_domande ass ON (ass.id_test=ts.id_test AND ass.record_attivo=1)
			INNER JOIN test_domande dom ON (ass.id_domanda=dom.id_domanda AND dom.record_attivo=1)
		WHERE ev.record_attivo=1 AND ev.id_evento=".$evento->id_evento."
		GROUP BY dom.id_domanda
		ORDER BY ass.ordinamento
	"; 

	$result_struttura = mysql_query($sql_struttura, $connection);
	$rows_num = mysql_num_rows($result_struttura);
	$sequenza='';
	$soglia=0;
	while($row = mysql_fetch_object($result_struttura) ){
		$sequenza.=lettera_da_numero($row->risp_corretta);
		$soglia=$row->soglia_superamento;
	}
	$num_min=ceil($rows_num*$soglia/100); //intero maggiore
	
		ini_set('soap.wsdl_cache_enabled', '0');
		$client = new SoapClient(WSDL, array('encoding'  =>'UTF-8'));

		$obj_input = new stdClass();
		$obj_input->username = $evento->username;
		$obj_input->password = $evento->password;
		$obj_input->id_evento = $evento->codice_evento; //codice_evento
		$obj_input->questionCount=$rows_num;
		$obj_input->minimumRightAnswers=$num_min;
		$obj_input->correctAnswersSequence=$sequenza; 
		$result = $client->StoreSurveyStructure($obj_input);	
		
	if ($result==true){		
		//Trovo gli utenti che hanno effettuato apprendimento con data_termine ok
		$sql_utenti=" 
			SELECT 
				ut.*,
				tr.risposte,
				DATE_FORMAT(tr.data_termine, '%Y%m%d%H%i%s') AS data_compilazione
			FROM utenti ut
				INNER JOIN utenti_associazione_eventi ass ON (ass.id_utente=ut.id_utente AND ass.record_attivo=1)
				INNER JOIN eventi ev ON (ass.id_evento=ev.id_evento AND ev.record_attivo=1 AND ev.apprendimento=1)
				INNER JOIN test ts ON (ts.id_evento=ev.id_evento AND ts.record_attivo=1)
				INNER JOIN test_risultato tr ON (tr.id_test=ts.id_test AND tr.id_utente=ass.id_utente  AND tr.record_attivo=1 AND tr.data_termine IS NOT NULL)
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

			$result = $client->StoreParticipantAnswers($obj_input);		
			
			if ($result==false){
				$evento->utenti_not_ok[]=$utente;
			} else {
				$n++;
				$evento->utenti_ok[]=$utente;
			}
		}
		$evento->n_invii=$n;
		
		if ($n>0){
			//Vedo se già esiste una riga con l'evento
			$sql_ev=" 
				SELECT *
				FROM  invio_risultati_eventi
				WHERE id_evento=".$evento->id_evento; 
			$result_ev = mysql_query($sql_ev, $connection);
			$num_ev = mysql_num_rows($result_ev);
			
			if ($num_ev==0)
				mysql_query("INSERT INTO invio_risultati_eventi (id_evento, invio_apprendimento) VALUES(".$evento->id_evento.", NOW()); ");
			else
				mysql_query("UPDATE invio_risultati_eventi SET invio_apprendimento=NOW() WHERE id_evento=".$evento->id_evento);
		}
	}
}

?>
