<?php
namespace MSE;

use MSE\Database;
use PDO;
use Exception;

class Report {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

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
                'mesures' => json_encode($data['mesures'] ?? []),
                'controles' => json_encode($data['controles'] ?? []),
                'releves' => json_encode($data['releves'] ?? []),
                'email_destinataire' => $data['email_destinataire'] ?? null
            ]);

            $reportId = $stmt->fetchColumn();

            if (!empty($photos)) {
                $this->savePhotos($reportId, $photos);
            }

            $this->db->commit();
            return $reportId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

private function savePhotos($reportId, $photos) {
    // Créer le dossier de stockage
    $uploadDir = __DIR__ . '/../uploads/reports/' . $reportId;
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Modifier la requête SQL
    $sql = "INSERT INTO report_photos (report_id, photo_path, photo_name, photo_type, photo_size, description) 
            VALUES (:report_id, :photo_path, :photo_name, :photo_type, :photo_size, :description)";

    $stmt = $this->db->prepare($sql);

    foreach ($photos as $i => $photo) {
        if (!empty($photo['data'])) {
            // Décoder base64
            $photoData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $photo['data']));
            
            // Générer un nom unique
            $extension = pathinfo($photo['name'], PATHINFO_EXTENSION) ?: 'jpg';
            $filename = uniqid('photo_', true) . '.' . $extension;
            $filepath = $uploadDir . '/' . $filename;
            
            // Sauvegarder sur disque
            if (file_put_contents($filepath, $photoData) === false) {
                throw new Exception("Impossible de sauvegarder la photo");
            }
            
            // Sauvegarder le chemin en base
            $stmt->execute([
                'report_id' => $reportId,
                'photo_path' => 'uploads/reports/' . $reportId . '/' . $filename,
                'photo_name' => $photo['name'],
                'photo_type' => $photo['type'],
                'photo_size' => $photo['size'],
                'description' => $photo['description'] ?? ''
            ]);
        }
    }
}

 public function getPhotos($reportId) {
    $sql = "SELECT id, photo_path, photo_name, photo_type, photo_size, description, created_at 
            FROM report_photos 
            WHERE report_id = :report_id 
            ORDER BY created_at ASC";

    $stmt = $this->db->prepare($sql);
    $stmt->execute(['report_id' => $reportId]);
    $photos = $stmt->fetchAll();
    
    // Charger les données des photos depuis le disque
    foreach ($photos as &$photo) {
        $fullPath = __DIR__ . '/../' . $photo['photo_path'];
        if (file_exists($fullPath)) {
            $photoData = file_get_contents($fullPath);
            $photo['photo_data'] = 'data:' . $photo['photo_type'] . ';base64,' . base64_encode($photoData);
        } else {
            $photo['photo_data'] = null;
        }
    }
    
    return $photos;
}
    
    public function deletePhoto($photoId) {
        $stmt = $this->db->prepare("DELETE FROM report_photos WHERE id = :id");
        return $stmt->execute(['id' => $photoId]);
    }

    public function markEmailSent($reportId) {
        $stmt = $this->db->prepare("UPDATE reports SET email_sent = TRUE, email_sent_at = CURRENT_TIMESTAMP WHERE id = :id");
        return $stmt->execute(['id' => $reportId]);
    }

    public function getAll($limit = 100, $offset = 0) {
        $sql = "SELECT 
            r.*, COUNT(p.id) as photo_count
        FROM reports r
        LEFT JOIN report_photos p ON r.id = p.report_id
        GROUP BY r.id
        ORDER BY r.created_at DESC 
        LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $reports = $stmt->fetchAll();

        foreach ($reports as &$r) {
            $r['mesures'] = json_decode($r['mesures'], true);
            $r['controles'] = json_decode($r['controles'], true);
            $r['releves'] = json_decode($r['releves'], true);
        }

        return $reports;
    }

    public function getById($id) {
        $stmt = $this->db->prepare("SELECT * FROM reports WHERE id = :id");
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
}

