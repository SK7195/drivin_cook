<?php
$pageTitle = 'Rapports';
require_once '../includes/header.php';
require_once '../classes/PDFManager.php';
requireAdmin();

$pdo = getDBConnection();

if (isset($_GET['pdf'])) {
    $pdfManager = new PDFManager($pdo);
    $pdfManager->downloadPDF($_GET['pdf'], $_GET['month'] ?? null);
    exit();
}

$stats = $pdo->query("
    SELECT 
        COALESCE(SUM(daily_revenue), 0) as ca_mois,
        COUNT(DISTINCT franchisee_id) as franchises_actives
    FROM sales 
    WHERE MONTH(sale_date) = MONTH(CURRENT_DATE)
")->fetch();

$trucks_stats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN status = 'available' THEN 1 END) as disponibles,
        COUNT(CASE WHEN status = 'assigned' THEN 1 END) as assignes
    FROM trucks
")->fetch();
?>

<div class="container mt-4">
    <h1><i class="fas fa-chart-bar me-2"></i>Rapports</h1>

    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="text-success"><?php echo number_format($stats['ca_mois'], 0); ?> €</h3>
                    <p>CA du mois</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="text-info"><?php echo $stats['franchises_actives']; ?></h3>
                    <p>Franchisés actifs</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="text-warning"><?php echo $trucks_stats['assignes']; ?>/<?php echo $trucks_stats['total']; ?></h3>
                    <p>Camions assignés</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5>Générer un rapport PDF</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <h6><i class="fas fa-euro-sign me-2"></i>Rapport des ventes</h6>
                            <p class="text-muted">Chiffre d'affaires par franchisé</p>
                            <form method="GET" class="d-flex gap-2">
                                <select name="month" class="form-select">
                                    <?php for ($i = 0; $i < 6; $i++): 
                                        $month = date('Y-m', strtotime("-$i month"));
                                    ?>
                                        <option value="<?php echo $month; ?>">
                                            <?php echo date('F Y', strtotime($month . '-01')); ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                                <button type="submit" name="pdf" value="sales" class="btn btn-primary">
                                    <i class="fas fa-download"></i> PDF
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <h6><i class="fas fa-truck me-2"></i>Rapport des camions</h6>
                            <p class="text-muted">État du parc de véhicules</p>
                            <a href="?pdf=trucks" class="btn btn-primary">
                                <i class="fas fa-download me-2"></i>Télécharger PDF
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5>Ventes récentes</h5>
                </div>
                <div class="card-body">
                    <?php
                    $recent_sales = $pdo->query("
                        SELECT f.name, s.sale_date, s.daily_revenue
                        FROM sales s
                        JOIN franchisees f ON s.franchisee_id = f.id
                        ORDER BY s.sale_date DESC
                        LIMIT 10
                    ")->fetchAll();
                    ?>
                    
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Franchisé</th>
                                    <th>Date</th>
                                    <th>Montant</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_sales as $sale): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($sale['name']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($sale['sale_date'])); ?></td>
                                        <td><?php echo number_format($sale['daily_revenue'], 2); ?> €</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5>Actions rapides</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="dashboard.php" class="btn btn-outline-primary">
                            <i class="fas fa-tachometer-alt me-2"></i>Tableau de bord
                        </a>
                        <a href="newsletter.php" class="btn btn-outline-success">
                            <i class="fas fa-envelope me-2"></i>Newsletter
                        </a>
                        <a href="franchises.php" class="btn btn-outline-info">
                            <i class="fas fa-users me-2"></i>Franchisés
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>