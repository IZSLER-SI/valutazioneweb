<?php
//FILE GENERALE richiamato nelle varie pagine del sistema per effettuare exit se non è loggato e per avere il valore di accesso(1 vede tutto, 0 vede solo i suoi eventi...)
global $user;

if(!in_array("authenticated user", $user->roles)) exit();//se non è autenticato lo butto fuori

$ruoli=array();
foreach($user->roles as $k=>$v) {
	$ruoli[]=$k;
}

$accesso=0;
if ((in_array("3", $ruoli) || in_array("4", $ruoli)))//Se è admin_eventi o amministratore allora vede tutti gli eventi, quindi accesso=1
	$accesso=1;


function verifica_permesso($acc, $ut, $ev){ //funzione richiamata nelle varie pagine per ottenere il permesso di un utente ad un evento (se true ok, altrimenti se false, no)
	if ($acc==1) //se l'accesso è 1, allora sicuramente può vedere quell'evento
		return true;

	//Altrimenti Verifico che esiste quell'evento legato a quell'utente
	$esiste = db_query("SELECT id_evento FROM eventi WHERE record_attivo=1 AND id_evento='".$ev."' AND id_amministratore='".$ut."'");
	$esiste = $esiste->fetch();  
	$esistente =-1;
	if (isset($esiste->id_evento))
		$esistente = $esiste->id_evento;

	if ($esistente==-1) //Se l'evento non è associato a questo utente, torno false, altrimenti true
		return false;
	else
		return true;
}

?>
