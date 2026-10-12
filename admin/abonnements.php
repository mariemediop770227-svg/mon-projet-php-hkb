<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

$conn   = getConnexion();
$succes = '';
$erreur = '';

// -- CHANGER STATUT ABONNEMENT --
if (isset($_GET['statut']) && isset($_GET['id'])) {
    $id     = (int)$_GET['id'];
    $statut = securiser($_GET['statut']);
    if (in_array($statut, ['actif','expire','resilie'])) {
        $stmt = $conn->prepare("
            UPDATE abonnement SET statut = ? WHERE id_abonnement = ?
        ");
        $stmt->bind_param('si', $statut, $id);
        if ($stmt->execute()) {
            $succes = 'Statut de l\'abonnement mis à jour !';
        }
        $stmt->close();
    }
}

// Stats abonnements
$stats = $conn->query("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN statut='actif'      THEN 1 ELSE 0 END) as actifs,
        SUM(CASE WHEN statut='expire'     THEN 1 ELSE 0 END) as expires,
        SUM(CASE WHEN statut='resilie'    THEN 1 ELSE 0 END) as resilies,
        SUM(CASE WHEN statut='en_attente' THEN 1 ELSE 0 END) as en_attente,
        COALESCE(SUM(montant), 0) as total_montant
    FROM abonnement
")->fetch_assoc();

// Liste abonnements
$filtre = securiser($_GET['filtre'] ?? '');
$where  = $filtre ? "WHERE a.statut = '$filtre'" : '';

$abonnements = $conn->query("
    SELECT a.*,
           CONCAT(u.prenom,' ',u.nom) as nom_usager,
           u.email,
           b.numero_badge,
           cv.nom_classe
    FROM abonnement a
    JOIN utilisateur u      ON a.id_utilisateur = u.id_utilisateur
    JOIN badge b            ON a.id_badge        = b.id_badge
    JOIN classe_vehicule cv ON a.id_classe        = cv.id_classe
    $where
    ORDER BY a.date_souscription DESC
");

$conn->close();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Abonnements - Admin Pont HKB</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>

<?php include '../includes/navbar_admin.php'; ?>

<div class="contenu-principal">

    <h3 class="titre-page mb-4">
        <i class="bi bi-card-checklist"></i> Gestion des Abonnements
    </h3>

    <?php if (!empty($succes)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle-fill"></i> <?= $succes ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Cartes stats -->
    <div class="row g-3 mb-4">
        <div class="col-md-2">
            <div class="card carte-stat text-center h-100"
                 style="border-top:4px solid #1a3c5e">
                <div class="card-body">
                    <div class="text-muted small">Total</div>
                    <div class="fs-2 fw-bold text-primary">
                        <?= $stats['total'] ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card carte-stat text-center h-100"
                 style="border-top:4px solid #28a745">
                <div class="card-body">
                    <div class="text-muted small">Actifs</div>
                    <div class="fs-2 fw-bold text-success">
                        <?= $stats['actifs'] ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card carte-stat text-center h-100"
                 style="border-top:4px solid #ffc107">
                <div class="card-body">
                    <div class="text-muted small">En attente</div>
                    <div class="fs-2 fw-bold text-warning">
                        <?= $stats['en_attente'] ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card carte-stat text-center h-100"
                 style="border-top:4px solid #6c757d">
                <div class="card-body">
                    <div class="text-muted small">Expirés</div>
                    <div class="fs-2 fw-bold text-secondary">
                        <?= $stats['expires'] ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card carte-stat text-center h-100"
                 style="border-top:4px solid #dc3545">
                <div class="card-body">
                    <div class="text-muted small">Résiliés</div>
                    <div class="fs-2 fw-bold text-danger">
                        <?= $stats['resilies'] ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card carte-stat text-center h-100"
                 style="border-top:4px solid #f0a500">
                <div class="card-body">
                    <div class="text-muted small">Total souscrit</div>
                    <div class="fs-6 fw-bold" style="color:#f0a500">
                        <?= formatMontant($stats['total_montant']) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtres -->
    <div class="mb-3 d-flex gap-2">
        <a href="abonnements.php"
           class="btn btn-<?= empty($filtre) ? 'pont' : 'outline-secondary' ?> btn-sm">
            Tous
        </a>
        <a href="?filtre=actif"
           class="btn btn-<?= $filtre === 'actif' ? 'success' : 'outline-success' ?> btn-sm">
            Actifs
        </a>
        <a href="?filtre=en_attente"
           class="btn btn-<?= $filtre === 'en_attente' ? 'warning' : 'outline-warning' ?> btn-sm">
            En attente
        </a>
        <a href="?filtre=expire"
           class="btn btn-<?= $filtre === 'expire' ? 'secondary' : 'outline-secondary' ?> btn-sm">
            Expirés
        </a>
        <a href="?filtre=resilie"
           class="btn btn-<?= $filtre === 'resilie' ? 'danger' : 'outline-danger' ?> btn-sm">
            Résiliés
        </a>
    </div>

    <!-- Tableau -->
    <div class="card shadow-sm">
        <div class="card-header"
             style="background:var(--couleur-primaire); color:white">
            <i class="bi bi-list-ul"></i>
            Liste des abonnements
            <span class="badge bg-light text-dark ms-1">
                <?= $abonnements->num_rows ?>
            </span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Usager</th>
                            <th>Badge</th>
                            <th>Classe</th>
                            <th>Type</th>
                            <th>Montant</th>
                            <th>Début</th>
                            <th>Fin</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($abonnements->num_rows > 0): ?>
                            <?php while ($a = $abonnements->fetch_assoc()): ?>
                            <tr>
                                <td><?= $a['id_abonnement'] ?></td>
                                <td>
                                    <div class="fw-semibold small">
                                        <?= $a['nom_usager'] ?>
                                    </div>
                                    <div class="text-muted"
                                         style="font-size:0.75rem">
                                        <?= $a['email'] ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-info text-dark">
                                        <?= $a['numero_badge'] ?>
                                    </span>
                                </td>
                                <td>
                                    <small><?= $a['nom_classe'] ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-primary">
                                        <?= $a['type_abonnement'] ?>
                                    </span>
                                </td>
                                <td class="fw-bold text-success">
                                    <?= formatMontant($a['montant']) ?>
                                </td>
                                <td>
                                    <small>
                                        <?= formatDateCourte($a['date_debut']) ?>
                                    </small>
                                </td>
                                <td>
                                    <small>
                                        <?= formatDateCourte($a['date_fin']) ?>
                                    </small>
                                </td>
                                <td>
                                    <span class="badge
                                          bg-<?= couleurStatut($a['statut']) ?>">
                                        <?= $a['statut'] ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <?php if ($a['statut'] !== 'actif'): ?>
                                        <a href="?statut=actif&id=<?= $a['id_abonnement'] ?>"
                                           class="btn btn-outline-success"
                                           title="Activer">
                                            <i class="bi bi-check-circle"></i>
                                        </a>
                                        <?php endif; ?>
                                        <?php if ($a['statut'] === 'actif'): ?>
                                        <a href="?statut=resilie&id=<?= $a['id_abonnement'] ?>"
                                           class="btn btn-outline-danger"
                                           title="Résilier"
                                           onclick="return confirm('Résilier cet abonnement ?')">
                                            <i class="bi bi-x-circle"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10"
                                    class="text-center text-muted py-4">
                                    <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                    Aucun abonnement trouvé.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>