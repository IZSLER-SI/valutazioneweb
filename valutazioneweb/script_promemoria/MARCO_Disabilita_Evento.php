<?php

function deactivateEvent($codice_evento) {
    //WEB SERVICES///
    ini_set('soap.wsdl_cache_enabled', '0');
	
    /** Recupero le credenziali dell'utente del DB */
    require_once ('../includes/fileinclusi/config.inc');
	echo"<pre>";
    $connection = mysqli_connect(HOST, USER, PASSWORD) or die;
    mysqli_select_db($connection,DATABASE) or die;
    mysqli_query($connection,"SET NAMES 'utf8'");

    $query = "
    select * from utenti_ws;
    ";
    $result = mysqli_query($connection,$query);
    while($row = mysqli_fetch_object($result) ){
        $ws_username = $row->username;
        $ws_password = $row->password;
    }

    $client = new SoapClient(WSDL, array('encoding'  =>'UTF-8'));

    /** Creo l'oggetto da inviare al webservice */
    $obj_input = new stdClass();
    $obj_input->username = $ws_username;
    $obj_input->password = $ws_password;
    $obj_input->id_evento = $codice_evento;


    /** Invoco il webservice */
    $result = $client->deactivateEvent($obj_input);

	print_r($result);
	
	echo"</pre>";

    /** Controllo l'esito dell'operazione */
    if ($result==false){
        return false;
    } else {
        return true;
    }
}
//deactivateEvent("2014");

?>