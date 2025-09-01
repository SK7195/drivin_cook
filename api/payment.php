<?php
require_once '../config/database.php';
require_once '../classes/ClientOrder.php';
require_once '../classes/Client.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'POST only']));
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['action'])) {
    die(json_encode(['success' => false, 'message' => 'Missing action']));
}

$order = new ClientOrder();
$client = new Client();

function respond($success, $data = null, $msg = '') {
    die(json_encode(['success' => $success, 'data' => $data, 'message' => $msg]));
}

function processPayment($data, $order, $client) {
    $orderId = $data['order_id'] ?? 0;
    $method = $data['payment_method'] ?? '';
    $amount = (float)($data['amount'] ?? 0);
    
    $orderData = $order->getById($orderId);
    if (!$orderData || $orderData['status'] !== 'pending') {
        respond(false, null, 'Commande invalide');
    }
    
    if (abs($amount - $orderData['total_amount']) > 0.01) {
        respond(false, null, 'Montant incorrect');
    }
    
    $result = null;
    switch ($method) {
        case 'card':
            $result = payByCard($data);
            break;
        case 'cash':
            $result = ['success' => true, 'payment_id' => 'CASH_' . uniqid()];
            break;
        case 'loyalty_points':
            $result = payByPoints($data, $orderData, $client);
            break;
        default:
            respond(false, null, 'Méthode inconnue');
    }
    
    if ($result['success']) {
        $order->updateStatus($orderId, 'confirmed');
        respond(true, ['order_id' => $orderId, 'payment_id' => $result['payment_id']], 'Paiement OK');
    } else {
        respond(false, $result, $result['message'] ?? 'Erreur paiement');
    }
}

function payByCard($data) {
    $card = $data['card_number'] ?? '';
    $cvv = $data['cvv'] ?? '';
    
    if (strlen($card) < 16 || strlen($cvv) < 3) {
        return ['success' => false, 'message' => 'Carte invalide'];
    }
    
    $lastDigit = substr($card, -1);
    if (in_array($lastDigit, ['1', '2', '3'])) {
        $errors = ['1' => 'Fonds insuffisants', '2' => 'Carte expirée', '3' => 'Refusée'];
        return ['success' => false, 'message' => $errors[$lastDigit]];
    }
    
    return [
        'success' => true,
        'payment_id' => 'PAY_' . uniqid(),
        'card_last4' => substr($card, -4)
    ];
}

function payByPoints($data, $orderData, $client) {
    $points = $data['loyalty_points_used'] ?? 0;
    $clientId = $orderData['client_id'];
    
    $clientInfo = $client->getById($clientId);
    if (!$clientInfo || $clientInfo['loyalty_points'] < $points) {
        return ['success' => false, 'message' => 'Points insuffisants'];
    }
    
    $discount = ($points / 100) * 5;
    if ($discount > $orderData['total_amount']) {
        return ['success' => false, 'message' => 'Réduction trop élevée'];
    }
    
    $client->useLoyaltyPoints($clientId, $points, 'Commande #' . $orderData['id'], $orderData['id']);
    
    return [
        'success' => true,
        'payment_id' => 'LOYALTY_' . uniqid(),
        'points_used' => $points,
        'discount' => $discount
    ];
}

function refundOrder($data, $order) {
    $orderId = $data['order_id'] ?? 0;
    $orderData = $order->getById($orderId);
    
    if (!$orderData || !in_array($orderData['status'], ['confirmed', 'ready', 'completed'])) {
        respond(false, null, 'Remboursement impossible');
    }
    
    $order->updateStatus($orderId, 'cancelled');
    respond(true, ['refund_id' => 'REFUND_' . uniqid()], 'Remboursé');
}

function getStatus($data, $order) {
    $orderId = $data['order_id'] ?? 0;
    $orderData = $order->getById($orderId);
    
    if (!$orderData) {
        respond(false, null, 'Commande introuvable');
    }
    
    respond(true, [
        'order_id' => $orderId,
        'status' => $orderData['status'],
        'amount' => $orderData['total_amount']
    ], 'Statut récupéré');
}

try {
    switch ($data['action']) {
        case 'process_payment':
            processPayment($data, $order, $client);
            break;
        case 'refund_payment':
            refundOrder($data, $order);
            break;
        case 'get_payment_status':
            getStatus($data, $order);
            break;
        case 'simulate_card_payment':
            $card = $data['card_number'] ?? '';
            $success = !in_array(substr($card, -1), ['2', '3']);
            respond($success, ['payment_id' => 'SIM_' . uniqid()], $success ? 'Simulé OK' : 'Carte refusée');
            break;
        default:
            respond(false, null, 'Action inconnue');
    }
} catch (Exception $e) {
    respond(false, null, 'Erreur serveur');
}
?>