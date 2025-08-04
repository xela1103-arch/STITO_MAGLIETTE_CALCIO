<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, PUT, GET, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once 'db_config.php'; // Assicurati che questo file sia corretto e $mysqli sia disponibile

$method = $_SERVER['REQUEST_METHOD'];

if ($method == "OPTIONS") {
    http_response_code(200);
    exit();
}

$data = json_decode(file_get_contents("php://input"));
$action = isset($_GET['action']) ? $_GET['action'] : (isset($data->action) ? $data->action : '');

define('ADMIN_PASSWORD_KEY', 'admin_password');

// Funzione per recuperare un'impostazione
function getAdminSetting($db, $key) {
    if (!$db || ($db instanceof mysqli && $db->connect_error)) {
        error_log("AdminAuthAPI - getAdminSetting: Connessione DB non valida.");
        return null; // O gestisci l'errore come preferisci
    }
    $stmt = $db->prepare("SELECT setting_value FROM admin_config WHERE setting_key = ?");
    if (!$stmt) {
        error_log("AdminAuthAPI - getAdminSetting: Errore prepare: " . $db->error);
        return null;
    }
    $stmt->bind_param("s", $key);
    if (!$stmt->execute()) {
         error_log("AdminAuthAPI - getAdminSetting: Errore execute: " . $stmt->error);
         $stmt->close();
         return null;
    }
    $result = $stmt->get_result();
    $setting = $result->fetch_assoc();
    $stmt->close();
    return $setting ? $setting['setting_value'] : null;
}

// Funzione per salvare/aggiornare un'impostazione
function setAdminSetting($db, $key, $value) {
    if (!$db || ($db instanceof mysqli && $db->connect_error)) {
        error_log("AdminAuthAPI - setAdminSetting: Connessione DB non valida.");
        return false;
    }
    $sql = "INSERT INTO admin_config (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
    $stmt = $db->prepare($sql);

    if (!$stmt) {
        error_log("AdminAuthAPI - setAdminSetting: Errore prepare: " . $db->error . " | SQL: " . $sql);
        return false;
    }

    $bind_success = $stmt->bind_param("ss", $key, $value);
    if (!$bind_success) {
        error_log("AdminAuthAPI - setAdminSetting: Errore bind_param: " . $stmt->error);
        $stmt->close();
        return false;
    }

    $execute_success = $stmt->execute();
    if (!$execute_success) {
        error_log("AdminAuthAPI - setAdminSetting: Errore execute: " . $stmt->error . " | Key: " . $key);
        $stmt->close();
        return false;
    }

    $stmt->close();
    return $execute_success; // execute() ritorna true/false
}


