<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'usager') {
    header('Location: ../auth/login.php');
    exit();
}

$conn           = getConnexion();
$id_utilisateur = $_SESSION['id_utilisateur'];
$erreur         = '';
$succes         = '';

// -- AJOUTER VÉHICULE --
if (isset($_POST['action']) && $_POST['action'] === 'ajouter') {
    $plaque    = strtoupper(securiser($_POST['plaque'] ?? ''));
    $id_classe = (int)($_POST['id_classe'] ?? 0);
    $marque    = securiser($_POST['marque'] ?? '');
    $modele    = securiser($_POST['modele'] ?? '');
    $couleur   = securiser($_POST['couleur'] ?? '');

    if (empty($plaque) || $id_classe === 0) {
        $erreur = 'La plaque et la classe sont obligatoires.';
    } else {
        $check = $conn->prepare("
            SELECT id_vehicule FROM vehicule WHERE plaque = ?
        ");
        $check->bind_param('s', $plaque);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $erreur = 'Cette plaque est déjà enregistrée.';
        } else {
            $stmt = $conn->prepare("
                INSERT INTO vehicule
                    (id_utilisateur, id_classe, plaque, marque, modele, couleur)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                'iissss',
                $id_utilisateur, $id_classe,
                $plaque, $marque, $modele, $couleur
            );
            if ($stmt->execute()) {
                $succes = 'Véhicule ajouté avec succès !';
            } else {
                $erreur = 'Erreur lors de l\'ajout.';
            }
            $stmt->close();
        }
        $check->close();
    }
}

// -- SUPPRIMER VÉHICULE --
if (isset($_GET['supprimer'])) {
    $id = (int)$_GET['supprimer'];
    $stmt = $conn->prepare("
        UPDATE vehicule SET statut = 'suspendu'
        WHERE id_vehicule = ? AND id_utilisateur = ?
    ");
    $stmt->bind_param('ii', $id, $id_utilisateur);
    if ($stmt->execute()) {
        $succes = 'Véhicule supprimé avec succès !';
    }
    $stmt->close();
}

// -- LISTE VÉHICULES --
$vehicules = $conn->query("
    SELECT v.*, cv.nom_classe, cv.tarif_normal, cv.tarif_badge
    FROM vehicule v
    JOIN classe_vehicule cv ON v.id_classe = cv.id_classe
    WHERE v.id_utilisateur = $id_utilisateur
    ORDER BY v.date_enregistrement DESC
");

// Classes
$classes = $conn->query("SELECT * FROM classe_vehicule ORDER BY ordre");
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Véhicules - Pont HKB</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>

<?php include '../includes/navbar_usager.php'; ?>

<div class="contenu-principal">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="titre-page mb-0">
            <i class="bi bi-car-front"></i> Mes véhicules
        </h3>
        <button class="btn btn-pont" data-bs-toggle="modal"
                data-bs-target="#modalAjout">
            <i class="bi bi-plus-circle"></i> Ajouter un véhicule
        </button>
    </div>

    <!-- Messages -->
    <?php if (!empty($succes)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle-fill"></i> <?= $succes ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($erreur)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle-fill"></i> <?= $erreur ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Grille des véhicules -->
    <div class="row g-3">
        <?php if ($vehicules->num_rows > 0): ?>
            <?php while ($v = $vehicules->fetch_assoc()): ?>
            <div class="col-md-4">
                <div class="card carte-stat h-100"
                     style="border-top: 4px solid var(--couleur-primaire)">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <span class="badge bg-dark fs-6 mb-1">
                                    <?= $v['plaque'] ?>
                                </span>
                                <div class="text-muted small">
                                    <?= $v['marque'] ?> <?= $v['modele'] ?>
                                </div>
                            </div>
                            <span class="badge bg-<?= couleurStatut($v['statut']) ?>">
                                <?= $v['statut'] ?>
                            </span>
                        </div>

                        <div class="row text-center mb-3">
                            <div class="col-6">
                                <div class="text-muted small">Classe</div>
                                <div class="fw-semibold small">
                                    <?= $v['nom_classe'] ?>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-muted small">Couleur</div>
                                <div class="fw-semibold">
                                    <?= $v['couleur'] ?: '-' ?>
                                </div>
                            </div>
                        </div>

                        <div class="row text-center mb-3">
                            <div class="col-6">
                                <div class="text-muted small">Tarif espèces</div>
                                <div class="fw-bold text-warning">
                                    <?= formatMontant($v['tarif_normal']) ?>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-muted small">Tarif badge</div>
                                <div class="fw-bold text-success">
                                    <?= formatMontant($v['tarif_badge']) ?>
                                </div>
                            </div>
                        </div>

                        <div class="text-muted small mb-3">
                            <i class="bi bi-calendar3"></i>
                            Enregistré le
                            <?= formatDateCourte($v['date_enregistrement']) ?>
                        </div>

                        <?php if ($v['statut'] === 'actif'): ?>
                        <a href="?supprimer=<?= $v['id_vehicule'] ?>"
                           class="btn btn-outline-danger btn-sm w-100"
                           onclick="return confirm('Supprimer ce véhicule ?')">
                            <i class="bi bi-trash"></i> Supprimer
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="text-center py-5">
                    <i class="bi bi-car-front fs-1 text-muted d-block mb-3"></i>
                    <h5 class="text-muted">Aucun véhicule enregistré</h5>
                    <p class="text-muted">
                        Ajoutez votre premier véhicule pour commencer.
                    </p>
                    <button class="btn btn-pont"
                            data-bs-toggle="modal"
                            data-bs-target="#modalAjout">
                        <i class="bi bi-plus-circle"></i>
                        Ajouter un véhicule
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- MODAL AJOUT -->
<div class="modal fade" id="modalAjout" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"
                 style="background:var(--couleur-primaire); color:white">
                <h5 class="modal-title">
                    <i class="bi bi-plus-circle"></i> Ajouter un véhicule
                </h5>
                <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="ajouter">
                <div class="modal-body">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Plaque d'immatriculation
                            <span class="text-danger">*</span>
                        </label>
                        <input type="text"
                               class="form-control text-uppercase fw-bold"
                               name="plaque"
                               placeholder="AB 1234 CI"
                               style="letter-spacing:2px"
                               oninput="this.value=this.value.toUpperCase()"
                               required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Classe de véhicule
                            <span class="text-danger">*</span>
                        </label>
                        <select class="form-select" name="id_classe" required>
                            <option value="">-- Sélectionner --</option>
                            <?php while ($c = $classes->fetch_assoc()): ?>
                            <option value="<?= $c['id_classe'] ?>">
                                <?= $c['nom_classe'] ?>
                                — <?= formatMontant($c['tarif_normal']) ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Marque</label>
                            <input type="text" class="form-control"
                                   name="marque" placeholder="Toyota">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Modèle</label>
                            <input type="text" class="form-control"
                                   name="modele" placeholder="Corolla">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Couleur</label>
                        <input type="text" class="form-control"
                               name="couleur" placeholder="Blanc">
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"
                            data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-pont">
                        <i class="bi bi-check-lg"></i> Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>