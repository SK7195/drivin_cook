<?php
require_once '../config/database.php';

$type = $_GET['type'] ?? 'sales';
$format = $_GET['format'] ?? 'pdf';
$month = $_GET['month'] ?? date('Y-m');
$franchisee = $_GET['franchisee_id'] ?? null;

header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="rapport_' . $type . '_' . $month . '.txt"');

function header_report($type, $month) {
    $h = "DRIV'N COOK - RAPPORT " . strtoupper($type) . "\n";
    $h .= str_repeat("=", 50) . "\n";
    $h .= "Période: " . $month . " | " . date('d/m/Y H:i') . "\n\n";
    return $h;
}

function sales_report($month, $franchisee, $pdo) {
    $where = "WHERE DATE_FORMAT(sale_date, '%Y-%m') = ?";
    $params = [$month];
    
    if ($franchisee) {
        $where .= " AND franchisee_id = ?";
        $params[] = $franchisee;
    }
    
    $stmt = $pdo->prepare("
        SELECT f.name, 
               COUNT(s.id) as ventes,
               SUM(s.daily_revenue) as ca,
               SUM(s.commission_due) as commission
        FROM sales s
        JOIN franchisees f ON s.franchisee_id = f.id
        $where
        GROUP BY f.id
        ORDER BY ca DESC
    ");
    $stmt->execute($params);
    $data = $stmt->fetchAll();
    
    if (!$data) return "Aucune donnée\n";
    
    $total_ca = array_sum(array_column($data, 'ca'));
    $total_com = array_sum(array_column($data, 'commission'));
    
    $report = "RÉSUMÉ:\n";
    $report .= "CA total: " . number_format($total_ca) . "€\n";
    $report .= "Commission: " . number_format($total_com) . "€\n";
    $report .= "Franchisés: " . count($data) . "\n\n";
    
    $report .= "DÉTAIL:\n" . str_repeat("-", 40) . "\n";
    foreach ($data as $row) {
        $report .= $row['name'] . "\n";
        $report .= "  Ventes: " . $row['ventes'] . "\n";
        $report .= "  CA: " . number_format($row['ca']) . "€\n";
        $report .= "  Com: " . number_format($row['commission']) . "€\n\n";
    }
    
    return $report;
}

function trucks_report($pdo) {
    $trucks = $pdo->query("
        SELECT status, COUNT(*) as nb
        FROM trucks
        GROUP BY status
    ")->fetchAll();
    
    $report = "PARC CAMIONS:\n";
    foreach ($trucks as $t) {
        $status = ['available'=>'Dispo','assigned'=>'Assigné','maintenance'=>'Maintenance','broken'=>'Panne'][$t['status']] ?? $t['status'];
        $report .= "$status: " . $t['nb'] . "\n";
    }
    
    $maintenance = $pdo->query("
        SELECT license_plate, DATEDIFF(NOW(), COALESCE(last_maintenance, created_at)) as days
        FROM trucks
        WHERE DATEDIFF(NOW(), COALESCE(last_maintenance, created_at)) > 90
    ")->fetchAll();
    
    if ($maintenance) {
        $report .= "\nMAINTENANCE NÉCESSAIRE:\n";
        foreach ($maintenance as $m) {
            $report .= $m['license_plate'] . " (" . $m['days'] . " jours)\n";
        }
    }
    
    return $report;
}

function stocks_report($pdo) {
    $stocks = $pdo->query("
        SELECT name, stock_quantity, price
        FROM products
        WHERE stock_quantity < 10
        ORDER BY stock_quantity
    ")->fetchAll();
    
    $report = "STOCK FAIBLE:\n";
    if (!$stocks) {
        $report .= "Aucun produit en stock faible\n";
    } else {
        foreach ($stocks as $s) {
            $report .= $s['name'] . " - Stock: " . $s['stock_quantity'] . "\n";
        }
    }
    
    return $report;
}

function franchisees_report($pdo) {
    $franchisees = $pdo->query("
        SELECT f.name, f.status, u.email,
               COUNT(t.id) as trucks
        FROM franchisees f
        JOIN users u ON f.user_id = u.id
        LEFT JOIN trucks t ON f.id = t.franchisee_id
        GROUP BY f.id
        ORDER BY f.status, f.name
    ")->fetchAll();
    
    $active = 0;
    $report = "FRANCHISÉS:\n";
    
    foreach ($franchisees as $f) {
        if ($f['status'] == 'active') $active++;
        $report .= $f['name'] . " (" . $f['status'] . ") - " . $f['trucks'] . " camions\n";
        $report .= "  " . $f['email'] . "\n\n";
    }
    
    $report = "Actifs: $active / " . count($franchisees) . "\n\n" . $report;
    
    return $report;
}

function commissions_report($month, $pdo) {
    $data = $pdo->prepare("
        SELECT f.name, f.commission_rate,
               SUM(s.daily_revenue) as ca,
               SUM(s.commission_due) as com
        FROM sales s
        JOIN franchisees f ON s.franchisee_id = f.id
        WHERE DATE_FORMAT(s.sale_date, '%Y-%m') = ?
        GROUP BY f.id
        ORDER BY com DESC
    ");
    $data->execute([$month]);
    $commissions = $data->fetchAll();
    
    if (!$commissions) return "Pas de commissions\n";
    
    $total = array_sum(array_column($commissions, 'com'));
    $report = "TOTAL: " . number_format($total) . "€\n\n";
    
    foreach ($commissions as $c) {
        $report .= $c['name'] . " (" . $c['commission_rate'] . "%)\n";
        $report .= "  CA: " . number_format($c['ca']) . "€\n";
        $report .= "  Com: " . number_format($c['com']) . "€\n\n";
    }
    
    return $report;
}

echo header_report($type, $month);

switch ($type) {
    case 'sales': echo sales_report($month, $franchisee, $pdo); break;
    case 'trucks': echo trucks_report($pdo); break;
    case 'stocks': echo stocks_report($pdo); break;
    case 'franchisees': echo franchisees_report($pdo); break;
    case 'commissions': echo commissions_report($month, $pdo); break;
    default: echo "Type inconnu\n";
}
?>