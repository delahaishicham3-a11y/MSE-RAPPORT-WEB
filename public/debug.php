<?php
// Activer l'affichage des erreurs
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "<!DOCTYPE html><html><head><title>Diagnostic MSE</title>";
echo "<style>body{font-family:Arial;padding:2rem;background:#f5f5f5;}";
echo ".box{background:white;padding:1.5rem;margin:1rem 0;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.1);}";
echo ".success{border-left:4px solid #10b981;background:#d1fae5;}";
echo ".error{border-left:4px solid #ef4444;background:#fee2e2;}";
echo "h2{color:#667eea;margin-top:0;}";
echo "pre{background:#1f2937;color:#f3f4f6;padding:1rem;border-radius:4px;overflow-x:auto;}";
echo "</style></head><body>";

echo "<h1>🔍 Diagnostic MSE Application</h1>";

// Test 1 : Version PHP
echo "<div class='box success'>";
echo "<h2>✅ Version PHP</h2>";
echo "<p>Version : <strong>" . phpversion() . "</strong></p>";
echo "<p>Requis : PHP 8.0+</p>";
echo "</div>";

// Test 2 : Extensions PHP
echo "<div class='box'>";
echo "<h2>📦 Extensions PHP</h2>";
$requiredExtensions = ['pdo', 'pdo_pgsql', 'mbstring', 'json', 'zip'];
foreach ($requiredExtensions as $ext) {
    $loaded = extension_loaded($ext);
    $icon = $loaded ? '✅' : '❌';
    $class = $loaded ? 'success' : 'error';
    echo "<p>$icon <strong>$ext</strong> : " . ($loaded ? 'Chargée' : 'MANQUANTE') . "</p>";
}
echo "</div>";

// Test 3 : Composer autoload
echo "<div class='box'>";
echo "<h2>📚 Autoload Composer</h2>";
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    echo "<p>✅ Fichier trouvé : <code>$autoloadPath</code></p>";
    try {
        require $autoloadPath;
        echo "<p>✅ Autoload chargé avec succès</p>";
    } catch (Exception $e) {
        echo "<p class='error'>❌ Erreur de chargement : " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p class='error'>❌ Fichier non trouvé : <code>$autoloadPath</code></p>";
    echo "<p>Exécutez : <code>composer install</code></p>";
}
echo "</div>";

// Test 4 : Variables d'environnement
echo "<div class='box'>";
echo "<h2>🔐 Variables d'environnement</h2>";
$envVars = ['DATABASE_URL', 'SMTP_HOST', 'SMTP_USER', 'SMTP_PASS', 'MAIL_FROM'];
foreach ($envVars as $var) {
    $value = getenv($var);
    $exists = !empty($value);
    $icon = $exists ? '✅' : '❌';
    echo "<p>$icon <strong>$var</strong> : ";
    if ($exists) {
        // Masquer les valeurs sensibles
        if (in_array($var, ['SMTP_PASS', 'DATABASE_URL'])) {
            echo "****** (configurée)";
        } else {
            echo htmlspecialchars(substr($value, 0, 30)) . "...";
        }
    } else {
        echo "NON CONFIGURÉE";
    }
    echo "</p>";
}
echo "</div>";

