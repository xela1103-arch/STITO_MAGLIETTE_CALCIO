<?php
header('Content-Type: application/json');

// Recupera le variabili ambiente fornite da Railway
$host = getenv('MYSQLHOST');
$user = getenv('MYSQLUSER');
$password = getenv('MYSQLPASSWORD');
$dbname = getenv('MYSQLDATABASE');

// Prova a connettersi
$mysqli = new mysqli($host, $user, $password, $dbname);

// Verifica la connessione
if ($mysqli->connect_error) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Connessione fallita: " . $mysqli->connect_error
    ]);
    exit;
}

// Connessione riuscita
echo json_encode([
    "status" => "success",
    "message" => "Connessione al database riuscita!"
]);

$mysqli->close();
?>
