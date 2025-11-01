<?php
/**
 * Générateur de PDF pour les rapports MSE
 * Version optimisée avec support PostgreSQL
 */

require_once __DIR__ . '/../vendor/autoload.php';

use MSE\Report;

// Vérifier si TCPDF est disponible
if (!class_exists('TCPDF')) {
    die("Erreur: TCPDF n'est pas installé. Exécutez: composer require tecnickcom/tcpdf");
}

// Récupérer les données depuis POST ou GET
$data = [
    'reportNum' => $_POST['reportNum'] ?? $_GET['reportNum'] ?? '',
    'reportDate' => $_POST['reportDate'] ?? $_GET['reportDate'] ?? date('Y-m-d'),
    'address' => $_POST['address'] ?? $_GET['address'] ?? '',
    'c1_marque' => $_POST['c1_marque'] ?? $_GET['c1_marque'] ?? '',
    'c1_modele' => $_POST['c1_modele'] ?? $_GET['c1_modele'] ?? '',
    'c1_serie' => $_POST['c1_serie'] ?? $_GET['c1_serie'] ?? '',
    'c2_marque' => $_POST['c2_marque'] ?? $_GET['c2_marque'] ?? '',
    'c2_modele' => $_POST['c2_modele'] ?? $_GET['c2_modele'] ?? '',
    'c2_serie' => $_POST['c2_serie'] ?? $_GET['c2_serie'] ?? '',
    'etat_general' => $_POST['etat_general'] ?? $_GET['etat_general'] ?? '',
    'anomalies' => $_POST['anomalies'] ?? $_GET['anomalies'] ?? '',
    'travaux_realises' => $_POST['travaux_realises'] ?? $_GET['travaux_realises'] ?? '',
    'recommandations' => $_POST['recommandations'] ?? $_GET['recommandations'] ?? '',
    'urgence' => $_POST['urgence'] ?? $_GET['urgence'] ?? '',
    'intervenant' => $_POST['intervenant'] ?? $_GET['intervenant'] ?? '',
    'mesures' => json_decode($_POST['mesures'] ?? $_GET['mesures'] ?? '{}', true),
    'controles' => json_decode($_POST['controles'] ?? $_GET['controles'] ?? '{}', true),
    'releves' => json_decode($_POST['releves'] ?? $_GET['releves'] ?? '{}', true),
];

// Si un ID de rapport est fourni, charger depuis la base de données
if (isset($_GET['report_id'])) {
    try {
        $reportModel = new Report();
        $reportData = $reportModel->getById($_GET['report_id']);
        if ($reportData) {
            $data = array_merge($data, $reportData);
        }
    } catch (Exception $e) {
        error_log("Erreur chargement rapport: " . $e->getMessage());
    }
}

// Classe PDF personnalisée
class MSEPDF extends TCPDF {
    private $reportData;
    
    public function setReportData($data) {
        $this->reportData = $data;
    }
    
    public function Header() {
        // Dégradé d'en-tête
        $this->SetFillColor(102, 126, 234);
        $this->Rect(0, 0, 210, 35, 'F');
        
        // Formes décoratives
        $this->SetFillColor(118, 75, 162);
        $points = [0, 0, 50, 0, 0, 35];
        $this->Polygon($points, 'F');
        $points = [210, 0, 160, 0, 210, 35];
        $this->Polygon($points, 'F');
        
        // Logo MSE
        $this->SetFillColor(255, 255, 255);
        $this->Circle(30, 17.5, 10, 0, 360, 'F');
        $this->SetTextColor(237, 134, 91);
        $this->SetFont('helvetica', 'B', 14);
        $this->SetXY(20, 12);
        $this->Cell(20, 10, 'MSE', 0, 0, 'C');
        
        // Titre
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('helvetica', 'B', 16);
        $this->SetXY(0, 10);
        $this->Cell(0, 10, 'RAPPORT D\'INTERVENTION', 0, 0, 'C');
        
        // Sous-titre
        $this->SetFont('helvetica', '', 10);
        $this->SetXY(0, 17);
        $this->Cell(0, 10, utf8_decode('Maintenance des Systèmes Énergétiques'), 0, 0, 'C');
        
        // Informations du rapport
        $this->SetFont('helvetica', '', 9);
        $this->SetXY(0, 24);
        $reportInfo = sprintf(
            utf8_decode('N° %s | Intervenant : %s | Date: %s'),
            $this->reportData['reportNum'] ?: utf8_decode('Non spécifié'),
            $this->reportData['intervenant'] ?: utf8_decode('Non spécifié'),
            $this->reportData['reportDate'] ?: date('Y-m-d')
        );
        $this->Cell(0, 10, $reportInfo, 0, 0, 'C');
    }
    
