<?php
require_once __DIR__ . '/../libs/fpdf/fpdf/fpdf.php';

class PDFManager extends FPDF {
    private $pdo;
    
    public function __construct($pdo) {
        parent::__construct();
        $this->pdo = $pdo;
        $this->SetAutoPageBreak(true, 15);
    }
    
    function Header() {
        $this->SetFont('Arial', 'B', 16);
        $this->SetTextColor(40, 120, 40);
        $this->Cell(0, 10, 'DRIV\'N COOK - RAPPORT', 0, 1, 'C');
        $this->Ln(5);
    }
    
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . ' - Généré le ' . date('d/m/Y H:i'), 0, 0, 'C');
    }
    
    public function generateSalesReport($month = null) {
        if (!$month) $month = date('Y-m');
        
        $this->AddPage();
        
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, 'RAPPORT DES VENTES - ' . date('F Y', strtotime($month . '-01')), 0, 1, 'C');
        $this->Ln(10);
        
        $sales = $this->pdo->prepare("
            SELECT f.name, f.company_name, 
                   COALESCE(SUM(s.daily_revenue), 0) as total_revenue,
                   COALESCE(SUM(s.commission_due), 0) as total_commission
            FROM franchisees f
            LEFT JOIN sales s ON f.id = s.franchisee_id 
                AND DATE_FORMAT(s.sale_date, '%Y-%m') = ?
            WHERE f.status = 'active'
            GROUP BY f.id
            ORDER BY total_revenue DESC
        ");
        $sales->execute([$month]);
        $data = $sales->fetchAll();
        
        $this->SetFont('Arial', 'B', 10);
        $this->SetFillColor(200, 220, 255);
        $this->Cell(50, 8, 'Franchisé', 1, 0, 'C', true);
        $this->Cell(40, 8, 'Entreprise', 1, 0, 'C', true);
        $this->Cell(40, 8, 'CA Total (€)', 1, 0, 'C', true);
        $this->Cell(40, 8, 'Commission (€)', 1, 1, 'C', true);
        
        $this->SetFont('Arial', '', 9);
        $totalCA = 0;
        $totalCom = 0;
        
        foreach ($data as $row) {
            $this->Cell(50, 6, substr($row['name'], 0, 20), 1, 0, 'L');
            $this->Cell(40, 6, substr($row['company_name'] ?? 'N/A', 0, 15), 1, 0, 'L');
            $this->Cell(40, 6, number_format($row['total_revenue'], 2), 1, 0, 'R');
            $this->Cell(40, 6, number_format($row['total_commission'], 2), 1, 1, 'R');
            
            $totalCA += $row['total_revenue'];
            $totalCom += $row['total_commission'];
        }
        
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(90, 8, 'TOTAL', 1, 0, 'C', true);
        $this->Cell(40, 8, number_format($totalCA, 2), 1, 0, 'R', true);
        $this->Cell(40, 8, number_format($totalCom, 2), 1, 1, 'R', true);
        
        return $this->Output('S');
    }
    
    public function generateTrucksReport() {
        $this->AddPage();
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, 'RAPPORT DU PARC DE CAMIONS - ' . date('d/m/Y'), 0, 1, 'C');
        $this->Ln(10);
        
        $trucks = $this->pdo->query("
            SELECT t.license_plate, t.model, t.status, 
                   f.name as franchisee_name, t.location
            FROM trucks t
            LEFT JOIN franchisees f ON t.franchisee_id = f.id
            ORDER BY t.status, t.license_plate
        ")->fetchAll();
        
        $this->SetFont('Arial', 'B', 10);
        $this->SetFillColor(200, 220, 255);
        $this->Cell(30, 8, 'Plaque', 1, 0, 'C', true);
        $this->Cell(35, 8, 'Modèle', 1, 0, 'C', true);
        $this->Cell(25, 8, 'Statut', 1, 0, 'C', true);
        $this->Cell(50, 8, 'Franchisé', 1, 0, 'C', true);
        $this->Cell(40, 8, 'Emplacement', 1, 1, 'C', true);
        
        $this->SetFont('Arial', '', 9);
        foreach ($trucks as $truck) {
            $this->Cell(30, 6, $truck['license_plate'], 1, 0, 'C');
            $this->Cell(35, 6, substr($truck['model'], 0, 15), 1, 0, 'L');
            $this->Cell(25, 6, ucfirst($truck['status']), 1, 0, 'C');
            $this->Cell(50, 6, substr($truck['franchisee_name'] ?? 'Non assigné', 0, 20), 1, 0, 'L');
            $this->Cell(40, 6, substr($truck['location'] ?? 'N/A', 0, 15), 1, 1, 'L');
        }
        
        return $this->Output('S');
    }
    
    public function downloadPDF($type, $month = null) {

        if (ob_get_level()) {
            ob_end_clean();
        }
        
        $filename = "rapport_{$type}_" . date('Y-m-d') . ".pdf";
        
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        
        try {
            switch ($type) {
                case 'sales':
                    $content = $this->generateSalesReport($month);
                    break;
                case 'trucks':
                    $content = $this->generateTrucksReport();
                    break;
                default:
                    $content = $this->generateSalesReport($month);
            }

            if (empty($content)) {
                throw new Exception('Contenu PDF vide');
            }
            
            echo $content;
            
        } catch (Exception $e) {
            header('Content-Type: text/html');
            echo "<h1>Erreur PDF</h1><p>" . $e->getMessage() . "</p>";
            echo "<p><a href='javascript:history.back()'>Retour</a></p>";
        }
    }
}
?>