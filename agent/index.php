<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'agent') {
    header('Location: ../auth/login.php');
    exit();
}

$conn        = getConnexion();
$id_agent    = $_SESSION['id_utilisateur'];
$aujourd_hui = date('Y-m-d');

// Passages de l'agent aujourd'hui
$r1 = $conn->query("
    SELECT COUNT(*) as total, COALESCE(SUM(montant), 0) as recette
    FROM passage
    WHERE id_agent = $id_agent
    AND DATE(date_heure) = '$aujourd_hui'
    AND statut = 'valide'
");
$stats_jour = $r1->fetch_assoc();

// Passages espèces aujourd'hui
$r2 = $conn->query("
    SELECT COUNT(*) as total, COALESCE(SUM(montant), 0) as recette
    FROM passage
    WHERE id_agent = $id_agent
    AND DATE(date_heure) = '$aujourd_hui'
    AND mode_paiement = 'espece'
    AND statut = 'valide'
");
$stats_espece = $r2->fetch_assoc();

// Passages badge aujourd'hui
$r3 = $conn->query("
    SELECT COUNT(*) as total, COALESCE(SUM(montant), 0) as recette
    FROM passage
    WHERE id_agent = $id_agent
    AND DATE(date_heure) = '$aujourd_hui'
    AND mode_paiement = 'badge'
    AND statut = 'valide'
");
$stats_badge = $r3->fetch_assoc();

// Guichet affecté
$r4 = $conn->query("
    SELECT g.nom_guichet, g.numero_guichet, g.statut
    FROM agent_guichet ag
    JOIN guichet g ON ag.id_guichet = g.id_guichet
    WHERE ag.id_utilisateur = $id_agent
    AND ag.statut = 'actif'
    LIMIT 1
");
$guichet_agent = $r4->num_rows > 0 ? $r4->fetch_assoc() : null;

// Derniers passages de l'agent
$derniers = $conn->query("
    SELECT p.*, cv.nom_classe, g.nom_guichet
    FROM passage p
    JOIN classe_vehicule cv ON p.id_classe = cv.id_classe
    JOIN guichet g          ON p.id_guichet = g.id_guichet
    WHERE p.id_agent = $id_agent
    ORDER BY p.date_heure DESC
    LIMIT 8
");

$conn->close();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord Agent - Pont HKB</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>

<?php include '../includes/navbar_agent.php'; ?>

<div class="contenu-principal">

    <!-- Titre -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="titre-page mb-0">
            <i class="bi bi-speedometer2"></i>
            Bonjour, <?= $_SESSION['prenom'] ?> !
        </h3>
        <div class="d-flex align-items-center gap-2">
            <?php if ($guichet_agent): ?>
                <span class="badge bg-<?= $guichet_agent['statut'] === 'ouvert' ? 'success' : 'danger' ?> fs-6">
                    <i class="bi bi-building"></i>
                    <?= $guichet_agent['numero_guichet'] ?> —
                    <?= $guichet_agent['nom_guichet'] ?>
                </span>
            <?php else: ?>
                <span class="badge bg-warning text-dark fs-6">
                    <i class="bi bi-exclamation-triangle"></i>
                    Aucun guichet affecté
                </span>
            <?php endif; ?>
            <span class="badge bg-secondary fs-6">
                <i class="bi bi-calendar3"></i>
                <?= date('d/m/Y H:i') ?>
            </span>
        </div>
    </div>

    <!-- Cartes stats -->
    <div class="row g-3 mb-4">

        <div class="col-md-4">
            <div class="card carte-stat h-100"
                 style="border-left:4px solid #1a3c5e">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Passages aujourd'hui</div>
                        <div class="fs-2 fw-bold text-primary">
                            <?= $stats_jour['total'] ?>
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

        <div class="col-md-4">
            <div class="card carte-stat h-100"
                 style="border-left:4px solid #f0a500">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Paiements espèces</div>
                        <div class="fs-2 fw-bold" style="color:#f0a500">
                            <?= $stats_espece['total'] ?>
                        </div>
                        <div class="text-success small">
                            <i class="bi bi-cash"></i>
                            <?= formatMontant($stats_espece['recette']) ?>
                        </div>
                    </div>
                    <div class="icone opacity-50" style="color:#f0a500">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card carte-stat h-100"
                 style="border-left:4px solid #17a2b8">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Paiements badge</div>
                        <div class="fs-2 fw-bold text-info">
                            <?= $stats_badge['total'] ?>
                        </div>
                        <div class="text-success small">
                            <i class="bi bi-cash"></i>
                            <?= formatMontant($stats_badge['recette']) ?>
                        </div>
                    </div>
                    <div class="icone text-info opacity-50">
                        <i class="bi bi-credit-card"></i>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Bouton enregistrer passage -->
    <div class="text-center mb-4">
        <a href="passage.php" class="btn btn-pont btn-lg px-5 py-3">
            <i class="bi bi-plus-circle fs-4"></i>
            <span class="ms-2 fs-5">Enregistrer un passage</span>
        </a>
    </div>

    <!-- Derniers passages -->
    <div class="card shadow-sm">
        <div class="card-header"
             style="background:var(--couleur-primaire); color:white">
            <i class="bi bi-clock-history"></i> Mes derniers passages
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Heure</th>
                            <th>Plaque</th>
                            <th>Classe</th>
                            <th>Mode paiement</th>
                            <th>Montant</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($derniers->num_rows > 0): ?>
                            <?php while ($p = $derniers->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <small><?= formatDate($p['date_heure']) ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-dark">
                                        <?= $p['plaque_saisie'] ?>
                                    </span>
                                </td>
                                <td><small><?= $p['nom_classe'] ?></small></td>
                                <td>
                                    <span class="badge bg-<?=
                                        $p['mode_paiement'] === 'espece'
                                            ? 'warning text-dark'
                                            : 'info'
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
                                <td colspan="6" class="text-center text-muted py-4">
                                    <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                    Aucun passage enregistré aujourd'hui.
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