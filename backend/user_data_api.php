<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once 'db_config.php'; // Connessione al DB

$method = $_SERVER['REQUEST_METHOD'];

if ($method == "OPTIONS") {
    http_response_code(200);
    exit();
}

$userId = null;
if (isset($_GET['user_id'])) {
    $userId = intval($_GET['user_id']);
} elseif (isset($_POST['user_id'])) {
    $userId = intval($_POST['user_id']);
} else {
    $request_body = json_decode(file_get_contents("php://input"));
    if (isset($request_body->user_id)) {
        $userId = intval($request_body->user_id);
    }
}

if (!$userId) {
    http_response_code(401);
    echo json_encode(['message' => 'User ID mancante o non autorizzato.']);
    exit;
}
$userCheckStmt = $mysqli->prepare("SELECT id FROM users WHERE id = ?");
$userCheckStmt->bind_param("i", $userId);
$userCheckStmt->execute();
if ($userCheckStmt->get_result()->num_rows === 0) {
    http_response_code(401);
    echo json_encode(['message' => 'Utente non valido.']);
    $userCheckStmt->close();
    exit;
}
$userCheckStmt->close();

$action = isset($_GET['action']) ? $_GET['action'] : '';
$data = json_decode(file_get_contents("php://input"));

switch ($action) {
    case 'get_personal_data':
        if ($method == 'GET') {
            getPersonalData($mysqli, $userId);
        }
        break;
    case 'save_personal_data':
        if ($method == 'POST' || $method == 'PUT') {
            savePersonalData($mysqli, $userId, $data);
        }
        break;
    // ... (altre actions per indirizzi rimangono invariate)
    case 'get_addresses':
        if ($method == 'GET') {
            getAddresses($mysqli, $userId);
        }
        break;
    case 'add_address':
        if ($method == 'POST') {
            addAddress($mysqli, $userId, $data);
        }
        break;
    case 'update_address':
        $addressId = isset($_GET['address_id']) ? intval($_GET['address_id']) : (isset($data->id) ? intval($data->id) : null);
        if ($method == 'PUT' && $addressId) {
            updateAddress($mysqli, $userId, $addressId, $data);
        } else {
             http_response_code(400); echo json_encode(['message' => 'ID Indirizzo mancante per l\'aggiornamento.']);
        }
        break;
    case 'delete_address':
        $addressId = isset($_GET['address_id']) ? intval($_GET['address_id']) : null;
        if ($method == 'DELETE' && $addressId) {
            deleteAddress($mysqli, $userId, $addressId);
        } else {
            http_response_code(400); echo json_encode(['message' => 'ID Indirizzo mancante per l\'eliminazione.']);
        }
        break;
    case 'set_default_address':
        $addressId = isset($data->address_id) ? intval($data->address_id) : null;
        if (($method == 'POST' || $method == 'PUT') && $addressId) {
            setDefaultAddress($mysqli, $userId, $addressId);
        } else {
            http_response_code(400); echo json_encode(['message' => 'ID Indirizzo mancante per impostare come predefinito.']);
        }
        break;
    default:
        http_response_code(404);
        echo json_encode(['message' => 'Azione dati utente non valida.']);
        break;
}

