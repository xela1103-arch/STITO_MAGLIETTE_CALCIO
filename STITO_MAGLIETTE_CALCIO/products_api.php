<?php
header("Access-Control-Allow-Origin: *"); // Per sviluppo, in produzione restringere
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once 'db_config.php'; // Il tuo file di connessione al DB

$method = $_SERVER['REQUEST_METHOD'];

if ($method == "OPTIONS") {
    http_response_code(200);
    exit();
}

$action = isset($_GET['action']) ? $_GET['action'] : null;

// Gestione Prodotti
if ($action === null) {
    switch ($method) {
        case 'GET':
            getProducts($mysqli);
            break;
        case 'POST':
            addProduct($mysqli);
            break;
        case 'PUT':
            updateProduct($mysqli);
            break;
        case 'DELETE':
            deleteProduct($mysqli);
            break;
        default:
            http_response_code(405);
            echo json_encode(['message' => 'Metodo non supportato per i prodotti']);
            break;
    }
}
// Gestione Wishlist
elseif ($action === 'wishlist') {
    switch ($method) {
        case 'GET':
            getWishlist($mysqli);
            break;
        case 'POST':
            addToWishlist($mysqli);
            break;
        case 'DELETE':
            removeFromWishlist($mysqli);
            break;
        default:
            http_response_code(405);
            echo json_encode(['message' => 'Metodo non supportato per la wishlist']);
            break;
    }
}
// Estensione per Admin: Svuota tutti i prodotti
elseif ($action === 'clearallproducts' && $method === 'DELETE' && isset($_GET['confirm']) && $_GET['confirm'] === 'true') {
     clearAllProducts($mysqli);
}
else {
    http_response_code(400);
    echo json_encode(['message' => 'Azione non valida o mancante.']);
}


// --- FUNZIONI PRODOTTI ---
function getProducts($db) {
    $sql = "SELECT * FROM products ORDER BY created_at DESC";
    $result = $db->query($sql);
    $products = [];
    if ($result) {
        while($row = $result->fetch_assoc()){
            $row['availableSizes'] = $row['availableSizes'] ? json_decode($row['availableSizes']) : [];
            $row['availableColors'] = $row['availableColors'] ? json_decode($row['availableColors']) : [];
            $products[] = $row;
        }
        echo json_encode($products);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Errore nel recupero dei prodotti: ' . $db->error]);
    }
}

function addProduct($db) {
    $data = json_decode(file_get_contents("php://input"));
    if (!$data || !isset($data->name) || !isset($data->description) || !isset($data->price) || !isset($data->category) || !isset($data->imageSmall)) {
        http_response_code(400);
        echo json_encode(['message' => 'Dati incompleti per aggiungere il prodotto.']);
        return;
    }

    $name = $data->name;
    $description = $data->description;
    $price = floatval($data->price);
    $category = $data->category;
    $imageSmall = $data->imageSmall;
    $imageLarge = isset($data->imageLarge) && !empty($data->imageLarge) ? $data->imageLarge : $imageSmall;
    $availableSizes = isset($data->availableSizes) && is_array($data->availableSizes) ? json_encode($data->availableSizes) : json_encode([]);
    $availableColors = isset($data->availableColors) && is_array($data->availableColors) ? json_encode($data->availableColors) : json_encode([]);

    $stmt = $db->prepare("INSERT INTO products (name, description, price, category, imageSmall, imageLarge, availableSizes, availableColors) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['message' => 'Errore nella preparazione della query: ' . $db->error]);
        return;
    }
    $stmt->bind_param("ssdsssss", $name, $description, $price, $category, $imageSmall, $imageLarge, $availableSizes, $availableColors);

    if ($stmt->execute()) {
        $last_id = $db->insert_id;
        $newProductQuery = "SELECT * FROM products WHERE id = ?";
        $stmt_get = $db->prepare($newProductQuery);
        $stmt_get->bind_param("i", $last_id);
        $stmt_get->execute();
        $result = $stmt_get->get_result();

        if ($result && $new_product = $result->fetch_assoc()) {
            $new_product['availableSizes'] = json_decode($new_product['availableSizes']);
            $new_product['availableColors'] = json_decode($new_product['availableColors']);
            http_response_code(201);
            echo json_encode($new_product);
        } else {
            http_response_code(201);
            echo json_encode(['message' => 'Prodotto aggiunto con successo (ID: '.$last_id.'), ma recupero fallito.', 'id' => $last_id]);
        }
        $stmt_get->close();
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Errore aggiunta prodotto: ' . $stmt->error]);
    }
    $stmt->close();
}

