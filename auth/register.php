<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (estConnecte()) {
    header('Location: ../usager/index.php');
    exit();
}

$erreur = '';
$succes = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom            = securiser($_POST['nom'] ?? '');
    $prenom         = securiser($_POST['prenom'] ?? '');
    $email          = securiser($_POST['email'] ?? '');
    $telephone      = securiser($_POST['telephone'] ?? '');
    $mot_de_passe   = $_POST['mot_de_passe'] ?? '';
    $confirmation   = $_POST['confirmation'] ?? '';

    // Validations
    if (empty($nom) || empty($prenom) || empty($email) || empty($mot_de_passe)) {
        $erreur = 'Veuillez remplir tous les champs obligatoires.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreur = 'Adresse email invalide.';
    } elseif (strlen($mot_de_passe) < 6) {
        $erreur = 'Le mot de passe doit contenir au moins 6 caractères.';
    } elseif ($mot_de_passe !== $confirmation) {
        $erreur = 'Les mots de passe ne correspondent pas.';
    } else {
        $conn = getConnexion();

        // Vérifier si l'email existe déjà
        $check = $conn->prepare("SELECT id_utilisateur FROM utilisateur WHERE email = ?");
        $check->bind_param('s', $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $erreur = 'Cette adresse email est déjà utilisée.';
        } else {
            // Hasher le mot de passe
            $hash = password_hash($mot_de_passe, PASSWORD_BCRYPT);

            // Insérer l'usager (id_role = 3 pour usager)
            $stmt = $conn->prepare("
                INSERT INTO utilisateur (id_role, nom, prenom, email, mot_de_passe, telephone, statut)
                VALUES (3, ?, ?, ?, ?, ?, 'actif')
            ");
            $stmt->bind_param('sssss', $nom, $prenom, $email, $hash, $telephone);

            if ($stmt->execute()) {
                $succes = 'Compte créé avec succès ! Vous pouvez maintenant vous connecter.';
            } else {
                $erreur = 'Erreur lors de la création du compte. Réessayez.';
            }
            $stmt->close();
        }
        $check->close();
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer un compte - Pont HKB</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>

<div class="container">
    <div class="card carte-connexion" style="max-width:520px">

        <div class="entete-connexion">
            <div class="logo-pont">🌉</div>
            <h2>Pont Henri Konan Bédié</h2>
            <p>Créer un compte usager</p>
        </div>

        <div class="card-body p-4">

            <?php if (!empty($erreur)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-exclamation-triangle-fill"></i> <?= $erreur ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($succes)): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle-fill"></i> <?= $succes ?>
                    <a href="login.php" class="btn btn-success btn-sm ms-2">Se connecter</a>
                </div>
            <?php endif; ?>

            <form method="POST" action="">

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Nom <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nom"
                            value="<?= isset($_POST['nom']) ? securiser($_POST['nom']) : '' ?>"
                            placeholder="KOUASSI" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Prénom <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="prenom"
                            value="<?= isset($_POST['prenom']) ? securiser($_POST['prenom']) : '' ?>"
                            placeholder="Jean" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">
                        <i class="bi bi-envelope"></i> Email <span class="text-danger">*</span>
                    </label>
                    <input type="email" class="form-control" name="email"
                        value="<?= isset($_POST['email']) ? securiser($_POST['email']) : '' ?>"
                        placeholder="jean@email.com" required>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">
                        <i class="bi bi-phone"></i> Téléphone
                    </label>
                    <input type="tel" class="form-control" name="telephone"
                        value="<?= isset($_POST['telephone']) ? securiser($_POST['telephone']) : '' ?>"
                        placeholder="07 00 00 00 00">
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">
                        <i class="bi bi-lock"></i> Mot de passe <span class="text-danger">*</span>
                    </label>
                    <input type="password" class="form-control" name="mot_de_passe"
                        placeholder="Minimum 6 caractères" required>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold">
                        <i class="bi bi-lock-fill"></i> Confirmer le mot de passe <span class="text-danger">*</span>
                    </label>
                    <input type="password" class="form-control" name="confirmation"
                        placeholder="Répétez le mot de passe" required>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-pont btn-lg">
                        <i class="bi bi-person-plus"></i> Créer mon compte
                    </button>
                </div>

            </form>

            <hr>
            <p class="text-center mb-0">
                Déjà un compte ?
                <a href="login.php" class="text-decoration-none fw-semibold">Se connecter</a>
            </p>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>