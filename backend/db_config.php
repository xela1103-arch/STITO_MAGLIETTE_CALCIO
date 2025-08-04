define('DB_SERVER', getenv('DB_SERVER'));
define('DB_USERNAME', getenv('DB_USERNAME'));
define('DB_PASSWORD', getenv('DB_PASSWORD'));
define('DB_NAME', getenv('DB_NAME'));

$mysqli = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

if($mysqli === false || $mysqli->connect_error){
    error_log("ERRORE: Impossibile connettersi. " . ($mysqli->connect_error ?? 'Errore sconosciuto'));
    http_response_code(503);
    echo json_encode(['message' => 'Errore del server, riprova più tardi. (DB Connection)']);
    exit;
}

if (!$mysqli->set_charset("utf8mb4")) {
    error_log("Errore nel caricamento del set di caratteri utf8mb4: " . $mysqli->error);
}
