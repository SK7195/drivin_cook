<?php
$pageTitle = 'Newsletter';
require_once '../includes/header.php';
require_once '../config/email.php';
require_once '../classes/EmailManager.php';
requireAdmin();

$pdo = getDBConnection();
$success = $error = '';

if (isset($_POST['send_newsletter'])) {
    $subject = $_POST['subject'] ?? '';
    $content = $_POST['content'] ?? '';
    
    if ($subject && $content) {
        $emailManager = new EmailManager($pdo);
        $result = $emailManager->sendNewsletter($subject, $content);
        
        if ($result['success']) {
            $success = $result['message'];
        } else {
            $error = $result['message'];
        }
    } else {
        $error = 'Sujet et contenu obligatoires';
    }
}

if (isset($_POST['test_smtp'])) {
    $emailManager = new EmailManager($pdo);
    if ($emailManager->testConnection()) {
        $success = "Test SMTP réussi !";
    } else {
        $error = "Test SMTP échoué - Vérifiez la configuration";
    }
}

$stats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN ns.subscribed = 1 THEN 1 END) as actifs
    FROM clients c
    LEFT JOIN newsletter_subscribers ns ON c.id = ns.client_id
")->fetch();
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-envelope me-2"></i>Newsletter</h1>
        <form method="POST" class="d-inline">
            <button type="submit" name="test_smtp" class="btn btn-warning">
                <i class="fas fa-flask me-2"></i>Tester SMTP
            </button>
        </form>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body text-center">
                    <h3><?php echo $stats['total']; ?></h3>
                    <p>Total clients</p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-body text-center">
                    <h3><?php echo $stats['actifs']; ?></h3>
                    <p>Abonnés actifs</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5>Envoyer une newsletter</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Sujet</label>
                    <input type="text" name="subject" class="form-control" required 
                           placeholder="Newsletter Driv'n Cook - Décembre 2024">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Message</label>
                    <textarea name="content" class="form-control" rows="8" required 
                              placeholder="Bonjour {{firstname}},

Découvrez nos nouveautés du mois...

L'équipe Driv'n Cook"></textarea>
                    <small class="text-muted">Variables: {{firstname}}, {{lastname}}</small>
                </div>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Sera envoyé à <strong><?php echo $stats['actifs']; ?> abonné(s)</strong>
                </div>
                
                <button type="submit" name="send_newsletter" class="btn btn-success">
                    <i class="fas fa-paper-plane me-2"></i>Envoyer
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>