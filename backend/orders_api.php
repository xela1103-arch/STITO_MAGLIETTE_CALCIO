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
if (($method == 'GET' || $method == 'PUT') && isset($_GET['user_id'])) {
    $userId = intval($_GET['user_id']);
} elseif ($method == 'POST') {
    $request_body_auth_check = json_decode(file_get_contents("php://input"));
    if (isset($request_body_auth_check->userId)) {
        $userId = intval($request_body_auth_check->userId);
    }
}

$is_admin_action = isset($_GET['action']) && (
    $_GET['action'] === 'get_all_orders_admin' ||
    $_GET['action'] === 'update_order_status_admin' ||
    $_GET['action'] === 'delete_order_admin' ||
    $_GET['action'] === 'get_order_detail_admin'
);

if (!$is_admin_action && !$userId && $_GET['action'] !== 'create_order') {
    http_response_code(401);
    echo json_encode(['message' => 'User ID mancante o non autorizzato per questa azione utente.']);
    exit;
}

if (!$is_admin_action && $userId) {
    $userCheckStmt = $mysqli->prepare("SELECT id FROM users WHERE id = ?");
    if ($userCheckStmt) {
        $userCheckStmt->bind_param("i", $userId);
        $userCheckStmt->execute();
        if ($userCheckStmt->get_result()->num_rows === 0) {
            http_response_code(401);
            echo json_encode(['message' => 'Utente non valido.']);
            $userCheckStmt->close();
            exit;
        }
        $userCheckStmt->close();
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Errore interno del server (controllo utente).']);
        exit;
    }
}

$action = isset($_GET['action']) ? $_GET['action'] : '';
$data = json_decode(file_get_contents("php://input"));

switch ($action) {
    case 'create_order':
        if ($method == 'POST') {
            createOrder($mysqli, $data);
        }
        break;
    case 'get_orders':
        if ($method == 'GET' && $userId) {
            getUserOrders($mysqli, $userId);
        } else if ($method == 'GET' && !$userId) {
            http_response_code(400); echo json_encode(['message' => 'User ID mancante per get_orders.']);
        }
        break;
    case 'get_order_detail':
        $orderId = isset($_GET['order_id']) ? intval($_GET['order_id']) : null;
        if ($method == 'GET' && $orderId && $userId) {
            getOrderDetail($mysqli, $userId, $orderId);
        } else {
            http_response_code(400); echo json_encode(['message' => 'User ID o Order ID mancante per get_order_detail.']);
        }
        break;
    case 'cancel_order':
        $orderIdToCancel = isset($_GET['order_id']) ? intval($_GET['order_id']) : null;
        if ($method == 'PUT' && $orderIdToCancel && $userId) {
            cancelUserOrder($mysqli, $userId, $orderIdToCancel);
        } else {
            http_response_code(400);
            echo json_encode(['message' => 'User ID o ID Ordine mancante, o metodo non corretto per l\'annullamento utente.']);
        }
        break;
    case 'get_all_orders_admin':
        if ($method == 'GET') {
            getAllOrdersAdmin($mysqli);
        }
        break;
    case 'update_order_status_admin':
        if ($method == 'PUT') {
            updateOrderStatusAdmin($mysqli, $data);
        }
        break;
    case 'delete_order_admin':
        $orderIdToDeleteAdmin = isset($_GET['order_id']) ? intval($_GET['order_id']) : null;
        if ($method == 'DELETE' && $orderIdToDeleteAdmin) {
            deleteOrderAdmin($mysqli, $orderIdToDeleteAdmin);
        } else {
             http_response_code(400); echo json_encode(['message' => 'Order ID mancante per l\'eliminazione admin.']);
        }
        break;
    case 'get_order_detail_admin':
        $orderIdDetailAdmin = isset($_GET['order_id']) ? intval($_GET['order_id']) : null;
        if ($method == 'GET' && $orderIdDetailAdmin) {
            getOrderDetailAdmin($mysqli, $orderIdDetailAdmin);
        } else {
            http_response_code(400); echo json_encode(['message' => 'Order ID mancante per i dettagli admin.']);
        }
        break;
    default:
        http_response_code(404);
        echo json_encode(['message' => 'Azione ordini non valida.']);
        break;
}

