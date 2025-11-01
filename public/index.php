<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MSE\database;
use MSE\Report;
use MSE\EmailService;
use MSE\PdfService;
use Dotenv\Dotenv;

// Charger les variables d'environnement
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

// Démarrer la session
session_start();

// --- Fonctions utilitaires ---

/**
 * Tester la connexion à la base de données
 */
function dbTest(): array {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->query("SELECT NOW() AS current_time, version() AS pg_version");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return [
            'success' => true,
            'message' => "✅ Connexion PostgreSQL réussie",
            'time' => $row['current_time'],
            'version' => explode(' ', $row['pg_version'])[0] . ' ' . explode(' ', $row['pg_version'])[1]
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => "❌ Erreur de connexion DB : " . $e->getMessage()
        ];
    }
}

/**
 * Envoyer un email de test
 */
function sendTestMail(): array {
    try {
        $emailService = new EmailService();
        
        // Test de configuration
        $configTest = $emailService->testConnection();
        if (!$configTest['success']) {
            return $configTest;
        }
        
        // Créer un rapport de test
        $testReport = [
            'id' => 'TEST',
            'report_num' => 'TEST-' . date('YmdHis'),
            'report_date' => date('Y-m-d'),
            'address' => "3, Avenue Pierre Brasseur\n95490 VAUREAL",
            'intervenant' => 'Technicien Test',
            'urgence' => 'faible',
            'c1_marque' => 'Test Marque',
            'c1_modele' => 'Modèle Test',
            'c1_serie' => 'SN123456',
            'c2_marque' => '',
            'c2_modele' => '',
            'c2_serie' => '',
            'etat_general' => 'Test d\'envoi d\'email depuis l\'application MSE',
            'anomalies' => null,
            'travaux_realises' => 'Configuration du système d\'emailing',
            'recommandations' => 'Vérifier la réception de cet email de test',
            'email_destinataire' => getenv('SMTP_USER')
        ];
        
        $emailService->sendReport($testReport);
        
        return [
            'success' => true,
            'message' => "✅ E-mail de test envoyé à " . getenv('SMTP_USER')
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => "❌ Erreur d'envoi d'e-mail : " . $e->getMessage()
        ];
    }
}

/**
 * Générer un PDF de test
 */
function generateTestPdf(): array {
    try {
        $testReport = [
            'id' => 'TEST',
            'report_num' => 'TEST-' . date('YmdHis'),
            'report_date' => date('d/m/Y'),
            'address' => "3, Avenue Pierre Brasseur\n95490 VAUREAL",
            'intervenant' => 'Technicien Test',
            'urgence' => 'moyenne',
            'c1_marque' => 'Test Marque 1',
            'c1_modele' => 'Modèle Test 1',
            'c1_serie' => 'SN123456',
            'c2_marque' => 'Test Marque 2',
            'c2_modele' => 'Modèle Test 2',
            'c2_serie' => 'SN789012',
            'etat_general' => 'Test de génération de PDF automatique via l\'application MSE',
            'anomalies' => 'Aucune anomalie détectée lors de ce test',
            'travaux_realises' => 'Configuration du système de génération PDF',
            'recommandations' => 'Vérifier le rendu du PDF généré',
            'mesures' => ['Température: 45°C', 'Pression: 2.5 bars'],
            'controles' => ['Étanchéité: OK', 'Combustion: OK'],
            'releves' => []
        ];
        
        $pdfService = new PdfService($testReport, []);
        $filePath = __DIR__ . '/test_report_' . time() . '.pdf';
        $pdfService->generate($filePath);
        
        $fileName = basename($filePath);
        
        return [
            'success' => true,
            'message' => "✅ PDF généré avec succès",
            'file' => $fileName,
            'download_link' => $fileName
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => "❌ Erreur génération PDF : " . $e->getMessage()
        ];
    }
}

/**
 * Créer un rapport depuis un formulaire
 */