    public function Footer() {
        $this->SetY(-20);
        
        // Ligne de séparation
        $this->SetDrawColor(243, 244, 246);
        $this->SetLineWidth(0.5);
        $this->Line(20, 277, 190, 277);
        
        // Texte du footer
        $this->SetTextColor(156, 163, 175);
        $this->SetFont('helvetica', '', 8);
        
        // Gauche
        $this->SetXY(20, 280);
        $this->Cell(0, 10, 'MSE', 0, 0, 'L');
        
        // Centre
        $this->SetXY(0, 280);
        $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'C');
        
        // Droite
        $this->SetXY(0, 280);
        $this->Cell(190, 10, date('d/m/Y'), 0, 0, 'R');
        
        // Contact info
        $this->SetFont('helvetica', '', 7);
        $this->SetXY(0, 285);
        $this->Cell(0, 10, utf8_decode('Adresse Siège Social : 3, Avenue Pierre Brasseur - 95490 VAUREAL   |   Tél : +33 7 60 06 94 05'), 0, 0, 'C');
    }
}

// Créer le PDF
$pdf = new MSEPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->setReportData($data);

// Métadonnées
$pdf->SetCreator('MSE Rapport System');
$pdf->SetAuthor($data['intervenant'] ?: 'MSE');
$pdf->SetTitle('Rapport MSE - ' . ($data['reportNum'] ?: 'Sans numéro'));
$pdf->SetSubject('Rapport d\'intervention - Maintenance des Systèmes Énergétiques');
$pdf->SetKeywords('MSE, maintenance, chaudière, intervention');

// Marges
$pdf->SetMargins(20, 45, 20);
$pdf->SetHeaderMargin(0);
$pdf->SetFooterMargin(25);
$pdf->SetAutoPageBreak(TRUE, 25);

// Ajouter une page
$pdf->AddPage();

// SECTION ADRESSE
if ($data['address']) {
    $pdf->SetTextColor(31, 41, 55);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetX(28);
    $pdf->MultiCell(157, 4, utf8_decode($data['anomalies']), 0, 'L');
    $pdf->Ln(2);
}

// Travaux réalisés
if ($data['travaux_realises']) {
    $pdf->SetFillColor(239, 246, 255);
    $pdf->RoundedRect(20, $pdf->GetY(), 170, 26, 3, '1111', 'F');
    
    $pdf->SetFillColor(59, 130, 246);
    $pdf->Rect(20, $pdf->GetY(), 4, 26, 'F');
    
    $pdf->SetTextColor(59, 130, 246);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetX(35);
    $pdf->Cell(0, 7, utf8_decode('Travaux réalisés :'), 0, 1);
    
    $pdf->SetTextColor(31, 41, 55);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetX(28);
    $pdf->MultiCell(157, 4, utf8_decode($data['travaux_realises']), 0, 'L');
    $pdf->Ln(2);
}

// Recommandations
if ($data['recommandations']) {
    $pdf->SetFillColor(240, 253, 244);
    $pdf->RoundedRect(20, $pdf->GetY(), 170, 26, 3, '1111', 'F');
    
    $pdf->SetFillColor(16, 185, 129);
    $pdf->Rect(20, $pdf->GetY(), 4, 26, 'F');
    
    $pdf->SetTextColor(16, 185, 129);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetX(35);
    $pdf->Cell(0, 7, 'Recommandations:', 0, 1);
    
    $pdf->SetTextColor(31, 41, 55);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetX(28);
    $pdf->MultiCell(157, 4, utf8_decode($data['recommandations']), 0, 'L');
    $pdf->Ln(2);
}

