<?php
// ============================================================
// CONFIGURATION DE LA BASE DE DONNÉES
// Projet : Automatisation Gestion Pont HKB
// ============================================================

// Paramètres de connexion
define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // utilisateur par défaut WampServer
define('DB_PASS', '');           // mot de passe vide par défaut WampServer
define('DB_NAME', 'pont_bkh');
define('DB_CHARSET', 'utf8mb4');

// ============================================================
// CONNEXION À LA BASE DE DONNÉES
// ============================================================
function getConnexion() {
    $connexion = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    // Vérifier la connexion
    if ($connexion->connect_error) {
        die("
            <div style='
                font-family: Arial, sans-serif;
                background: #f8d7da;
                color: #721c24;
                border: 1px solid #f5c6cb;
                padding: 20px;
                margin: 20px;
                border-radius: 8px;
            '>
                <h3>❌ Erreur de connexion à la base de données</h3>
                <p><strong>Détail :</strong> " . $connexion->connect_error . "</p>
                <p>Vérifiez que WampServer est bien démarré (icône verte).</p>
            </div>
        ");
    }

    // Définir l'encodage
    $connexion->set_charset(DB_CHARSET);

    return $connexion;
}

// ============================================================
// CONSTANTES GLOBALES DU PROJET
// ============================================================

// URL de base du projet
define('BASE_URL', 'http://localhost/pont_bkh/');

// Chemin absolu vers la racine du projet
define('ROOT_PATH', 'C:/wamp64/www/pont_bkh/');

// Dossiers d'upload
define('UPLOAD_PLAQUES',     ROOT_PATH . 'uploads/plaques/');
define('UPLOAD_CONDUCTEURS', ROOT_PATH . 'uploads/conducteurs/');
define('UPLOAD_VEHICULES',   ROOT_PATH . 'uploads/vehicules/');

// Nom du projet
define('NOM_PROJET', 'Pont HKB - Système de Péage');

// Version
define('VERSION', '1.0.0');
?>