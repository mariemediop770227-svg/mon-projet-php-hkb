<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}
$conn = getConnexion();

// ---- Statistiques du jour ----
$aujourd_hui = date('Y-m-d');

// Total passages aujourd'hui
$r = $conn->query("
    SELECT COUNT(*) as total, COALESCE(SUM(montant), 0) as recette
    FROM passage
    WHERE DATE(date_heure) = '$aujourd_hui' AND statut = 'valide'
");
$stats_jour = $r->fetch_assoc();

// Total passages ce mois
$r2 = $conn->query("
    SELECT COUNT(*) as total, COALESCE(SUM(montant), 0) as recette
    FROM passage
    WHERE MONTH(date_heure) = MONTH(NOW())
    AND YEAR(date_heure) = YEAR(NOW())
    AND statut = 'valide'
");
$stats_mois = $r2->fetch_assoc();

// Nombre d'agents actifs
$r3 = $conn->query("
    SELECT COUNT(*) as total FROM utilisateur
    WHERE id_role = 2 AND statut = 'actif'
");
$nb_agents = $r3->fetch_assoc()['total'];

// Nombre d'usagers
$r4 = $conn->query("
    SELECT COUNT(*) as total FROM utilisateur
    WHERE id_role = 3 AND statut = 'actif'
");
$nb_usagers = $r4->fetch_assoc()['total'];

// Nombre de badges actifs
$r5 = $conn->query("
    SELECT COUNT(*) as total FROM badge WHERE statut = 'actif'
");
$nb_badges = $r5->fetch_assoc()['total'];

// Nombre de guichets ouverts
$r6 = $conn->query("
    SELECT COUNT(*) as total FROM guichet WHERE statut = 'ouvert'
");
$nb_guichets = $r6->fetch_assoc()['total'];

// Derniers passages (10)
$derniers_passages = $conn->query("
    SELECT p.*, cv.nom_classe, g.nom_guichet,
           CONCAT(u.prenom, ' ', u.nom) as nom_agent
    FROM passage p
    JOIN classe_vehicule cv ON p.id_classe = cv.id_classe
    JOIN guichet g          ON p.id_guichet = g.id_guichet
    JOIN utilisateur u      ON p.id_agent = u.id_utilisateur
    ORDER BY p.date_heure DESC
    LIMIT 10
");

// Passages par classe aujourd'hui
$stats_classes = $conn->query("
    SELECT cv.nom_classe, COUNT(*) as nb, SUM(p.montant) as total
    FROM passage p
    JOIN classe_vehicule cv ON p.id_classe = cv.id_classe
    WHERE DATE(p.date_heure) = '$aujourd_hui' AND p.statut = 'valide'
    GROUP BY cv.id_classe
    ORDER BY nb DESC
");

$conn->close();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - Admin Pont HKB</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>

<?php include '../includes/navbar_admin.php'; ?>

<div class="contenu-principal">

    <!-- Titre -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="titre-page mb-0">
            <i class="bi bi-speedometer2"></i> Tableau de bord
        </h3>
        <span class="badge bg-secondary fs-6">
            <i class="bi bi-calendar3"></i>
            <?= date('d/m/Y H:i') ?>
        </span>
    </div>

    <!-- Cartes statistiques -->
    <div class="row g-3 mb-4">

        <!-- Passages aujourd'hui -->
        <div class="col-md-3">
            <div class="card carte-stat h-100"
                 style="border-left: 4px solid #1a3c5e;">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Passages aujourd'hui</div>
                        <div class="fs-2 fw-bold text-primary">
                            <?= number_format($stats_jour['total']) ?>
                        </div>
                        <div class="text-success small">
                            <i class="bi bi-cash"></i>
                            <?= formatMontant($stats_jour['recette']) ?>
                        </div>
                    </div>
                    <div class="icone text-primary opacity-50">
                        <i class="bi bi-car-front"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Passages ce mois -->
        <div class="col-md-3">
            <div class="card carte-stat h-100"
                 style="border-left: 4px solid #f0a500;">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Passages ce mois</div>
                        <div class="fs-2 fw-bold" style="color:#f0a500">
                            <?= number_format($stats_mois['total']) ?>
                        </div>
                        <div class="text-success small">
                            <i class="bi bi-cash"></i>
                            <?= formatMontant($stats_mois['recette']) ?>
                        </div>
                    </div>
                    <div class="icone opacity-50" style="color:#f0a500">
                        <i class="bi bi-calendar-month"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Agents actifs -->
        <div class="col-md-3">
            <div class="card carte-stat h-100"
                 style="border-left: 4px solid #28a745;">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Agents actifs</div>
                        <div class="fs-2 fw-bold text-success"><?= $nb_agents ?></div>
                        <div class="text-muted small">
                            <i class="bi bi-building"></i>
                            <?= $nb_guichets ?> guichet(s) ouvert(s)
                        </div>
                    </div>
                    <div class="icone text-success opacity-50">
                        <i class="bi bi-people"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Usagers / Badges -->
        <div class="col-md-3">
            <div class="card carte-stat h-100"
                 style="border-left: 4px solid #17a2b8;">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Usagers inscrits</div>
                        <div class="fs-2 fw-bold text-info"><?= $nb_usagers ?></div>
                        <div class="text-muted small">
                            <i class="bi bi-credit-card"></i>
                            <?= $nb_badges ?> badge(s) actif(s)
                        </div>
                    </div>
                    <div class="icone text-info opacity-50">
                        <i class="bi bi-person-check"></i>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <div class="row g-3">

        <!-- Derniers passages -->
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center"
                     style="background:var(--couleur-primaire); color:white">
                    <span>
                        <i class="bi bi-clock-history"></i> Derniers passages
                    </span>
                    <a href="recettes.php" class="btn btn-sm btn-outline-light">
                        Voir tout
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Date/Heure</th>
                                    <th>Plaque</th>
                                    <th>Classe</th>
                                    <th>Guichet</th>
                                    <th>Paiement</th>
                                    <th>Montant</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($derniers_passages->num_rows > 0): ?>
                                    <?php while ($p = $derniers_passages->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <small><?= formatDate($p['date_heure']) ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-dark">
                                                <?= $p['plaque_saisie'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small><?= $p['nom_classe'] ?></small>
                                        </td>
                                        <td>
                                            <small><?= $p['nom_guichet'] ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?=
                                                $p['mode_paiement'] === 'espece' ? 'warning text-dark' :
                                                ($p['mode_paiement'] === 'badge' ? 'info' : 'success')
                                            ?>">
                                                <?= $p['mode_paiement'] ?>
                                            </span>
                                        </td>
                                        <td class="fw-bold text-success">
                                            <?= formatMontant($p['montant']) ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= couleurStatut($p['statut']) ?>">
                                                <?= $p['statut'] ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-3">
                                            <i class="bi bi-inbox"></i>
                                            Aucun passage enregistré pour le moment
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats par classe aujourd'hui -->
        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-header"
                     style="background:var(--couleur-primaire); color:white">
                    <i class="bi bi-bar-chart"></i>
                    Passages par classe — Aujourd'hui
                </div>
                <div class="card-body">
                    <?php if ($stats_classes->num_rows > 0): ?>
                        <?php while ($sc = $stats_classes->fetch_assoc()): ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <small class="fw-semibold"><?= $sc['nom_classe'] ?></small>
                                <small class="text-muted"><?= $sc['nb'] ?> passage(s)</small>
                            </div>
                            <div class="text-success small mb-1">
                                <?= formatMontant($sc['total']) ?>
                            </div>
                            <div class="progress" style="height:6px">
                                <div class="progress-bar"
                                     style="width:<?= min(100, $sc['nb'] * 10) ?>%;
                                            background:var(--couleur-primaire)">
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-inbox fs-3"></i>
                            <p class="mt-2">Aucun passage aujourd'hui</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>

    <!-- Raccourcis -->
    <div class="row g-3 mt-2">
        <div class="col-12">
            <h6 class="text-muted">
                <i class="bi bi-lightning"></i> Accès rapide
            </h6>
        </div>
        <div class="col-md-2">
            <a href="agents.php" class="btn btn-outline-primary w-100 py-3">
                <i class="bi bi-people fs-4 d-block mb-1"></i>
                Gérer les agents
            </a>
        </div>
        <div class="col-md-2">
            <a href="guichets.php" class="btn btn-outline-warning w-100 py-3">
                <i class="bi bi-building fs-4 d-block mb-1"></i>
                Gérer les guichets
            </a>
        </div>
        <div class="col-md-2">
            <a href="abonnements.php" class="btn btn-outline-success w-100 py-3">
                <i class="bi bi-card-checklist fs-4 d-block mb-1"></i>
                Abonnements
            </a>
        </div>
        <div class="col-md-2">
            <a href="recettes.php" class="btn btn-outline-info w-100 py-3">
                <i class="bi bi-cash-stack fs-4 d-block mb-1"></i>
                Recettes
            </a>
        </div>
        <div class="col-md-2">
            <a href="rapports.php" class="btn btn-outline-secondary w-100 py-3">
                <i class="bi bi-file-earmark-bar-graph fs-4 d-block mb-1"></i>
                Rapports
            </a>
        </div>
        <div class="col-md-2">
            <a href="statistiques.php" class="btn btn-outline-dark w-100 py-3">
                <i class="bi bi-bar-chart-line fs-4 d-block mb-1"></i>
                Statistiques
            </a>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>