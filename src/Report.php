<?php
namespace MSE;

require_once __DIR__ . '/../config/database.php';

class Report {
    private $db;
    
    public function __construct() {
        $this->db = \Database::getInstance()->getConnection();
    }
    
    /**
     * Sauvegarder un rapport avec photos
     */
    public function save($data, $photos = []) {
        try {
            $this->db->beginTransaction();
            
            $sql = "INSERT INTO reports (
                report_num, report_date, address,
                c1_marque, c1_modele, c1_serie,
                c2_marque, c2_modele, c2_serie,
                etat_general, anomalies, travaux_realises, recommandations,
                urgence, intervenant, mesures, controles, releves, email_destinataire
            ) VALUES (
                :report_num, :report_date, :address,
                :c1_marque, :c1_modele, :c1_serie,
                :c2_marque, :c2_modele, :c2_serie,
                :etat_general, :anomalies, :travaux_realises, :recommandations,
                :urgence, :intervenant, :mesures, :controles, :releves, :email_destinataire
            ) RETURNING id";
            
            $stmt = $this->db->prepare($sql);
            
            $mesuresJson = json_encode($data['mesures'] ?? []);
            $controlesJson = json_encode($data['controles'] ?? []);
            $relevesJson = json_encode($data['releves'] ?? []);
            
            $stmt->execute([
                'report_num' => $data['reportNum'] ?? null,
                'report_date' => $data['reportDate'] ?? null,
                'address' => $data['address'] ?? null,
                'c1_marque' => $data['c1_marque'] ?? null,
                'c1_modele' => $data['c1_modele'] ?? null,
                'c1_serie' => $data['c1_serie'] ?? null,
                'c2_marque' => $data['c2_marque'] ?? null,
                'c2_modele' => $data['c2_modele'] ?? null,
                'c2_serie' => $data['c2_serie'] ?? null,
                'etat_general' => $data['etat_general'] ?? null,
                'anomalies' => $data['anomalies'] ?? null,
                'travaux_realises' => $data['travaux_realises'] ?? null,
                'recommandations' => $data['recommandations'] ?? null,
                'urgence' => $data['urgence'] ?? null,
                'intervenant' => $data['intervenant'] ?? null,
                'mesures' => $mesuresJson,
                'controles' => $controlesJson,
                'releves' => $relevesJson,
                'email_destinataire' => $data['email_destinataire'] ?? null
            ]);
            
            $result = $stmt->fetch();
            $reportId = $result['id'];
            
            // Sauvegarder les photos
            if (!empty($photos)) {
                $this->savePhotos($reportId, $photos);
            }
            
            $this->db->commit();
            return $reportId;
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Sauvegarder les photos d'un rapport
     */
    private function savePhotos($reportId, $photos) {
        $sql = "INSERT INTO report_photos (report_id, photo_data, photo_name, photo_type, photo_size, description) 
                VALUES (:report_id, :photo_data, :photo_name, :photo_type, :photo_size, :description)";
        
        $stmt = $this->db->prepare($sql);
        
        foreach ($photos as $photo) {
            $stmt->execute([
                'report_id' => $reportId,
                'photo_data' => $photo['data'],
                'photo_name' => $photo['name'],
                'photo_type' => $photo['type'],
                'photo_size' => $photo['size'],
                'description' => $photo['description'] ?? ''
            ]);
        }
    }
    
    /**
     * Récupérer les photos d'un rapport
     */
    public function getPhotos($reportId) {
        $sql = "SELECT id, photo_data, photo_name, photo_type, photo_size, description, created_at 
                FROM report_photos 
                WHERE report_id = :report_id 
                ORDER BY created_at ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['report_id' => $reportId]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Supprimer une photo
     */
    public function deletePhoto($photoId) {
        $sql = "DELETE FROM report_photos WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $photoId]);
    }
    
    /**
     * Marquer un email comme envoyé
     */
    public function markEmailSent($reportId) {
        $sql = "UPDATE reports SET email_sent = TRUE, email_sent_at = CURRENT_TIMESTAMP WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $reportId]);
    }
    
    /**
     * Récupérer tous les rapports avec nombre de photos
     */
    public function getAll($limit = 100, $offset = 0) {
        $sql = "SELECT 
            r.id, r.report_num, r.report_date, r.address,
            r.c1_marque, r.c1_modele, r.c1_serie,
            r.c2_marque, r.c2_modele, r.c2_serie,
            r.etat_general, r.anomalies, r.travaux_realises, r.recommandations,
            r.urgence, r.intervenant,
            r.mesures::text, r.controles::text, r.releves::text,
            r.email_destinataire, r.email_sent, r.email_sent_at,
            r.created_at, r.updated_at,
            COUNT(p.id) as photo_count
        FROM reports r
        LEFT JOIN report_photos p ON r.id = p.report_id
        GROUP BY r.id
        ORDER BY r.created_at DESC 
        LIMIT :limit OFFSET :offset";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        
        $reports = $stmt->fetchAll();
        
        // Décoder les JSON
        foreach ($reports as &$report) {
            $report['mesures'] = json_decode($report['mesures'], true);
            $report['controles'] = json_decode($report['controles'], true);
            $report['releves'] = json_decode($report['releves'], true);
        }
        
        return $reports;
    }
    
    /**
     * Récupérer un rapport par ID avec ses photos
     */
    public function getById($id) {
        $sql = "SELECT 
            r.id, r.report_num, r.report_date, r.address,
            r.c1_marque, r.c1_modele, r.c1_serie,
            r.c2_marque, r.c2_modele, r.c2_serie,
            r.etat_general, r.anomalies, r.travaux_realises, r.recommandations,
            r.urgence, r.intervenant,
            r.mesures::text, r.controles::text, r.releves::text,
            r.email_destinataire, r.email_sent, r.email_sent_at,
            r.created_at, r.updated_at
        FROM reports r
        WHERE r.id = :id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        
        $report = $stmt->fetch();
        
        if ($report) {
            $report['mesures'] = json_decode($report['mesures'], true);
            $report['controles'] = json_decode($report['controles'], true);
            $report['releves'] = json_decode($report['releves'], true);
            $report['photos'] = $this->getPhotos($id);
        }
        
        return $report;
    }
    
    /**
     * Mettre à jour un rapport
     */
    public function update($id, $data) {
        $sql = "UPDATE reports SET
            report_num = :report_num,
            report_date = :report_date,
            address = :address,
            c1_marque = :c1_marque,
            c1_modele = :c1_modele,
            c1_serie = :c1_serie,
            c2_marque = :c2_marque,
            c2_modele = :c2_modele,
            c2_serie = :c2_serie,
            etat_general = :etat_general,
            anomalies = :anomalies,
            travaux_realises = :travaux_realises,
            recommandations = :recommandations,
            urgence = :urgence,
            intervenant = :intervenant,
            mesures = :mesures,
            controles = :controles,
            releves = :releves,
            email_destinataire = :email_destinataire,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        
        $mesuresJson = json_encode($data['mesures'] ?? []);
        $controlesJson = json_encode($data['controles'] ?? []);
        $relevesJson = json_encode($data['releves'] ?? []);
        
        return $stmt->execute([
            'id' => $id,
            'report_num' => $data['reportNum'] ?? null,
            'report_date' => $data['reportDate'] ?? null,
            'address' => $data['address'] ?? null,
            'c1_marque' => $data['c1_marque'] ?? null,
            'c1_modele' => $data['c1_modele'] ?? null,
            'c1_serie' => $data['c1_serie'] ?? null,
            'c2_marque' => $data['c2_marque'] ?? null,
            'c2_modele' => $data['c2_modele'] ?? null,
            'c2_serie' => $data['c2_serie'] ?? null,
            'etat_general' => $data['etat_general'] ?? null,
            'anomalies' => $data['anomalies'] ?? null,
            'travaux_realises' => $data['travaux_realises'] ?? null,
            'recommandations' => $data['recommandations'] ?? null,
            'urgence' => $data['urgence'] ?? null,
            'intervenant' => $data['intervenant'] ?? null,
            'mesures' => $mesuresJson,
            'controles' => $controlesJson,
            'releves' => $relevesJson,
            'email_destinataire' => $data['email_destinataire'] ?? null
        ]);
    }
    
    /**
     * Supprimer un rapport et ses photos
     */
    public function delete($id) {
        $sql = "DELETE FROM reports WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }
    
    /**
     * Rechercher des rapports
     */
    public function search($query, $limit = 50) {
        $sql = "SELECT 
            r.id, r.report_num, r.report_date, r.address,
            r.c1_marque, r.c1_modele, r.c1_serie,
            r.c2_marque, r.c2_modele, r.c2_serie,
            r.etat_general, r.anomalies, r.travaux_realises, r.recommandations,
            r.urgence, r.intervenant,
            r.mesures::text, r.controles::text, r.releves::text,
            r.email_destinataire, r.email_sent,
            r.created_at, r.updated_at,
            COUNT(p.id) as photo_count
        FROM reports r
        LEFT JOIN report_photos p ON r.id = p.report_id
        WHERE 
            r.report_num ILIKE :query OR
            r.address ILIKE :query OR
            r.intervenant ILIKE :query OR
            r.c1_marque ILIKE :query OR
            r.c2_marque ILIKE :query
        GROUP BY r.id
        ORDER BY r.created_at DESC 
        LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':query', "%$query%", \PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        
        $reports = $stmt->fetchAll();
        
        foreach ($reports as &$report) {
            $report['mesures'] = json_decode($report['mesures'], true);
            $report['controles'] = json_decode($report['controles'], true);
            $report['releves'] = json_decode($report['releves'], true);
        }
        
        return $reports;
    }
    
    /**
     * Compter le nombre total de rapports
     */
    public function count() {
        $sql = "SELECT COUNT(*) as total FROM reports";
        $stmt = $this->db->query($sql);
        $result = $stmt->fetch();
        return $result['total'];
    }
}
