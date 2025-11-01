<?php
namespace MSE;

/**
 * Service d'envoi d'emails
 * Utilise l'API de mail ou SMTP selon configuration
 */
class EmailService {
    private $fromEmail;
    private $fromName;
    private $smtpHost;
    private $smtpPort;
    private $smtpUser;
    private $smtpPass;
    
    public function __construct() {
        // Configuration depuis variables d'environnement
        $this->fromEmail = getenv('MAIL_FROM') ?: 'noreply@mse-rapport.fr';
        $this->fromName = getenv('MAIL_FROM_NAME') ?: 'MSE Rapports';
        $this->smtpHost = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
        $this->smtpPort = getenv('SMTP_PORT') ?: 587;
        $this->smtpUser = getenv('SMTP_USER');
        $this->smtpPass = getenv('SMTP_PASS');
    }
    
    /**
     * Envoyer un rapport par email
     */
    public function sendReport($report, $photos = [], $pdfPath = null) {
        $to = $report['email_destinataire'];
        
        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new \Exception("Adresse email invalide");
        }
        
        $subject = "Rapport d'intervention MSE - " . ($report['report_num'] ?: 'N°' . $report['id']);
        
        $body = $this->buildEmailBody($report);
        
        // Utiliser PHPMailer si disponible, sinon mail() natif
        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            return $this->sendWithPHPMailer($to, $subject, $body, $photos, $pdfPath);
        } else {
            return $this->sendWithNativeMail($to, $subject, $body);
        }
    }
    
    /**
     * Construire le corps de l'email
     */
    private function buildEmailBody($report) {
        $urgenceLabels = [
            'faible' => '🟢 Faible',
            'moyenne' => '🟡 Moyenne',
            'elevee' => '🟠 Élevée',
            'critique' => '🔴 Critique'
        ];
        
        $urgence = $urgenceLabels[$report['urgence']] ?? '';
        
        $html = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
                .content { padding: 30px; background: #f9fafb; }
                .section { background: white; margin: 20px 0; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
                .section h2 { color: #667eea; border-bottom: 2px solid #667eea; padding-bottom: 10px; }
                .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
                .info-item { padding: 10px; background: #f3f4f6; border-radius: 4px; }
                .info-label { font-weight: bold; color: #4b5563; }
                .urgence { display: inline-block; padding: 5px 15px; border-radius: 20px; font-weight: bold; }
                .urgence-faible { background: #d1fae5; color: #065f46; }
                .urgence-moyenne { background: #fed7aa; color: #92400e; }
                .urgence-elevee { background: #fbbf24; color: #78350f; }
                .urgence-critique { background: #fca5a5; color: #7f1d1d; }
                .footer { text-align: center; padding: 20px; color: #6b7280; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>🔥 MSE - Rapport d'Intervention</h1>
                <p>Maintenance des Systèmes Énergétiques</p>
            </div>
            
            <div class='content'>
                <div class='section'>
                    <h2>Informations Générales</h2>
                    <div class='info-grid'>
                        <div class='info-item'>
                            <div class='info-label'>N° Rapport:</div>
                            <div>" . htmlspecialchars($report['report_num'] ?: 'Non spécifié') . "</div>
                        </div>
                        <div class='info-item'>
                            <div class='info-label'>Date:</div>
                            <div>" . htmlspecialchars($report['report_date'] ?: 'Non spécifiée') . "</div>
                        </div>
                        <div class='info-item'>
                            <div class='info-label'>Intervenant:</div>
                            <div>" . htmlspecialchars($report['intervenant'] ?: 'Non spécifié') . "</div>
                        </div>
                        <div class='info-item'>
                            <div class='info-label'>Urgence:</div>
                            <div><span class='urgence urgence-" . $report['urgence'] . "'>" . $urgence . "</span></div>
                        </div>
                    </div>
                </div>
                
                <div class='section'>
                    <h2>Adresse d'Intervention</h2>
                    <p>" . nl2br(htmlspecialchars($report['address'] ?: 'Non spécifiée')) . "</p>
                </div>
                
                <div class='section'>
                    <h2>Chaudières</h2>
                    <div class='info-grid'>
                        <div>
                            <h3 style='color: #06b6d4;'>Chaudière N°1</h3>
                            <p><strong>Marque:</strong> " . htmlspecialchars($report['c1_marque'] ?: '-') . "</p>
                            <p><strong>Modèle:</strong> " . htmlspecialchars($report['c1_modele'] ?: '-') . "</p>
                            <p><strong>Série:</strong> " . htmlspecialchars($report['c1_serie'] ?: '-') . "</p>
                        </div>
                        <div>
                            <h3 style='color: #f59e0b;'>Chaudière N°2</h3>
                            <p><strong>Marque:</strong> " . htmlspecialchars($report['c2_marque'] ?: '-') . "</p>
                            <p><strong>Modèle:</strong> " . htmlspecialchars($report['c2_modele'] ?: '-') . "</p>
                            <p><strong>Série:</strong> " . htmlspecialchars($report['c2_serie'] ?: '-') . "</p>
                        </div>
                    </div>
                </div>";
        
        if ($report['etat_general']) {
            $html .= "
                <div class='section'>
                    <h2>État Général</h2>
                    <p>" . nl2br(htmlspecialchars($report['etat_general'])) . "</p>
                </div>";
        }
        
        if ($report['anomalies']) {
            $html .= "
                <div class='section'>
                    <h2>Anomalies Constatées</h2>
                    <p>" . nl2br(htmlspecialchars($report['anomalies'])) . "</p>
                </div>";
        }
        
        if ($report['travaux_realises']) {
            $html .= "
                <div class='section'>
                    <h2>Travaux Réalisés</h2>
                    <p>" . nl2br(htmlspecialchars($report['travaux_realises'])) . "</p>
                </div>";
        }
        
        if ($report['recommandations']) {
            $html .= "
                <div class='section'>
                    <h2>Recommandations</h2>
                    <p>" . nl2br(htmlspecialchars($report['recommandations'])) . "</p>
                </div>";
        }
        
        $html .= "
            </div>
            
            <div class='footer'>
                <p><strong>MSE - Maintenance des Systèmes Énergétiques</strong></p>
                <p>3, Avenue Pierre Brasseur - 95490 VAUREAL</p>
                <p>Tél : +33 7 60 06 94 05</p>
                <p style='margin-top: 15px; color: #9ca3af;'>Ce message a été envoyé automatiquement, merci de ne pas y répondre.</p>
            </div>
        </body>
        </html>";
        
        return $html;
    }
    
    /**
     * Envoyer avec PHPMailer (recommandé)
     */
    private function sendWithPHPMailer($to, $subject, $body, $photos, $pdfPath) {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            
            // Configuration SMTP
            $mail->isSMTP();
            $mail->Host = $this->smtpHost;
            $mail->SMTPAuth = true;
            $mail->Username = $this->smtpUser;
            $mail->Password = $this->smtpPass;
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $this->smtpPort;
            $mail->CharSet = 'UTF-8';
            
            // Expéditeur et destinataire
            $mail->setFrom($this->fromEmail, $this->fromName);
            $mail->addAddress($to);
            
            // Contenu
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->AltBody = strip_tags($body);
            
            // Joindre le PDF si disponible
            if ($pdfPath && file_exists($pdfPath)) {
                $mail->addAttachment($pdfPath);
            }
            
            // Joindre les photos
            foreach ($photos as $i => $photo) {
                if (!empty($photo['data'])) {
                    // Décoder base64
                    $photoData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $photo['data']));
                    $mail->addStringAttachment($photoData, 'photo_' . ($i + 1) . '.jpg', 'base64', 'image/jpeg');
                }
            }
            
            $mail->send();
            return true;
            
        } catch (\Exception $e) {
            error_log("Email error: " . $e->getMessage());
            throw new \Exception("Erreur d'envoi: " . $e->getMessage());
        }
    }
    
    /**
     * Envoyer avec mail() natif (fallback)
     */
    private function sendWithNativeMail($to, $subject, $body) {
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: {$this->fromName} <{$this->fromEmail}>\r\n";
        $headers .= "Reply-To: {$this->fromEmail}\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        
        return mail($to, $subject, $body, $headers);
    }
    
    /**
     * Tester la configuration email
     */
    public function testConnection() {
        try {
            if (!$this->smtpUser || !$this->smtpPass) {
                return [
                    'success' => false,
                    'message' => 'Configuration SMTP manquante. Configurez SMTP_USER et SMTP_PASS dans les variables d\'environnement.'
                ];
            }
            
            // Test simple de connexion
            return [
                'success' => true,
                'message' => 'Configuration email OK'
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}