// Test 5 : Connexion à la base de données
echo "<div class='box'>";
echo "<h2>🗄️ Base de données PostgreSQL</h2>";
try {
    $dbUrl = getenv('DATABASE_URL');
    if (empty($dbUrl)) {
        echo "<p class='error'>❌ DATABASE_URL non configurée</p>";
    } else {
        $db = new PDO($dbUrl);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $db->query("SELECT version() as version, NOW() as current_time");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<p>✅ Connexion réussie</p>";
        echo "<p>Version : " . htmlspecialchars(explode(' ', $row['version'])[1]) . "</p>";
        echo "<p>Heure serveur : " . htmlspecialchars($row['current_time']) . "</p>";
        
        // Vérifier les tables
        $stmt = $db->query("SELECT tablename FROM pg_tables WHERE schemaname = 'public'");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "<p>Tables trouvées : " . count($tables) . "</p>";
        if (!empty($tables)) {
            echo "<ul>";
            foreach ($tables as $table) {
                echo "<li>" . htmlspecialchars($table) . "</li>";
            }
            echo "</ul>";
        }
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Erreur : " . htmlspecialchars($e->getMessage()) . "</p>";
}
echo "</div>";

// Test 6 : Classes MSE
echo "<div class='box'>";
echo "<h2>🏗️ Classes de l'application</h2>";
$classes = ['MSE\\Database', 'MSE\\Report', 'MSE\\EmailService', 'MSE\\PdfService'];
foreach ($classes as $class) {
    $exists = class_exists($class);
    $icon = $exists ? '✅' : '❌';
    echo "<p>$icon <strong>$class</strong> : " . ($exists ? 'Trouvée' : 'MANQUANTE') . "</p>";
}
echo "</div>";

// Test 7 : Dossiers et permissions
echo "<div class='box'>";
echo "<h2>📁 Dossiers et permissions</h2>";
$dirs = [
    __DIR__ . '/../uploads' => 'Uploads',
    __DIR__ . '/../temp' => 'Temp',
    __DIR__ . '/../vendor' => 'Vendor'
];
foreach ($dirs as $dir => $name) {
    $exists = is_dir($dir);
    $writable = $exists ? is_writable($dir) : false;
    
    if ($exists && $writable) {
        echo "<p>✅ <strong>$name</strong> : Existe et accessible en écriture</p>";
    } elseif ($exists) {
        echo "<p>⚠️ <strong>$name</strong> : Existe mais NON accessible en écriture</p>";
    } else {
        echo "<p>❌ <strong>$name</strong> : N'existe pas</p>";
    }
}
echo "</div>";

// Test 8 : Librairies externes
echo "<div class='box'>";
echo "<h2>📦 Librairies externes</h2>";
$libs = [
    'PHPMailer\\PHPMailer\\PHPMailer' => 'PHPMailer',
    'TCPDF' => 'TCPDF',
    'Dotenv\\Dotenv' => 'PHP Dotenv'
];
foreach ($libs as $class => $name) {
    $exists = class_exists($class);
    $icon = $exists ? '✅' : '❌';
    echo "<p>$icon <strong>$name</strong> : " . ($exists ? 'Installée' : 'MANQUANTE') . "</p>";
}
echo "</div>";

// Test 9 : Informations serveur
echo "<div class='box'>";
echo "<h2>🖥️ Informations serveur</h2>";
echo "<p>Système : " . php_uname() . "</p>";
echo "<p>Document root : " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
echo "<p>Script actuel : " . __FILE__ . "</p>";
echo "<p>PHP SAPI : " . php_sapi_name() . "</p>";
echo "</div>";

// Test 10 : Test rapide des fonctionnalités
echo "<div class='box'>";
echo "<h2>🧪 Test des fonctionnalités</h2>";

// Test Database
try {
    if (class_exists('MSE\\Database')) {
        $db = MSE\Database::getInstance();
        echo "<p>✅ Database::getInstance() fonctionne</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Database : " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test Report
try {
    if (class_exists('MSE\\Report')) {
        $report = new MSE\Report();
        echo "<p>✅ Report peut être instancié</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Report : " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test EmailService
try {
    if (class_exists('MSE\\EmailService')) {
        $email = new MSE\EmailService();
        echo "<p>✅ EmailService peut être instancié</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ EmailService : " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</div>";

echo "<div class='box success'>";
echo "<h2>✅ Diagnostic terminé</h2>";
echo "<p><a href='/' style='color:#667eea;font-weight:bold;'>Retourner à l'application</a></p>";
echo "</div>";

echo "</body></html>";
?>
