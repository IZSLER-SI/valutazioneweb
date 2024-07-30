<?php

function inviaRisposteTestuali($array_risposte, $ws_username, $ws_password) {
	print_r($array_risposte);
	require_once ('../includes/fileinclusi/config.inc');

		echo"<pre>";
        $n=0;
        $id_domanda = 1;
        $id_utente_temp = 0;
        foreach ($array_risposte as $risposta){
            //WEB SERVICES///
            ini_set('soap.wsdl_cache_enabled', '0');

            // TODO CANCELLARE IN PRODUZIONE
            $client = new SoapClient(WSDL, array('encoding'  =>'UTF-8'));

            /** Incremento il contatore per il codice della domanda */
            if ($id_utente_temp!=$risposta->id_utente) {
                $id_domanda = 1;
            } else {
                $id_domanda++;
            }
            $id_utente_temp = $risposta->id_utente;

            /** Creo l'oggetto da inviare al webservice */
            $obj_input = new stdClass();
            $obj_input->username = $ws_username;
            $obj_input->password = $ws_password;
            $obj_input->id_evento = $risposta->codice_evento;
            $obj_input->id_domanda = $risposta->ordinamento;
            $obj_input->tx_risposta = $risposta->tx_risposta;
            $obj_input->dt_compilazione = $risposta->dt_compilazione;
            $obj_input->tx_domanda = $risposta->testo_domanda;
            $result = $client->StoreParticipantQualitySurveyOpenAnswer($obj_input);

            // TODO DECOMMENTARE SE RICHIESTO UN MESSAGGIO DI ERRORE IN CASO DI PROBLEMI
            if ($result==false){
               // print_r("<script type=\"text/javascript\">alert('ERRORE nel salvataggio delle risposte testuali.');</script>"); //exit();
            } else {
            }
			echo"<pre>";
        } // fine foreach
} // fine funzione inviaRisposteTestuali

?>