function createOrder($db, $orderData) {
    error_log("createOrder API - Inizio. Dati ricevuti: " . print_r($orderData, true));

    $requiredOrderFields = [
        'userId', 'numericOrderId', 'items', 'fullName', 'email', 'street', 'city', 'zip',
        'shippingMethod', 'shippingCost', 'paymentMethod', 'subtotal', 'totalAmount'
    ];
    foreach ($requiredOrderFields as $field) {
        if (!isset($orderData->$field)) {
            error_log("createOrder API - Errore Validazione: Campo ordine mancante: " . $field);
            http_response_code(400);
            echo json_encode(['message' => 'Dati ordine incompleti. Campo mancante: ' . $field]);
            return;
        }
    }
    $currentUserId = intval($orderData->userId);

    $userCheckStmtOrder = $db->prepare("SELECT id FROM users WHERE id = ?");
    if (!$userCheckStmtOrder) {
        error_log("createOrder API - Errore preparazione verifica utente: " . $db->error);
        http_response_code(500); echo json_encode(['message' => 'Errore interno del server (controllo utente).']); return;
    }
    $userCheckStmtOrder->bind_param("i", $currentUserId);
    $userCheckStmtOrder->execute();
    if ($userCheckStmtOrder->get_result()->num_rows === 0) {
        error_log("createOrder API - Errore Validazione: Utente non trovato con ID: " . $currentUserId);
        http_response_code(401); echo json_encode(['message' => 'Utente non valido.']); $userCheckStmtOrder->close(); return;
    }
    $userCheckStmtOrder->close();

    if (!is_array($orderData->items) || empty($orderData->items)) {
        error_log("createOrder API - Errore Validazione: 'items' non è un array o è vuoto.");
        http_response_code(400); echo json_encode(['message' => 'Nessun articolo presente nell\'ordine.']); return;
    }

    foreach ($orderData->items as $index => $item) {
        $requiredItemFields = ['productId', 'name', 'quantity', 'price', 'imageSmall'];
        foreach ($requiredItemFields as $field) {
            if (!isset($item->$field)) {
                error_log("createOrder API - Errore Validazione: Campo articolo mancante (item " . $index . "): " . $field);
                http_response_code(400); echo json_encode(['message' => 'Dati articolo incompleti (articolo ' . ($index + 1) . '). Campo mancante: ' . $field]); return;
            }
        }
        if (!is_numeric($item->quantity) || $item->quantity <= 0) {
             error_log("createOrder API - Errore Validazione: Quantità articolo non valida (item " . $index . "): " . $item->quantity);
             http_response_code(400); echo json_encode(['message' => 'Quantità articolo non valida (articolo ' . ($index + 1) . ').']); return;
        }
        if (!is_numeric($item->price)) {
             error_log("createOrder API - Errore Validazione: Prezzo articolo non valido (item " . $index . "): " . $item->price);
             http_response_code(400); echo json_encode(['message' => 'Prezzo articolo non valido (articolo ' . ($index + 1) . ').']); return;
        }
    }
    error_log("createOrder API - Validazione dati superata.");

    $db->begin_transaction();
    error_log("createOrder API - Transazione iniziata.");

    try {
        $stmtOrder = $db->prepare(
            "INSERT INTO orders (numeric_order_id, user_id, status, full_name, email, phone, shipping_street, shipping_city, shipping_zip_code, shipping_method, shipping_cost, payment_method, subtotal, total_amount)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        if (!$stmtOrder) {
            error_log("createOrder API - Errore preparazione statement ordine: " . $db->error);
            throw new Exception("Errore preparazione statement ordine: " . $db->error);
        }

        $status = ($orderData->paymentMethod === 'paypal') ? 'pending_payment' : 'processing';
        $phone = isset($orderData->phone) ? $orderData->phone : null;
        $numericOrderId = intval($orderData->numericOrderId);
        $shippingCost = floatval($orderData->shippingCost);
        $subtotal = floatval($orderData->subtotal);
        $totalAmount = floatval($orderData->totalAmount);

        $stmtOrder->bind_param(
            "iisssssssdssdd",
            $numericOrderId,
            $currentUserId,
            $status,
            $orderData->fullName,
            $orderData->email,
            $phone,
            $orderData->street,
            $orderData->city,
            $orderData->zip,
            $orderData->shippingMethod,
            $shippingCost,
            $orderData->paymentMethod,
            $subtotal,
            $totalAmount
        );

        if (!$stmtOrder->execute()) {
            error_log("createOrder API - Errore esecuzione statement ordine: " . $stmtOrder->error);
            throw new Exception("Errore inserimento ordine: " . $stmtOrder->error);
        }
        $dbOrderId = $db->insert_id;
        $stmtOrder->close();
        error_log("createOrder API - Ordine principale inserito con ID DB: " . $dbOrderId);

        $stmtItem = $db->prepare(
            "INSERT INTO order_items (order_id, product_id, product_name, size, color, quantity, unit_price)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        if (!$stmtItem) {
            error_log("createOrder API - Errore preparazione statement articoli: " . $db->error);
            throw new Exception("Errore preparazione statement articoli: " . $db->error);
        }

        $items_for_email = [];
        $base_image_url = "https://alexander82.altervista.org/magliette%20calcio/img/";

        foreach ($orderData->items as $item) {
            $productId = intval($item->productId);
            $quantity = intval($item->quantity);
            $unitPrice = floatval($item->price);
            $size = (isset($item->size) && $item->size !== 'Unica' && !empty(trim($item->size))) ? trim($item->size) : null;
            $color = (isset($item->color) && $item->color !== 'Unico' && !empty(trim($item->color))) ? trim($item->color) : null;

            $stmtItem->bind_param(
                "iissssd",
                $dbOrderId,
                $productId,
                $item->name,
                $size,
                $color,
                $quantity,
                $unitPrice
            );
            if (!$stmtItem->execute()) {
                error_log("createOrder API - Errore esecuzione statement articolo: " . $stmtItem->error . " | Articolo: " . print_r($item, true));
                throw new Exception("Errore inserimento articolo ordine: " . $stmtItem->error);
            }

            $item_email_copy = clone $item;
            $image_path_or_url = $item_email_copy->imageSmall;
            if (filter_var($image_path_or_url, FILTER_VALIDATE_URL)) {
                $item_email_copy->imageSmall = $image_path_or_url;
            } else {
                $image_filename = ltrim(basename($image_path_or_url), '/');
                $item_email_copy->imageSmall = $base_image_url . rawurlencode($image_filename);
            }
            $items_for_email[] = $item_email_copy;
        }
        $stmtItem->close();
        error_log("createOrder API - Articoli ordine inseriti.");

        $db->commit();
        error_log("createOrder API - Transazione commessa con successo.");

        $displayOrderId = '#' . str_pad($numericOrderId, 4, '0', STR_PAD_LEFT);
        $url_negozio_base = "https://alexander82.altervista.org/magliette%20calcio/";
        $url_homepage_negozio = $url_negozio_base . "index.html";
        $admin_order_management_url = $url_negozio_base . "admin-manage-orders.html";

        $dati_email_comuni = [
            'id_ordine_db' => $dbOrderId,
            'id_ordine_display' => $displayOrderId,
            'data_ordine' => date('d/m/Y H:i'),
            'indirizzo_spedizione' => [
                'nome' => $orderData->fullName,
                'via' => $orderData->street,
                'citta' => $orderData->city,
                'cap' => $orderData->zip,
                'telefono' => $phone ?? 'N/D'
            ],
            'articoli' => $items_for_email,
            'subtotale' => $subtotal,
            'costo_spedizione' => $shippingCost,
            'totale_ordine' => $totalAmount,
            'metodo_pagamento' => ucfirst(str_replace('_', ' ', $orderData->paymentMethod)),
            'metodo_spedizione' => ucfirst(str_replace('_', ' ', $orderData->shippingMethod)),
            'logo_url' => $url_negozio_base . 'logo.png',
            'nome_negozio' => 'E-Shop Kids',
            'url_negozio_homepage' => $url_homepage_negozio,
            'admin_order_management_url' => $admin_order_management_url . '?order_id=' . $dbOrderId
        ];

        // ---- INVIO EMAIL AL CLIENTE ----
        $email_destinatario_cliente = $orderData->email;
        $nome_destinatario_cliente = $orderData->fullName;
        $email_oggetto_cliente = "Conferma Ordine " . $displayOrderId . " - " . $dati_email_comuni['nome_negozio'];
        $dati_email_cliente = array_merge($dati_email_comuni, ['nome_cliente' => $nome_destinatario_cliente]);

        // LOGGING PER EMAIL CLIENTE
        $corpo_email_html_cliente = "";
        ob_start();
        extract($dati_email_cliente);
        $template_path_cliente = __DIR__ . '/template_email_conferma_ordine.php'; // AGGIORNATO .php
        error_log("Email Cliente - Tentativo inclusione template: " . $template_path_cliente);
        if (file_exists($template_path_cliente)) {
            include $template_path_cliente;
            error_log("Email Cliente - Template incluso con successo.");
        } else {
            error_log("Email Cliente - ERRORE: Template NON TROVATO: " . $template_path_cliente);
            echo "ERRORE SERVER: Template email cliente mancante."; // Questo andrà nel buffer
        }
        $corpo_email_html_cliente = ob_get_clean();
        error_log("Email Cliente - Corpo generato (lunghezza: " . strlen($corpo_email_html_cliente) . "). Anteprima: " . substr(str_replace(["\r", "\n"], ' ', $corpo_email_html_cliente), 0, 500));
        if (empty(trim(strip_tags($corpo_email_html_cliente)))) { // Controllo aggiuntivo se il corpo è essenzialmente vuoto
            error_log("Email Cliente - ATTENZIONE: Corpo email cliente sembra vuoto dopo strip_tags.");
        }

        $headers_cliente = "MIME-Version: 1.0" . "\r\n";
        $headers_cliente .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers_cliente .= 'From: '.$dati_email_comuni['nome_negozio'].' <alexander.zed@hotmail.it>' . "\r\n";

        if (mail($email_destinatario_cliente, $email_oggetto_cliente, $corpo_email_html_cliente, $headers_cliente)) {
            error_log("createOrder API - Email di conferma cliente inviata a: " . $email_destinatario_cliente);
        } else {
            error_log("createOrder API - ERRORE INVIO EMAIL cliente a: " . $email_destinatario_cliente . ". Errore PHP Mail: " . print_r(error_get_last(), true));
        }

        // ---- INVIO EMAIL ALL'ADMIN ----
        $email_destinatario_admin = "alexander.zed@hotmail.it";
        $email_oggetto_admin = "Nuovo Ordine Ricevuto: " . $displayOrderId . " da " . $orderData->fullName;
        $dati_email_admin = array_merge($dati_email_comuni, [
            'nome_cliente' => $orderData->fullName,
            'email_cliente' => $orderData->email
        ]);

        // LOGGING PER EMAIL ADMIN
        $corpo_email_html_admin = "";
        ob_start();
        extract($dati_email_admin);
        $template_path_admin = __DIR__ . '/template_email_admin_nuovo_ordine.php'; // AGGIORNATO .php
        error_log("Email Admin - Tentativo inclusione template: " . $template_path_admin);
        if (file_exists($template_path_admin)) {
            include $template_path_admin;
            error_log("Email Admin - Template incluso con successo.");
        } else {
            error_log("Email Admin - ERRORE: Template NON TROVATO: " . $template_path_admin);
            echo "ERRORE SERVER: Template email admin mancante."; // Questo andrà nel buffer
        }
        $corpo_email_html_admin = ob_get_clean();
        error_log("Email Admin - Corpo generato (lunghezza: " . strlen($corpo_email_html_admin) . "). Anteprima: " . substr(str_replace(["\r", "\n"], ' ', $corpo_email_html_admin), 0, 500));
        if (empty(trim(strip_tags($corpo_email_html_admin)))) { // Controllo aggiuntivo se il corpo è essenzialmente vuoto
            error_log("Email Admin - ATTENZIONE: Corpo email admin sembra vuoto dopo strip_tags.");
        }

        $headers_admin = "MIME-Version: 1.0" . "\r\n";
        $headers_admin .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers_admin .= 'From: Notifiche '.$dati_email_comuni['nome_negozio'].' <no-reply@tuodominio.com>' . "\r\n";

        if (mail($email_destinatario_admin, $email_oggetto_admin, $corpo_email_html_admin, $headers_admin)) {
            error_log("createOrder API - Email di notifica admin inviata a: " . $email_destinatario_admin);
        } else {
            error_log("createOrder API - ERRORE INVIO EMAIL admin a: " . $email_destinatario_admin . ". Errore PHP Mail: " . print_r(error_get_last(), true));
        }

        http_response_code(201);
        echo json_encode(['message' => 'Ordine creato con successo.', 'orderId' => $dbOrderId, 'numericOrderId' => $numericOrderId]);

    } catch (Exception $e) {
        $db->rollback();
        error_log("createOrder API - Rollback eseguito. Eccezione: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
        http_response_code(500);
        $errorMessage = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        echo json_encode(['message' => 'Errore durante la creazione dell\'ordine: ' . $errorMessage]);
    }
}

function getUserOrders($db, $currentUserId) {
    $stmt = $db->prepare(
        "SELECT id, numeric_order_id, order_date, status, total_amount, payment_method
         FROM orders
         WHERE user_id = ?
         ORDER BY order_date DESC"
    );
    $stmt->bind_param("i", $currentUserId);
    $stmt->execute();
    $result = $stmt->get_result();
    $orders = [];
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
    echo json_encode($orders);
    $stmt->close();
}

function getOrderDetail($db, $currentUserId, $dbOrderId) {
    $order = null;
    $items = [];
    $stmtOrder = $db->prepare(
        "SELECT * FROM orders WHERE id = ? AND user_id = ?"
    );
    $stmtOrder->bind_param("ii", $dbOrderId, $currentUserId);
    $stmtOrder->execute();
    $resultOrder = $stmtOrder->get_result();
    if ($orderData = $resultOrder->fetch_assoc()) {
        $order = $orderData;
        $stmtItems = $db->prepare(
            "SELECT oi.product_id, oi.product_name, oi.size, oi.color, oi.quantity, oi.unit_price, p.imageSmall
             FROM order_items oi
             LEFT JOIN products p ON oi.product_id = p.id
             WHERE oi.order_id = ?"
        );
        $stmtItems->bind_param("i", $dbOrderId);
        $stmtItems->execute();
        $resultItems = $stmtItems->get_result();
        while ($itemRow = $resultItems->fetch_assoc()) {
            $items[] = $itemRow;
        }
        $stmtItems->close();
        $order['items'] = $items;
    }
    $stmtOrder->close();
    if ($order) {
        echo json_encode($order);
    } else {
        http_response_code(404);
        echo json_encode(['message' => 'Ordine non trovato o non appartenente all\'utente.']);
    }
}

function cancelUserOrder($db, $currentUserId, $dbOrderId) {
    $cancellationWindowSeconds = 5 * 60;
    $db->begin_transaction();
    try {
        $stmtGet = $db->prepare("SELECT status, order_date FROM orders WHERE id = ? AND user_id = ? FOR UPDATE");
        if (!$stmtGet) throw new Exception("Errore interno (prep get).", 500);
        $stmtGet->bind_param("ii", $dbOrderId, $currentUserId);
        if (!$stmtGet->execute()) throw new Exception("Errore interno (exec get).", 500);
        $result = $stmtGet->get_result();
        $order = $result->fetch_assoc();
        $stmtGet->close();
        if (!$order) throw new Exception("Ordine non trovato.", 404);
        if ($order['status'] !== 'processing' && $order['status'] !== 'pending_payment') throw new Exception("L'ordine non può essere annullato in questo stato (" . $order['status'] . ").", 409);
        if ((time() - strtotime($order['order_date'])) > $cancellationWindowSeconds) throw new Exception("Tempo scaduto per l'annullamento.", 403);
        $stmtUpdate = $db->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ? AND user_id = ? AND (status = 'processing' OR status = 'pending_payment')");
        if (!$stmtUpdate) throw new Exception("Errore interno (prep update).", 500);
        $stmtUpdate->bind_param("ii", $dbOrderId, $currentUserId);
        if (!$stmtUpdate->execute()) throw new Exception("Errore interno (exec update).", 500);
        if ($stmtUpdate->affected_rows > 0) {
            $db->commit();
            http_response_code(200);
            echo json_encode(['message' => 'Ordine annullato con successo.']);
        } else {
            throw new Exception("Impossibile annullare l'ordine (conflitto o già modificato).", 409);
        }
        $stmtUpdate->close();
    } catch (Exception $e) {
        $db->rollback();
        $httpCode = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
        http_response_code($httpCode);
        echo json_encode(['message' => 'Errore: ' . htmlspecialchars($e->getMessage())]);
    }
}

function getAllOrdersAdmin($db) {
    $sql = "SELECT o.*, u.username as user_username, u.email as user_login_email
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id
            ORDER BY o.order_date DESC";
    $result = $db->query($sql);
    $orders = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
        echo json_encode($orders);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Errore nel recupero di tutti gli ordini (admin): ' . $db->error]);
    }
}