function getPersonalData($db, $userId) {
    // Ora recuperiamo username e loginEmail da users, e il resto da user_personal_data
    $stmt = $db->prepare("SELECT u.username, u.email as login_email, 
                                 pd.full_name, pd.phone, pd.email as contact_email
                          FROM users u 
                          LEFT JOIN user_personal_data pd ON u.id = pd.user_id 
                          WHERE u.id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($personalData = $result->fetch_assoc()) {
        $response = [
            'username' => $personalData['username'] ?? '', // Dalla tabella users
            'loginEmail' => $personalData['login_email'] ?? '', // Dalla tabella users
            'contactFullName' => $personalData['full_name'] ?? '',
            'contactPhone' => $personalData['phone'] ?? '',
            'contactEmail' => $personalData['contact_email'] ?? '' // Email di contatto da user_personal_data
        ];
        echo json_encode($response);
    } else {
        http_response_code(404);
        echo json_encode(['message' => 'Dati personali non trovati.']);
    }
    $stmt->close();
}

function savePersonalData($db, $userId, $data) {
    // Ora $data dovrebbe contenere: contactFullName, contactEmail, contactPhone
    // L'email di login (users.email) NON viene modificata qui.
    if (!isset($data->contactFullName) || !isset($data->contactEmail) || !isset($data->contactPhone)) {
        http_response_code(400); 
        echo json_encode(['message' => 'Dati di contatto incompleti (Nome, Email Contatto, Telefono).']); 
        return;
    }
    $contactFullName = $data->contactFullName;
    $contactEmail = $data->contactEmail; // Questa è user_personal_data.email
    $contactPhone = $data->contactPhone;

    // Validazione email di contatto opzionale (se fornita, deve essere valida)
    if (!empty($contactEmail) && !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['message' => 'Formato email di contatto non valido.']);
        return;
    }

    // Aggiorna o inserisce dati in user_personal_data
    // (users.email non viene toccata qui)
    $stmtPd = $db->prepare("INSERT INTO user_personal_data (user_id, full_name, email, phone) VALUES (?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), email = VALUES(email), phone = VALUES(phone)");
    $stmtPd->bind_param("isss", $userId, $contactFullName, $contactEmail, $contactPhone);
    
    if ($stmtPd->execute()) {
        http_response_code(200);
        echo json_encode(['message' => 'Dati di contatto salvati con successo.']);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Errore salvataggio dati di contatto: ' . $stmtPd->error]);
    }
    $stmtPd->close();
}

// --- FUNZIONI PER INDIRIZZI (invariate, ma riproposte per completezza) ---
function getAddresses($db, $userId) {
    $stmt = $db->prepare("SELECT id, street, city, zip_code, is_default FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, created_at DESC");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $addresses = [];
    while ($row = $result->fetch_assoc()) {
        $row['is_default'] = (bool)$row['is_default'];
        $addresses[] = $row;
    }
    echo json_encode($addresses);
    $stmt->close();
}

function addAddress($db, $userId, $data) {
    if (!isset($data->street) || !isset($data->city) || !isset($data->zip_code)) {
        http_response_code(400); echo json_encode(['message' => 'Dati indirizzo incompleti.']); return;
    }
    $street = $data->street;
    $city = $data->city;
    $zip_code = $data->zip_code;

    $countStmt = $db->prepare("SELECT COUNT(*) as address_count FROM user_addresses WHERE user_id = ?");
    $countStmt->bind_param("i", $userId);
    $countStmt->execute();
    $countResult = $countStmt->get_result()->fetch_assoc();
    $is_first_address = ($countResult['address_count'] == 0);
    $countStmt->close();

    $is_default_request = isset($data->is_default) ? (bool)$data->is_default : false;
    if ($is_first_address) {
        $is_default_final = true;
    } else {
        $is_default_final = $is_default_request;
    }

    if ($is_default_final) {
        $updateOldDefaultStmt = $db->prepare("UPDATE user_addresses SET is_default = FALSE WHERE user_id = ? AND is_default = TRUE");
        $updateOldDefaultStmt->bind_param("i", $userId);
        $updateOldDefaultStmt->execute();
        $updateOldDefaultStmt->close();
    }

    $stmt = $db->prepare("INSERT INTO user_addresses (user_id, street, city, zip_code, is_default) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isssi", $userId, $street, $city, $zip_code, $is_default_final);
    if ($stmt->execute()) {
        $newAddressId = $db->insert_id;
        http_response_code(201);
        $newAddressData = [
            'id' => $newAddressId, 
            'street' => $street, 
            'city' => $city, 
            'zip_code' => $zip_code, 
            'is_default' => $is_default_final
        ];
        echo json_encode(['message' => 'Indirizzo aggiunto.', 'address' => $newAddressData]);
    } else {
        http_response_code(500); echo json_encode(['message' => 'Errore aggiunta indirizzo: ' . $stmt->error]);
    }
    $stmt->close();
}

