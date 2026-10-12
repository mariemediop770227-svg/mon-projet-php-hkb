<?php
session_start();

require_once '../config/database.php';
require_once '../includes/functions.php';

// Si déjà connecté, rediriger selon le rôle
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: ../admin/index.php');
        exit();
    } elseif ($_SESSION['role'] === 'agent') {
        header('Location: ../agent/index.php');
        exit();
    } elseif ($_SESSION['role'] === 'usager') {
        header('Location: ../usager/index.php');
        exit();
    }
}

$erreur = '';
$succes = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email        = securiser($_POST['email'] ?? '');
    $mot_de_passe = $_POST['mot_de_passe'] ?? '';

    if (empty($email) || empty($mot_de_passe)) {
        $erreur = 'Veuillez remplir tous les champs.';
    } else {
        $conn = getConnexion();

        $stmt = $conn->prepare("
            SELECT u.*, r.nom_role
            FROM utilisateur u
            JOIN role r ON u.id_role = r.id_role
            WHERE u.email = ? AND u.statut = 'actif'
        ");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $resultat = $stmt->get_result();

        if ($resultat->num_rows === 1) {
            $utilisateur = $resultat->fetch_assoc();

            if (password_verify($mot_de_passe, $utilisateur['mot_de_passe'])) {

                // Créer la session
                $_SESSION['id_utilisateur'] = $utilisateur['id_utilisateur'];
                $_SESSION['nom']            = $utilisateur['nom'];
                $_SESSION['prenom']         = $utilisateur['prenom'];
                $_SESSION['email']          = $utilisateur['email'];
                $_SESSION['role']           = $utilisateur['nom_role'];
                $_SESSION['id_role']        = $utilisateur['id_role'];

                // Mettre à jour dernière connexion
                $update = $conn->prepare("
                    UPDATE utilisateur SET derniere_connexion = NOW()
                    WHERE id_utilisateur = ?
                ");
                $update->bind_param('i', $utilisateur['id_utilisateur']);
                $update->execute();
                $update->close();

                $stmt->close();
                $conn->close();

                // Rediriger selon le rôle
                if ($utilisateur['nom_role'] === 'admin') {
                    header('Location: ../admin/index.php');
                } elseif ($utilisateur['nom_role'] === 'agent') {
                    header('Location: ../agent/index.php');
                } else {
                    header('Location: ../usager/index.php');
                }
                exit();

            } else {
                $erreur = 'Email ou mot de passe incorrect.';
            }
        } else {
            $erreur = 'Email ou mot de passe incorrect.';
        }

        $stmt->close();
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Pont HKB</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>

<div class="container">
    <div class="carte-connexion card">

        <div class="entete-connexion">
            <div class="logo-pont">🌉</div>
            <h2>Pont Henri Konan Bédié</h2>
            <p>Système de Gestion du Péage</p>
        </div>

        <div class="card-body p-4">
            <h5 class="text-center mb-4 text-muted">Connexion à votre espace</h5>

            <?php if (!empty($erreur)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-exclamation-triangle-fill"></i> <?= $erreur ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($succes)): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle-fill"></i> <?= $succes ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">

                <div class="mb-3">
                    <label for="email" class="form-label fw-semibold">
                        <i class="bi bi-envelope"></i> Adresse email
                    </label>
                    <input
                        type="email"
                        class="form-control form-control-lg"
                        id="email"
                        name="email"
                        placeholder="exemple@email.com"
                        value="<?= isset($_POST['email']) ? securiser($_POST['email']) : '' ?>"
                        required
                    >
                </div>

                <div class="mb-4">
                    <label for="mot_de_passe" class="form-label fw-semibold">
                        <i class="bi bi-lock"></i> Mot de passe
                    </label>
                    <div class="input-group">
                        <input
                            type="password"
                            class="form-control form-control-lg"
                            id="mot_de_passe"
                            name="mot_de_passe"
                            placeholder="Votre mot de passe"
                            required
                        >
                        <button class="btn btn-outline-secondary" type="button"
                                onclick="toggleMdp()">
                            <i class="bi bi-eye" id="icone-oeil"></i>
                        </button>
                    </div>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-pont btn-lg">
                        <i class="bi bi-box-arrow-in-right"></i> Se connecter
                    </button>
                </div>

            </form>

            <hr>
            <p class="text-center mb-0">
                Pas encore de compte ?
                <a href="register.php" class="text-decoration-none fw-semibold">
                    Créer un compte usager
                </a>
            </p>
        </div>
    </div>

    <div class="text-center mt-3">
        <small class="text-muted">
            <strong>Compte admin :</strong> admin@ponthkb.ci / password
        </small>
    </div>
</div>

<script src="https://cdn.j