function updateOrderStatusAdmin($db, $data) {
    if (!isset($data->order_id) || !isset($data->status)) {
        http_response_code(400);
        echo json_encode(['message' => 'Dati incompleti: order_id e status sono richiesti.']);
        return;
    }
    $orderId = intval($data->order_id);
    $newStatus = $data->status;
    $allowedStatuses = ['pending_payment', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded', 'on_hold'];
    if (!in_array($newStatus, $allowedStatuses)) {
        http_response_code(400);
        echo json_encode(['message' => 'Stato non valido fornito.']);
        return;
    }
    $stmt = $db->prepare("UPDATE orders SET status = ? WHERE id = ?");
    if (!$stmt) {
        http_response_code(500); echo json_encode(['message' => 'Errore preparazione query (update status admin): ' . $db->error]); return;
    }
    $stmt->bind_param("si", $newStatus, $orderId);
    if ($stmt->execute()) {
        if ($db->affected_rows > 0) {
            echo json_encode(['message' => 'Stato ordine aggiornato con successo.']);
        } else {
            $checkStmt = $db->prepare("SELECT id FROM orders WHERE id = ?");
            $checkStmt->bind_param("i", $orderId);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows === 0) {
                 http_response_code(404); echo json_encode(['message' => 'Ordine non trovato.']);
            } else {
                 echo json_encode(['message' => 'Nessuna modifica apportata (lo stato potrebbe essere già quello attuale o ordine non trovato).']);
            }
            $checkStmt->close();
        }
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Errore durante l\'aggiornamento dello stato dell\'ordine: ' . $stmt->error]);
    }
    $stmt->close();
}

