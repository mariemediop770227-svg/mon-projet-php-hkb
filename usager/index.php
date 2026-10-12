<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'usager') {
    header('Location: ../auth/login.php');
    exit();
}

$conn          = getConnexion();
$id_utilisateur = $_SESSION['id_utilisateur'];

// Nombre de véhicules
$r1 = $conn->query("
    SELECT COUNT(*) as total FROM vehicule
    WHERE id_utilisateur = $id_utilisateur AND statut = 'actif'
");
$nb_vehicules = $r1->fetch_assoc()['total'];

// Badge actif
$r2 = $conn->query("
    SELECT * FROM badge
    WHERE id_utilisateur = $id_utilisateur
    AND statut = 'actif'
    LIMIT 1
");
$badge = $r2->num_rows > 0 ? $r2->fetch_assoc() : null;

// Derniers passages
$r3 = $conn->query("
    SELECT p.*, cv.nom_classe, g.nom_guichet
    FROM passage p
    JOIN vehicule v         ON p.id_vehicule = v.id_vehicule
    JOIN classe_vehicule cv ON p.id_classe = cv.id_classe
    JOIN guichet g          ON p.id_guichet = g.id_guichet
    WHERE v.id_utilisateur = $id_utilisateur
    ORDER BY p.date_heure DESC
    LIMIT 5
");

// Total dépensé ce mois
$r4 = $conn->query("
    SELECT COALESCE(SUM(p.montant), 0) as total
    FROM passage p
    JOIN vehicule v ON p.id_vehicule = v.id_vehicule
    WHERE v.id_utilisateur = $id_utilisateur
    AND MONTH(p.date_heure) = MONTH(NOW())
    AND YEAR(p.date_heure)  = YEAR(NOW())
    AND p.statut = 'valide'
");
$depenses_mois = $r4->fetch_assoc()['total'];

// Abonnement actif
$r5 = $conn->query("
    SELECT a.*, cv.nom_classe, b.numero_badge
    FROM abonnement a
    JOIN classe_vehicule cv ON a.id_classe = cv.id_classe
    JOIN badge b            ON a.id_badge  = b.id_badge
    WHERE a.id_utilisateur = $id_utilisateur
    AND a.statut = 'actif'
    LIMIT 1
");
$abonnement = $r5->num_rows > 0 ? $r5->fetch_assoc() : null;

$conn->close();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon espace - Pont HKB</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>

<?php include '../includes/navbar_usager.php'; ?>

<div class="contenu-principal">

    <!-- Titre -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="titre-page mb-0">
            <i class="bi bi-house"></i>
            Bonjour, <?= $_SESSION['prenom'] ?> !
        </h3>
        <span class="badge bg-secondary fs-6">
            <i class="bi bi-calendar3"></i>
            <?= date('d/m/Y') ?>
        </span>
    </div>

    <!-- Cartes stats -->
    <div class="row g-3 mb-4">

        <!-- Véhicules -->
        <div class="col-md-3">
            <div class="card carte-stat h-100"
                 style="border-left:4px solid #1a3c5e">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Mes véhicules</div>
                        <div class="fs-2 fw-bold text-primary">
                            <?= $nb_vehicules ?>
                        </div>
                        <a href="vehicules.php" class="small text-decoration-none">
                            Gérer mes véhicules →
                        </a>
                    </div>
                    <div class="icone text-primary opacity-50">
                        <i class="bi bi-car-front"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Badge -->
        <div class="col-md-3">
            <div class="card carte-stat h-100"
                 style="border-left:4px solid <?= $badge ? '#28a745' : '#dc3545' ?>">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Mon badge</div>
                        <?php if ($badge): ?>
                            <div class="fs-5 fw-bold text-success">
                                <?= formatMontant($badge['solde']) ?>
                            </div>
                            <div class="small text-muted">
                                <?= $badge['numero_badge'] ?>
                            </div>
                        <?php else: ?>
                            <div class="text-danger fw-bold">Aucun badge</div>
                            <a href="badge.php" class="small text-decoration-none">
                                Souscrire →
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="icone opacity-50"
                         style="color:<?= $badge ? '#28a745' : '#dc3545' ?>">
                        <i class="bi bi-credit-card"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dépenses mois -->
        <div class="col-md-3">
            <div class="card carte-stat h-100"
                 style="border-left:4px solid #f0a500">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Dépenses ce mois</div>
                        <div class="fs-5 fw-bold" style="color:#f0a500">
                            <?= formatMontant($depenses_mois) ?>
                        </div>
                        <a href="historique.php" class="small text-decoration-none">
                            Voir historique →
                        </a>
                    </div>
                    <div class="icone opacity-50" style="color:#f0a500">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Abonnement -->
        <div class="col-md-3">
            <div class="card carte-stat h-100"
                 style="border-left:4px solid #17a2b8">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Abonnement</div>
                        <?php if ($abonnement): ?>
                            <div class="text-success fw-bold small">
                                <?= $abonnement['type_abonnement'] ?>
                            </div>
                            <div class="text-muted small">
                                Expire : <?= formatDateCourte($abonnement['date_fin']) ?>
                            </div>
                        <?php else: ?>
                            <div class="text-muted fw-bold">Aucun</div>
                            <a href="abonnement.php" class="small text-decoration-none">
                                Souscrire →
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="icone text-info opacity-50">
                        <i class="bi bi-card-checklist"></i>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Alerte solde faible -->
    <?php if ($badge && $badge['solde'] < 1000): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <strong>Solde faible !</strong>
            Votre badge ne contient que
            <strong><?= formatMontant($badge['solde']) ?></strong>.
            <a href="recharge.php" class="btn btn-warning btn-sm ms-2">
                <i class="bi bi-plus-circle"></i> Recharger maintenant
            </a>
        </div>
    <?php endif; ?>

    <div class="row g-3">

        <!-- Derniers passages -->
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between"
                     style="background:var(--couleur-primaire); color:white">
                    <span>
                        <i class="bi bi-clock-history"></i>
                        Mes derniers passages
                    </span>
                    <a href="historique.php"
                       class="btn btn-sm btn-outline-light">
                        Voir tout
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Plaque</th>
                                    <th>Guichet</th>
                                    <th>Mode</th>
                                    <th>Montant</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($r3->num_rows > 0): ?>
                                    <?php while ($p = $r3->fetch_assoc()): ?>
                                    <tr>
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
                                        <td>
                                            <small><?= $p['nom_guichet'] ?></small>
                                        </td>
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
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5"
                                            class="text-center text-muted py-3">
                                            <i class="bi bi-inbox d-block fs-3 mb-1"></i>
                                            Aucun passage enregistré.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Accès rapide -->
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header"
                     style="background:var(--couleur-primaire); color:white">
                    <i class="bi bi-lightning"></i> Accès rapide
                </div>
                <div class="card-body d-grid gap-2">
                    <a href="vehicules.php"
                       class="btn btn-outline-primary text-start">
                        <i class="bi bi-car-front me-2"></i>
                        Gérer mes véhicules
                    </a>
                    <a href="badge.php"
                       class="btn btn-outline-success text-start">
                        <i class="bi bi-credit-card me-2"></i>
                        Mon badge
                    </a>
                    <a href="recharge.php"
                       class="btn btn-outline-warning text-start">
                        <i class="bi bi-plus-circle me-2"></i>
                        Recharger mon badge
                    </a>
                    <a href="abonnement.php"
                       class="btn btn-outline-info text-start">
                        <i class="bi bi-card-checklist me-2"></i>
                        Souscrire un abonnement
                    </a>
                    <a href="historique.php"
                       class="btn btn-outline-secondary text-start">
                        <i class="bi bi-clock-history me-2"></i>
                        Voir mon historique
                    </a>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>