function updateAddress($db, $userId, $addressId, $data) {
    if (!isset($data->street) || !isset($data->city) || !isset($data->zip_code)) {
        http_response_code(400); echo json_encode(['message' => 'Dati indirizzo incompleti per aggiornamento.']); return;
    }
    $street = $data->street;
    $city = $data->city;
    $zip_code = $data->zip_code;

    $stmt = $db->prepare("UPDATE user_addresses SET street = ?, city = ?, zip_code = ? WHERE id = ? AND user_id = ?");
    $stmt->bind_param("sssii", $street, $city, $zip_code, $addressId, $userId);
    if ($stmt->execute()) {
        if ($db->affected_rows > 0) {
            $getStmt = $db->prepare("SELECT id, street, city, zip_code, is_default FROM user_addresses WHERE id = ?");
            $getStmt->bind_param("i", $addressId);
            $getStmt->execute();
            $updatedAddress = $getStmt->get_result()->fetch_assoc();
            $updatedAddress['is_default'] = (bool)$updatedAddress['is_default'];
            $getStmt->close();
            echo json_encode(['message' => 'Indirizzo aggiornato.', 'address' => $updatedAddress]);
        } else {
            http_response_code(404);
            echo json_encode(['message' => 'Nessuna modifica o indirizzo non trovato per questo utente.']);
        }
    } else {
        http_response_code(500); echo json_encode(['message' => 'Errore aggiornamento indirizzo: ' . $stmt->error]);
    }
    $stmt->close();
}

function deleteAddress($db, $userId, $addressId) {
    $wasDefaultStmt = $db->prepare("SELECT is_default FROM user_addresses WHERE id = ? AND user_id = ?");
    $wasDefaultStmt->bind_param("ii", $addressId, $userId);
    $wasDefaultStmt->execute();
    $wasDefaultResult = $wasDefaultStmt->get_result()->fetch_assoc();
    $wasDefaultStmt->close();

    if (!$wasDefaultResult) {
        http_response_code(404); echo json_encode(['message' => 'Indirizzo non trovato per l\'eliminazione.']); return;
    }
    $wasThisDefault = (bool)$wasDefaultResult['is_default'];

    $stmt = $db->prepare("DELETE FROM user_addresses WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $addressId, $userId);
    if ($stmt->execute()) {
        if ($db->affected_rows > 0) {
            if ($wasThisDefault) {
                $remainingAddressesStmt = $db->prepare("SELECT id FROM user_addresses WHERE user_id = ? ORDER BY created_at ASC LIMIT 1");
                $remainingAddressesStmt->bind_param("i", $userId);
                $remainingAddressesStmt->execute();
                $newDefaultCandidate = $remainingAddressesStmt->get_result()->fetch_assoc();
                $remainingAddressesStmt->close();

                if ($newDefaultCandidate) {
                    setDefaultAddress($db, $userId, $newDefaultCandidate['id'], false);
                }
            }
            echo json_encode(['message' => 'Indirizzo eliminato.']);
        } else {
            http_response_code(404); echo json_encode(['message' => 'Indirizzo non trovato o già eliminato.']);
        }
    } else {
        http_response_code(500); echo json_encode(['message' => 'Errore eliminazione indirizzo: ' . $stmt->error]);
    }
    $stmt->close();
}

function setDefaultAddress($db, $userId, $addressId, $outputJson = true) {
    $db->begin_transaction();
    try {
        $stmtUnset = $db->prepare("UPDATE user_addresses SET is_default = FALSE WHERE user_id = ? AND is_default = TRUE");
        $stmtUnset->bind_param("i", $userId);
        $stmtUnset->execute();
        $stmtUnset->close();

        $stmtSet = $db->prepare("UPDATE user_addresses SET is_default = TRUE WHERE id = ? AND user_id = ?");
        $stmtSet->bind_param("ii", $addressId, $userId);
        $stmtSet->execute();
        $affectedRowsSet = $db->affected_rows;
        $stmtSet->close();

        $db->commit();

        if ($outputJson) {
            if ($affectedRowsSet > 0) {
                echo json_encode(['message' => 'Indirizzo predefinito aggiornato.']);
            } else {
                http_response_code(404);
                echo json_encode(['message' => 'Indirizzo da impostare come predefinito non trovato o nessun cambiamento.']);
            }
        }
        return true;

    } catch (mysqli_sql_exception $exception) {
        $db->rollback();
        if ($outputJson) {
            http_response_code(500);
            echo json_encode(['message' => 'Errore impostazione indirizzo predefinito: ' . $exception->getMessage()]);
        }
        return false;
    }
}

$mysqli->close();
?>