function updateProduct($db) {
    $data = json_decode(file_get_contents("php://input"));
    if (!$data || !isset($data->id) || !isset($data->name) || !isset($data->description) ||  !isset($data->price) || !isset($data->category) || !isset($data->imageSmall)) {
        http_response_code(400);
        echo json_encode(['message' => 'Dati incompleti per aggiornare il prodotto.']);
        return;
    }
    $id = intval($data->id);
    $name = $data->name;
    $description = $data->description;
    $price = floatval($data->price);
    $category = $data->category;
    $imageSmall = $data->imageSmall;
    $imageLarge = isset($data->imageLarge) && !empty($data->imageLarge) ? $data->imageLarge : $imageSmall;
    $availableSizes = isset($data->availableSizes) && is_array($data->availableSizes) ? json_encode($data->availableSizes) : json_encode([]);
    $availableColors = isset($data->availableColors) && is_array($data->availableColors) ? json_encode($data->availableColors) : json_encode([]);

    $stmt = $db->prepare("UPDATE products SET name = ?, description = ?, price = ?, category = ?, imageSmall = ?, imageLarge = ?, availableSizes = ?, availableColors = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['message' => 'Errore nella preparazione della query di update: ' . $db->error]);
        return;
    }
    $stmt->bind_param("ssdsssssi", $name, $description, $price, $category, $imageSmall, $imageLarge, $availableSizes, $availableColors, $id);

    if ($stmt->execute()) {
        if ($db->affected_rows > 0) {
            $updatedProductQuery = "SELECT * FROM products WHERE id = ?";
            $stmt_get = $db->prepare($updatedProductQuery);
            $stmt_get->bind_param("i", $id);
            $stmt_get->execute();
            $result = $stmt_get->get_result();

            if ($result && $updated_product = $result->fetch_assoc()) {
                $updated_product['availableSizes'] = json_decode($updated_product['availableSizes']);
                $updated_product['availableColors'] = json_decode($updated_product['availableColors']);
                echo json_encode($updated_product);
            } else {
                echo json_encode(['message' => 'Prodotto modificato (ID: '.$id.'), ma recupero fallito.']);
            }
            $stmt_get->close();
        } else {
            echo json_encode(['message' => 'Nessuna modifica o prodotto non trovato (ID: '.$id.').']);
        }
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Errore modifica prodotto: ' . $stmt->error]);
    }
    $stmt->close();
}

function deleteProduct($db) {
    if (!isset($_GET['id'])) {
        http_response_code(400);
        echo json_encode(['message' => 'ID prodotto mancante per eliminazione.']);
        return;
    }
    $id = intval($_GET['id']);
    $stmt = $db->prepare("DELETE FROM products WHERE id = ?");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['message' => 'Errore nella preparazione della query di delete: ' . $db->error]);
        return;
    }
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        if ($db->affected_rows > 0) {
            echo json_encode(['message' => 'Prodotto eliminato con successo.']);
        } else {
            http_response_code(404);
            echo json_encode(['message' => 'Prodotto non trovato o già eliminato.']);
        }
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Errore nell\'eliminazione del prodotto: ' . $stmt->error]);
    }
    $stmt->close();
}

