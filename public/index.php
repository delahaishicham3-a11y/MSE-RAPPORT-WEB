<?php
session_save_path("/tmp/sessions");
session_start();

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use MSE\Report;
use MSE\EmailService;

// Initialiser les services
try {
    $reportModel = new Report();
    $emailService = new EmailService();
} catch (Exception $e) {
    die("Erreur de connexion à la base de données: " . $e->getMessage());
}

// Traitement des actions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'save') {
            // Récupérer les données du rapport
            $reportData = [
                'reportNum' => $_POST['reportNum'] ?? '',
                'reportDate' => $_POST['reportDate'] ?? '',
                'address' => $_POST['address'] ?? '',
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
                'urgence' => $_POST['urgence'] ?? '',
                'intervenant' => $_POST['intervenant'] ?? '',
                'email_destinataire' => $_POST['email_destinataire'] ?? '',
                'mesures' => json_decode($_POST['mesures'] ?? '{}', true),
                'controles' => json_decode($_POST['controles'] ?? '{}', true),
                'releves' => json_decode($_POST['releves'] ?? '{}', true),
            ];
            
            // Récupérer les photos
            $photos = [];
            if (!empty($_POST['photos'])) {
                $photosData = json_decode($_POST['photos'], true);
                foreach ($photosData as $photoData) {
                    $photos[] = [
                        'data' => $photoData['data'],
                        'name' => $photoData['name'],
                        'type' => $photoData['type'],
                        'size' => $photoData['size'],
                        'description' => $photoData['description'] ?? ''
                    ];
                }
            }
            
            $id = $reportModel->save($reportData, $photos);
            
            $message = 'Rapport sauvegardé avec succès ! (ID: ' . $id . ')';
            $messageType = 'success';
        }
        
        if ($action === 'delete') {
            $id = intval($_POST['id']);
            $reportModel->delete($id);
            
            $message = 'Rapport supprimé';
            $messageType = 'success';
        }
        
        if ($action === 'send_email') {
            $id = intval($_POST['report_id']);
            $report = $reportModel->getById($id);
            
            if ($report) {
                $photos = $report['photos'] ?? [];
                $emailService->sendReport($report, $photos);
                $reportModel->markEmailSent($id);
                
                $message = 'Email envoyé avec succès à ' . $report['email_destinataire'];
                $messageType = 'success';
            }
        }
        
        if ($action === 'generate_pdf') {
            // Redirection vers le générateur PDF
            header('Location: generate_pdf.php?' . http_build_query($_POST));
            exit;
        }
    } catch (Exception $e) {
        $message = 'Erreur: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Récupérer tous les rapports
try {
    $reports = $reportModel->getAll(100);
    $totalReports = $reportModel->count();
} catch (Exception $e) {
    $reports = [];
    $totalReports = 0;
    $message = 'Erreur de chargement des rapports: ' . $e->getMessage();
    $messageType = 'danger';
}

// Paramètres pour les formulaires
$mesures = ['P.gaz', 'O2', 'CO2', 'CO', 'NOx', 'Ionisation', 'T° Amb', 'T° Dép', 'T° Fumée', 'Rendement', 'Vit. ventilateur'];
$controles = ['Temp. ext', 'Temp. dép. prim', 'Temp. ret. prim', 'Temp. dép. chauffage', 'Temp. ret. chauffage', 'Temp. dép. ECS', 'Temp. boucle ECS', 'Pression réseau'];
$releves = ['Compteur apt. eau', 'Compteur ECS', 'Compteur gaz', 'Compteur énergie', 'Manœuvrage vannes', 'Contrôles expansion', 'Analyses PH/TH', 'Permutation pompes'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MSE - Rapport d'Intervention</title>
<meta name="description" content="Application de gestion des rapports d'intervention - Maintenance des Systèmes Énergétiques">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }

:root {
  --primary: #3B82F6;
  --primary-dark: #2563EB;
  --secondary: #10B981;
  --danger: #EF4444;
  --warning: #F59E0B;
  --info: #06B6D4;
  --dark: #1F2937;
  --gray: #6B7280;
  --light-gray: #F3F4F6;
  --border: #E5E7EB;
  --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
  --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
}

body { 
  font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; 
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  min-height: 100vh;
}

.app { 
  max-width: 100%; 
  background: rgba(255, 255, 255, 0.95); 
  min-height: 100vh;
  backdrop-filter: blur(10px);
}

.header { 
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white; 
  padding: 25px 20px; 
  text-align: center; 
  position: sticky; 
  top: 0; 
  z-index: 100;
  box-shadow: 0 4px 20px rgba(0,0,0,0.15);
}

.header h1 { 
  font-size: 24px; 
  margin-bottom: 8px;
  font-weight: 700;
  text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
}

.header p { 
  font-size: 14px; 
  opacity: 0.95;
  letter-spacing: 1px;
}

.nav { 
  display: flex; 
  background: white; 
  border-bottom: 1px solid var(--border); 
  position: sticky; 
  top: 85px; 
  z-index: 50; 
  overflow-x: auto;
  box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.nav-btn { 
  flex: none; 
  padding: 18px 16px; 
  border: none; 
  background: transparent; 
  cursor: pointer; 
  font-size: 14px; 
  min-width: 100px; 
  border-bottom: 3px solid transparent; 
  color: var(--gray);
  transition: all 0.3s ease;
  font-weight: 500;
}

.nav-btn:hover {
  background: var(--light-gray);
  color: var(--primary);
}

.nav-btn.active { 
  background: linear-gradient(to bottom, #f0f9ff, #e0f2fe);
  color: var(--primary); 
  border-bottom-color: var(--primary); 
  font-weight: 600;
}

.content { 
  padding: 30px 20px 120px;
  background: linear-gradient(to bottom, transparent, rgba(255,255,255,0.5));
}

.tab { display: none; animation: fadeIn 0.4s ease; }
.tab.active { display: block; }

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

.form-group { margin-bottom: 20px; }

.form-group label { 
  display: block; 
  margin-bottom: 8px; 
  font-weight: 600; 
  font-size: 14px; 
  color: var(--dark);
  letter-spacing: 0.5px;
}

.form-control { 
  width: 100%; 
  padding: 14px 16px; 
  border: 2px solid var(--border); 
  border-radius: 12px; 
  font-size: 15px; 
  background: white;
  transition: all 0.3s ease;
}

.form-control:focus { 
  outline: none; 
  border-color: var(--primary); 
  box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15);
}

select.form-control {
  cursor: pointer;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%236B7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E");
  background-position: right 12px center;
  background-repeat: no-repeat;
  background-size: 20px;
  padding-right: 40px;
}

.card { 
  background: white; 
  border: 1px solid var(--border); 
  border-radius: 16px; 
  padding: 20px; 
  margin-bottom: 20px; 
  box-shadow: var(--shadow);
  transition: all 0.3s ease;
}

.card:hover {
  box-shadow: var(--shadow-lg);
  transform: translateY(-2px);
}

.card h3 { 
  font-size: 18px; 
  margin-bottom: 16px; 
  color: var(--dark); 
  font-weight: 600;
  display: flex;
  align-items: center;
  padding-bottom: 12px;
  border-bottom: 2px solid var(--light-gray);
}

.card h3::before {
  content: '▸';
  color: var(--primary);
  margin-right: 8px;
  font-size: 20px;
}

/* Photos Section */
.photos-container {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
  gap: 15px;
  margin-top: 15px;
}

.photo-item {
  position: relative;
  border: 2px dashed var(--border);
  border-radius: 12px;
  padding: 10px;
  text-align: center;
  cursor: pointer;
  transition: all 0.3s ease;
  background: var(--light-gray);
}

.photo-item:hover {
  border-color: var(--primary);
  background: rgba(59, 130, 246, 0.05);
}

.photo-item img {
  max-width: 100%;
  height: 120px;
  object-fit: cover;
  border-radius: 8px;
  margin-bottom: 8px;
}

.photo-item .remove-photo {
  position: absolute;
  top: 5px;
  right: 5px;
  background: var(--danger);
  color: white;
  border: none;
  border-radius: 50%;
  width: 24px;
  height: 24px;
  cursor: pointer;
  font-size: 16px;
  line-height: 1;
  display: flex;
  align-items: center;
  justify-content: center;
}

.photo-upload {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 150px;
}

.photo-upload-icon {
  font-size: 40px;
  color: var(--gray);
  margin-bottom: 10px;
}

.photo-count {
  font-size: 12px;
  color: var(--gray);
  margin-top: 5px;
}

.email-section {
  background: linear-gradient(135deg, #DBEAFE, #BFDBFE);
  border-left: 4px solid var(--primary);
  padding: 20px;
  border-radius: 12px;
  margin: 20px 0;
}

.email-status {
  display: inline-block;
  padding: 4px 12px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 600;
}

.email-sent {
  background: #D1FAE5;
  color: #065F46;
}

.email-not-sent {
  background: #FEE2E2;
  color: #7F1D1D;
}

.bottom-bar { 
  position: fixed; 
  bottom: 0; 
  left: 0; 
  right: 0; 
  background: linear-gradient(to top, white, rgba(255,255,255,0.98));
  border-top: 1px solid var(--border); 
  padding: 14px 20px; 
  text-align: center; 
  box-shadow: 0 -10px 40px rgba(0,0,0,0.1); 
  z-index: 1000;
  backdrop-filter: blur(10px);
}

.btn { 
  padding: 12px 18px; 
  margin: 0 6px; 
  border: none; 
  border-radius: 10px; 
  cursor: pointer; 
  font-size: 14px; 
  font-weight: 600; 
  box-shadow: var(--shadow);
  transition: all 0.3s ease;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.btn-save { background: linear-gradient(135deg, var(--secondary), #059669); color: white; }
.btn-save:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4); }

.btn-pdf { background: linear-gradient(135deg, var(--danger), #DC2626); color: white; }
.btn-pdf:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4); }

.btn-email { background: linear-gradient(135deg, var(--info), #0891B2); color: white; }
.btn-email:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(6, 182, 212, 0.4); }

.btn-load { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; flex: 1; padding: 10px 16px; }
.btn-delete { background: linear-gradient(135deg, var(--danger), #B91C1C); color: white; flex: 1; padding: 10px 16px; }

.saved-card { 
  background: white; 
  border: 1px solid var(--border); 
  border-radius: 16px; 
  padding: 20px; 
  margin-bottom: 16px; 
  box-shadow: var(--shadow);
  border-left: 4px solid var(--primary);
}

.saved-card h4 { margin: 0 0 12px 0; color: var(--dark); font-size: 18px; font-weight: 600; }
.saved-card p { margin: 6px 0; font-size: 14px; color: var(--gray); }
.saved-actions { margin-top: 16px; display: flex; gap: 12px; }

.alert {
  padding: 16px;
  border-radius: 12px;
  margin-bottom: 20px;
}

.alert-info {
  background: linear-gradient(135deg, #DBEAFE, #BFDBFE);
  border-left: 4px solid var(--primary);
  color: #1E40AF;
}

.alert-success {
  background: linear-gradient(135deg, #D1FAE5, #A7F3D0);
  border-left: 4px solid var(--secondary);
  color: #065F46;
}

.alert-danger {
  background: linear-gradient(135deg, #FEE2E2, #FECACA);
  border-left: 4px solid var(--danger);
  color: #7F1D1D;
}

.row { display: block; }
.col { width: 100%; margin-bottom: 16px; }

@media (min-width: 768px) { 
  .row { display: flex; gap: 20px; } 
  .col { flex: 1; margin-bottom: 0; }
}

.counter-badge {
  background: var(--danger);
  color: white;
  border-radius: 50%;
  padding: 2px 8px;
  font-size: 12px;
  margin-left: 4px;
}

.urgence-badge {
  display: inline-block;
  padding: 6px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
}

.urgence-faible { background: #D1FAE5; color: #065F46; }
.urgence-moyenne { background: #FED7AA; color: #92400E; }
.urgence-elevee { background: #FBBF24; color: #78350F; }
.urgence-critique { background: #FCA5A5; color: #7F1D1D; }

textarea.form-control {
  resize: vertical;
  min-height: 80px;
}

.table-wrap { 
  overflow-x: auto; 
  margin: 20px 0; 
  border: 1px solid var(--border); 
  border-radius: 12px; 
  background: white;
  box-shadow: var(--shadow);
}

.table { 
  width: 100%; 
  min-width: 500px; 
  border-collapse: separate;
  border-spacing: 0;
  font-size: 13px;
}

.table th, .table td { 
  border-bottom: 1px solid var(--light-gray);
  border-right: 1px solid var(--light-gray);
  padding: 12px 10px; 
  text-align: left;
}

.table th:last-child, .table td:last-child { border-right: none; }
.table tr:last-child td { border-bottom: none; }

.table th { 
  background: linear-gradient(to bottom, #F8FAFC, #F1F5F9);
  font-weight: 600; 
  color: var(--dark);
  text-transform: uppercase;
  font-size: 12px;
}

.table tr:hover { background: rgba(59, 130, 246, 0.05); }

.table input { 
  width: 100%; 
  padding: 6px 8px; 
  border: 1px solid #D1D5DB; 
  border-radius: 6px; 
  font-size: 13px;
  text-align: center;
}

.chaudiere-1 {
  border-left: 4px solid var(--info);
  background: linear-gradient(to right, rgba(6, 182, 212, 0.05), white);
}

.chaudiere-2 {
  border-left: 4px solid var(--warning);
  background: linear-gradient(to right, rgba(245, 158, 11, 0.05), white);
}
</style>
</head>
<body>
<div class="app">
  <div class="header">
    <h1>🔥 MSE - Rapport d'Intervention</h1>
    <p>Maintenance des Systèmes Énergétiques</p>
  </div>
  
  <div class="nav">
    <button class="nav-btn active" onclick="showTab(0)">📋 Info</button>
    <button class="nav-btn" onclick="showTab(1)">📊 Mesures</button>
    <button class="nav-btn" onclick="showTab(2)">🔧 Contrôles</button>
    <button class="nav-btn" onclick="showTab(3)">📝 Observations</button>
    <button class="nav-btn" onclick="showTab(4)">📸 Photos</button>
    <button class="nav-btn" onclick="showTab(5)">💾 Sauvés <span class="counter-badge"><?= count($reports) ?></span></button>
  </div>

  <div class="content">
    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>">
      <?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>

    <form id="mainForm" method="POST" action="">
      <input type="hidden" name="action" id="formAction">
      <input type="hidden" name="mesures" id="mesuresData">
      <input type="hidden" name="controles" id="controlesData">
      <input type="hidden" name="releves" id="relevesData">
      <input type="hidden" name="photos" id="photosData">

      <div class="tab active" id="tab-0">
        <div class="alert alert-info">
          <span>💡 Remplissez les informations générales pour commencer votre rapport d'intervention</span>
        </div>
        
        <div class="card">
          <h3>Informations Générales</h3>
          <div class="row">
            <div class="col">
              <div class="form-group">
                <label>N° Rapport</label>
                <input type="text" class="form-control" name="reportNum" id="reportNum" placeholder="Ex: R-2024-001">
              </div>
            </div>
            <div class="col">
              <div class="form-group">
                <label>Date d'intervention</label>
                <input type="date" class="form-control" name="reportDate" id="reportDate" value="<?= date('Y-m-d') ?>">
              </div>
            </div>
          </div>
        </div>

        <div class="email-section">
          <h3 style="border: none; padding: 0; margin-bottom: 15px;">📧 Envoi par Email</h3>
          <div class="form-group">
            <label>Email du destinataire</label>
            <input type="email" class="form-control" name="email_destinataire" id="email_destinataire" placeholder="exemple@domaine.fr">
            <small style="color: #6B7280; margin-top: 5px; display: block;">Le rapport sera envoyé automatiquement à cette adresse après sauvegarde</small>
          </div>
        </div>

        <div class="card">
          <h3>Adresse d'Intervention</h3>
          <div class="form-group">
            <textarea class="form-control" name="address" id="address" rows="3" placeholder="Entrez l'adresse complète du site d'intervention..."></textarea>
          </div>
        </div>

        <div class="row">
          <div class="col">
            <div class="card chaudiere-1">
              <h3>Chaudière N°1</h3>
              <div class="form-group">
                <label>Marque</label>
                <input type="text" class="form-control" name="c1_marque" id="c1_marque" placeholder="Ex: Viessmann">
              </div>
              <div class="form-group">
                <label>Modèle</label>
                <input type="text" class="form-control" name="c1_modele" id="c1_modele" placeholder="Ex: Vitodens 200-W">
              </div>
              <div class="form-group">
                <label>N° de Série</label>
                <input type="text" class="form-control" name="c1_serie" id="c1_serie" placeholder="Ex: 7823456789">
              </div>
            </div>
          </div>
          <div class="col">
            <div class="card chaudiere-2">
              <h3>Chaudière N°2</h3>
              <div class="form-group">
                <label>Marque</label>
                <input type="text" class="form-control" name="c2_marque" id="c2_marque" placeholder="Ex: Viessmann">
              </div>
              <div class="form-group">
                <label>Modèle</label>
                <input type="text" class="form-control" name="c2_modele" id="c2_modele" placeholder="Ex: Vitodens 200-W">
              </div>
              <div class="form-group">
                <label>N° de Série</label>
                <input type="text" class="form-control" name="c2_serie" id="c2_serie" placeholder="Ex: 7823456789">
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="tab" id="tab-1">
        <h3 style="color: var(--dark); margin-bottom: 20px;">📈 Mesures MIN/MAX</h3>
        <div class="table-wrap">
          <table class="table">
            <tr>
              <th>Paramètre</th>
              <th style="background: linear-gradient(135deg, #DBEAFE, #BFDBFE);">C1 MIN</th>
              <th style="background: linear-gradient(135deg, #DBEAFE, #BFDBFE);">C1 MAX</th>
              <th style="background: linear-gradient(135deg, #FED7AA, #FBBF24);">C2 MIN</th>
              <th style="background: linear-gradient(135deg, #FED7AA, #FBBF24);">C2 MAX</th>
            </tr>
            <?php foreach ($mesures as $i => $mesure): ?>
            <tr style="<?= $i % 2 === 0 ? 'background: rgba(243, 244, 246, 0.5);' : '' ?>">
              <td><b><?= htmlspecialchars($mesure) ?></b></td>
              <td><input type="text" data-mesure="<?= $i ?>" data-field="c1min" placeholder="-"></td>
              <td><input type="text" data-mesure="<?= $i ?>" data-field="c1max" placeholder="-"></td>
              <td><input type="text" data-mesure="<?= $i ?>" data-field="c2min" placeholder="-"></td>
              <td><input type="text" data-mesure="<?= $i ?>" data-field="c2max" placeholder="-"></td>
            </tr>
            <?php endforeach; ?>
          </table>
        </div>
      </div>

      <div class="tab" id="tab-2">
        <h3 style="color: var(--dark); margin-bottom: 20px;">🔧 Contrôles et Relevés</h3>
        <div class="row">
          <div class="col">
            <div class="card">
              <h3>Contrôles Réalisés</h3>
              <?php foreach ($controles as $i => $controle): ?>
              <div class="form-group">
                <label><?= htmlspecialchars($controle) ?></label>
                <input type="text" class="form-control" data-controle="<?= $i ?>" placeholder="Entrez la valeur...">
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="col">
            <div class="card">
              <h3>Relevés de Mesures</h3>
              <?php foreach ($releves as $i => $releve): ?>
              <div class="form-group">
                <label><?= htmlspecialchars($releve) ?></label>
                <?php if ($releve === 'Permutation pompes'): ?>
                <select class="form-control" data-releve="<?= $i ?>">
                  <option value="">-- Sélectionner --</option>
                  <option value="Oui">✅ Oui</option>
                  <option value="Non">❌ Non</option>
                </select>
                <?php else: ?>
                <input type="text" class="form-control" data-releve="<?= $i ?>" placeholder="Entrez la valeur...">
                <?php endif; ?>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>

      <div class="tab" id="tab-3">
        <h3 style="color: var(--dark); margin-bottom: 20px;">📋 Observations et Recommandations</h3>
        <div class="card">
          <div class="form-group">
            <label>État général de l'installation</label>
            <textarea class="form-control" name="etat_general" id="etat_general" rows="3" placeholder="Décrivez l'état général..."></textarea>
          </div>
          <div class="form-group">
            <label>Anomalies constatées</label>
            <textarea class="form-control" name="anomalies" id="anomalies" rows="3" placeholder="Listez les anomalies détectées..."></textarea>
          </div>
          <div class="form-group">
            <label>Travaux réalisés</label>
            <textarea class="form-control" name="travaux_realises" id="travaux_realises" rows="3" placeholder="Décrivez les travaux effectués..."></textarea>
          </div>
          <div class="form-group">
            <label>Recommandations</label>
            <textarea class="form-control" name="recommandations" id="recommandations" rows="3" placeholder="Vos recommandations..."></textarea>
          </div>
          <div class="row">
            <div class="col">
              <div class="form-group">
                <label>Niveau d'urgence</label>
                <select class="form-control" name="urgence" id="urgence">
                  <option value="">-- Sélectionner --</option>
                  <option value="faible">🟢 Faible</option>
                  <option value="moyenne">🟡 Moyenne</option>
                  <option value="elevee">🟠 Élevée</option>
                  <option value="critique">🔴 Critique</option>
                </select>
              </div>
            </div>
            <div class="col">
              <div class="form-group">
                <label>Intervenant</label>
                <input type="text" class="form-control" name="intervenant" id="intervenant" placeholder="Nom et prénom">
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="tab" id="tab-4">
        <h3 style="color: var(--dark); margin-bottom: 20px;">📸 Photos (Max 3)</h3>
        <div class="card">
          <div class="alert alert-info">
            <span>💡 Ajoutez jusqu'à 3 photos pour documenter votre intervention. Les photos seront incluses dans le PDF et l'email.</span>
          </div>
          
          <div class="photos-container" id="photosContainer">
            <!-- Les photos seront ajoutées dynamiquement ici -->
            <div class="photo-item photo-upload" onclick="document.getElementById('photoInput').click()">
              <div class="photo-upload-icon">📷</div>
              <div style="font-size: 14px; color: var(--gray);">Ajouter une photo</div>
              <div class="photo-count" id="photoCount">0/3 photos</div>
            </div>
          </div>
          
          <input type="file" id="photoInput" accept="image/*" style="display: none;" onchange="handlePhotoUpload(event)">
        </div>
      </div>

      <div class="tab" id="tab-5">
        <h3 style="color: var(--dark); margin-bottom: 20px;">📂 Rapports Sauvegardés (<?= $totalReports ?>)</h3>
        <div id="saved-list">
          <?php if (empty($reports)): ?>
          <div class="alert alert-info">
            <span>Aucun rapport sauvegardé pour le moment</span>
          </div>
          <?php else: ?>
            <?php foreach ($reports as $report): ?>
            <div class="saved-card">
              <h4>📄 Rapport <?= htmlspecialchars($report['report_num'] ?: 'Sans numéro') ?></h4>
              <p>Date: <?= htmlspecialchars($report['report_date'] ?: 'Non spécifiée') ?></p>
              <p>Intervenant: <?= htmlspecialchars($report['intervenant'] ?: 'Non spécifié') ?></p>
              <?php if ($report['photo_count'] > 0): ?>
              <p>📸 Photos: <?= $report['photo_count'] ?></p>
              <?php endif; ?>
              <?php if ($report['email_destinataire']): ?>
              <p>📧 Email: <?= htmlspecialchars($report['email_destinataire']) ?>
                <?php if ($report['email_sent']): ?>
                  <span class="email-status email-sent">✓ Envoyé</span>
                <?php else: ?>
                  <span class="email-status email-not-sent">✗ Non envoyé</span>
                <?php endif; ?>
              </p>
              <?php endif; ?>
              <?php if ($report['urgence']): ?>
              <p>Urgence: <span class="urgence-badge urgence-<?= $report['urgence'] ?>">
                <?php 
                  $icons = ['faible' => '🟢', 'moyenne' => '🟡', 'elevee' => '🟠', 'critique' => '🔴'];
                  echo $icons[$report['urgence']] . ' ' . strtoupper($report['urgence']);
                ?>
              </span></p>
              <?php endif; ?>
              <div class="saved-actions">
                <button type="button" class="btn-load" onclick="loadReport(<?= $report['id'] ?>)">Charger</button>
                <?php if ($report['email_destinataire'] && !$report['email_sent']): ?>
                <button type="button" class="btn-email" onclick="sendEmail(<?= $report['id'] ?>)">📧 Envoyer</button>
                <?php endif; ?>
                <form method="POST" style="flex: 1; margin: 0;" onsubmit="return confirm('⚠️ Êtes-vous sûr de vouloir supprimer ce rapport ?')">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $report['id'] ?>">
                  <button type="submit" class="btn-delete" style="width: 100%;">Supprimer</button>
                </form>
              </div>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </form>
  </div>

  <div class="bottom-bar">
    <button class="btn btn-save" onclick="saveReport()">💾 Sauvegarder</button>
    <button class="btn btn-pdf" onclick="generatePDF()">📄 PDF</button>
  </div>
</div>

<script>
const mesures = <?= json_encode($mesures) ?>;
const controles = <?= json_encode($controles) ?>;
const releves = <?= json_encode($releves) ?>;
const savedReports = <?= json_encode($reports) ?>;

// Gestion des photos
let photos = [];
const MAX_PHOTOS = 3;

function showTab(index) {
  const tabs = document.querySelectorAll('.tab');
  const btns = document.querySelectorAll('.nav-btn');
  tabs.forEach((tab, i) => {
    tab.classList.toggle('active', i === index);
    btns[i].classList.toggle('active', i === index);
  });
}

function handlePhotoUpload(event) {
  const file = event.target.files[0];
  if (!file) return;
  
  if (photos.length >= MAX_PHOTOS) {
    alert(`Maximum ${MAX_PHOTOS} photos autorisées`);
    return;
  }
  
  // Vérifier la taille (max 5MB)
  if (file.size > 5 * 1024 * 1024) {
    alert('La photo est trop grande (max 5MB)');
    return;
  }
  
  const reader = new FileReader();
  reader.onload = function(e) {
    const photo = {
      data: e.target.result,
      name: file.name,
      type: file.type,
      size: file.size,
      description: ''
    };
    
    photos.push(photo);
    renderPhotos();
  };
  reader.readAsDataURL(file);
  
  // Reset input
  event.target.value = '';
}

function renderPhotos() {
  const container = document.getElementById('photosContainer');
  container.innerHTML = '';
  
  // Afficher les photos existantes
  photos.forEach((photo, index) => {
    const div = document.createElement('div');
    div.className = 'photo-item';
    div.innerHTML = `
      <button class="remove-photo" onclick="removePhoto(${index})" type="button">×</button>
      <img src="${photo.data}" alt="Photo ${index + 1}">
      <input type="text" class="form-control" placeholder="Description..." 
             value="${photo.description}" 
             onchange="updatePhotoDescription(${index}, this.value)"
             style="margin-top: 5px; padding: 6px; font-size: 12px;">
    `;
    container.appendChild(div);
  });
  
  // Ajouter le bouton d'upload si moins de 3 photos
  if (photos.length < MAX_PHOTOS) {
    const uploadDiv = document.createElement('div');
    uploadDiv.className = 'photo-item photo-upload';
    uploadDiv.onclick = () => document.getElementById('photoInput').click();
    uploadDiv.innerHTML = `
      <div class="photo-upload-icon">📷</div>
      <div style="font-size: 14px; color: var(--gray);">Ajouter une photo</div>
      <div class="photo-count">${photos.length}/${MAX_PHOTOS} photos</div>
    `;
    container.appendChild(uploadDiv);
  }
  
  // Mettre à jour le compteur
  const counter = document.getElementById('photoCount');
  if (counter) counter.textContent = `${photos.length}/${MAX_PHOTOS} photos`;
}

function removePhoto(index) {
  if (confirm('Supprimer cette photo ?')) {
    photos.splice(index, 1);
    renderPhotos();
  }
}

function updatePhotoDescription(index, description) {
  photos[index].description = description;
}

function collectMesures() {
  const data = {};
  mesures.forEach((mesure, i) => {
    data[mesure] = {
      c1min: document.querySelector(`[data-mesure="${i}"][data-field="c1min"]`).value,
      c1max: document.querySelector(`[data-mesure="${i}"][data-field="c1max"]`).value,
      c2min: document.querySelector(`[data-mesure="${i}"][data-field="c2min"]`).value,
      c2max: document.querySelector(`[data-mesure="${i}"][data-field="c2max"]`).value
    };
  });
  return data;
}

function collectControles() {
  const data = {};
  controles.forEach((controle, i) => {
    data[controle] = document.querySelector(`[data-controle="${i}"]`).value;
  });
  return data;
}

function collectReleves() {
  const data = {};
  releves.forEach((releve, i) => {
    data[releve] = document.querySelector(`[data-releve="${i}"]`).value;
  });
  return data;
}

function saveReport() {
  // Vérifier l'email si fourni
  const email = document.getElementById('email_destinataire').value;
  if (email && !validateEmail(email)) {
    alert('Adresse email invalide');
    return;
  }
  
  document.getElementById('formAction').value = 'save';
  document.getElementById('mesuresData').value = JSON.stringify(collectMesures());
  document.getElementById('controlesData').value = JSON.stringify(collectControles());
  document.getElementById('relevesData').value = JSON.stringify(collectReleves());
  document.getElementById('photosData').value = JSON.stringify(photos);
  document.getElementById('mainForm').submit();
}

function generatePDF() {
  document.getElementById('formAction').value = 'generate_pdf';
  document.getElementById('mesuresData').value = JSON.stringify(collectMesures());
  document.getElementById('controlesData').value = JSON.stringify(collectControles());
  document.getElementById('relevesData').value = JSON.stringify(collectReleves());
  document.getElementById('photosData').value = JSON.stringify(photos);
  document.getElementById('mainForm').submit();
}

function sendEmail(reportId) {
  if (confirm('Envoyer ce rapport par email ?')) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
      <input type="hidden" name="action" value="send_email">
      <input type="hidden" name="report_id" value="${reportId}">
    `;
    document.body.appendChild(form);
    form.submit();
  }
}

function loadReport(id) {
  const report = savedReports.find(r => r.id === id);
  if (!report) return;
  
  document.getElementById('reportNum').value = report.report_num || '';
  document.getElementById('reportDate').value = report.report_date || '';
  document.getElementById('address').value = report.address || '';
  document.getElementById('email_destinataire').value = report.email_destinataire || '';
  document.getElementById('c1_marque').value = report.c1_marque || '';
  document.getElementById('c1_modele').value = report.c1_modele || '';
  document.getElementById('c1_serie').value = report.c1_serie || '';
  document.getElementById('c2_marque').value = report.c2_marque || '';
  document.getElementById('c2_modele').value = report.c2_modele || '';
  document.getElementById('c2_serie').value = report.c2_serie || '';
  document.getElementById('etat_general').value = report.etat_general || '';
  document.getElementById('anomalies').value = report.anomalies || '';
  document.getElementById('travaux_realises').value = report.travaux_realises || '';
  document.getElementById('recommandations').value = report.recommandations || '';
  document.getElementById('urgence').value = report.urgence || '';
  document.getElementById('intervenant').value = report.intervenant || '';
  
  // Charger les mesures
  mesures.forEach((mesure, i) => {
    const m = report.mesures[mesure] || {};
    document.querySelector(`[data-mesure="${i}"][data-field="c1min"]`).value = m.c1min || '';
    document.querySelector(`[data-mesure="${i}"][data-field="c1max"]`).value = m.c1max || '';
    document.querySelector(`[data-mesure="${i}"][data-field="c2min"]`).value = m.c2min || '';
    document.querySelector(`[data-mesure="${i}"][data-field="c2max"]`).value = m.c2max || '';
  });
  
  // Charger les contrôles
  controles.forEach((controle, i) => {
    document.querySelector(`[data-controle="${i}"]`).value = report.controles[controle] || '';
  });
  
  // Charger les relevés
  releves.forEach((releve, i) => {
    document.querySelector(`[data-releve="${i}"]`).value = report.releves[releve] || '';
  });
  
  // Charger les photos
  if (report.photos && report.photos.length > 0) {
    photos = report.photos.map(p => ({
      data: p.photo_data,
      name: p.photo_name,
      type: p.photo_type,
      size: p.photo_size,
      description: p.description || ''
    }));
    renderPhotos();
  }
  
  showTab(0);
  alert('🔥 Rapport chargé avec succès !');
}

function validateEmail(email) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

// Initialiser l'affichage des photos
renderPhotos();
</script>
</body>
</html>
