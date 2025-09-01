<?php
$pageTitle = 'Mes Commandes';
require_once '../includes/header.php';
requireLogin();

if (!isFranchisee()) {
    header('Location: ../admin/dashboard.php');
    exit();
}

$pdo = getDBConnection();
$action = $_GET['action'] ?? 'list';
$error = $success = '';

$stmt = $pdo->prepare("SELECT id FROM franchisees WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$franchisee_id = $stmt->fetchColumn();

if (!$franchisee_id) {
    $_SESSION['error'] = 'Franchisé non trouvé';
    header('Location: ../logout.php');
    exit();
}

if (isset($_GET['warehouse']) && is_numeric($_GET['warehouse'])) {
    $warehouse_id = (int)$_GET['warehouse'];
    
    try {
        $products_query = $pdo->prepare("
            SELECT id, name, category, price, stock_quantity 
            FROM products 
            WHERE warehouse_id = ?
            ORDER BY category, name
        ");
        $products_query->execute([$warehouse_id]);
        $products = $products_query->fetchAll(PDO::FETCH_ASSOC);
        
        $total_products = count($products);
        $products_with_stock = count(array_filter($products, function($p) { return intval($p['stock_quantity']) > 0; }));
        
        $response = [
            'success' => true,
            'products' => $products,
            'debug' => [
                'warehouse_id' => $warehouse_id,
                'total_products' => $total_products,
                'products_with_stock' => $products_with_stock,
                'message' => "Entrepôt $warehouse_id: $total_products produits au total, $products_with_stock avec stock > 0"
            ]
        ];
        
    } catch (Exception $e) {
        $response = [
            'success' => false,
            'error' => 'Erreur lors du chargement des produits: ' . $e->getMessage(),
            'products' => [],
            'debug' => [
                'warehouse_id' => $warehouse_id,
                'total_products' => 0,
                'products_with_stock' => 0,
                'message' => "Erreur: " . $e->getMessage()
            ]
        ];
    }
    
    if (ob_get_level()) {
        ob_clean();
    }
    
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache');
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}

if ($_POST && $action === 'new') {
    $warehouse_id = $_POST['warehouse_id'] ?? 0;
    $products = $_POST['products'] ?? [];
    $quantities = $_POST['quantities'] ?? [];
    
    $errors = [];
    
    if (!$warehouse_id) {
        $errors[] = 'Veuillez sélectionner un entrepôt';
    }
    
    if (empty($products) || empty($quantities)) {
        $errors[] = 'Veuillez sélectionner au moins un produit';
    }
    
    $stmt = $pdo->prepare("SELECT id FROM warehouses WHERE id = ?");
    $stmt->execute([$warehouse_id]);
    if (!$stmt->fetchColumn()) {
        $errors[] = 'Entrepôt non valide';
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            $total_amount = 0;
            $order_items = [];
            
            foreach ($products as $index => $product_id) {
                if ($product_id && isset($quantities[$index]) && $quantities[$index] > 0) {
                    $stmt = $pdo->prepare("
                        SELECT price, stock_quantity, name 
                        FROM products 
                        WHERE id = ? AND warehouse_id = ?
                    ");
                    $stmt->execute([$product_id, $warehouse_id]);
                    $product = $stmt->fetch();
                    
                    if ($product) {
                        $quantity = (int)$quantities[$index];
                        
                        if ($quantity > $product['stock_quantity']) {
                            throw new Exception("Stock insuffisant pour {$product['name']} (disponible: {$product['stock_quantity']})");
                        }
                        
                        $item_total = $product['price'] * $quantity;
                        $total_amount += $item_total;
                        
                        $order_items[] = [
                            'product_id' => $product_id,
                            'quantity' => $quantity,
                            'unit_price' => $product['price']
                        ];
                    }
                }
            }
            
            if ($total_amount > 0 && !empty($order_items)) {

                $stmt = $pdo->prepare("INSERT INTO stock_orders (franchisee_id, warehouse_id, total_amount) VALUES (?, ?, ?)");
                $stmt->execute([$franchisee_id, $warehouse_id, $total_amount]);
                $order_id = $pdo->lastInsertId();
                
                $stmt_item = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, unit_price) VALUES (?, ?, ?, ?)");
                $stmt_stock = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
                
                foreach ($order_items as $item) {
                    $stmt_item->execute([$order_id, $item['product_id'], $item['quantity'], $item['unit_price']]);

                }
                
                $pdo->commit();
                $_SESSION['success'] = 'Commande passée avec succès pour un montant de ' . number_format($total_amount, 2) . ' €';
                header('Location: orders.php');
                exit();
            } else {
                throw new Exception('Aucun produit valide dans la commande');
            }
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Erreur lors de la commande : ' . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

$warehouses = $pdo->query("SELECT * FROM warehouses ORDER BY name")->fetchAll();

$stmt = $pdo->prepare("
    SELECT so.*, w.name as warehouse_name,
           COUNT(oi.id) as item_count,
           SUM(oi.quantity) as total_items
    FROM stock_orders so
    JOIN warehouses w ON so.warehouse_id = w.id
    LEFT JOIN order_items oi ON so.id = oi.order_id
    WHERE so.franchisee_id = ?
    GROUP BY so.id
    ORDER BY so.order_date DESC
");
$stmt->execute([$franchisee_id]);
$my_orders = $stmt->fetchAll();
?>

<div class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-shopping-cart me-2"></i>Mes Commandes</h1>
        <?php if ($action === 'list'): ?>
            <a href="?action=new" class="btn btn-success">
                <i class="fas fa-plus me-2"></i>Nouvelle commande
            </a>
        <?php else: ?>
            <a href="orders.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Retour à la liste
            </a>
        <?php endif; ?>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <?php if ($action === 'new'): ?>
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-plus me-2"></i>Nouvelle commande de stock</h5>
            </div>
            <div class="card-body">
                <form method="POST" id="orderForm">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label for="warehouse_id" class="form-label">Entrepôt *</label>
                            <select class="form-select" id="warehouse_id" name="warehouse_id" required onchange="loadProducts()">
                                <option value="">Sélectionner un entrepôt</option>
                                <?php foreach ($warehouses as $warehouse): ?>
                                    <option value="<?php echo $warehouse['id']; ?>">
                                        <?php echo htmlspecialchars($warehouse['name']); ?> - <?php echo htmlspecialchars($warehouse['manager_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Rappel</label>
                            <div class="alert alert-info py-2 mb-0">
                                <small><i class="fas fa-info-circle me-2"></i>80% de vos achats doivent être effectués chez Driv'n Cook</small>
                            </div>
                        </div>
                    </div>

                    <div id="productsSection" style="display: none;">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6><i class="fas fa-boxes me-2"></i>Sélection des produits</h6>
                            <small id="debugInfo" class="text-muted"></small>
                        </div>
                        <div id="productsList"></div>
                        
                        <div class="mt-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <strong>Total de la commande : <span id="totalAmount">0.00</span> €</strong>
                                <button type="submit" class="btn btn-success" id="submitOrder" disabled>
                                    <i class="fas fa-shopping-cart me-2"></i>Passer la commande
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

    <?php else: ?>
        <div class="card">
            <div class="card-header">
                <h5>Historique de mes commandes (<?php echo count($my_orders); ?>)</h5>
            </div>
            <div class="card-body">
                <?php if (empty($my_orders)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Vous n'avez pas encore passé de commande</p>
                        <a href="?action=new" class="btn btn-success">
                            <i class="fas fa-plus me-2"></i>Passer ma première commande
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Entrepôt</th>
                                    <th>Articles</th>
                                    <th>Montant</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($my_orders as $order): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y H:i', strtotime($order['order_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($order['warehouse_name']); ?></td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $order['item_count']; ?> article<?php echo $order['item_count'] > 1 ? 's' : ''; ?></span>
                                            <?php if ($order['total_items']): ?>
                                                <br><small class="text-muted"><?php echo $order['total_items']; ?> unité<?php echo $order['total_items'] > 1 ? 's' : ''; ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?php echo number_format($order['total_amount'], 2); ?> €</strong></td>
                                        <td>
                                            <span class="badge status-<?php echo $order['status']; ?>">
                                                <?php
                                                $status_labels = [
                                                    'pending' => 'En attente',
                                                    'delivered' => 'Livrée',
                                                    'cancelled' => 'Annulée'
                                                ];
                                                echo $status_labels[$order['status']] ?? $order['status'];
                                                ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                    data-bs-toggle="modal" data-bs-target="#orderModal<?php echo $order['id']; ?>">
                                                <i class="fas fa-eye"></i> Détails
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="row mt-4">
                        <?php
                        $total_orders = count($my_orders);
                        $total_spent = array_sum(array_column($my_orders, 'total_amount'));
                        $pending_orders = count(array_filter($my_orders, fn($o) => $o['status'] === 'pending'));
                        $avg_order = $total_orders > 0 ? $total_spent / $total_orders : 0;
                        ?>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h5><?php echo $total_orders; ?></h5>
                                <small class="text-muted">Total commandes</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h5><?php echo number_format($total_spent, 2); ?> €</h5>
                                <small class="text-muted">Total dépensé</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h5><?php echo $pending_orders; ?></h5>
                                <small class="text-muted">En attente</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h5><?php echo number_format($avg_order, 2); ?> €</h5>
                                <small class="text-muted">Panier moyen</small>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if ($action === 'list'): ?>
    <?php foreach ($my_orders as $order): ?>
        <?php
        $stmt = $pdo->prepare("
            SELECT oi.*, p.name as product_name, p.category 
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
            ORDER BY p.category, p.name
        ");
        $stmt->execute([$order['id']]);
        $order_details = $stmt->fetchAll();
        ?>
        
        <div class="modal fade" id="orderModal<?php echo $order['id']; ?>" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-receipt me-2"></i>Détails de la commande #<?php echo $order['id']; ?>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Date :</strong> <?php echo date('d/m/Y à H:i', strtotime($order['order_date'])); ?><br>
                                <strong>Entrepôt :</strong> <?php echo htmlspecialchars($order['warehouse_name']); ?>
                            </div>
                            <div class="col-md-6 text-end">
                                <strong>Statut :</strong>
                                <span class="badge status-<?php echo $order['status']; ?>">
                                    <?php
                                    $status_labels = [
                                        'pending' => 'En attente',
                                        'delivered' => 'Livrée',
                                        'cancelled' => 'Annulée'
                                    ];
                                    echo $status_labels[$order['status']] ?? $order['status'];
                                    ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Produit</th>
                                        <th>Catégorie</th>
                                        <th>Quantité</th>
                                        <th>Prix unitaire</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($order_details as $detail): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($detail['product_name']); ?></td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?php
                                                    $categories = [
                                                        'ingredient' => 'Ingrédient',
                                                        'prepared_dish' => 'Plat préparé',
                                                        'beverage' => 'Boisson'
                                                    ];
                                                    echo $categories[$detail['category']] ?? $detail['category'];
                                                    ?>
                                                </span>
                                            </td>
                                            <td><?php echo $detail['quantity']; ?></td>
                                            <td><?php echo number_format($detail['unit_price'], 2); ?> €</td>
                                            <td><strong><?php echo number_format($detail['quantity'] * $detail['unit_price'], 2); ?> €</strong></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="table-info">
                                        <td colspan="4"><strong>Total de la commande</strong></td>
                                        <td><strong><?php echo number_format($order['total_amount'], 2); ?> €</strong></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php if ($action === 'new'): ?>
<script>
let products = [];
let selectedProducts = {};

function loadProducts() {
    const warehouseId = document.getElementById('warehouse_id').value;
    const productsSection = document.getElementById('productsSection');
    const productsList = document.getElementById('productsList');
    const debugInfo = document.getElementById('debugInfo');
    
    if (!warehouseId) {
        productsSection.style.display = 'none';
        return;
    }
    
    productsList.innerHTML = '<div class="text-center py-3"><i class="fas fa-spinner fa-spin"></i> Chargement des produits...</div>';
    productsSection.style.display = 'block';
    debugInfo.textContent = 'Chargement...';
    
    const currentUrl = new URL(window.location.href);
    currentUrl.searchParams.set('warehouse', warehouseId);
    
    fetch(currentUrl.href, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        }
    })
    .then(response => {
        console.log('Status:', response.status);
        console.log('Headers:', [...response.headers.entries()]);

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        return response.text().then(text => {
            console.log('Réponse brute:', text.substring(0, 500) + (text.length > 500 ? '...' : ''));
            
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Erreur de parsing JSON:', e);
                console.error('Contenu reçu:', text);
                throw new Error('Réponse invalide du serveur (pas de JSON valide)');
            }
        });
    })
    .then(data => {
        console.log('Données parsées:', data);

        if (!data) {
            throw new Error('Données vides reçues');
        }
        
        if (data.success === false) {
            throw new Error(data.error || 'Erreur serveur inconnue');
        }
        
        products = data.products || [];
        selectedProducts = {}; 
        
        if (data.debug) {
            debugInfo.textContent = data.debug.message;
            debugInfo.className = 'text-info small';
            console.log('Debug info:', data.debug);
        }
        
        displayProducts();
        updateTotal();
    })
    .catch(error => {
        console.error('Erreur complète:', error);
        
        let errorMessage = 'Erreur lors du chargement des produits';
        if (error.message) {
            errorMessage += ': ' + error.message;
        }
        
        productsList.innerHTML = `
            <div class="alert alert-danger">
                <strong>Erreur:</strong> ${errorMessage}
                <br><small class="text-muted">Vérifiez la console (F12) pour plus de détails.</small>
                <br><small class="text-muted">Entrepôt ID: ${warehouseId}</small>
            </div>
        `;
        debugInfo.textContent = 'Erreur de chargement';
        debugInfo.className = 'text-danger small';
    });
}

function displayProducts() {
    const productsList = document.getElementById('productsList');
    
    if (products.length === 0) {
        productsList.innerHTML = '<div class="alert alert-info">Aucun produit disponible dans cet entrepôt</div>';
        return;
    }
    
    let html = '<div class="table-responsive"><table class="table table-sm table-hover"><thead><tr><th>Produit</th><th>Catégorie</th><th>Prix</th><th>Stock</th><th>Quantité</th></tr></thead><tbody>';
    
    const categories = ['ingredient', 'prepared_dish', 'beverage'];
    const categoryLabels = {
        'ingredient': 'Ingrédients',
        'prepared_dish': 'Plats préparés',
        'beverage': 'Boissons'
    };
    
    categories.forEach(category => {
        const categoryProducts = products.filter(p => p.category === category);
        if (categoryProducts.length > 0) {
            html += `<tr class="table-secondary"><td colspan="5"><strong>${categoryLabels[category]}</strong></td></tr>`;
            
            categoryProducts.forEach((product) => {
                const stockQuantity = parseInt(product.stock_quantity) || 0;
                const stockClass = stockQuantity === 0 ? 'bg-danger text-white' :
                                  stockQuantity < 5 ? 'bg-warning text-dark' : 
                                  stockQuantity < 10 ? 'bg-info' : 'bg-success';
                
                const isOutOfStock = stockQuantity === 0;
                const maxOrderQuantity = Math.max(0, stockQuantity);
                
                html += `
                    <tr ${isOutOfStock ? 'class="table-light text-muted"' : ''}>
                        <td>
                            ${product.name} 
                            ${isOutOfStock ? '<span class="badge bg-danger ms-2">Rupture</span>' : ''}
                        </td>
                        <td><span class="badge bg-secondary">${categoryLabels[product.category]}</span></td>
                        <td>${parseFloat(product.price).toFixed(2)} €</td>
                        <td><span class="badge ${stockClass}">${stockQuantity}</span></td>
                        <td>
                            <div class="input-group input-group-sm" style="width: 120px;">
                                <input type="number" class="form-control" name="quantities[]" 
                                       min="0" max="${maxOrderQuantity}" value="0" 
                                       ${isOutOfStock ? 'disabled title="Produit en rupture de stock"' : ''}
                                       onchange="updateProductSelection(${product.id}, this.value, ${product.price})"
                                       oninput="validateQuantity(this, ${maxOrderQuantity})">
                                <input type="hidden" name="products[]" value="${product.id}">
                            </div>
                            ${isOutOfStock ? '<small class="text-danger">Stock épuisé</small>' : 
                              stockQuantity < 10 ? '<small class="text-warning">Stock faible</small>' : ''}
                        </td>
                    </tr>
                `;
            });
        }
    });
    
    html += '</tbody></table></div>';
    
    const totalProducts = products.length;
    const availableProducts = products.filter(p => (parseInt(p.stock_quantity) || 0) > 0).length;
    const outOfStockProducts = totalProducts - availableProducts;
    
    if (outOfStockProducts > 0) {
        html += `<div class="alert alert-warning mt-2">
            <small><i class="fas fa-exclamation-triangle me-2"></i>
            ${totalProducts} produits au total - ${availableProducts} disponibles, ${outOfStockProducts} en rupture
            </small>
        </div>`;
    } else {
        html += `<div class="alert alert-success mt-2">
            <small><i class="fas fa-check-circle me-2"></i>
            ${totalProducts} produits disponibles
            </small>
        </div>`;
    }
    
    productsList.innerHTML = html;
}

function validateQuantity(input, maxStock) {
    const value = parseInt(input.value) || 0;
    if (value > maxStock) {
        input.value = maxStock;
        input.classList.add('is-invalid');
        setTimeout(() => input.classList.remove('is-invalid'), 2000);
    }
}

function updateProductSelection(productId, quantity, price) {
    quantity = parseInt(quantity) || 0;
    
    if (quantity > 0) {
        selectedProducts[productId] = {
            quantity: quantity,
            price: price
        };
    } else {
        delete selectedProducts[productId];
    }
    
    updateTotal();
}

function updateTotal() {
    let total = 0;
    Object.values(selectedProducts).forEach(item => {
        total += item.quantity * item.price;
    });
    
    document.getElementById('totalAmount').textContent = total.toFixed(2);
    const submitBtn = document.getElementById('submitOrder');
    submitBtn.disabled = total === 0;
    
    const itemCount = Object.keys(selectedProducts).length;
    if (itemCount > 0) {
        const totalQuantity = Object.values(selectedProducts).reduce((sum, item) => sum + item.quantity, 0);
        submitBtn.innerHTML = `<i class="fas fa-shopping-cart me-2"></i>Passer la commande (${itemCount} produit${itemCount > 1 ? 's' : ''}, ${totalQuantity} unité${totalQuantity > 1 ? 's' : ''})`;
    } else {
        submitBtn.innerHTML = '<i class="fas fa-shopping-cart me-2"></i>Passer la commande';
    }
}

document.getElementById('orderForm')?.addEventListener('submit', function(e) {
    if (Object.keys(selectedProducts).length === 0) {
        e.preventDefault();
        alert('Veuillez sélectionner au moins un produit');
        return false;
    }
    
    const warehouseId = document.getElementById('warehouse_id').value;
    if (!warehouseId) {
        e.preventDefault();
        alert('Veuillez sélectionner un entrepôt');
        return false;
    }
    const itemCount = Object.keys(selectedProducts).length;
    const total = Object.values(selectedProducts).reduce((sum, item) => sum + (item.quantity * item.price), 0);
    
    if (!confirm(`Confirmer la commande de ${itemCount} produit${itemCount > 1 ? 's' : ''} pour un montant de ${total.toFixed(2)} € ?`)) {
        e.preventDefault();
        return false;
    }
    
    return true;
});
</script>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>