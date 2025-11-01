<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use Dotenv\Dotenv;
use TCPDF;

// Charger les variables d'environnement
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

// --- Fonctions utilitaires ---
function dbTest(): string {
    try {
        $db = new PDO($_ENV['DATABASE_URL']);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $db->query("SELECT NOW() AS current_time");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return "✅ Connexion PostgreSQL réussie : " . $row['current_time'];
    } catch (Exception $e) {
        return "❌ Erreur de connexion DB : " . $e->getMessage();
    }
}

function sendTestMail(): string {
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
        $mail->Subject = "Test d'envoi de mail depuis Render";
        $mail->Body = "✅ Message de test envoyé avec succès depuis votre application PHP hébergée sur Render.";
        $mail->send();
        return "✅ E-mail envoyé à " . $_ENV['SMTP_USER'];
    } catch (Exception $e) {
        return "❌ Erreur d'envoi d'e-mail : " . $mail->ErrorInfo;
    }
}

function generatePdf(): string {
    try {
        $pdf = new TCPDF();
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Write(0, "Rapport d'intervention - Exemple généré depuis Render", '', 0, 'L', true);
        $pdf->Ln(5);
        $pdf->Write(0, "Date : " . date('d/m/Y H:i:s'), '', 0, 'L', true);
        $pdf->Ln(5);
        $pdf->Write(0, "Contenu : Ceci est un test de génération de PDF automatique via TCPDF.", '', 0, 'L', true);

        $filePath = __DIR__ . '/test_report.pdf';
        $pdf->Output($filePath, 'F');
        return "✅ PDF généré avec succès : <a href='test_report.pdf' target='_blank'>Télécharger</a>";
    } catch (Exception $e) {
        return "❌ Erreur génération PDF : " . $e->getMessage();
    }
}

// --- Actions via URL ---
$action = $_GET['action'] ?? null;
$message = null;

if ($action === 'db') {
    $message = dbTest();
} elseif ($action === 'pdf') {
    $message = generatePdf();
} elseif ($action === 'mail') {
    $message = sendTestMail();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>MSE - Application de rapport d'intervention</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f7f9fc;
            margin: 0;
            padding: 2rem;
        }
        h1 {
            color: #2c3e50;
        }
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 2rem;
            max-width: 600px;
            margin: auto;
            text-align: center;
        }
        a.button {
            display: inline-block;
            margin: 1rem;
            padding: 0.8rem 1.5rem;
            background: #007BFF;
            color: white;
            border-radius: 6px;
            text-decoration: none;
        }
        a.button:hover {
            background: #0056b3;
        }
        .result {
            margin-top: 1.5rem;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>🚀 Application MSE - Rapports d'intervention</h1>
        <p>Testez les fonctionnalités principales :</p>
        <div>
            <a href="?action=db" class="button">Tester la base de données</a>
            <a href="?action=pdf" class="button">Générer un PDF</a>
            <a href="?action=mail" class="button">Envoyer un e-mail</a>
        </div>
        <?php if ($message): ?>
            <div class="result"><?= $message ?></div>
        <?php endif; ?>
    </div>
</body>
</html>
