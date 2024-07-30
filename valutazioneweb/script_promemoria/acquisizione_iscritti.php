<?php
//INIZIO SCRIPT Recupero discenti ciclando per evento
require_once ('../includes/fileinclusi/config.inc');

//Connetto il DB valutazione_web
$connection = mysql_connect(HOST, USER, PASSWORD) or die;

mysql_select_db(DATABASE, $connection) or die;
mysql_query("SET NAMES 'utf8'");

$data_attuale = date ('Y-m-d H:i:s');

//Seleziono nel range di date gli eventi
$sql_eventi=" 
	SELECT 
		ev.id_evento,
		ev.codice_evento,
		ws.username,
		ws.password,
		count(ass.id_evento) AS associati
	FROM eventi ev
	INNER JOIN utenti_ws ws ON (ev.id_amministratore=ws.id_amministratore)
	LEFT JOIN utenti_associazione_eventi ass ON (ev.id_evento=ass.id_evento AND ass.record_attivo=1)
	WHERE  ev.record_attivo=1 AND 
	('".$data_attuale."' BETWEEN ev.data_inizio AND ev.data_termine)
	GROUP BY ev.id_evento;
"; 

$result_eventi = mysql_query($sql_eventi, $connection);

$num_rows = mysql_num_rows($result_eventi);

$array_eventi=array();
if ($num_rows>0){//C'è almeno un evento nel DB in quel range date
	while($row = mysql_fetch_object($result_eventi)){
		if ($row->associati==0){ //Se non ho neanche un associato a questo evento, allora seleziono tale evento
			$array_eventi[$row->id_evento] = $row;
		}
	}
}

if (count($array_eventi)>0)
foreach ($array_eventi as $evento){//Per ogni evento recupero gli iscritti via WS

	//WEB SERVICES///
	ini_set('soap.wsdl_cache_enabled', '0');

	$client = new SoapClient(WSDL, array('encoding'  =>'UTF-8'));

	$obj_input = new stdClass();
	$obj_input->username = $evento->username;
	$obj_input->password = $evento->password;
	$obj_input->id_evento = $evento->codice_evento;

	$result_ws = array();
	$result_ws = $client->ValutazioneWeb_GetPresentParticipantsList($obj_input);
					
	if (isset($result_ws->ValutazioneWeb_GetPresentParticipantsListResult->ValutazioneWebParticipant)){
		$array_eventi[$evento->id_evento]=$result_ws->ValutazioneWeb_GetPresentParticipantsListResult->ValutazioneWebParticipant;
	}
}

$num_rows = count($array_eventi);

if ($num_rows>0)
	foreach($array_eventi as $k=>$item) {//Ciclo sugli eventi che non hanno nemmeno un utente associato (come ho fatto sopra)
		$num_anagrafati = 0;
		$num_associati = 0;
		$id_ev=$k;

		if(!is_array($item)){$item = array($item);}

		foreach($item as $ut_sel) {//Ciclo sugli utenti di questo evento

			if ($ut_sel->id_ISCRITTO!=''){
			$cod_ut=$ut_sel->id_ISCRITTO;
			$cognome=$ut_sel->tx_COGNOME;
			$nome=$ut_sel->tx_NOME;
			$email=$ut_sel->tx_EMAIL;
	
			//Verifico che quel codice utente non esista già...
			$sql_esiste = "
				SELECT *
				FROM utenti
				WHERE record_attivo=1 AND codice_utente='".trim($cod_ut)."'
			";
			
			$ris_esiste = mysql_query($sql_esiste);
			$ris_esiste = mysql_fetch_assoc($ris_esiste);

			if (isset($ris_esiste['id_utente'])){//se c'è già in anagrafe questo utente
			
				//Discente già presente in anagrafica
				//Aggiorno l'email
				 mysql_query("UPDATE utenti SET email='".Addslashes(trim($email))."' where  record_attivo=1 AND  id_utente=".$ris_esiste['id_utente']);
				
				//Verifico se è associato...a questo evento		
				$sql_esiste_ass = "
					SELECT *
					FROM utenti_associazione_eventi
					WHERE record_attivo=1 AND id_utente=".$ris_esiste['id_utente']." AND id_evento=".$id_ev;
				
				$ris_esiste_ass = mysql_query($sql_esiste_ass);
				$ris_esiste_ass = mysql_fetch_assoc($ris_esiste_ass);
				if (isset($ris_esiste_ass['id_utente'])){//SE è associato non faccio nulla
					//salta riga
				} else { //altrimenti lo associo
					$sql = " INSERT INTO utenti_associazione_eventi (id_utente, id_evento, data_associazione, record_attivo)
					VALUES(".$ris_esiste['id_utente'].", ".$id_ev.", NOW(), 1); ";

					mysql_query($sql);
					$num_associati++;
				}
			} else {//Inserisco in utenti ed associo all'evento
			
				$sql="INSERT INTO utenti (codice_utente, cognome, nome, email, data_creazione, record_attivo)
					  VALUES ('".trim($cod_ut)."', '".Addslashes(trim($cognome))."', '".Addslashes(trim($nome))."', '".Addslashes(trim($email))."', NOW(), 1)";
				
				mysql_query($sql);
				$last_id_utente=mysql_query('SELECT LAST_INSERT_ID();');
				$last_id_utente = mysql_fetch_assoc($last_id_utente);
				$sql = " INSERT INTO utenti_associazione_eventi (id_utente, id_evento, data_associazione, record_attivo)
				VALUES(".$last_id_utente['LAST_INSERT_ID()'].", ".$id_ev.", NOW(), 1); ";
				mysql_query($sql);
				
				$num_anagrafati++;
				$num_associati++;
			}
		
		}
		}//end foreach utenti di questo evento
	
		echo '<br>EVENTO:'.$id_ev.' ANAGRAFATI:'.$num_anagrafati.' ASSOCIATI:'.$num_associati.'<br><br>'; 
	}//end foreach eventi
	else
		echo 'Nessun EVENTO da gestire.'; 
?> 
