<?php
require __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use Dotenv\Dotenv;

// Charger les variables d'environnement
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

echo "<h2>Test de l'environnement</h2>";

// Test connexion DB
try {
    $db = new PDO($_ENV['DATABASE_URL']);
    echo "✅ Connexion à la base de données réussie<br>";
} catch (Exception $e) {
    echo "❌ Erreur DB : " . $e->getMessage() . "<br>";
}

// Test envoi mail
try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $_ENV['SMTP_HOST'];
    $mail->SMTPAuth = true;
    $mail->Username = $_ENV['SMTP_USER'];
    $mail->Password = $_ENV['SMTP_PASS'];
    $mail->SMTPSecure = 'tls';
    $mail->Port = $_ENV['SMTP_PORT'];
    $mail->setFrom($_ENV['MAIL_FROM'], $_ENV['MAIL_FROM_NAME']);
    $mail->addAddress($_ENV['SMTP_USER']);
    $mail->Subject = "Test PHPMailer depuis Render";
    $mail->Body = "Ceci est un test d'envoi de mail depuis votre application Render.";
    $mail->send();

    echo "✅ Email envoyé avec succès<br>";
} catch (Exception $e) {
    echo "❌ Erreur email : " . $mail->ErrorInfo . "<br>";
}

echo "<hr><p>Si les deux tests sont ✅, ton application est bien configurée !</p>";
