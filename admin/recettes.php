<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

$conn = getConnexion();

// Filtres
$date_debut  = securiser($_GET['date_debut']  ?? date('Y-m-01'));
$date_fin    = securiser($_GET['date_fin']    ?? date('Y-m-d'));
$id_guichet  = (int)($_GET['id_guichet']     ?? 0);
$mode        = securiser($_GET['mode']        ?? '');

// Construction filtre SQL
$where = "WHERE DATE(p.date_heure) BETWEEN '$date_debut' AND '$date_fin'
          AND p.statut = 'valide'";
if ($id_guichet > 0) $where .= " AND p.id_guichet = $id_guichet";
if (!empty($mode))   $where .= " AND p.mode_paiement = '$mode'";

// Totaux généraux
$totaux = $conn->query("
    SELECT
        COUNT(*)                                      as nb_passages,
        COALESCE(SUM(montant), 0)                    as total_recette,
        COALESCE(SUM(CASE WHEN mode_paiement='espece'
                     THEN montant END), 0)            as total_espece,
        COALESCE(SUM(CASE WHEN mode_paiement='badge'
                     THEN montant END), 0)            as total_badge,
        COALESCE(SUM(CASE WHEN mode_paiement='abonnement'
                     THEN montant END), 0)            as total_abonnement
    FROM passage p
    $where
")->fetch_assoc();

// Recettes par guichet
$par_guichet = $conn->query("
    SELECT g.nom_guichet, g.numero_guichet,
           COUNT(*) as nb,
           COALESCE(SUM(p.montant), 0) as total
    FROM passage p
    JOIN guichet g ON p.id_guichet = g.id_guichet
    $where
    GROUP BY g.id_guichet
    ORDER BY total DESC
");

// Recettes par classe
$par_classe = $conn->query("
    SELECT cv.nom_classe,
           COUNT(*) as nb,
           COALESCE(SUM(p.montant), 0) as total
    FROM passage p
    JOIN classe_vehicule cv ON p.id_classe = cv.id_classe
    $where
    GROUP BY cv.id_classe
    ORDER BY total DESC
");

// Recettes par agent
$par_agent = $conn->query("
    SELECT CONCAT(u.prenom,' ',u.nom) as nom_agent,
           COUNT(*) as nb,
           COALESCE(SUM(p.montant), 0) as total
    FROM passage p
    JOIN utilisateur u ON p.id_agent = u.id_utilisateur
    $where
    GROUP BY p.id_agent
    ORDER BY total DESC
");

// Recettes par jour
$par_jour = $conn->query("
    SELECT DATE(p.date_heure) as jour,
           COUNT(*) as nb,
           COALESCE(SUM(p.montant), 0) as total
    FROM passage p
    $where
    GROUP BY DATE(p.date_heure)
    ORDER BY jour DESC
");

// Liste des passages
$passages = $conn->query("
    SELECT p.*,
           cv.nom_classe,
           g.nom_guichet,
           g.numero_guichet,
           CONCAT(u.prenom,' ',u.nom) as nom_agent
    FROM passage p
    JOIN classe_vehicule cv ON p.id_classe   = cv.id_classe
    JOIN guichet g          ON p.id_guichet  = g.id_guichet
    JOIN utilisateur u      ON p.id_agent    = u.id_utilisateur
    $where
    ORDER BY p.date_heure DESC
    LIMIT 100
");

// Guichets pour filtre
$guichets = $conn->query("SELECT * FROM guichet ORDER BY numero_guichet");

$conn->close();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recettes - Admin Pont HKB</title>
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
            <i class="bi bi-cash-stack"></i> Gestion des Recettes
        </h3>
        <a href="rapports.php" class="btn btn-pont">
            <i class="bi bi-file-earmark-pdf"></i> Générer un rapport
        </a>
    </div>

    <!-- Filtres -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Date début</label>
                    <input type="date" class="form-control"
                           name="date_debut" value="<?= $date_debut ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Date fin</label>
                    <input type="date" class="form-control"
                           name="date_fin" value="<?= $date_fin ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Guichet</label>
                    <select class="form-select" name="id_guichet">
                        <option value="0">Tous les guichets</option>
                        <?php while ($g = $guichets->fetch_assoc()): ?>
                        <option value="<?= $g['id_guichet'] ?>"
                                <?= $id_guichet == $g['id_guichet'] ? 'selected' : '' ?>>
                            <?= $g['numero_guichet'] ?> — <?= $g['nom_guichet'] ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Mode paiement</label>
                    <select class="form-select" name="mode">
                        <option value="">Tous</option>
                        <option value="espece"
                                <?= $mode === 'espece' ? 'selected' : '' ?>>
                            Espèces
                        </option>
                        <option value="badge"
                                <?= $mode === 'badge' ? 'selected' : '' ?>>
                            Badge
                        </option>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-pont flex-fill">
                        <i class="bi bi-search"></i> Filtrer
                    </button>
                    <a href="recettes.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Cartes totaux -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card carte-stat h-100"
                 style="border-left:4px solid #1a3c5e">
                <div class="card-body text-center">
                    <div class="text-muted small">Total passages</div>
                    <div class="fs-2 fw-bold text-primary">
                        <?= number_format($totaux['nb_passages']) ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card carte-stat h-100"
                 style="border-left:4px solid #28a745">
                <div class="card-body text-center">
                    <div class="text-muted small">Recette totale</div>
                    <div class="fs-5 fw-bold text-success">
                        <?= formatMontant($totaux['total_recette']) ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card carte-stat h-100"
                 style="border-left:4px solid #f0a500">
                <div class="card-body text-center">
                    <div class="text-muted small">Recettes espèces</div>
                    <div class="fs-5 fw-bold" style="color:#f0a500">
                        <?= formatMontant($totaux['total_espece']) ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card carte-stat h-100"
                 style="border-left:4px solid #17a2b8">
                <div class="card-body text-center">
                    <div class="text-muted small">Recettes badge</div>
                    <div class="fs-5 fw-bold text-info">
                        <?= formatMontant($totaux['total_badge']) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tableaux de synthèse -->
    <div class="row g-3 mb-4">

        <!-- Par guichet -->
        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-header"
                     style="background:var(--couleur-primaire); color:white">
                    <i class="bi bi-building"></i> Par guichet
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Guichet</th>
                                <th>Passages</th>
                                <th>Recette</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($r = $par_guichet->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-primary">
                                        <?= $r['numero_guichet'] ?>
                                    </span>
                                </td>
                                <td><?= $r['nb'] ?></td>
                                <td class="text-success fw-bold small">
                                    <?= formatMontant($r['total']) ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Par classe -->
        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-header"
                     style="background:var(--couleur-primaire); color:white">
                    <i class="bi bi-truck"></i> Par classe
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Classe</th>
                                <th>Nb</th>
                                <th>Recette</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($r = $par_classe->fetch_assoc()): ?>
                            <tr>
                                <td><small><?= $r['nom_classe'] ?></small></td>
                                <td><?= $r['nb'] ?></td>
                                <td class="text-success fw-bold small">
                                    <?= formatMontant($r['total']) ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Par agent -->
        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-header"
                     style="background:var(--couleur-primaire); color:white">
                    <i class="bi bi-people"></i> Par agent
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Agent</th>
                                <th>Nb</th>
                                <th>Recette</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($r = $par_agent->fetch_assoc()): ?>
                            <tr>
                                <td><small><?= $r['nom_agent'] ?></small></td>
                                <td><?= $r['nb'] ?></td>
                                <td class="text-success fw-bold small">
                                    <?= formatMontant($r['total']) ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <!-- Recettes par jour -->
    <div class="card shadow-sm mb-4">
        <div class="card-header"
             style="background:var(--couleur-primaire); color:white">
            <i class="bi bi-calendar3"></i> Recettes par jour
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Passages</th>
                            <th>Recette</th>
                            <th>Progression</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $max_total = 1;
                        $jours_data = [];
                        while ($r = $par_jour->fetch_assoc()) {
                            $jours_data[] = $r;
                            if ($r['total'] > $max_total) {
                                $max_total = $r['total'];
                            }
                        }
                        foreach ($jours_data as $r):
                        $pct = round(($r['total'] / $max_total) * 100);
                        ?>
                        <tr>
                            <td>
                                <strong>
                                    <?= formatDateCourte($r['jour']) ?>
                                </strong>
                            </td>
                            <td><?= $r['nb'] ?></td>
                            <td class="fw-bold text-success">
                                <?= formatMontant($r['total']) ?>
                            </td>
                            <td style="min-width:150px">
                                <div class="progress" style="height:8px">
                                    <div class="progress-bar"
                                         style="width:<?= $pct ?>%;
                                                background:var(--couleur-primaire)">
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Liste des passages -->
    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between"
             style="background:var(--couleur-primaire); color:white">
            <span>
                <i class="bi bi-list-ul"></i>
                Liste des passages
                <span class="badge bg-light text-dark ms-1">
                    <?= $passages->num_rows ?>
                </span>
            </span>
            <small class="opacity-75">100 derniers résultats</small>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date/Heure</th>
                            <th>Plaque</th>
                            <th>Classe</th>
                            <th>Guichet</th>
                            <th>Agent</th>
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
                                <td>
                                    <span class="badge bg-primary">
                                        <?= $p['numero_guichet'] ?>
                                    </span>
                                </td>
                                <td><small><?= $p['nom_agent'] ?></small></td>
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
                                <td colspan="9"
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