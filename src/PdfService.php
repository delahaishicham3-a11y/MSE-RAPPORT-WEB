<?php
namespace MSE;

use TCPDF;

class PdfService {
    private $report;
    private $photos;
    
    public function __construct($report, $photos = []) {
        $this->report = $report;
        $this->photos = $photos;
    }
    
    public function generate($outputPath = null) {
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
        
        // Métadonnées
        $pdf->SetCreator('MSE - Rapports d\'Intervention');
        $pdf->SetAuthor('MSE');
        $pdf->SetTitle('Rapport ' . ($this->report['report_num'] ?: 'N°' . $this->report['id']));
        
        // Marges
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 15);
        
        // Page
        $pdf->AddPage();
        
        // En-tête avec logo
        $this->addHeader($pdf);
        
        // Informations générales
        $this->addGeneralInfo($pdf);
        
        // Détails des chaudières
        $this->addBoilerInfo($pdf);
        
        // État et observations
        $this->addObservations($pdf);
        
        // Mesures et contrôles
        $this->addMeasurements($pdf);
        
        // Photos
        if (!empty($this->photos)) {
            $this->addPhotos($pdf);
        }
        
        // Pied de page
        $this->addFooter($pdf);
        
        // Générer le fichier
        if ($outputPath) {
            $pdf->Output($outputPath, 'F');
            return $outputPath;
        } else {
            return $pdf->Output('rapport_' . $this->report['id'] . '.pdf', 'S');
        }
    }
    
    private function addHeader($pdf) {
        $html = '
        <style>
            .header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 20px;
                text-align: center;
                border-radius: 8px;
            }
            h1 { font-size: 24px; margin: 0; }
            .subtitle { font-size: 14px; margin-top: 5px; }
        </style>
        <div class="header">
            <h1>🔥 MSE - RAPPORT D\'INTERVENTION</h1>
            <div class="subtitle">Maintenance des Systèmes Énergétiques</div>
        </div>
        ';
        
        $pdf->writeHTML($html, true, false, true, false, '');
        $pdf->Ln(5);
    }
    
    private function addGeneralInfo($pdf) {
        $urgenceColors = [
            'faible' => '#d1fae5',
            'moyenne' => '#fed7aa',
            'elevee' => '#fbbf24',
            'critique' => '#fca5a5'
        ];
        
        $urgenceLabels = [
            'faible' => '🟢 Faible',
            'moyenne' => '🟡 Moyenne',
            'elevee' => '🟠 Élevée',
            'critique' => '🔴 Critique'
        ];
        
        $urgence = $urgenceLabels[$this->report['urgence']] ?? 'Non spécifié';
        $bgColor = $urgenceColors[$this->report['urgence']] ?? '#e5e7eb';
        
        $html = '
        <style>
            .section { 
                background: #f9fafb; 
                padding: 15px; 
                margin: 10px 0; 
                border-radius: 8px;
                border-left: 4px solid #667eea;
            }
            .section-title { 
                color: #667eea; 
                font-size: 16px; 
                font-weight: bold; 
                margin-bottom: 10px;
                border-bottom: 2px solid #667eea;
                padding-bottom: 5px;
            }
            .info-grid { 
                display: table; 
                width: 100%; 
                margin-top: 10px;
            }
            .info-row { 
                display: table-row; 
            }
            .info-label { 
                display: table-cell; 
                width: 40%; 
                font-weight: bold; 
                color: #4b5563;
                padding: 8px 5px;
                background: #e5e7eb;
            }
            .info-value { 
                display: table-cell; 
                width: 60%; 
                padding: 8px 5px;
                background: white;
            }
            .urgence {
                display: inline-block;
                padding: 5px 15px;
                border-radius: 20px;
                background: ' . $bgColor . ';
                font-weight: bold;
            }
        </style>
        
        <div class="section">
            <div class="section-title">INFORMATIONS GÉNÉRALES</div>
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-label">N° Rapport:</div>
                    <div class="info-value">' . htmlspecialchars($this->report['report_num'] ?: 'Non spécifié') . '</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Date:</div>
                    <div class="info-value">' . htmlspecialchars($this->report['report_date'] ?: 'Non spécifiée') . '</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Intervenant:</div>
                    <div class="info-value">' . htmlspecialchars($this->report['intervenant'] ?: 'Non spécifié') . '</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Urgence:</div>
                    <div class="info-value"><span class="urgence">' . $urgence . '</span></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Adresse:</div>
                    <div class="info-value">' . nl2br(htmlspecialchars($this->report['address'] ?: 'Non spécifiée')) . '</div>
                </div>
            </div>
        </div>
        ';
        
        $pdf->writeHTML($html, true, false, true, false, '');
    }
    
    private function addBoilerInfo($pdf) {
        $html = '
        <div class="section">
            <div class="section-title">CHAUDIÈRES</div>
            
            <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
                <tr>
                    <th style="background: #06b6d4; color: white; padding: 10px; text-align: left;">Chaudière N°1</th>
                    <th style="background: #f59e0b; color: white; padding: 10px; text-align: left;">Chaudière N°2</th>
                </tr>
                <tr>
                    <td style="padding: 8px; background: #e0f2fe; border: 1px solid #bae6fd;">
                        <strong>Marque:</strong> ' . htmlspecialchars($this->report['c1_marque'] ?: '-') . '<br>
                        <strong>Modèle:</strong> ' . htmlspecialchars($this->report['c1_modele'] ?: '-') . '<br>
                        <strong>Série:</strong> ' . htmlspecialchars($this->report['c1_serie'] ?: '-') . '
                    </td>
                    <td style="padding: 8px; background: #fef3c7; border: 1px solid #fde68a;">
                        <strong>Marque:</strong> ' . htmlspecialchars($this->report['c2_marque'] ?: '-') . '<br>
                        <strong>Modèle:</strong> ' . htmlspecialchars($this->report['c2_modele'] ?: '-') . '<br>
                        <strong>Série:</strong> ' . htmlspecialchars($this->report['c2_serie'] ?: '-') . '
                    </td>
                </tr>
            </table>
        </div>
        ';
        
        $pdf->writeHTML($html, true, false, true, false, '');
    }
    
    private function addObservations($pdf) {
        $sections = [
            ['title' => 'ÉTAT GÉNÉRAL', 'content' => $this->report['etat_general']],
            ['title' => 'ANOMALIES CONSTATÉES', 'content' => $this->report['anomalies']],
            ['title' => 'TRAVAUX RÉALISÉS', 'content' => $this->report['travaux_realises']],
            ['title' => 'RECOMMANDATIONS', 'content' => $this->report['recommandations']]
        ];
        
        foreach ($sections as $section) {
            if (!empty($section['content'])) {
                $html = '
                <div class="section">
                    <div class="section-title">' . $section['title'] . '</div>
                    <p style="line-height: 1.6; margin: 10px 0;">' . 
                        nl2br(htmlspecialchars($section['content'])) . 
                    '</p>
                </div>
                ';
                $pdf->writeHTML($html, true, false, true, false, '');
            }
        }
    }
    
    private function addMeasurements($pdf) {
        $mesures = $this->report['mesures'] ?? [];
        $controles = $this->report['controles'] ?? [];
        $releves = $this->report['releves'] ?? [];
        
        if (!empty($mesures) || !empty($controles) || !empty($releves)) {
            $html = '<div class="section"><div class="section-title">MESURES ET CONTRÔLES</div>';
            
            if (!empty($mesures)) {
                $html .= '<h3 style="color: #667eea;">Mesures</h3><ul>';
                foreach ($mesures as $mesure) {
                    $html .= '<li>' . htmlspecialchars($mesure) . '</li>';
                }
                $html .= '</ul>';
            }
            
            if (!empty($controles)) {
                $html .= '<h3 style="color: #667eea;">Contrôles</h3><ul>';
                foreach ($controles as $controle) {
                    $html .= '<li>' . htmlspecialchars($controle) . '</li>';
                }
                $html .= '</ul>';
            }
            
            if (!empty($releves)) {
                $html .= '<h3 style="color: #667eea;">Relevés</h3><ul>';
                foreach ($releves as $releve) {
                    $html .= '<li>' . htmlspecialchars($releve) . '</li>';
                }
                $html .= '</ul>';
            }
            
            $html .= '</div>';
            $pdf->writeHTML($html, true, false, true, false, '');
        }
    }
    
    private function addPhotos($pdf) {
        $pdf->AddPage();
        $html = '<div class="section-title">PHOTOGRAPHIES</div>';
        $pdf->writeHTML($html, true, false, true, false, '');
        $pdf->Ln(5);
        
        $x = 15;
        $y = $pdf->GetY();
        $maxWidth = 90;
        $maxHeight = 70;
        $col = 0;
        
        foreach ($this->photos as $i => $photo) {
            if (!empty($photo['photo_data'])) {
                // Décoder base64
                $imgData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $photo['photo_data']));
                
                // Créer fichier temporaire
                $tmpFile = tempnam(sys_get_temp_dir(), 'pdf_img_');
                file_put_contents($tmpFile, $imgData);
                
                // Ajouter l'image
                try {
                    $pdf->Image($tmpFile, $x, $y, $maxWidth, 0, '', '', '', true, 150);
                    
                    // Description
                    if (!empty($photo['description'])) {
                        $pdf->SetXY($x, $y + $maxHeight + 2);
                        $pdf->SetFont('helvetica', 'I', 9);
                        $pdf->MultiCell($maxWidth, 5, 'Photo ' . ($i + 1) . ': ' . $photo['description'], 0, 'L');
                    }
                } catch (\Exception $e) {
                    error_log("Erreur ajout photo PDF: " . $e->getMessage());
                }
                
                unlink($tmpFile);
                
                // Positionnement colonne suivante
                $col++;
                if ($col % 2 == 0) {
                    $y += $maxHeight + 15;
                    $x = 15;
                    
                    if ($y > 250) {
                        $pdf->AddPage();
                        $y = 15;
                    }
                } else {
                    $x = 110;
                }
            }
        }
    }
    
    private function addFooter($pdf) {
        $pdf->SetY(-30);
        $html = '
        <div style="border-top: 2px solid #667eea; padding-top: 10px; text-align: center; color: #6b7280; font-size: 10px;">
            <p><strong>MSE - Maintenance des Systèmes Énergétiques</strong></p>
            <p>3, Avenue Pierre Brasseur - 95490 VAUREAL</p>
            <p>Tél : +33 7 60 06 94 05</p>
        </div>
        ';
        $pdf->writeHTML($html, true, false, true, false, '');
    }
}
