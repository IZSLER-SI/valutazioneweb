<?php
//INIZIO SCRIPT Recupero discenti ciclando per evento
require_once ('../includes/fileinclusi/config.inc');

//Connetto il DB valutazione_web
$connection = mysql_connect(HOST, USER, PASSWORD) or die;
mysql_select_db(DATABASE, $connection) or die;
mysql_query("SET NAMES 'utf8'");

$data_attuale = date ('Y-m-d');

//Sottraggo 1 mese alla data:
$data_che_fu = strtotime ( '-1 month' , strtotime ( $data_attuale ) ) ; // facciamo l'operazione
$data_che_fu = date ( 'Y-m-d' , $data_che_fu ); //trasformiamo la data nel formato accettato dal db YYYY-MM-DD

//Seleziono nel range di date gli eventi
$sql_eventi=" 
	SELECT 
		ev.*,
		DATE_FORMAT(ev.data_inizio, '%Y%m%d%H%i') AS inizio,
		DATE_FORMAT(ev.data_termine, '%Y%m%d%H%i') AS fine,
		ws.username,
		ws.password
	FROM eventi ev
		INNER JOIN utenti_ws ws ON (ev.id_amministratore=ws.id_amministratore)
	WHERE  ev.record_attivo=1 AND ev.data_inizio > '".$data_che_fu."' "; 
$result_eventi = mysql_query($sql_eventi, $connection);

$num_rows = mysql_num_rows($result_eventi);

if ($num_rows>0){//C'è almeno un evento nel DB in quel range date Allora CHIAMO WS RETURNS......
	
	while($row = mysql_fetch_object($result_eventi)){
		//WEB SERVICES///
		ini_set('soap.wsdl_cache_enabled', '0');
		$client = new SoapClient(WSDL, array('encoding'  =>'UTF-8'));

		$obj_input = new stdClass();
		$obj_input->username = $row->username;
		$obj_input->password = $row->password;
		$obj_input->id_evento = $row->codice_evento; //codice_evento
		$obj_input->dataOraInizio = $row->inizio; //Inizio dell'evento data e ora in questo formato  --> DATE_FORMAT(`data_inizio`, '%Y%m%d%H%i')
		$obj_input->dataOraFine = $row->fine; //Fine dell'evento come prima
		$obj_input->apprendimento = $row->apprendimento;//Apprendimento
		$obj_input->qualita = $row->gradimento;//Gradimento

		$result = $client->ValutazioneWeb_SetSurveyTimeRange($obj_input);					 
	}	
}

?> 