// Badge d'urgence
if ($data['urgence']) {
    $pdf->Ln(2);
    
    $urgenceConfig = [
        'faible' => ['color' => [16, 185, 129], 'label' => 'FAIBLE'],
        'moyenne' => ['color' => [245, 158, 11], 'label' => 'MOYENNE'],
        'elevee' => ['color' => [251, 146, 60], 'label' => utf8_decode('ÉLEVÉE')],
        'critique' => ['color' => [239, 68, 68], 'label' => 'CRITIQUE']
    ];
    
    $config = $urgenceConfig[$data['urgence']] ?? $urgenceConfig['moyenne'];
    
    $pdf->SetFillColor($config['color'][0], $config['color'][1], $config['color'][2]);
    $pdf->RoundedRect(20, $pdf->GetY(), 80, 12, 6, '1111', 'F');
    
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetXY(20, $pdf->GetY());
    $pdf->Cell(80, 12, 'URGENCE: ' . $config['label'], 0, 0, 'C');
}

// Générer le nom de fichier
$reportNumber = $data['reportNum'] ?: 'AUTO_' . date('Ymd_His');
$fileName = 'Rapport_MSE_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $reportNumber) . '_' . date('Y-m-d') . '.pdf';

// Output PDF
$pdf->Output($fileName, 'D');
exit;SetFillColor(219, 234, 254);
    $pdf->RoundedRect(20, $pdf->GetY(), 170, 30, 3, '1111', 'F');
    
    $pdf->SetTextColor(59, 130, 246);
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 8, utf8_decode('ADRESSE D\'INTERVENTION'), 0, 1, 'L');
    
    $pdf->SetTextColor(31, 41, 55);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->MultiCell(165, 5, utf8_decode($data['address']), 0, 'L');
    $pdf->Ln(10);
}

// SECTION CHAUDIÈRES
$pdf->SetTextColor(59, 130, 246);
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 8, utf8_decode('CHAUDIÈRES'), 0, 1, 'L');
$pdf->Ln(2);

// Tableau des chaudières
$html = '<table border="1" cellpadding="5" style="border-collapse: collapse;">
    <thead>
        <tr style="background-color: #667eea; color: white; font-weight: bold;">
            <th width="20%">' . utf8_decode('Chaudière') . '</th>
            <th width="27%">Marque</th>
            <th width="27%">' . utf8_decode('Modèle') . '</th>
            <th width="26%">' . utf8_decode('N° Série') . '</th>
        </tr>
    </thead>
    <tbody>';

$chaudieres = [
    [utf8_decode('Chaudière N°1'), $data['c1_marque'], $data['c1_modele'], $data['c1_serie']],
    [utf8_decode('Chaudière N°2'), $data['c2_marque'], $data['c2_modele'], $data['c2_serie']]
];

foreach ($chaudieres as $i => $chaud) {
    $bg = $i % 2 === 0 ? '#F3F4F6' : '#FFFFFF';
    $html .= sprintf(
        '<tr style="background-color: %s;">
            <td><strong>%s</strong></td>
            <td>%s</td>
            <td>%s</td>
            <td>%s</td>
        </tr>',
        $bg,
        $chaud[0],
        htmlspecialchars(utf8_decode($chaud[1] ?: '-')),
        htmlspecialchars(utf8_decode($chaud[2] ?: '-')),
        htmlspecialchars(utf8_decode($chaud[3] ?: '-'))
    );
}

$html .= '</tbody></table>';
$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Ln(10);

// SECTION MESURES
$pdf->SetTextColor(59, 130, 246);
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 8, 'MESURES MIN/MAX', 0, 1, 'L');
$pdf->Ln(2);

if (!empty($data['mesures'])) {
    $html = '<table border="1" cellpadding="4" style="border-collapse: collapse; font-size: 9px;">
        <thead>
            <tr style="background-color: #667eea; color: white; font-weight: bold;">
                <th width="25%">' . utf8_decode('Paramètre') . '</th>
                <th width="18.75%">C1 MIN</th>
                <th width="18.75%">C1 MAX</th>
                <th width="18.75%">C2 MIN</th>
                <th width="18.75%">C2 MAX</th>
            </tr>
        </thead>
        <tbody>';
    
    $i = 0;
    foreach ($data['mesures'] as $param => $values) {
        $bg = $i % 2 === 0 ? '#F3F4F6' : '#FFFFFF';
        $html .= sprintf(
            '<tr style="background-color: %s;">
                <td><strong>%s</strong></td>
                <td style="text-align: center;">%s</td>
                <td style="text-align: center;">%s</td>
                <td style="text-align: center;">%s</td>
                <td style="text-align: center;">%s</td>
            </tr>',
            $bg,
            htmlspecialchars(utf8_decode($param)),
            htmlspecialchars(utf8_decode($values['c1min'] ?: '-')),
            htmlspecialchars(utf8_decode($values['c1max'] ?: '-')),
            htmlspecialchars(utf8_decode($values['c2min'] ?: '-')),
            htmlspecialchars(utf8_decode($values['c2max'] ?: '-'))
        );
        $i++;
    }
    
    $html .= '</tbody></table>';
    $pdf->writeHTML($html, true, false, true, false, '');
}
$pdf->Ln(10);

