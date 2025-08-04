<?php
// File: db_config.php (per AlterVista)

// Sostituisci con i TUOI dati forniti da AlterVista
define('DB_SERVER', 'localhost'); // Di solito è localhost
define('DB_USERNAME', 'xela1103'); // Es. 'mariorossi'
define('DB_PASSWORD', '');   // La password che hai impostato per il DB
define('DB_NAME', 'negozio_db'); // Es. 'my_mariorossi'

// Tenta la connessione al database MySQL
$mysqli = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Controlla la connessione
if($mysqli === false || $mysqli->connect_error){ // Aggiunto controllo $mysqli->connect_error
    // Non usare die() in produzione, ma logga l'errore e mostra un messaggio generico
    error_log("ERRORE: Impossibile connettersi. " . ($mysqli->connect_error ?? 'Errore sconosciuto'));
    http_response_code(503); // Service Unavailable
    echo json_encode(['message' => 'Errore del server, riprova più tardi. (DB Connection)']);
    exit;
}

// Imposta il charset a utf8mb4 (opzionale ma raccomandato)
if (!$mysqli->set_charset("utf8mb4")) {
    error_log("Errore nel caricamento del set di caratteri utf8mb4: " . $mysqli->error);
    // Potresti decidere di terminare se il charset è critico
}
?>