function createReport(): array {
    try {
        // Validation des données
        if (empty($_POST['reportDate'])) {
            throw new Exception("La date est obligatoire");
        }
        
        if (empty($_POST['address'])) {
            throw new Exception("L'adresse est obligatoire");
        }
        
        // Préparer les données
        $data = [
            'reportNum' => $_POST['reportNum'] ?? 'R-' . date('YmdHis'),
            'reportDate' => $_POST['reportDate'],
            'address' => $_POST['address'],
            'intervenant' => $_POST['intervenant'] ?? '',
            'urgence' => $_POST['urgence'] ?? 'faible',
            'c1_marque' => $_POST['c1_marque'] ?? '',
            'c1_modele' => $_POST['c1_modele'] ?? '',
            'c1_serie' => $_POST['c1_serie'] ?? '',
            'c2_marque' => $_POST['c2_marque'] ?? '',
            'c2_modele' => $_POST['c2_modele'] ?? '',
            'c2_serie' => $_POST['c2_serie'] ?? '',
            'etat_general' => $_POST['etat_general'] ?? '',
            'anomalies' => $_POST['anomalies'] ?? '',
            'travaux_realises' => $_POST['travaux_realises'] ?? '',
            'recommandations' => $_POST['recommandations'] ?? '',
            'email_destinataire' => $_POST['email_destinataire'] ?? '',
            'mesures' => !empty($_POST['mesures']) ? explode("\n", $_POST['mesures']) : [],
            'controles' => !empty($_POST['controles']) ? explode("\n", $_POST['controles']) : [],
            'releves' => !empty($_POST['releves']) ? explode("\n", $_POST['releves']) : []
        ];
        
        // Gérer les photos uploadées
        $photos = [];
        if (!empty($_FILES['photos']['name'][0])) {
            foreach ($_FILES['photos']['tmp_name'] as $key => $tmp_name) {
                if (!empty($tmp_name) && is_uploaded_file($tmp_name)) {
                    $photoData = file_get_contents($tmp_name);
                    $photos[] = [
                        'data' => 'data:' . $_FILES['photos']['type'][$key] . ';base64,' . base64_encode($photoData),
                        'name' => $_FILES['photos']['name'][$key],
                        'type' => $_FILES['photos']['type'][$key],
                        'size' => $_FILES['photos']['size'][$key],
                        'description' => $_POST['photo_descriptions'][$key] ?? ''
                    ];
                }
            }
        }
        
        // Sauvegarder le rapport
        $report = new Report();
        $reportId = $report->save($data, $photos);
        
        return [
            'success' => true,
            'message' => "✅ Rapport créé avec succès (ID: $reportId)",
            'report_id' => $reportId
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => "❌ Erreur : " . $e->getMessage()
        ];
    }
}

/**
 * Envoyer un rapport existant par email
 */
function sendReportEmail($reportId): array {
    try {
        $report = new Report();
        $data = $report->getById($reportId);
        
        if (!$data) {
            throw new Exception("Rapport introuvable");
        }
        
        if (empty($data['email_destinataire'])) {
            throw new Exception("Aucun email destinataire configuré");
        }
        
        // Générer le PDF
        $pdfService = new PdfService($data, $data['photos'] ?? []);
        $pdfPath = __DIR__ . '/../temp/report_' . $reportId . '_' . time() . '.pdf';
        
        // Créer le dossier temp si nécessaire
        $tempDir = dirname($pdfPath);
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        
        $pdfService->generate($pdfPath);
        
        // Envoyer l'email
        $emailService = new EmailService();
        $emailService->sendReport($data, $data['photos'] ?? [], $pdfPath);
        
        // Marquer comme envoyé
        $report->markEmailSent($reportId);
        
        // Supprimer le PDF temporaire
        if (file_exists($pdfPath)) {
            unlink($pdfPath);
        }
        
        return [
            'success' => true,
            'message' => "✅ Email envoyé avec succès à " . $data['email_destinataire']
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => "❌ Erreur : " . $e->getMessage()
        ];
    }
}

/**
 * Télécharger un rapport en PDF
 */
function downloadReportPdf($reportId) {
    try {
        $report = new Report();
        $data = $report->getById($reportId);
        
        if (!$data) {
            throw new Exception("Rapport introuvable");
        }
        
        $pdfService = new PdfService($data, $data['photos'] ?? []);
        $pdfContent = $pdfService->generate();
        
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="rapport_' . $data['report_num'] . '.pdf"');
        header('Content-Length: ' . strlen($pdfContent));
        echo $pdfContent;
        exit;
        
    } catch (Exception $e) {
        $_SESSION['message'] = [
            'success' => false,
            'message' => "❌ Erreur : " . $e->getMessage()
        ];
        header('Location: /');
        exit;
    }
}

