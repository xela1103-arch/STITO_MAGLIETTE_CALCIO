// admin-manage-orders.js
document.addEventListener('DOMContentLoaded', () => {
    const ADMIN_SESSION_TOKEN_KEY = 'adminAuthToken';
    const ORDERS_API_PATH = 'orders_api.php'; // Assicurati che sia corretto

    function isAdminAuthenticated() {
        return sessionStorage.getItem(ADMIN_SESSION_TOKEN_KEY) !== null;
    }

    function redirectToLoginGate(currentPage) {
        window.location.href = `admin-login-gate.html?redirect=${encodeURIComponent(currentPage)}`;
    }

    const currentPageName = window.location.pathname.split('/').pop();

    if (!isAdminAuthenticated()) {
        redirectToLoginGate(currentPageName);
        return; // Interrompe l'esecuzione se non autenticato
    }
    
    // --- INIZIO MODIFICA PER PULSANTE LOGOUT NELL'HEADER ---
    const logoutAdminBtnHeader = document.getElementById('admin-logout-button-header');
    if (logoutAdminBtnHeader) {
        logoutAdminBtnHeader.onclick = () => {
            sessionStorage.removeItem(ADMIN_SESSION_TOKEN_KEY);
            window.location.href = 'admin-login-gate.html';
        };
    } else {
        // Questo log è utile per il debug se il pulsante non viene trovato
        console.warn("Pulsante Logout Admin (ID: admin-logout-button-header) non trovato nell'HTML.");
    }
    // --- FINE MODIFICA PER PULSANTE LOGOUT NELL'HEADER ---

    // Variabili DOM specifiche
    const ordersTableBody = document.getElementById('orders-table-body');
    const noOrdersMessage = document.getElementById('no-orders-message');
    const loadingSpinner = document.getElementById('loading-orders-spinner');
    const adminFeedbackZone = document.getElementById('admin-feedback-zone');

    const searchOrderInput = document.getElementById('search-order-input');
    const filterStatusSelect = document.getElementById('filter-status-select');

    const deleteConfirmModal = document.getElementById('delete-confirm-modal');
    const closeDeleteModalBtn = document.getElementById('close-delete-modal-btn');
    const confirmDeleteBtn = document.getElementById('confirm-delete-btn');
    const cancelDeleteBtn = document.getElementById('cancel-delete-btn');
    const deleteConfirmMessage = document.getElementById('delete-confirm-message');
    let orderIdToDelete = null;

    const editStatusModal = document.getElementById('edit-status-modal');
    const closeEditStatusModalBtn = document.getElementById('close-edit-status-modal-btn');
    const confirmEditStatusBtn = document.getElementById('confirm-edit-status-btn');
    const cancelEditStatusBtn = document.getElementById('cancel-edit-status-btn');
    const orderStatusSelect = document.getElementById('order-status-select');
    const editStatusOrderIdDisplay = document.getElementById('edit-status-order-id-display');
    let orderToEditStatus = null;

    const orderDetailsModal = document.getElementById('order-details-modal');
    const closeOrderDetailsModalBtn = document.getElementById('close-order-details-modal-btn');
    const closeDetailsViewBtn = document.getElementById('close-details-view-btn');
    const orderDetailsContentArea = document.getElementById('order-details-content-area');
    const detailsOrderIdDisplay = document.getElementById('details-order-id-display');

    let allFetchedOrders = [];
    let currentFilters = { searchTerm: '', status: '' };

    function showFeedback(message, type = 'info', duration = 4000) {
        adminFeedbackZone.textContent = message;
        adminFeedbackZone.className = `feedback-message ${type}`;
        adminFeedbackZone.style.display = 'block';
        setTimeout(() => { adminFeedbackZone.style.display = 'none'; }, duration);
    }

    function formatPrice(price) { return `€${parseFloat(price).toFixed(2)}`; }
    function formatDate(dateString) { if (!dateString) return 'N/A'; const date = new Date(dateString); return date.toLocaleDateString('it-IT', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }); }
    function getStatusClass(status) { return `status-${(status || 'unknown').toLowerCase().replace(/\s+/g, '_')}`; }
    function getOrderStatusText(status) { const map = { pending_payment: "In Attesa di Pagamento", processing: "In Lavorazione", shipped: "Spedito", delivered: "Consegnato", cancelled: "Annullato", refunded: "Rimborsato", on_hold: "In Attesa" }; return map[(status || '').toLowerCase()] || status || 'Sconosciuto'; }

    function openModal(modalElement) { if (modalElement) { modalElement.classList.add('visible'); modalElement.style.display = 'flex'; document.body.style.overflow = 'hidden'; } }
    function closeModal(modalElement) { 
        if (modalElement) { 
            modalElement.classList.remove('visible'); 
            document.body.style.overflow = ''; // Ripristina scroll
            setTimeout(() => { 
                if (!modalElement.classList.contains('visible')) { 
                    modalElement.style.display = 'none'; 
                } 
            }, 300); 
        } 
    }


    if(closeDeleteModalBtn) closeDeleteModalBtn.addEventListener('click', () => closeModal(deleteConfirmModal));
    if(cancelDeleteBtn) cancelDeleteBtn.addEventListener('click', () => closeModal(deleteConfirmModal));
    if(deleteConfirmModal) deleteConfirmModal.addEventListener('click', (e) => { if (e.target === deleteConfirmModal) closeModal(deleteConfirmModal); });
    if(confirmDeleteBtn) confirmDeleteBtn.addEventListener('click', handleDeleteOrder);

    if(closeEditStatusModalBtn) closeEditStatusModalBtn.addEventListener('click', () => closeModal(editStatusModal));
    if(cancelEditStatusBtn) cancelEditStatusBtn.addEventListener('click', () => closeModal(editStatusModal));
    if(editStatusModal) editStatusModal.addEventListener('click', (e) => { if (e.target === editStatusModal) closeModal(editStatusModal); });
    if(confirmEditStatusBtn) confirmEditStatusBtn.addEventListener('click', handleSaveOrderStatus);

    if(closeOrderDetailsModalBtn) closeOrderDetailsModalBtn.addEventListener('click', () => closeModal(orderDetailsModal));
    if(closeDetailsViewBtn) closeDetailsViewBtn.addEventListener('click', () => closeModal(orderDetailsModal));
    if(orderDetailsModal) orderDetailsModal.addEventListener('click', (e) => { if (e.target === orderDetailsModal) closeModal(orderDetailsModal); });

    async function fetchAllOrders() {
        loadingSpinner.style.display = 'block';
        noOrdersMessage.style.display = 'none';
        ordersTableBody.innerHTML = '';
        try {
            const response = await fetch(`${ORDERS_API_PATH}?action=get_all_orders_admin`);
            if (!response.ok) { const errorData = await response.json().catch(() => ({ message: `Errore HTTP ${response.status}` })); throw new Error(errorData.message); }
            allFetchedOrders = await response.json();
            if (!Array.isArray(allFetchedOrders)) { console.warn("Risposta API ordini non è un array:", allFetchedOrders); allFetchedOrders = []; }
            applyFiltersAndRenderTable();
        } catch (error) { console.error('Errore caricamento ordini:', error); showFeedback(`Errore caricamento ordini: ${error.message}`, 'error'); noOrdersMessage.innerHTML = `<p>Errore: ${error.message}</p>`; noOrdersMessage.style.display = 'block'; allFetchedOrders = []; }
        finally { loadingSpinner.style.display = 'none'; }
    }

    async function deleteOrderAPI(orderId) {
        try {
            const response = await fetch(`${ORDERS_API_PATH}?action=delete_order_admin&order_id=${orderId}`, { method: 'DELETE' });
            const result = await response.json();
            if (!response.ok) { throw new Error(result.message || `Errore HTTP ${response.status}`); }
            showFeedback(result.message || 'Ordine eliminato.', 'success'); fetchAllOrders();
        } catch (error) { console.error('Errore eliminazione:', error); showFeedback(`Errore: ${error.message}`, 'error'); }
    }

    async function updateOrderStatusAPI(orderId, newStatus) {
        try {
            const response = await fetch(`${ORDERS_API_PATH}?action=update_order_status_admin`, { method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ order_id: orderId, status: newStatus }) });
            const result = await response.json();
            if (!response.ok) { throw new Error(result.message || `Errore HTTP ${response.status}`); }
            showFeedback(result.message || 'Stato aggiornato.', 'success'); fetchAllOrders();
        } catch (error) { console.error('Errore aggiornamento stato:', error); showFeedback(`Errore: ${error.message}`, 'error'); }
    }

    async function fetchOrderDetailsAPI(orderId) {
        orderDetailsContentArea.innerHTML = '<p><i class="fas fa-spinner fa-spin"></i> Caricamento...</p>';
        try {
            const response = await fetch(`${ORDERS_API_PATH}?action=get_order_detail_admin&order_id=${orderId}`);
            if (!response.ok) { const errorData = await response.json().catch(() => ({ message: `Errore HTTP ${response.status}` })); throw new Error(errorData.message); }
            const order = await response.json(); renderOrderDetailsInModal(order);
        } catch (error) { console.error('Errore caricamento dettagli:', error); orderDetailsContentArea.innerHTML = `<p style="color:red;">Errore: ${error.message}</p>`; }
    }

    function renderOrdersTable(orders) {
        ordersTableBody.innerHTML = '';
        if (orders.length === 0) { 
            noOrdersMessage.style.display = 'block'; 
            loadingSpinner.style.display = 'none'; 
            noOrdersMessage.innerHTML = (currentFilters.searchTerm || currentFilters.status) ? '<p>Nessun ordine per i filtri.</p>' : '<p>Nessun ordine.</p>'; 
            return; 
        }
        noOrdersMessage.style.display = 'none';
        orders.forEach(order => {
            const row = ordersTableBody.insertRow(); row.dataset.orderId = order.id;
            const displayOrderId = order.numeric_order_id ? `#${String(order.numeric_order_id).padStart(4, '0')}` : `#${order.id}`;
            row.insertCell().textContent = displayOrderId; row.insertCell().textContent = formatDate(order.order_date);
            const customerName = order.full_name || 'N/D'; 
            const customerUserId = order.user_id ? ` (ID: ${order.user_id})` : ` (Utente Guest/Sconosciuto)`;
            row.insertCell().textContent = `${customerName}${customerUserId}`;
            row.insertCell().textContent = order.email || 'N/D'; row.insertCell().textContent = formatPrice(order.total_amount);
            const statusCell = row.insertCell(); const statusBadge = document.createElement('span'); statusBadge.className = `status-badge ${getStatusClass(order.status)}`; statusBadge.textContent = getOrderStatusText(order.status); statusCell.appendChild(statusBadge);
            const actionsCell = row.insertCell(); actionsCell.innerHTML = `<button class="action-btn view-details-btn" title="Vedi Dettagli"><i class="fas fa-eye"></i></button> <button class="action-btn edit-status-btn" title="Modifica Stato"><i class="fas fa-edit"></i></button> <button class="action-btn delete-order-btn" title="Elimina Ordine"><i class="fas fa-trash-alt"></i></button>`;
            actionsCell.querySelector('.view-details-btn').addEventListener('click', () => openOrderDetailsModal(order));
            actionsCell.querySelector('.edit-status-btn').addEventListener('click', () => openEditStatusModal(order));
            actionsCell.querySelector('.delete-order-btn').addEventListener('click', () => openDeleteConfirmModal(order));
        });
    }

    function applyFiltersAndRenderTable() {
        let filteredOrders = [...allFetchedOrders];
        const term = currentFilters.searchTerm.toLowerCase().replace(/^#/, '');
        if (term) { 
            filteredOrders = filteredOrders.filter(order => 
                String(order.id).toLowerCase().includes(term) || 
                (order.numeric_order_id && String(order.numeric_order_id).toLowerCase().includes(term)) || 
                (order.full_name && order.full_name.toLowerCase().includes(currentFilters.searchTerm.toLowerCase())) || 
                (order.user_username && order.user_username.toLowerCase().includes(currentFilters.searchTerm.toLowerCase())) || // Aggiunto user_username
                (order.email && order.email.toLowerCase().includes(currentFilters.searchTerm.toLowerCase())) ||
                (order.user_login_email && order.user_login_email.toLowerCase().includes(currentFilters.searchTerm.toLowerCase())) // Aggiunto user_login_email
            ); 
        }
        if (currentFilters.status) { filteredOrders = filteredOrders.filter(order => order.status === currentFilters.status); }
        renderOrdersTable(filteredOrders);
    }

    let searchDebounceTimeout;
    if(searchOrderInput) { searchOrderInput.addEventListener('input', () => { clearTimeout(searchDebounceTimeout); searchDebounceTimeout = setTimeout(() => { currentFilters.searchTerm = searchOrderInput.value.trim(); applyFiltersAndRenderTable(); }, 300); }); }
    if(filterStatusSelect) { filterStatusSelect.addEventListener('change', () => { currentFilters.status = filterStatusSelect.value; applyFiltersAndRenderTable(); }); }

    function openDeleteConfirmModal(order) { orderIdToDelete = order.id; const displayOrderId = order.numeric_order_id ? `#${String(order.numeric_order_id).padStart(4, '0')}` : `#${order.id}`; deleteConfirmMessage.innerHTML = `Eliminare ordine <strong>${displayOrderId}</strong>?`; openModal(deleteConfirmModal); }
    function handleDeleteOrder() { if (orderIdToDelete) { deleteOrderAPI(orderIdToDelete); orderIdToDelete = null; } closeModal(deleteConfirmModal); }
    function openEditStatusModal(order) { orderToEditStatus = order; const displayOrderId = order.numeric_order_id ? `#${String(order.numeric_order_id).padStart(4, '0')}` : `#${order.id}`; editStatusOrderIdDisplay.textContent = displayOrderId; orderStatusSelect.value = order.status || 'processing'; openModal(editStatusModal); }
    function handleSaveOrderStatus() { if (orderToEditStatus) { const newStatus = orderStatusSelect.value; updateOrderStatusAPI(orderToEditStatus.id, newStatus); orderToEditStatus = null; } closeModal(editStatusModal); }
    function openOrderDetailsModal(order) { const displayOrderId = order.numeric_order_id ? `#${String(order.numeric_order_id).padStart(4, '0')}` : `#${order.id}`; detailsOrderIdDisplay.textContent = displayOrderId; fetchOrderDetailsAPI(order.id); openModal(orderDetailsModal); }
    function getPaymentMethodText(method) { const map = { 'credit_card': 'Carta Credito', 'paypal': 'PayPal', 'bank_transfer': 'Bonifico' }; return map[method] || method; }
    function getShippingMethodText(method) { const map = { 'standard': 'Standard', 'express': 'Express' }; return map[method] || method; }

    function renderOrderDetailsInModal(order) {
        if (!order || !order.id) { orderDetailsContentArea.innerHTML = '<p style="color:red;">Dettagli non disponibili.</p>'; return; }
        const displayOrderId = order.numeric_order_id ? `#${String(order.numeric_order_id).padStart(4, '0')}` : `#${order.id}`;
        orderDetailsContentArea.innerHTML = `
            <div class="detail-section"><h4>Info Generali</h4> <p><strong>ID Ordine DB:</strong> ${order.id}</p> <p><strong>ID Cliente:</strong> ${displayOrderId}</p> <p><strong>Data:</strong> ${formatDate(order.order_date)}</p> <p><strong>Stato:</strong> <span class="status-badge ${getStatusClass(order.status)}">${getOrderStatusText(order.status)}</span></p> </div>
            <div class="detail-section"><h4>Cliente</h4> <p><strong>Nome:</strong> ${order.full_name || 'N/D'}</p> <p><strong>Email (per ordine):</strong> ${order.email || 'N/D'}</p> ${order.user_username ? `<p><strong>Username (login):</strong> ${order.user_username}</p>` : ''} ${order.user_login_email && order.user_login_email !== order.email ? `<p><strong>Email (login):</strong> ${order.user_login_email}</p>` : ''} <p><strong>Telefono:</strong> ${order.phone || 'N/D'}</p> <p><strong>ID Utente:</strong> ${order.user_id || 'N/D'}</p> </div>
            <div class="detail-section"><h4>Indirizzo Spedizione</h4> <p><strong>Via:</strong> ${order.shipping_street || 'N/D'}</p> <p><strong>Città:</strong> ${order.shipping_city || 'N/D'}</p> <p><strong>CAP:</strong> ${order.shipping_zip_code || 'N/D'}</p> </div>
            <div class="detail-section"><h4>Pagamento e Spedizione</h4> <p><strong>Pagamento:</strong> ${order.payment_method ? getPaymentMethodText(order.payment_method) : 'N/D'}</p> <p><strong>Spedizione:</strong> ${order.shipping_method ? getShippingMethodText(order.shipping_method) : 'N/D'}</p> <p><strong>Costo Sped.:</strong> ${formatPrice(order.shipping_cost)}</p> </div>
            <div class="detail-section"><h4>Articoli (${order.items ? order.items.length : 0})</h4> <div class="items-list"> ${(order.items && Array.isArray(order.items) && order.items.length > 0) ? order.items.map(item => ` <div class="item" style="display: flex; align-items: center; padding: 8px 0; border-bottom: 1px dotted #ccc;"> <img src="${item.product_image_small_url || 'https://via.placeholder.com/40x40?text=N/A'}" alt="${item.product_name || ''}" style="width:40px; height:40px; object-fit:contain; margin-right:10px; border:1px solid #eee;"> <span class="item-name" style="flex-grow:1;">${item.product_name || 'Prodotto Sconosciuto'}  <small>(ID: ${item.product_id || 'N/A'}) ${item.size ? `, Tg: ${item.size}` : ''} ${item.color ? `, Col: ${item.color}` : ''} </small> </span> <span class="item-qty" style="margin-left:15px; white-space:nowrap;">x ${item.quantity}</span> <span class="item-price" style="margin-left:15px; white-space:nowrap;">${formatPrice(item.unit_price * item.quantity)}</span> </div> `).join('') : '<p>Nessun articolo.</p>'} </div> </div>
            <div class="detail-section totals-summary"> <div class="total-line"><span>Subtotale:</span> <span>${formatPrice(order.subtotal)}</span></div> <div class="total-line"><span>Spedizione:</span> <span>${formatPrice(order.shipping_cost)}</span></div> <div class="total-line grand-total"><span>TOTALE:</span> <span>${formatPrice(order.total_amount)}</span></div> </div>`;
    }
    function initAdminOrdersPage() { fetchAllOrders(); }
    initAdminOrdersPage();
});