function clearAllProducts($db) {
    $deleteWishlistItemsSql = "DELETE FROM wishlist_items";
    if (!$db->query($deleteWishlistItemsSql)) {
        error_log("Errore durante la pulizia preliminare di wishlist_items: " . $db->error);
    }

    $sql = "TRUNCATE TABLE products";
    if ($db->query($sql) === TRUE) {
        echo json_encode(['message' => 'Tutti i prodotti sono stati eliminati con successo (via TRUNCATE).']);
    } else {
        error_log("TRUNCATE TABLE products fallito: " . $db->error . ". Tentativo con DELETE FROM.");
        $sql_delete = "DELETE FROM products";
        if ($db->query($sql_delete) === TRUE) {
             echo json_encode(['message' => 'Tutti i prodotti sono stati eliminati con successo (via DELETE).']);
        } else {
            http_response_code(500);
            echo json_encode(['message' => 'Errore nell\'eliminazione di tutti i prodotti: ' . $db->error]);
        }
    }
}

// --- FUNZIONI WISHLIST (Modificate per richiedere utente loggato) ---
function getWishlist($db) {
    if (!isset($_GET['user_id'])) {
        http_response_code(400);
        echo json_encode(['message' => 'User ID mancante per recuperare la wishlist.']);
        return;
    }
    $user_id_param = $_GET['user_id'];

    if (!is_numeric($user_id_param) || intval($user_id_param) <= 0) {
        http_response_code(401);
        echo json_encode(['message' => 'Accesso non autorizzato o User ID non valido per la wishlist. Devi essere loggato.']);
        return;
    }
    $numeric_user_id = intval($user_id_param);

    $checkUserStmt = $db->prepare("SELECT id FROM users WHERE id = ?");
    if (!$checkUserStmt) {
        http_response_code(500); echo json_encode(['message' => 'Errore preparazione verifica utente: ' . $db->error]); return;
    }
    $checkUserStmt->bind_param("i", $numeric_user_id);
    $checkUserStmt->execute();
    $userResult = $checkUserStmt->get_result();
    if ($userResult->num_rows === 0) {
        http_response_code(401);
        echo json_encode(['message' => 'Utente non trovato. Devi essere loggato per usare la wishlist.']);
        $checkUserStmt->close();
        return;
    }
    $checkUserStmt->close();

    $sql = "SELECT p.* FROM products p INNER JOIN wishlist_items w ON p.id = w.product_id WHERE w.user_id = ? ORDER BY w.added_at DESC";
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        http_response_code(500); echo json_encode(['message' => 'Errore preparazione query wishlist: ' . $db->error]); return;
    }
    $stmt->bind_param("i", $numeric_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $wishlist_products = [];
    if ($result) {
        while($row = $result->fetch_assoc()){
            $row['availableSizes'] = $row['availableSizes'] ? json_decode($row['availableSizes']) : [];
            $row['availableColors'] = $row['availableColors'] ? json_decode($row['availableColors']) : [];
            $wishlist_products[] = $row;
        }
        echo json_encode($wishlist_products);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Errore nel recupero dei prodotti della wishlist: ' . $stmt->error]);
    }
    $stmt->close();
}