// --- Traitement des actions ---

$message = null;
$action = $_GET['action'] ?? $_POST['action'] ?? null;

// Actions GET
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    switch ($action) {
        case 'db':
            $message = dbTest();
            break;
            
        case 'pdf':
            $message = generateTestPdf();
            break;
            
        case 'mail':
            $message = sendTestMail();
            break;
            
        case 'download':
            if (!empty($_GET['id'])) {
                downloadReportPdf((int)$_GET['id']);
            }
            break;
            
        case 'send':
            if (!empty($_GET['id'])) {
                $message = sendReportEmail((int)$_GET['id']);
            }
            break;
    }
}

// Actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'create':
            $message = createReport();
            if ($message['success']) {
                $_SESSION['message'] = $message;
                header('Location: /?action=view&id=' . $message['report_id']);
                exit;
            }
            break;
    }
}

// Récupérer la liste des rapports pour l'affichage
$reports = [];
try {
    $reportModel = new Report();
    $reports = $reportModel->getAll(20, 0);
} catch (Exception $e) {
    error_log("Erreur récupération rapports: " . $e->getMessage());
}

// Récupérer un rapport spécifique si demandé
$currentReport = null;
if ($action === 'view' && !empty($_GET['id'])) {
    try {
        $reportModel = new Report();
        $currentReport = $reportModel->getById((int)$_GET['id']);
    } catch (Exception $e) {
        $message = [
            'success' => false,
            'message' => "❌ Erreur : " . $e->getMessage()
        ];
    }
}

