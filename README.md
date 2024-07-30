# Valutazione Web

PHP 5.6.40 | Drupal 7.63 | MariaDB 10.6.7

## About
Il progetto Valutazione web, mira ad ottimizzare le procedure di gestione dell'attività didattica condotta in aula, puntando ad automatizzare l'attività di valutazione dei discenti. La dematerializzazione dei moduli cartacei riferiti al questionario di gradimento e al test di apprendimento, è uno degli obbiettivi che lo strumento consente di raggiungere attraverso la predisposizione di semplici ed intuitive form di inserimento. Nel caso del test di apprendimento, il discente può immediatamente consultare la correzione delle risposte che ha fornito, prendendo visione del punteggio ottenuto e del conseguente esito. <br/>
Più nessuna operazione di data entry è quindi necessaria da parte degli operatori della formazione, i quali possono investire il loro tempo ad analizzare la ricca reportistica offerta in merito ai dati acquisiti, in modo da valutare eventuali margini di miglioramento e conseguenti interventi, finalizzati a garantire i più elevati standard qualitativi.

## Database
Il database è condiviso tra l'applicato ed il CMS: il dump da cui partire si trova nella cartella Others

Prima di importare il database, potrebbe essere necessario creare l'utente per accedere al DB. Ad esempio
```sql
CREATE USER 'MYUSER'@'%' IDENTIFIED VIA mysql_native_password USING 'MYUSERPASSWORD';
GRANT ALL PRIVILEGES ON *.* TO 'MYUSER'@'%' REQUIRE NONE WITH MAX_QUERIES_PER_HOUR 0 MAX_CONNECTIONS_PER_HOUR 0 MAX_UPDATES_PER_HOUR 0 MAX_USER_CONNECTIONS 0;
GRANT ALL PRIVILEGES ON `MYDATABASE`.* TO 'MYUSER'@'%';
```

Prima di importare il database, sostituire il placeholder `{MYPW-TOGESTFORM}` con la password definita nel Gestionale della Formazionazione

## Codice
Clonare il repository.

Gli utenti per utilizzare l'applicativo sono
* admin (ruolo **administrator**) : EDBl8l4gq4Qi
* admin_eventi (ruolo **admin_eventi**) : YFtWlonl5NEl

Modificare, poi, entrambe le password.

L'applicativo necessità dei suguenti moduli:
|                       |                       |                   |                       |                   |
| -------------         | -------------         | -------------     | -------------         | -------------     |
| block                 | color                 | comment           | Content translation   | Contextual links  | 
| Dashboard             | Database logging      | Field             | Field SQL storage     | Field UI          | 
| File                  | filter                | help              | image                 | list              | 
| locale                | menu                  | node              | number                | options           | 
| overlay               | path                  | php filter        | RDF                   | search            | 
| shortcut              | system                | taxonomy          | text                  | toolbar           | 
| update manager        | user                  | content access    | nodeaccess            | login destination | 
| menu item visibility  | no request new pass   |                   |                       |                   | 


Il codice si trova nella cartella valutazioneweb

- Copiare il file `sites/default/default.settings.php` e rinominarlo in `sites/default/settings.php`
- Modificare il file `sites/default/settings.php`, impostando correttamente i seguenti placeholder
    - MYDATABASE
    - MYUSER
    - MYUSERPASSWORD
    - MYHOST
    - MYPORT (eventualmente anche scringa vuota)
- Modificare il file `includes/fileinclusi/config.inc`, impostando correttamente i seguenti placeholder
    - MYSECURITYKEY: è lo stesso valore definito nel Web.config del FrontOffice (key *ValutazioneWeb_Key*)
    - MYWSDL: è il DNS dell'applicativo web BackOffice (per accedere al WSDL esposto da GFWs)
    - MYDATABASE
    - MYUSER
    - MYUSERPASSWORD
    - MYHOST

La cartella *script_promemoria* contiene delle delle schedulazioni per informare gli iscritti all'evento su scadenze o disponibilità dei dati.
Di seguito un impostazioni d'esempio del `crontab`
```bash
# m h  dom mon dow   command
*/5 * * * *     php ritorno_date.php
*/15 * * * *    php acquisizione_iscritti.php

1,31 * * * *    php primo_avviso.php
2,32 * * * *    php secondo_avviso.php

*/15 * * * *    php invio_apprendimento_eventi.php
3,18,33,48 * * * *      php invio_gradimento_eventi.php
```

- **ritorno_date.php**: comunica al Gestionale della Formazione il periodo di compilazione dei questionari
- **acquisizione_iscritti.php**: legge i presenti da Gestionale della Formazione e li abilita alla compilazione del questionari. L'acquisizione, seppur schedulata ogni 15 minuti come da esempio, importa i dati degli iscritti che potranno compilare i questionari solo alla prima esecuzione dopo la data di apertura del periodo di compilazione
- **primo_avviso.php**: informa gli iscritti che è possibile iniziare la compilazione del questionario
- **secondo_avviso.php**: 24h prima della chiusura del periodo di compilazione dei questionari, attenziona gli iscritti che non hanno ancora compilato il questionario
- **invio_apprendimento_eventi.php**: invia i risultati del test di verifica apprendimento al Gestionale della Formazione. L'importazione viene effettuata in un unico batch alla prima esecuzione dopo la data di chiusura del periodo di compilazione
- **invio_gradimento_eventi.php**: invia i risultati del questionario qualità al Gestionale della Formazione. L'importazione viene effettuata in un unico batch alla prima esecuzione dopo la data di chiusura del periodo di compilazione

Di seguito la configurazione degli *script_promemoria*
- Modificare il file `script_promemoria/config`, impostando correttamente i seguenti placeholder
    - MYDATABASE
    - MYUSER
    - MYUSERPASSWORD
    - MYHOST
- Modificare il file `script_promemoria/invio_gradimento_eventi.php`
    - MYSMTP
    - MYSMTPPORT
    - MYSMTPUSERNAME
    - MYSMTPPSW
    - MYSMTPMITTENTE
    - MYSMTPDESTINATARIO
- Modificare il file `script_promemoria/primo_avviso.php`
    - MYSMTP
    - MYSMTPPORT
    - MYSMTPUSERNAME
    - MYSMTPPSW
    - MYSMTPMITTENTE
    - MYSMTPDESTINATARIOBCC
    - FRONTOFFICEURL
- Modificare il file `script_promemoria/secondo_avviso.php`
    - MYSMTP
    - MYSMTPPORT
    - MYSMTPUSERNAME
    - MYSMTPPSW
    - MYSMTPMITTENTE
    - MYSMTPDESTINATARIOBCC
    - FRONTOFFICEURL