function deleteOrderAdmin($db, $orderId) {
    $stmtItems = $db->prepare("DELETE FROM order_items WHERE order_id = ?");
    if (!$stmtItems) {
        http_response_code(500); echo json_encode(['message' => 'Errore preparazione query (delete items admin): ' . $db->error]); return;
    }
    $stmtItems->bind_param("i", $orderId);
    if (!$stmtItems->execute()) {
        http_response_code(500);
        echo json_encode(['message' => 'Errore durante l\'eliminazione degli articoli dell\'ordine: ' . $stmtItems->error]);
        $stmtItems->close();
        return;
    }
    $stmtItems->close();
    $stmtOrder = $db->prepare("DELETE FROM orders WHERE id = ?");
    if (!$stmtOrder) {
        http_response_code(500); echo json_encode(['message' => 'Errore preparazione query (delete order admin): ' . $db->error]); return;
    }
    $stmtOrder->bind_param("i", $orderId);
    if ($stmtOrder->execute()) {
        if ($db->affected_rows > 0) {
            echo json_encode(['message' => 'Ordine e relativi articoli eliminati con successo.']);
        } else {
            http_response_code(404);
            echo json_encode(['message' => 'Ordine non trovato o già eliminato.']);
        }
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Errore durante l\'eliminazione dell\'ordine: ' . $stmtOrder->error]);
    }
    $stmtOrder->close();
}

