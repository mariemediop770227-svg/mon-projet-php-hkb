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

// Filtres
$date_debut = securiser($_GET['date_debut'] ?? date('Y-m-01'));
$date_fin   = securiser($_GET['date_fin']   ?? date('Y-m-d'));

$passages = $conn->query("
    SELECT p.*, cv.nom_classe, g.nom_guichet, v.plaque
    FROM passage p
    JOIN vehicule v         ON p.id_vehicule = v.id_vehicule
    JOIN classe_vehicule cv ON p.id_classe   = cv.id_classe
    JOIN guichet g          ON p.id_guichet  = g.id_guichet
    WHERE v.id_utilisateur = $id_utilisateur
    AND DATE(p.date_heure) BETWEEN '$date_debut' AND '$date_fin'
    ORDER BY p.date_heure DESC
");

$totaux = $conn->query("
    SELECT COUNT(*) as nb, COALESCE(SUM(p.montant), 0) as total
    FROM passage p
    JOIN vehicule v ON p.id_vehicule = v.id_vehicule
    WHERE v.id_utilisateur = $id_utilisateur
    AND DATE(p.date_heure) BETWEEN '$date_debut' AND '$date_fin'
    AND p.statut = 'valide'
")->fetch_assoc();

$conn->close();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Historique - Pont HKB</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>

<?php include '../includes/navbar_usager.php'; ?>

<div class="contenu-principal">

    <h3 class="titre-page mb-4">
        <i class="bi bi-clock-history"></i> Mon historique de passages
    </h3>

    <!-- Filtres -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Date début</label>
                    <input type="date" class="form-control"
                           name="date_debut" value="<?= $date_debut ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Date fin</label>
                    <input type="date" class="form-control"
                           name="date_fin" value="<?= $date_fin ?>">
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-pont w-100">
                        <i class="bi bi-search"></i> Filtrer
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Totaux -->
    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <div class="card text-center border-primary">
                <div class="card-body">
                    <div class="text-muted small">Total passages</div>
                    <div class="fs-2 fw-bold text-primary">
                        <?= $totaux['nb'] ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card text-center border-success">
                <div class="card-body">
                    <div class="text-muted small">Total dépensé</div>
                    <div class="fs-4 fw-bold text-success">
                        <?= formatMontant($totaux['total']) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tableau -->
    <div class="card shadow-sm">
        <div class="card-header"
             style="background:var(--couleur-primaire); color:white">
            <i class="bi bi-table"></i>
            Passages (<?= $passages->num_rows ?>)
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date/Heure</th>
                            <th>Plaque</th>
                            <th>Classe</th>
                            <th>Guichet</th>
                            <th>Mode</th>
                            <th>Montant</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($passages->num_rows > 0): ?>
                            <?php while ($p = $passages->fetch_assoc()): ?>
                            <tr>
                                <td><?= $p['id_passage'] ?></td>
                                <td>
                                    <small>
                                        <?= formatDate($p['date_heure']) ?>
                                    </small>
                                </td>
                                <td>
                                    <span class="badge bg-dark">
                                        <?= $p['plaque_saisie'] ?>
                                    </span>
                                </td>
                                <td><small><?= $p['nom_classe'] ?></small></td>
                                <td><small><?= $p['nom_guichet'] ?></small></td>
                                <td>
                                    <span class="badge bg-<?=
                                        $p['mode_paiement'] === 'espece'
                                            ? 'warning text-dark' : 'info'
                                    ?>">
                                        <?= $p['mode_paiement'] ?>
                                    </span>
                                </td>
                                <td class="fw-bold text-success">
                                    <?= formatMontant($p['montant']) ?>
                                </td>
                                <td>
                                    <span class="badge
                                          bg-<?= couleurStatut($p['statut']) ?>">
                                        <?= $p['statut'] ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8"
                                    class="text-center text-muted py-4">
                                    <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                    Aucun passage pour cette période.
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