function addToWishlist($db) {
    $data = json_decode(file_get_contents("php://input"));

    if (!isset($data->user_id) || !isset($data->product_id)) {
        http_response_code(400);
        echo json_encode(['message' => 'User ID o Product ID mancante per aggiungere alla wishlist.']);
        return;
    }

    $user_id_param = $data->user_id;
    $product_id = intval($data->product_id);

    if (!is_numeric($user_id_param) || intval($user_id_param) <= 0) {
        http_response_code(401);
        echo json_encode(['message' => 'Accesso non autorizzato o User ID non valido. Devi essere loggato per aggiungere ai preferiti.']);
        return;
    }
    $numeric_user_id = intval($user_id_param);

    $stmtUser = $db->prepare("SELECT id FROM users WHERE id = ?");
    if (!$stmtUser) { http_response_code(500); echo json_encode(['message' => 'Errore preparazione query utente: ' . $db->error]); return; }
    $stmtUser->bind_param("i", $numeric_user_id);
    $stmtUser->execute();
    $userResultDb = $stmtUser->get_result();
    if ($userResultDb->num_rows === 0) {
        http_response_code(401); echo json_encode(['message' => 'Utente non trovato. Devi essere loggato.']); $stmtUser->close(); return;
    }
    $stmtUser->close();
    
    $stmtProd = $db->prepare("SELECT id FROM products WHERE id = ?");
    if (!$stmtProd) { http_response_code(500); echo json_encode(['message' => 'Errore preparazione query prodotto: ' . $db->error]); return; }
    $stmtProd->bind_param("i", $product_id);
    $stmtProd->execute();
    $productResult = $stmtProd->get_result();
    if ($productResult->num_rows === 0) {
        http_response_code(404); echo json_encode(['message' => 'Prodotto non trovato.']); $stmtProd->close(); return;
    }
    $stmtProd->close();

    $stmtInsert = $db->prepare("INSERT INTO wishlist_items (user_id, product_id) VALUES (?, ?)");
    if (!$stmtInsert) { http_response_code(500); echo json_encode(['message' => 'Errore preparazione query inserimento wishlist: ' . $db->error]); return; }
    $stmtInsert->bind_param("ii", $numeric_user_id, $product_id);

    if ($stmtInsert->execute()) {
        http_response_code(201);
        echo json_encode(['message' => 'Prodotto aggiunto alla wishlist.', 'product_id' => $product_id]);
    } else {
        if ($db->errno == 1062) { 
            http_response_code(200); 
            echo json_encode(['message' => 'Prodotto già presente nella wishlist.', 'product_id' => $product_id]);
        } else {
            http_response_code(500);
            echo json_encode(['message' => 'Errore nell\'aggiunta alla wishlist: ' . $stmtInsert->error]);
        }
    }
    $stmtInsert->close();
}

function removeFromWishlist($db) {
    if (!isset($_GET['user_id']) || !isset($_GET['product_id'])) {
        http_response_code(400);
        echo json_encode(['message' => 'User ID o Product ID mancante per rimuovere dalla wishlist.']);
        return;
    }
    $user_id_param = $_GET['user_id'];
    $product_id = intval($_GET['product_id']);
    
    if (!is_numeric($user_id_param) || intval($user_id_param) <= 0) {
        http_response_code(401);
        echo json_encode(['message' => 'Accesso non autorizzato o User ID non valido. Devi essere loggato.']);
        return;
    }
    $numeric_user_id = intval($user_id_param);

    $stmtUser = $db->prepare("SELECT id FROM users WHERE id = ?");
    if (!$stmtUser) { http_response_code(500); echo json_encode(['message' => 'Errore preparazione query utente: ' . $db->error]); return; }
    $stmtUser->bind_param("i", $numeric_user_id);
    $stmtUser->execute();
    $userResultDb = $stmtUser->get_result();
    if ($userResultDb->num_rows === 0) {
        http_response_code(401); echo json_encode(['message' => 'Utente non trovato. Devi essere loggato.']); $stmtUser->close(); return;
    }
    $stmtUser->close();

    $stmtDelete = $db->prepare("DELETE FROM wishlist_items WHERE user_id = ? AND product_id = ?");
    if (!$stmtDelete) { http_response_code(500); echo json_encode(['message' => 'Errore preparazione query rimozione wishlist: ' . $db->error]); return; }
    $stmtDelete->bind_param("ii", $numeric_user_id, $product_id);

    if ($stmtDelete->execute()) {
        if ($stmtDelete->affected_rows > 0) {
            echo json_encode(['message' => 'Prodotto rimosso dalla wishlist.', 'product_id' => $product_id]);
        } else {
            http_response_code(200); 
            echo json_encode(['message' => 'Prodotto non trovato nella wishlist o già rimosso.', 'product_id' => $product_id]);
        }
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Errore nella rimozione dalla wishlist: ' . $stmtDelete->error]);
    }
    $stmtDelete->close();
}

$mysqli->close();
?>