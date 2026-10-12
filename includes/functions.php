<?php
// ============================================================
// FONCTIONS UTILITAIRES GLOBALES - PONT HKB
// ============================================================

// Sécuriser les données entrantes
function securiser($donnee) {
    $donnee = trim($donnee);
    $donnee = stripslashes($donnee);
    $donnee = htmlspecialchars($donnee, ENT_QUOTES, 'UTF-8');
    return $donnee;
}

// Vérifier si l'utilisateur est connecté
function estConnecte() {
    return isset($_SESSION['id_utilisateur'])
        && !empty($_SESSION['id_utilisateur']);
}

// Vérifier le rôle
function estAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}
function estAgent() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'agent';
}
function estUsager() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'usager';
}

// Protéger les pages
function requiertConnexion() {
    if (!estConnecte()) {
        header('Location: ' . BASE_URL . 'auth/login.php');
        exit();
    }
}
function requiertAdmin() {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        header('Location: ' . BASE_URL . 'auth/login.php');
        exit();
    }
}
function requiertAgent() {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'agent') {
        header('Location: ' . BASE_URL . 'auth/login.php');
        exit();
    }
}
function requiertUsager() {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'usager') {
        header('Location: ' . BASE_URL . 'auth/login.php');
        exit();
    }
}

// Formater un montant en FCFA
function formatMontant($montant) {
    return number_format($montant, 0, ',', ' ') . ' FCFA';
}

// Formater une date complète
function formatDate($date) {
    if (empty($date)) return '-';
    return date('d/m/Y à H:i', strtotime($date));
}

// Formater une date courte
function formatDateCourte($date) {
    if (empty($date)) return '-';
    return date('d/m/Y', strtotime($date));
}

// Générer un numéro de badge unique
function genererNumeroBadge() {
    return 'HKB-' . strtoupper(substr(md5(uniqid()), 0, 4))
         . '-' . rand(1000, 9999);
}

// Afficher une alerte Bootstrap
function alerte($message, $type = 'success') {
    $icones = [
        'success' => '✅',
        'danger'  => '❌',
        'warning' => '⚠️',
        'info'    => 'ℹ️'
    ];
    $icone = $icones[$type] ?? 'ℹ️';
    return "
        <div class='alert alert-{$type} alert-dismissible fade show'>
            {$icone} {$message}
            <button type='button' class='btn-close'
                    data-bs-dismiss='alert'></button>
        </div>
    ";
}

// Couleur Bootstrap selon statut
function couleurStatut($statut) {
    $couleurs = [
        'actif'       => 'success',
        'inactif'     => 'secondary',
        'suspendu'    => 'danger',
        'ouvert'      => 'success',
        'ferme'       => 'danger',
        'maintenance' => 'warning',
        'valide'      => 'success',
        'annule'      => 'danger',
        'litigieux'   => 'warning',
        'effectue'    => 'success',
        'echoue'      => 'danger',
        'en_attente'  => 'warning',
        'expire'      => 'secondary',
        'bloque'      => 'danger',
        'traitee'     => 'success',
        'erreur'      => 'danger',
        'termine'     => 'secondary',
        'resilie'     => 'danger',
    ];
    return $couleurs[$statut] ?? 'secondary';
}

// Vérifier token CSRF
function genererTokenCSRF() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifierTokenCSRF($token) {
    if (!isset($_SESSION['csrf_token'])
        || !hash_equals($_SESSION['csrf_token'], $token)) {
        die("<div style='font-family:Arial; color:red; padding:20px'>
                ❌ Requête invalide (token CSRF incorrect).
                <a href='javascript:history.back()'>Retour</a>
             </div>");
    }
}

// Vérifier force du mot de passe
function verifierMotDePasse($mdp) {
    $erreurs = [];
    if (strlen($mdp) < 6) {
        $erreurs[] = 'Au moins 6 caractères';
    }
    return $erreurs;
}

// Logger une action (journal d'activité)
function logAction($conn, $id_utilisateur, $action, $details = '') {
    // Table optionnelle — on ignore si elle n'existe pas
    $table_exists = $conn->query("
        SHOW TABLES LIKE 'journal_activite'
    ");
    if ($table_exists && $table_exists->num_rows > 0) {
        $ip   = $_SERVER['REMOTE_ADDR'] ?? '';
        $stmt = $conn->prepare("
            INSERT INTO journal_activite
                (id_utilisateur, action, details, ip_adresse)
            VALUES (?, ?, ?, ?)
        ");
        if ($stmt) {
            $stmt->bind_param('isss',
                $id_utilisateur, $action, $details, $ip);
            $stmt->execute();
            $stmt->close();
        }
    }
}

// Paginer des résultats
function paginer($total, $par_page, $page_actuelle) {
    $nb_pages = ceil($total / $par_page);
    if ($nb_pages <= 1) return '';

    $html = '<nav><ul class="pagination pagination-sm justify-content-center">';

    // Précédent
    $html .= '<li class="page-item '
           . ($page_actuelle <= 1 ? 'disabled' : '') . '">';
    $html .= '<a class="page-link" href="?page='
           . ($page_actuelle - 1) . '">«</a></li>';

    // Pages
    for ($i = 1; $i <= $nb_pages; $i++) {
        $html .= '<li class="page-item '
               . ($i == $page_actuelle ? 'active' : '') . '">';
        $html .= '<a class="page-link" href="?page=' . $i . '">'
               . $i . '</a></li>';
    }

    // Suivant
    $html .= '<li class="page-item '
           . ($page_actuelle >= $nb_pages ? 'disabled' : '') . '">';
    $html .= '<a class="page-link" href="?page='
           . ($page_actuelle + 1) . '">»</a></li>';

    $html .= '</ul></nav>';
    return $html;
}
?>