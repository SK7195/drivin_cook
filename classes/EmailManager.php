<?php

require_once __DIR__ . '/../config/email.php';

class EmailManager {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function sendNewsletter($subject, $content) {
        $subscribers = $this->pdo->query("
            SELECT c.email, c.firstname, c.lastname
            FROM clients c
            JOIN newsletter_subscribers ns ON c.id = ns.client_id
            WHERE ns.subscribed = 1
        ")->fetchAll();
        
        if (empty($subscribers)) {
            return ['success' => false, 'message' => 'Aucun abonné'];
        }
        
        $sent = 0;
        foreach ($subscribers as $subscriber) {
            $personalizedContent = str_replace(
                ['{{firstname}}', '{{lastname}}'],
                [$subscriber['firstname'], $subscriber['lastname']],
                $content
            );
            
            $htmlEmail = $this->createNewsletterHTML($personalizedContent);
            
            if (sendEmail($subscriber['email'], $subject, $htmlEmail)) {
                $sent++;
            }
        }
        
        return [
            'success' => true,
            'message' => "Newsletter envoyée à $sent abonné(s)"
        ];
    }
    
    private function createNewsletterHTML($content) {
        return "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f4f4f4; }
                .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; }
                .header { background: linear-gradient(135deg, #28a745, #20c997); color: white; padding: 30px 20px; text-align: center; }
                .content { padding: 30px 20px; line-height: 1.6; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🍔 Driv'n Cook</h1>
                    <p>Newsletter</p>
                </div>
                <div class='content'>
                    " . nl2br(htmlspecialchars($content)) . "
                </div>
                <div class='footer'>
                    <p><strong>Driv'n Cook</strong></p>
                    <p>📞 01 23 45 67 89 | 📧 contact@drivinCook.fr</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    public function testConnection() {
        return sendEmail(SMTP_USERNAME, 'Test Driv\'n Cook', '<h1>Test réussi !</h1><p>SMTP fonctionne.</p>');
    }
}
?>