// Récupérer le message de session si présent
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MSE - Rapports d'Intervention</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .header h1 {
            color: #2c3e50;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .header p {
            color: #6b7280;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .card h2 {
            color: #667eea;
            border-bottom: 2px solid #667eea;
            padding-bottom: 0.5rem;
            margin-bottom: 1.5rem;
        }
        
        .button-group {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin: 1.5rem 0;
        }
        
        .button {
            display: inline-block;
            padding: 0.8rem 1.5rem;
            background: #667eea;
            color: white;
            border-radius: 8px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            transition: background 0.3s;
        }
        
        .button:hover {
            background: #5568d3;
        }
        
        .button-secondary {
            background: #6b7280;
        }
        
        .button-secondary:hover {
            background: #4b5563;
        }
        
        .button-success {
            background: #10b981;
        }
        
        .button-success:hover {
            background: #059669;
        }
        
        .button-danger {
            background: #ef4444;
        }
        
        .button-danger:hover {
            background: #dc2626;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        
        .alert-success {
            background: #d1fae5;
            border-left: 4px solid #10b981;
            color: #065f46;
        }
        
        .alert-error {
            background: #fee2e2;
            border-left: 4px solid #ef4444;
            color: #7f1d1d;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            font-weight: bold;
            color: #4b5563;
            margin-bottom: 0.5rem;
        }
        
        .form-group input[type="text"],
        .form-group input[type="date"],
        .form-group input[type="email"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 1rem;
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .reports-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .reports-table th,
        .reports-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .reports-table th {
            background: #f3f4f6;
            font-weight: bold;
            color: #4b5563;
        }
        
        .reports-table tr:hover {
            background: #f9fafb;
        }
        
        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.875rem;
            font-weight: bold;
        }
        
        .badge-faible {
            background: #d1fae5;
            color: #065f46;
        }
        
        .badge-moyenne {
            background: #fed7aa;
            color: #92400e;
        }
        
        .badge-elevee {
            background: #fbbf24;
            color: #78350f;
        }
        
        .badge-critique {
            background: #fca5a5;
            color: #7f1d1d;
        }
        
        .tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .tab {
            padding: 0.75rem 1.5rem;
            background: none;
            border: none;
            cursor: pointer;
            color: #6b7280;
            font-size: 1rem;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
        }
        
        .tab.active {
            color: #667eea;
            border-bottom-color: #667eea;
            font-weight: bold;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .button-group {
                flex-direction: column;
            }
            
            .reports-table {
                font-size: 0.875rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔥 MSE - Rapports d'Intervention</h1>
            <p>Maintenance des Systèmes Énergétiques</p>
        </div>
        
        <?php if ($message): ?>
            <div class="alert <?= $message['success'] ? 'alert-success' : 'alert-error' ?>">
                <?= htmlspecialchars($message['message']) ?>
                <?php if (isset($message['download_link'])): ?>
                    <br><a href="<?= htmlspecialchars($message['download_link']) ?>" target="_blank" class="button button-secondary" style="margin-top: 0.5rem;">📥 Télécharger le PDF</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="tabs">
                <button class="tab active" onclick="switchTab('tests')">🧪 Tests</button>
                <button class="tab" onclick="switchTab('create')">➕ Nouveau Rapport</button>
                <button class="tab" onclick="switchTab('list')">📋 Liste des Rapports</button>
            </div>
            
            <!-- Onglet Tests -->
            <div id="tab-tests" class="tab-content active">
                <h2>Tests des Fonctionnalités</h2>
                <p>Testez les fonctionnalités principales de l'application :</p>
                <div class="button-group">
                    <a href="?action=db" class="button">🗄️ Tester la base de données</a>
                    <a href="?action=pdf" class="button">📄 Générer un PDF test</a>
                    <a href="?action=mail" class="button">📧 Envoyer un e-mail test</a>
                </div>
            </div>
            
            <!-- Onglet Création -->
            <div id="tab-create" class="tab-content">
                <h2>Créer un Nouveau Rapport</h2>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="reportNum">N° Rapport</label>
                            <input type="text" id="reportNum" name="reportNum" placeholder="R-20240101-001">
                        </div>
                        <div class="form-group">
                            <label for="reportDate">Date *</label>
                            <input type="date" id="reportDate" name="reportDate" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Adresse d'Intervention *</label>
                        <textarea id="address" name="address" required></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="intervenant">Intervenant</label>
                            <input type="text" id="intervenant" name="intervenant">
                        </div>
                        <div class="form-group">
                            <label for="urgence">Urgence</label>
                            <select id="urgence" name="urgence">
                                <option value="faible">🟢 Faible</option>
                                <option value="moyenne" selected>🟡 Moyenne</option>
                                <option value="elevee">🟠 Élevée</option>
                                <option value="critique">🔴 Critique</option>
                            </select>
                        </div>
                    </div>
                    
                    <h3 style="margin: 2rem 0 1rem; color: #667eea;">Chaudière N°1</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="c1_marque">Marque</label>
                            <input type="text" id="c1_marque" name="c1_marque">
                        </div>
                        <div class="form-group">
                            <label for="c1_modele">Modèle</label>
                            <input type="text" id="c1_modele" name="c1_modele">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="c1_serie">N° de Série</label>
                        <input type="text" id="c1_serie" name="c1_serie">
                    </div>
                    
                    <h3 style="margin: 2rem 0 1rem; color: #667eea;">Chaudière N°2</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="c2_marque">Marque</label>
                            <input type="text" id="c2_marque" name="c2_marque">
                        </div>
                        <div class="form-group">
                            <label for="c2_modele">Modèle</label>
                            <input type="text" id="c2_modele" name="c2_modele">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="c2_serie">N° de Série</label>
                        <input type="text" id="c2_serie" name="c2_serie">
                    </div>
                    
                    <h3 style="margin: 2rem 0 1rem; color: #667eea;">Observations</h3>
                    <div class="form-group">
                        <label for="etat_general">État Général</label>
                        <textarea id="etat_general" name="etat_general"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="anomalies">Anomalies Constatées</label>
                        <textarea id="anomalies" name="anomalies"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="travaux_realises">Travaux Réalisés</label>
                        <textarea id="travaux_realises" name="travaux_realises"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="recommandations">Recommandations</label>
                        <textarea id="recommandations" name="recommandations"></textarea>
                    </div>
                    
                    <h3 style="margin: 2rem 0 1rem; color: #667eea;">Mesures et Contrôles</h3>
                    <div class="form-group">
                        <label for="mesures">Mesures (une par ligne)</label>
                        <textarea id="mesures" name="mesures" placeholder="Température: 45°C&#10;Pression: 2.5 bars"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="controles">Contrôles (une par ligne)</label>
                        <textarea id="controles" name="controles" placeholder="Étanchéité: OK&#10;Combustion: OK"></textarea>
                    </div>
                    
                    <h3 style="margin: 2rem 0 1rem; color: #667eea;">Photos</h3>
                    <div class="form-group">
                        <label for="photos">Ajouter des photos</label>
                        <input type="file" id="photos" name="photos[]" accept="image/*" multiple>
                    </div>
                    
                    <h3 style="margin: 2rem 0 1rem; color: #667eea;">Envoi</h3>
                    <div class="form-group">
                        <label for="email_destinataire">Email Destinataire</label>
                        <input type="email" id="email_destinataire" name="email_destinataire">
                    </div>
                    
                    <button type="submit" class="button button-success">✅ Créer le Rapport</button>
                </form>
            </div>
            
            <!-- Onglet Liste -->
            <div id="tab-list" class="tab-content">
                <h2>Liste des Rapports</h2>
                <?php if (empty($reports)): ?>
                    <p>Aucun rapport trouvé. Créez-en un pour commencer.</p>
                <?php else: ?>
                    <table class="reports-table">
                        <thead>
                            <tr>
                                <th>N°</th>
                                <th>Date</th>
                                <th>Adresse</th>
                                <th>Urgence</th>
                                <th>Intervenant</th>
                                <th>Photos</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reports as $r): ?>
                                <tr>
                                    <td><?= isset($r['photo_count']) ? $r['photo_count'] : 0 ?></td>
                                    <td>
                                        <a href="?action=view&id=<?= $r['id'] ?>" class="button" style="padding: 0.5rem 1rem; font-size: 0.875rem;">👁️ Voir</a>
                                        <a href="?action=download&id=<?= $r['id'] ?>" class="button button-secondary" style="padding: 0.5rem 1rem; font-size: 0.875rem;">📥 PDF</a>
                                        <?php if (!empty($r['email_destinataire'])): ?>
                                            <a href="?action=send&id=<?= $r['id'] ?>" class="button button-success" style="padding: 0.5rem 1rem; font-size: 0.875rem;" onclick="return confirm('Envoyer ce rapport par email ?')">📧 Envoyer</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Affichage détaillé d'un rapport -->
        <?php if ($currentReport): ?>
            <div class="card">
                <h2>📄 Rapport <?= htmlspecialchars($currentReport['report_num'] ?: '#' . $currentReport['id']) ?></h2>
                
                <div class="button-group">
                    <a href="/" class="button button-secondary">⬅️ Retour</a>
                    <a href="?action=download&id=<?= $currentReport['id'] ?>" class="button">📥 Télécharger PDF</a>
                    <?php if (!empty($currentReport['email_destinataire'])): ?>
                        <a href="?action=send&id=<?= $currentReport['id'] ?>" class="button button-success" onclick="return confirm('Envoyer ce rapport par email ?')">📧 Envoyer par Email</a>
                    <?php endif; ?>
                </div>
                
                <h3 style="margin: 2rem 0 1rem; color: #667eea;">Informations Générales</h3>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 0.5rem; background: #f3f4f6; font-weight: bold; width: 30%;">N° Rapport:</td>
                        <td style="padding: 0.5rem;"><?= htmlspecialchars($currentReport['report_num'] ?: '-') ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 0.5rem; background: #f3f4f6; font-weight: bold;">Date:</td>
                        <td style="padding: 0.5rem;"><?= htmlspecialchars($currentReport['report_date'] ?: '-') ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 0.5rem; background: #f3f4f6; font-weight: bold;">Intervenant:</td>
                        <td style="padding: 0.5rem;"><?= htmlspecialchars($currentReport['intervenant'] ?: '-') ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 0.5rem; background: #f3f4f6; font-weight: bold;">Urgence:</td>
                        <td style="padding: 0.5rem;">
                            <span class="badge badge-<?= htmlspecialchars($currentReport['urgence']) ?>">
                                <?php
                                $urgenceLabels = [
                                    'faible' => '🟢 Faible',
                                    'moyenne' => '🟡 Moyenne',
                                    'elevee' => '🟠 Élevée',
                                    'critique' => '🔴 Critique'
                                ];
                                echo $urgenceLabels[$currentReport['urgence']] ?? 'Non spécifié';
                                ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 0.5rem; background: #f3f4f6; font-weight: bold;">Adresse:</td>
                        <td style="padding: 0.5rem;"><?= nl2br(htmlspecialchars($currentReport['address'] ?: '-')) ?></td>
                    </tr>
                    <?php if (!empty($currentReport['email_destinataire'])): ?>
                        <tr>
                            <td style="padding: 0.5rem; background: #f3f4f6; font-weight: bold;">Email:</td>
                            <td style="padding: 0.5rem;"><?= htmlspecialchars($currentReport['email_destinataire']) ?></td>
                        </tr>
                    <?php endif; ?>
                </table>
                
                <h3 style="margin: 2rem 0 1rem; color: #667eea;">Chaudières</h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div style="padding: 1rem; background: #e0f2fe; border-radius: 8px;">
                        <h4 style="color: #06b6d4; margin-bottom: 0.5rem;">Chaudière N°1</h4>
                        <p><strong>Marque:</strong> <?= htmlspecialchars($currentReport['c1_marque'] ?: '-') ?></p>
                        <p><strong>Modèle:</strong> <?= htmlspecialchars($currentReport['c1_modele'] ?: '-') ?></p>
                        <p><strong>Série:</strong> <?= htmlspecialchars($currentReport['c1_serie'] ?: '-') ?></p>
                    </div>
                    <div style="padding: 1rem; background: #fef3c7; border-radius: 8px;">
                        <h4 style="color: #f59e0b; margin-bottom: 0.5rem;">Chaudière N°2</h4>
                        <p><strong>Marque:</strong> <?= htmlspecialchars($currentReport['c2_marque'] ?: '-') ?></p>
                        <p><strong>Modèle:</strong> <?= htmlspecialchars($currentReport['c2_modele'] ?: '-') ?></p>
                        <p><strong>Série:</strong> <?= htmlspecialchars($currentReport['c2_serie'] ?: '-') ?></p>
                    </div>
                </div>
                
                <?php if (!empty($currentReport['etat_general'])): ?>
                    <h3 style="margin: 2rem 0 1rem; color: #667eea;">État Général</h3>
                    <p style="padding: 1rem; background: #f9fafb; border-left: 4px solid #667eea; border-radius: 4px;">
                        <?= nl2br(htmlspecialchars($currentReport['etat_general'])) ?>
                    </p>
                <?php endif; ?>
                
                <?php if (!empty($currentReport['anomalies'])): ?>
                    <h3 style="margin: 2rem 0 1rem; color: #667eea;">Anomalies Constatées</h3>
                    <p style="padding: 1rem; background: #fee2e2; border-left: 4px solid #ef4444; border-radius: 4px;">
                        <?= nl2br(htmlspecialchars($currentReport['anomalies'])) ?>
                    </p>
                <?php endif; ?>
                
                <?php if (!empty($currentReport['travaux_realises'])): ?>
                    <h3 style="margin: 2rem 0 1rem; color: #667eea;">Travaux Réalisés</h3>
                    <p style="padding: 1rem; background: #f9fafb; border-left: 4px solid #667eea; border-radius: 4px;">
                        <?= nl2br(htmlspecialchars($currentReport['travaux_realises'])) ?>
                    </p>
                <?php endif; ?>
                
                <?php if (!empty($currentReport['recommandations'])): ?>
                    <h3 style="margin: 2rem 0 1rem; color: #667eea;">Recommandations</h3>
                    <p style="padding: 1rem; background: #dbeafe; border-left: 4px solid #3b82f6; border-radius: 4px;">
                        <?= nl2br(htmlspecialchars($currentReport['recommandations'])) ?>
                    </p>
                <?php endif; ?>
                
                <?php 
                $hasMesures = !empty($currentReport['mesures']) && is_array($currentReport['mesures']);
                $hasControles = !empty($currentReport['controles']) && is_array($currentReport['controles']);
                $hasReleves = !empty($currentReport['releves']) && is_array($currentReport['releves']);
                
                if ($hasMesures || $hasControles || $hasReleves): 
                ?>
                    <h3 style="margin: 2rem 0 1rem; color: #667eea;">Mesures et Contrôles</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem;">
                        <?php if ($hasMesures): ?>
                            <div style="padding: 1rem; background: #f9fafb; border-radius: 8px;">
                                <h4 style="color: #667eea; margin-bottom: 0.5rem;">📊 Mesures</h4>
                                <ul style="margin: 0; padding-left: 1.5rem;">
                                    <?php foreach ($currentReport['mesures'] as $mesure): ?>
                                        <li><?= htmlspecialchars($mesure) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($hasControles): ?>
                            <div style="padding: 1rem; background: #f9fafb; border-radius: 8px;">
                                <h4 style="color: #667eea; margin-bottom: 0.5rem;">✅ Contrôles</h4>
                                <ul style="margin: 0; padding-left: 1.5rem;">
                                    <?php foreach ($currentReport['controles'] as $controle): ?>
                                        <li><?= htmlspecialchars($controle) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($hasReleves): ?>
                            <div style="padding: 1rem; background: #f9fafb; border-radius: 8px;">
                                <h4 style="color: #667eea; margin-bottom: 0.5rem;">📋 Relevés</h4>
                                <ul style="margin: 0; padding-left: 1.5rem;">
                                    <?php foreach ($currentReport['releves'] as $releve): ?>
                                        <li><?= htmlspecialchars($releve) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($currentReport['photos'])): ?>
                    <h3 style="margin: 2rem 0 1rem; color: #667eea;">📷 Photos (<?= count($currentReport['photos']) ?>)</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 1rem;">
                        <?php foreach ($currentReport['photos'] as $photo): ?>
                            <div style="border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;">
                                <?php if (!empty($photo['photo_data'])): ?>
                                    <img src="<?= htmlspecialchars($photo['photo_data']) ?>" 
                                         alt="<?= htmlspecialchars($photo['photo_name']) ?>"
                                         style="width: 100%; height: 200px; object-fit: cover;">
                                <?php endif; ?>
                                <div style="padding: 0.75rem; background: #f9fafb;">
                                    <p style="font-size: 0.875rem; font-weight: bold; margin-bottom: 0.25rem;">
                                        <?= htmlspecialchars($photo['photo_name']) ?>
                                    </p>
                                    <?php if (!empty($photo['description'])): ?>
                                        <p style="font-size: 0.75rem; color: #6b7280;">
                                            <?= htmlspecialchars($photo['description']) ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($currentReport['email_sent']): ?>
                    <div class="alert alert-success" style="margin-top: 2rem;">
                        ✅ Email envoyé le <?= date('d/m/Y à H:i', strtotime($currentReport['email_sent_at'])) ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div style="text-align: center; color: white; margin-top: 2rem; padding: 1rem;">
            <p><strong>MSE - Maintenance des Systèmes Énergétiques</strong></p>
            <p>3, Avenue Pierre Brasseur - 95490 VAUREAL</p>
            <p>Tél : +33 7 60 06 94 05</p>
        </div>
    </div>
    
    <script>
        function switchTab(tabName) {
            // Cacher tous les onglets
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Désactiver tous les boutons d'onglet
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Activer l'onglet sélectionné
            document.getElementById('tab-' + tabName).classList.add('active');
            event.target.classList.add('active');
        }
        
        // Gestion de l'upload de photos avec prévisualisation
        document.getElementById('photos').addEventListener('change', function(e) {
            const files = e.target.files;
            if (files.length > 0) {
                console.log(`${files.length} photo(s) sélectionnée(s)`);
                // Vous pouvez ajouter ici une prévisualisation si souhaité
            }
        });
        
        // Confirmation avant suppression
        document.querySelectorAll('a[href*="delete"]').forEach(link => {
            link.addEventListener('click', function(e) {
                if (!confirm('Êtes-vous sûr de vouloir supprimer cet élément ?')) {
                    e.preventDefault();
                }
            });
        });
        
        // Auto-génération du numéro de rapport
        document.addEventListener('DOMContentLoaded', function() {
            const reportNumInput = document.getElementById('reportNum');
            if (reportNumInput && !reportNumInput.value) {
                const now = new Date();
                const year = now.getFullYear();
                const month = String(now.getMonth() + 1).padStart(2, '0');
                const day = String(now.getDate()).padStart(2, '0');
                const hours = String(now.getHours()).padStart(2, '0');
                const minutes = String(now.getMinutes()).padStart(2, '0');
                reportNumInput.value = `R-${year}${month}${day}-${hours}${minutes}`;
            }
        });
    </script>
</body>
</html> htmlspecialchars($r['report_num'] ?: '#' . $r['id']) ?></td>
                                    <td><?= htmlspecialchars($r['report_date'] ?: '-') ?></td>
                                    <td><?= htmlspecialchars(substr($r['address'] ?? '-', 0, 40)) ?>...</td>
                                    <td>
                                        <span class="badge badge-<?= htmlspecialchars($r['urgence']) ?>">
                                            <?= htmlspecialchars(ucfirst($r['urgence'])) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($r['intervenant'] ?: '-') ?></td>
                                    <td><?=
