<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MSE\Database;
use MSE\Report;
use MSE\EmailService;
use MSE\PdfService;
use Dotenv\Dotenv;

// Configuration
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Gérer les requêtes OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Récupérer la méthode et le chemin
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace('/api', '', $path);
$segments = explode('/', trim($path, '/'));

// Helper pour réponses JSON
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function jsonError($message, $status = 400) {
    jsonResponse(['error' => $message], $status);
}

// Parser le body JSON
function getJsonInput() {
    $input = file_get_contents('php://input');
    return json_decode($input, true) ?? [];
}

try {
    // Routes
    switch ($method) {
        case 'GET':
            if ($segments[0] === 'reports') {
                if (isset($segments[1]) && is_numeric($segments[1])) {
                    // GET /reports/{id}
                    $report = new Report();
                    $data = $report->getById((int)$segments[1]);
                    
                    if (!$data) {
                        jsonError('Rapport non trouvé', 404);
                    }
                    
                    jsonResponse($data);
                } else {
                    // GET /reports?limit=50&offset=0
                    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
                    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
                    
                    $report = new Report();
                    $data = $report->getAll($limit, $offset);
                    
                    jsonResponse([
                        'data' => $data,
                        'meta' => [
                            'limit' => $limit,
                            'offset' => $offset,
                            'count' => count($data)
                        ]
                    ]);
                }
            } elseif ($segments[0] === 'reports' && isset($segments[1]) && $segments[2] === 'photos') {
                // GET /reports/{id}/photos
                $report = new Report();
                $photos = $report->getPhotos((int)$segments[1]);
                jsonResponse(['photos' => $photos]);
            } elseif ($segments[0] === 'health') {
                // GET /health - Health check
                jsonResponse([
                    'status' => 'ok',
                    'timestamp' => date('c'),
                    'database' => 'connected'
                ]);
            } else {
                jsonError('Route non trouvée', 404);
            }
            break;
            
        case 'POST':
            if ($segments[0] === 'reports') {
                // POST /reports - Créer un rapport
                $input = getJsonInput();
                
                // Validation basique
                if (empty($input['reportDate'])) {
                    jsonError('La date est obligatoire');
                }
                
                if (empty($input['address'])) {
                    jsonError('L\'adresse est obligatoire');
                }
                
                // Extraire les photos
                $photos = [];
                if (!empty($input['photos'])) {
                    foreach ($input['photos'] as $photo) {
                        $photos[] = [
                            'data' => $photo['data'] ?? '',
                            'name' => $photo['name'] ?? 'photo.jpg',
                            'type' => $photo['type'] ?? 'image/jpeg',
                            'size' => $photo['size'] ?? 0,
                            'description' => $photo['description'] ?? ''
                        ];
                    }
                }
                
                // Sauvegarder
                $report = new Report();
                $reportId = $report->save($input, $photos);
                
                jsonResponse([
                    'success' => true,
                    'message' => 'Rapport créé avec succès',
                    'id' => $reportId
                ], 201);
                
            } elseif ($segments[0] === 'reports' && isset($segments[1]) && $segments[2] === 'send') {
                // POST /reports/{id}/send - Envoyer par email
                $report = new Report();
                $data = $report->getById((int)$segments[1]);
                
                if (!$data) {
                    jsonError('Rapport non trouvé', 404);
                }
                
                if (empty($data['email_destinataire'])) {
                    jsonError('Aucun email destinataire configuré');
                }
                
                // Générer le PDF
                $pdfService = new PdfService($data, $data['photos'] ?? []);
                $pdfPath = __DIR__ . '/../temp/report_' . $data['id'] . '.pdf';
                
                if (!is_dir(__DIR__ . '/../temp')) {
                    mkdir(__DIR__ . '/../temp', 0755, true);
                }
                
                $pdfService->generate($pdfPath);
                
                // Envoyer l'email
                $emailService = new EmailService();
                $emailService->sendReport($data, $data['photos'] ?? [], $pdfPath);
                
                // Marquer comme envoyé
                $report->markEmailSent($data['id']);
                
                // Supprimer le PDF temporaire
                if (file_exists($pdfPath)) {
                    unlink($pdfPath);
                }
                
                jsonResponse([
                    'success' => true,
                    'message' => 'Email envoyé avec succès à ' . $data['email_destinataire']
                ]);
                
            } elseif ($segments[0] === 'reports' && isset($segments[1]) && $segments[2] === 'pdf') {
                // POST /reports/{id}/pdf - Générer et télécharger le PDF
                $report = new Report();
                $data = $report->getById((int)$segments[1]);
                
                if (!$data) {
                    jsonError('Rapport non trouvé', 404);
                }
                
                $pdfService = new PdfService($data, $data['photos'] ?? []);
                $pdfContent = $pdfService->generate();
                
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="rapport_' . $data['id'] . '.pdf"');
                echo $pdfContent;
                exit;
                
            } else {
                jsonError('Route non trouvée', 404);
            }
            break;
            
        case 'PUT':
            if ($segments[0] === 'reports' && isset($segments[1])) {
                // PUT /reports/{id} - Mettre à jour un rapport
                $input = getJsonInput();
                
                // TODO: Implémenter la mise à jour
                jsonError('Mise à jour non implémentée', 501);
            } else {
                jsonError('Route non trouvée', 404);
            }
            break;
            
        case 'DELETE':
            if ($segments[0] === 'reports' && isset($segments[1]) && $segments[2] === 'photos' && isset($segments[3])) {
                // DELETE /reports/{id}/photos/{photoId}
                $report = new Report();
                $success = $report->deletePhoto((int)$segments[3]);
                
                if ($success) {
                    jsonResponse(['success' => true, 'message' => 'Photo supprimée']);
                } else {
                    jsonError('Impossible de supprimer la photo', 500);
                }
            } else {
                jsonError('Route non trouvée', 404);
            }
            break;
            
        default:
            jsonError('Méthode non autorisée', 405);
    }
    
} catch (\Exception $e) {
    error_log('API Error: ' . $e->getMessage());
    
    jsonError(
        getenv('APP_ENV') === 'development' 
            ? $e->getMessage() 
            : 'Une erreur est survenue',
        500
    );
}
