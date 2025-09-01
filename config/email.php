<?php

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'kakiswas@gmail.com');
define('SMTP_PASSWORD', 'kbuv mhzb gupl nbhg');
define('FROM_EMAIL', 'noreply@drivinCook.fr');
define('FROM_NAME', 'Driv\'n Cook');

require_once __DIR__ . '/../libs/PHPMailer/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../libs/PHPMailer/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../libs/PHPMailer/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function sendEmail($to, $subject, $message) {
    $mail = new PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        
        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($to);
        
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        
        return $mail->send();
        
    } catch (Exception $e) {
        error_log("Erreur email: " . $e->getMessage());
        return false;
    }
}
?>