function getOrderDetailAdmin($db, $dbOrderId) {
    $order = null;
    $items = [];
    $stmtOrder = $db->prepare(
        "SELECT o.*, u.username as user_username, u.email as user_login_email
         FROM orders o
         LEFT JOIN users u ON o.user_id = u.id
         WHERE o.id = ?"
    );
    if (!$stmtOrder) {
        http_response_code(500); echo json_encode(['message' => 'Errore preparazione query (order detail admin): ' . $db->error]); return;
    }
    $stmtOrder->bind_param("i", $dbOrderId);
    $stmtOrder->execute();
    $resultOrder = $stmtOrder->get_result();
    if ($orderData = $resultOrder->fetch_assoc()) {
        $order = $orderData;
        $stmtItems = $db->prepare(
            "SELECT oi.*, p.imageSmall as product_image_small_url
             FROM order_items oi
             LEFT JOIN products p ON oi.product_id = p.id
             WHERE oi.order_id = ?"
        );
        if (!$stmtItems) {
            http_response_code(500); echo json_encode(['message' => 'Errore preparazione query (order items detail admin): ' . $db->error]); return;
        }
        $stmtItems->bind_param("i", $dbOrderId);
        $stmtItems->execute();
        $resultItems = $stmtItems->get_result();
        while ($itemRow = $resultItems->fetch_assoc()) {
            $itemRow['product_image_small_url'] = $itemRow['product_image_small_url'] ?? null;
            $items[] = $itemRow;
        }
        $stmtItems->close();
        $order['items'] = $items;
    }
    $stmtOrder->close();
    if ($order) {
        echo json_encode($order);
    } else {
        http_response_code(404);
        echo json_encode(['message' => 'Dettaglio ordine (admin) non trovato.']);
    }
}

$mysqli->close();
?>