<?php
header("Access-Control-Allow-Origin: *"); // Per sviluppo, in produzione restringere
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, PUT, DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once 'db_config.php'; // Il tuo file di connessione al DB

$method = $_SERVER['REQUEST_METHOD'];

if ($method == "OPTIONS") {
    http_response_code(200);
    exit();
}

$action = isset($_GET['action']) ? $_GET['action'] : '';
$data = json_decode(file_get_contents("php://input"));

// Funzione helper per generare una chiave di crittografia sicura
function generateUserEncryptionKey() {
    return bin2hex(random_bytes(32)); // Chiave AES-256 (32 bytes = 64 caratteri hex)
}

switch ($action) {
    case 'register':
        if ($method == 'POST') {
            if (!isset($data->username) || !isset($data->email) || !isset($data->password)) {
                http_response_code(400);
                echo json_encode(['message' => 'Dati incompleti per la registrazione.']);
                exit;
            }

            $username = $mysqli->real_escape_string(trim($data->username));
            $email = $mysqli->real_escape_string(trim(strtolower($data->email)));
            $password = trim($data->password);

            if (empty($username) || empty($email) || empty($password)) {
                http_response_code(400);
                echo json_encode(['message' => 'Tutti i campi sono obbligatori.']);
                exit;
            }
            if (strlen($password) < 6) {
                http_response_code(400);
                echo json_encode(['message' => 'La password deve essere di almeno 6 caratteri.']);
                exit;
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                echo json_encode(['message' => 'Formato email non valido.']);
                exit;
            }

            $stmt_check = $mysqli->prepare("SELECT id, username FROM users WHERE username = ? OR email = ?"); // Seleziona anche username per messaggio errore più preciso
            $stmt_check->bind_param("ss", $username, $email);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();

            if ($result_check->num_rows > 0) {
                $existing_user = $result_check->fetch_assoc();
                $error_message = ($existing_user['username'] === $username) ? 'Username già esistente.' : 'Email già registrata.';
                http_response_code(409); // Conflict
                echo json_encode(['message' => $error_message]);
                $stmt_check->close();
                exit;
            }
            $stmt_check->close();

            $hashed_password = null; 

            if (defined('PASSWORD_ARGON2ID')) {
                $hashed_password = password_hash($password, PASSWORD_ARGON2ID);
            }

            if ($hashed_password === false || $hashed_password === null) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            }
            
            if ($hashed_password === false || $hashed_password === null || !is_string($hashed_password)) {
                http_response_code(500);
                echo json_encode(['message' => 'Errore interno del server durante la creazione sicura della password. Si prega di contattare l\'assistenza.']);
                exit;
            }

            $encryption_key_hex = generateUserEncryptionKey();

            $stmt_insert = $mysqli->prepare("INSERT INTO users (username, email, hashed_password, encryption_key_hex) VALUES (?, ?, ?, ?)");
            if (!$stmt_insert) {
                http_response_code(500);
                echo json_encode(['message' => 'Errore interno del server (prep statement).']);
                exit;
            }

            $bind_result = $stmt_insert->bind_param("ssss", $username, $email, $hashed_password, $encryption_key_hex);
            if (!$bind_result) {
                http_response_code(500);
                echo json_encode(['message' => 'Errore interno del server (bind param).']);
                exit;
            }
            
            if ($stmt_insert->execute()) {
                http_response_code(201);
                echo json_encode(['message' => 'Utente registrato con successo.']);
            } else {
                http_response_code(500);
                echo json_encode(['message' => 'Errore durante la registrazione dell\'utente: ' . $stmt_insert->error]);
            }
            $stmt_insert->close();
        } else {
            http_response_code(405);
            echo json_encode(['message' => 'Metodo non consentito per la registrazione.']);
        }
        break;

    case 'login':
        if ($method == 'POST') {
            if (!isset($data->identifier) || !isset($data->password)) {
                http_response_code(400);
                echo json_encode(['message' => 'Dati incompleti per il login.']);
                exit;
            }

            $identifier = $mysqli->real_escape_string(trim($data->identifier));
            $password = trim($data->password);
            
            $is_email = filter_var($identifier, FILTER_VALIDATE_EMAIL);
            
            if ($is_email) {
                $stmt = $mysqli->prepare("SELECT id, username, email, hashed_password, encryption_key_hex FROM users WHERE email = ?");
            } else {
                $stmt = $mysqli->prepare("SELECT id, username, email, hashed_password, encryption_key_hex FROM users WHERE username = ?");
            }
            if (!$stmt) {
                 http_response_code(500);
                 echo json_encode(['message' => 'Errore interno del server (prep statement login).']);
                 exit;
            }
            $stmt->bind_param("s", $identifier);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($user = $result->fetch_assoc()) {
                if (password_verify($password, $user['hashed_password'])) {
                    http_response_code(200);
                    echo json_encode([
                        'message' => 'Login effettuato con successo.',
                        'userData' => [
                            'userId' => $user['id'],
                            'username' => $user['username'],
                            'email' => $user['email'],
                            'encryptionKeyHex' => $user['encryption_key_hex']
                        ]
                    ]);
                } else {
                    http_response_code(401);
                    echo json_encode(['message' => 'Username/Email o password non validi.']);
                }
            } else {
                http_response_code(401);
                echo json_encode(['message' => 'Username/Email o password non validi.']);
            }
            $stmt->close();
        } else {
            http_response_code(405);
            echo json_encode(['message' => 'Metodo non consentito per il login.']);
        }
        break;

    case 'update_account_details':
        if ($method == 'PUT') {
            $userIdToUpdate = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;
            if (!$userIdToUpdate) {
                http_response_code(400);
                echo json_encode(['message' => 'User ID mancante per l\'aggiornamento.']);
                exit;
            }
            if (!isset($data->username) || !isset($data->email)) {
                http_response_code(400);
                echo json_encode(['message' => 'Dati incompleti per l\'aggiornamento (username, email).']);
                exit;
            }

            $newUsername = $mysqli->real_escape_string(trim($data->username));
            $newEmail = $mysqli->real_escape_string(trim(strtolower($data->email)));

            if (empty($newUsername) || empty($newEmail)) {
                http_response_code(400);
                echo json_encode(['message' => 'Username ed Email sono obbligatori.']);
                exit;
            }
            if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                echo json_encode(['message' => 'Formato email non valido.']);
                exit;
            }

            $stmt_check_username = $mysqli->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmt_check_username->bind_param("si", $newUsername, $userIdToUpdate);
            $stmt_check_username->execute();
            if ($stmt_check_username->get_result()->num_rows > 0) {
                http_response_code(409);
                echo json_encode(['message' => 'Username già esistente.']);
                $stmt_check_username->close();
                exit;
            }
            $stmt_check_username->close();

            $stmt_check_email = $mysqli->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt_check_email->bind_param("si", $newEmail, $userIdToUpdate);
            $stmt_check_email->execute();
            if ($stmt_check_email->get_result()->num_rows > 0) {
                http_response_code(409);
                echo json_encode(['message' => 'Email già registrata da un altro utente.']);
                $stmt_check_email->close();
                exit;
            }
            $stmt_check_email->close();

            $stmt_update = $mysqli->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
            $stmt_update->bind_param("ssi", $newUsername, $newEmail, $userIdToUpdate);

            if ($stmt_update->execute()) {
                if ($stmt_update->affected_rows > 0) {
                    http_response_code(200);
                    echo json_encode([
                        'message' => 'Dettagli account aggiornati con successo.',
                        'updatedUserData' => [
                            'username' => $newUsername,
                            'email' => $newEmail
                        ]
                    ]);
                } else {
                     http_response_code(200);
                     echo json_encode(['message' => 'Nessuna modifica apportata ai dettagli dell\'account.']);
                }
            } else {
                http_response_code(500);
                echo json_encode(['message' => 'Errore durante l\'aggiornamento dei dettagli dell\'account: ' . $stmt_update->error]);
            }
            $stmt_update->close();
        } else {
            http_response_code(405);
            echo json_encode(['message' => 'Metodo non consentito per l\'aggiornamento dei dettagli account.']);
        }
        break;

    case 'delete_account':
        if ($method == 'DELETE') {
            $userIdToDelete = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;

            if (!$userIdToDelete) {
                http_response_code(400);
                echo json_encode(['message' => 'User ID mancante per l\'eliminazione.']);
                exit;
            }
            
            $stmt = $mysqli->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $userIdToDelete);

            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    http_response_code(200);
                    echo json_encode(['message' => 'Account eliminato con successo.']);
                } else {
                    http_response_code(404);
                    echo json_encode(['message' => 'Account non trovato.']);
                }
            } else {
                http_response_code(500);
                echo json_encode(['message' => 'Errore durante l\'eliminazione dell\'account: ' . $stmt->error]);
            }
            $stmt->close();
        } else {
            http_response_code(405);
            echo json_encode(['message' => 'Metodo non consentito per l\'eliminazione dell\'account.']);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(['message' => 'Azione non valida.']);
        break;
}

$mysqli->close();
?>