// SECTION CONTRÔLES ET RELEVÉS
$pdf->SetTextColor(59, 130, 246);
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 8, utf8_decode('CONTRÔLES ET RELEVÉS'), 0, 1, 'L');
$pdf->Ln(2);

// Créer deux colonnes
$leftX = 20;
$rightX = 110;
$colWidth = 80;
$startY = $pdf->GetY();

// Colonne gauche - Contrôles
$pdf->SetXY($leftX, $startY);
$pdf->SetFillColor(243, 244, 246);
$pdf->RoundedRect($leftX, $startY, $colWidth, 80, 3, '1111', 'F');

$pdf->SetFillColor(59, 130, 246);
$pdf->Rect($leftX, $startY + 5, $colWidth, 8, 'F');
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetXY($leftX, $startY + 5);
$pdf->Cell($colWidth, 8, utf8_decode('Contrôles réalisés'), 0, 0, 'L');

$pdf->SetTextColor(31, 41, 55);
$pdf->SetFont('helvetica', '', 9);
$y = $startY + 15;
foreach ($data['controles'] as $ctrl => $val) {
    if ($val) {
        $pdf->SetXY($leftX + 3, $y);
        $pdf->Cell(5, 5, chr(149), 0, 0);
        $pdf->SetXY($leftX + 7, $y);
        $pdf->Cell($colWidth - 10, 5, utf8_decode($ctrl . ': ' . $val), 0, 1);
        $y += 5;
    }
}

// Colonne droite - Relevés
$pdf->SetXY($rightX, $startY);
$pdf->SetFillColor(243, 244, 246);
$pdf->RoundedRect($rightX, $startY, $colWidth, 80, 3, '1111', 'F');

$pdf->SetFillColor(245, 158, 11);
$pdf->Rect($rightX, $startY + 5, $colWidth, 8, 'F');
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetXY($rightX, $startY + 5);
$pdf->Cell($colWidth, 8, utf8_decode('Relevés de mesures'), 0, 0, 'L');

$pdf->SetTextColor(31, 41, 55);
$pdf->SetFont('helvetica', '', 9);
$y = $startY + 15;
foreach ($data['releves'] as $rel => $val) {
    if ($val) {
        $pdf->SetXY($rightX + 3, $y);
        $pdf->Cell(5, 5, chr(149), 0, 0);
        $pdf->SetXY($rightX + 7, $y);
        $pdf->Cell($colWidth - 10, 5, utf8_decode($rel . ': ' . $val), 0, 1);
        $y += 5;
    }
}

$pdf->SetY($startY + 85);

// SECTION OBSERVATIONS
$pdf->SetTextColor(59, 130, 246);
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 8, 'OBSERVATIONS ET RECOMMANDATIONS', 0, 1, 'L');
$pdf->Ln(2);

// État général
if ($data['etat_general']) {
    $pdf->SetFillColor(243, 244, 246);
    $pdf->RoundedRect(20, $pdf->GetY(), 170, 26, 3, '1111', 'F');
    
    $pdf->SetFillColor(169, 169, 169);
    $pdf->Rect(20, $pdf->GetY(), 4, 26, 'F');
    
    $pdf->SetTextColor(59, 130, 246);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetX(35);
    $pdf->Cell(0, 7, utf8_decode('État général:'), 0, 1);
    
    $pdf->SetTextColor(31, 41, 55);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetX(28);
    $pdf->MultiCell(157, 4, utf8_decode($data['etat_general']), 0, 'L');
    $pdf->Ln(2);
}

// Anomalies
if ($data['anomalies']) {
    $pdf->SetFillColor(254, 242, 242);
    $pdf->RoundedRect(20, $pdf->GetY(), 170, 26, 3, '1111', 'F');
    
    $pdf->SetFillColor(239, 68, 68);
    $pdf->Rect(20, $pdf->GetY(), 4, 26, 'F');
    
    $pdf->SetTextColor(239, 68, 68);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetX(35);
    $pdf->Cell(0, 7, utf8_decode('Anomalies constatées :'), 0, 1);
    
    $pdf->