switch ($action) {
    case 'check_password_set':
        if ($method == 'GET') {
            $hashed_password = getAdminSetting($mysqli, ADMIN_PASSWORD_KEY);
            // Se getAdminSetting ritorna null a causa di un errore DB interno, $hashed_password sarà null.
            // Il client JS si aspetta un booleano, quindi questo è ok.
            // Una gestione più robusta potrebbe controllare se l'errore era DB o solo "chiave non trovata".
            echo json_encode(['isPasswordSet' => ($hashed_password !== null)]);
        } else {
            http_response_code(405);
            echo json_encode(['message' => 'Metodo non consentito per check_password_set.']);
        }
        break;

    case 'set_initial_password':
        if ($method == 'POST') {
            if (!isset($data->new_password) || empty(trim($data->new_password))) {
                http_response_code(400);
                echo json_encode(['message' => 'Nuova password mancante o vuota.']);
                exit;
            }
            if (strlen(trim($data->new_password)) < 8) {
                http_response_code(400);
                echo json_encode(['message' => 'La password deve essere di almeno 8 caratteri.']);
                exit;
            }

            $existing_password = getAdminSetting($mysqli, ADMIN_PASSWORD_KEY);
            if ($existing_password !== null) { // Esiste se non è null (potrebbe essere stringa o errore loggato)
                http_response_code(409);
                echo json_encode(['message' => 'Una password amministratore è già impostata. Usa la funzione di cambio password.']);
                exit;
            }

            $password_to_hash = trim($data->new_password);
            if (defined('PASSWORD_ARGON2ID')) {
                $hashed_new_password = password_hash($password_to_hash, PASSWORD_ARGON2ID);
            } else {
                $hashed_new_password = password_hash($password_to_hash, PASSWORD_DEFAULT);
            }

            if ($hashed_new_password === false) {
                error_log("AdminAuthAPI - set_initial_password: password_hash() ha fallito.");
                http_response_code(500);
                echo json_encode(['message' => 'Errore interno del server durante la creazione sicura della password.']);
                exit;
            }
            
            if (setAdminSetting($mysqli, ADMIN_PASSWORD_KEY, $hashed_new_password)) {
                http_response_code(201);
                echo json_encode(['message' => 'Password amministratore iniziale impostata con successo.']);
            } else {
                http_response_code(500);
                echo json_encode(['message' => 'Errore durante l\'impostazione della password iniziale.']);
            }
        } else {
             http_response_code(405);
             echo json_encode(['message' => 'Metodo non consentito per set_initial_password.']);
        }
        break;

    case 'login_admin':
        if ($method == 'POST') {
            if (!isset($data->password) || empty(trim($data->password))) {
                http_response_code(400);
                echo json_encode(['message' => 'Password mancante.']);
                exit;
            }
            $hashed_password_from_db = getAdminSetting($mysqli, ADMIN_PASSWORD_KEY);
            if ($hashed_password_from_db === null) { //  Se null, o non esiste o errore DB (loggato da getAdminSetting)
                http_response_code(401);
                echo json_encode(['message' => 'Password amministratore non ancora impostata o errore nel recupero.']);
                exit;
            }

            if (password_verify(trim($data->password), $hashed_password_from_db)) {
                http_response_code(200);
                // In un sistema reale, qui genereresti un token JWT o di sessione server-side sicuro
                echo json_encode(['message' => 'Login amministratore riuscito.', 'isAdminAuthenticated' => true, 'token' => bin2hex(random_bytes(16))]); // Token fittizio per demo
            } else {
                http_response_code(401);
                echo json_encode(['message' => 'Password amministratore errata.']);
            }
        } else {
            http_response_code(405);
            echo json_encode(['message' => 'Metodo non consentito per login_admin.']);
        }
        break;

    case 'change_password':
        if ($method == 'PUT' || $method == 'POST') {
            if (!isset($data->current_password) || !isset($data->new_password) || empty(trim($data->new_password))) {
                http_response_code(400);
                echo json_encode(['message' => 'Password corrente e nuova password sono richieste.']);
                exit;
            }
            if (strlen(trim($data->new_password)) < 8) {
                 http_response_code(400);
                 echo json_encode(['message' => 'La nuova password deve essere di almeno 8 caratteri.']);
                 exit;
            }

            $hashed_password_from_db = getAdminSetting($mysqli, ADMIN_PASSWORD_KEY);
            if ($hashed_password_from_db === null) {
                http_response_code(404);
                echo json_encode(['message' => 'Nessuna password amministratore trovata da modificare. Impostane una prima.']);
                exit;
            }

            if (password_verify(trim($data->current_password), $hashed_password_from_db)) {
                $password_to_hash_change = trim($data->new_password);
                if (defined('PASSWORD_ARGON2ID')) {
                    $hashed_new_password = password_hash($password_to_hash_change, PASSWORD_ARGON2ID);
                } else {
                    $hashed_new_password = password_hash($password_to_hash_change, PASSWORD_DEFAULT);
                }

                if ($hashed_new_password === false) {
                    error_log("AdminAuthAPI - change_password: password_hash() ha fallito.");
                    http_response_code(500);
                    echo json_encode(['message' => 'Errore interno del server durante la creazione sicura della nuova password.']);
                    exit;
                }

                if (setAdminSetting($mysqli, ADMIN_PASSWORD_KEY, $hashed_new_password)) {
                    http_response_code(200);
                    echo json_encode(['message' => 'Password amministratore cambiata con successo.']);
                } else {
                    http_response_code(500);
                    echo json_encode(['message' => 'Errore durante il cambio della password.']);
                }
            } else {
                http_response_code(401);
                echo json_encode(['message' => 'Password corrente errata.']);
            }
        } else {
            http_response_code(405);
            echo json_encode(['message' => 'Metodo non consentito per change_password.']);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(['message' => 'Azione admin auth non valida.']);
        break;
}

if ($mysqli && ($mysqli instanceof mysqli) && !$mysqli->connect_error) {
    $mysqli->close();
}
?>