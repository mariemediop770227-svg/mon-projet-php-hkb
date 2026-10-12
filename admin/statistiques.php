<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

$conn = getConnexion();

// Période
$annee = (int)($_GET['annee'] ?? date('Y'));
$mois  = (int)($_GET['mois']  ?? date('m'));

// Stats globales
$stats = $conn->query("
    SELECT
        COUNT(*)                                      as total_passages,
        COALESCE(SUM(montant), 0)                    as total_recettes,
        COALESCE(AVG(montant), 0)                    as moyenne_passage,
        COUNT(DISTINCT DATE(date_heure))             as jours_actifs,
        COUNT(DISTINCT id_guichet)                   as guichets_actifs,
        COUNT(DISTINCT plaque_saisie)                as vehicules_uniques
    FROM passage
    WHERE YEAR(date_heure) = $annee
    AND MONTH(date_heure)  = $mois
    AND statut = 'valide'
")->fetch_assoc();

// Passages par heure (pour graphique)
$par_heure = $conn->query("
    SELECT HOUR(date_heure) as heure, COUNT(*) as nb
    FROM passage
    WHERE YEAR(date_heure) = $annee
    AND MONTH(date_heure)  = $mois
    AND statut = 'valide'
    GROUP BY HOUR(date_heure)
    ORDER BY heure
");
$heures_data = array_fill(0, 24, 0);
while ($r = $par_heure->fetch_assoc()) {
    $heures_data[$r['heure']] = (int)$r['nb'];
}

// Passages par jour du mois
$par_jour = $conn->query("
    SELECT DAY(date_heure) as jour, COUNT(*) as nb,
           SUM(montant) as total
    FROM passage
    WHERE YEAR(date_heure) = $annee
    AND MONTH(date_heure)  = $mois
    AND statut = 'valide'
    GROUP BY DAY(date_heure)
    ORDER BY jour
");
$jours_nb    = array_fill(1, 31, 0);
$jours_total = array_fill(1, 31, 0);
while ($r = $par_jour->fetch_assoc()) {
    $jours_nb[$r['jour']]    = (int)$r['nb'];
    $jours_total[$r['jour']] = (float)$r['total'];
}

// Répartition par classe
$par_classe = $conn->query("
    SELECT cv.nom_classe, COUNT(*) as nb,
           SUM(p.montant) as total
    FROM passage p
    JOIN classe_vehicule cv ON p.id_classe = cv.id_classe
    WHERE YEAR(p.date_heure) = $annee
    AND MONTH(p.date_heure)  = $mois
    AND p.statut = 'valide'
    GROUP BY cv.id_classe
    ORDER BY nb DESC
");
$classes_labels  = [];
$classes_nb      = [];
$classes_total   = [];
while ($r = $par_classe->fetch_assoc()) {
    $classes_labels[] = $r['nom_classe'];
    $classes_nb[]     = (int)$r['nb'];
    $classes_total[]  = (float)$r['total'];
}

// Répartition mode paiement
$par_mode = $conn->query("
    SELECT mode_paiement, COUNT(*) as nb,
           SUM(montant) as total
    FROM passage
    WHERE YEAR(date_heure) = $annee
    AND MONTH(date_heure)  = $mois
    AND statut = 'valide'
    GROUP BY mode_paiement
");
$modes_labels = [];
$modes_nb     = [];
while ($r = $par_mode->fetch_assoc()) {
    $modes_labels[] = $r['mode_paiement'];
    $modes_nb[]     = (int)$r['nb'];
}

// Évolution 12 derniers mois
$evolution = $conn->query("
    SELECT DATE_FORMAT(date_heure, '%Y-%m') as mois_annee,
           COUNT(*) as nb,
           SUM(montant) as total
    FROM passage
    WHERE date_heure >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    AND statut = 'valide'
    GROUP BY DATE_FORMAT(date_heure, '%Y-%m')
    ORDER BY mois_annee
");
$evo_labels  = [];
$evo_nb      = [];
$evo_total   = [];
while ($r = $evolution->fetch_assoc()) {
    $evo_labels[] = $r['mois_annee'];
    $evo_nb[]     = (int)$r['nb'];
    $evo_total[]  = (float)$r['total'];
}

// Top plaques
$top_plaques = $conn->query("
    SELECT plaque_saisie, COUNT(*) as nb,
           SUM(montant) as total
    FROM passage
    WHERE YEAR(date_heure) = $annee
    AND MONTH(date_heure)  = $mois
    AND statut = 'valide'
    GROUP BY plaque_saisie
    ORDER BY nb DESC
    LIMIT 10
");

$conn->close();

// Noms des mois
$noms_mois = [
    1=>'Janvier',2=>'Février',3=>'Mars',4=>'Avril',
    5=>'Mai',6=>'Juin',7=>'Juillet',8=>'Août',
    9=>'Septembre',10=>'Octobre',11=>'Novembre',12=>'Décembre'
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiques - Admin Pont HKB</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>

<?php include '../includes/navbar_admin.php'; ?>

<div class="contenu-principal">

    <!-- Titre + filtre période -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="titre-page mb-0">
            <i class="bi bi-bar-chart-line"></i> Statistiques
        </h3>
        <form method="GET" class="d-flex gap-2 align-items-center">
            <select class="form-select form-select-sm" name="mois">
                <?php foreach ($noms_mois as $num => $nom): ?>
                <option value="<?= $num ?>"
                        <?= $mois == $num ? 'selected' : '' ?>>
                    <?= $nom ?>
                </option>
                <?php endforeach; ?>
            </select>
            <select class="form-select form-select-sm" name="annee">
                <?php for ($y = date('Y'); $y >= date('Y')-3; $y--): ?>
                <option value="<?= $y ?>"
                        <?= $annee == $y ? 'selected' : '' ?>>
                    <?= $y ?>
                </option>
                <?php endfor; ?>
            </select>
            <button type="submit" class="btn btn-pont btn-sm">
                <i class="bi bi-search"></i>
            </button>
        </form>
    </div>

    <!-- Période affichée -->
    <div class="alert alert-info py-2 mb-4">
        <i class="bi bi-calendar3"></i>
        Statistiques pour :
        <strong><?= $noms_mois[$mois] ?> <?= $annee ?></strong>
    </div>

    <!-- Cartes KPI -->
    <div class="row g-3 mb-4">
        <div class="col-md-2">
            <div class="card carte-stat text-center h-100"
                 style="border-top:4px solid #1a3c5e">
                <div class="card-body">
                    <div class="text-muted small">Total passages</div>
                    <div class="fs-2 fw-bold text-primary">
                        <?= number_format($stats['total_passages']) ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card carte-stat text-center h-100"
                 style="border-top:4px solid #28a745">
                <div class="card-body">
                    <div class="text-muted small">Recettes totales</div>
                    <div class="fs-6 fw-bold text-success">
                        <?= formatMontant($stats['total_recettes']) ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card carte-stat text-center h-100"
                 style="border-top:4px solid #f0a500">
                <div class="card-body">
                    <div class="text-muted small">Moy. / passage</div>
                    <div class="fs-6 fw-bold" style="color:#f0a500">
                        <?= formatMontant(round($stats['moyenne_passage'])) ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card carte-stat text-center h-100"
                 style="border-top:4px solid #17a2b8">
                <div class="card-body">
                    <div class="text-muted small">Jours actifs</div>
                    <div class="fs-2 fw-bold text-info">
                        <?= $stats['jours_actifs'] ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card carte-stat text-center h-100"
                 style="border-top:4px solid #6f42c1">
                <div class="card-body">
                    <div class="text-muted small">Guichets actifs</div>
                    <div class="fs-2 fw-bold text-purple">
                        <?= $stats['guichets_actifs'] ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card carte-stat text-center h-100"
                 style="border-top:4px solid #dc3545">
                <div class="card-body">
                    <div class="text-muted small">Véhicules uniques</div>
                    <div class="fs-2 fw-bold text-danger">
                        <?= $stats['vehicules_uniques'] ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Graphiques ligne 1 -->
    <div class="row g-3 mb-4">

        <!-- Passages par heure -->
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header"
                     style="background:var(--couleur-primaire); color:white">
                    <i class="bi bi-clock"></i>
                    Passages par heure de la journée
                </div>
                <div class="card-body">
                    <canvas id="graphHeure" height="100"></canvas>
                </div>
            </div>
        </div>

        <!-- Répartition mode paiement -->
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header"
                     style="background:var(--couleur-primaire); color:white">
                    <i class="bi bi-pie-chart"></i>
                    Modes de paiement
                </div>
                <div class="card-body d-flex align-items-center
                            justify-content-center">
                    <canvas id="graphMode" height="200"></canvas>
                </div>
            </div>
        </div>

    </div>

    <!-- Graphiques ligne 2 -->
    <div class="row g-3 mb-4">

        <!-- Passages par jour du mois -->
        <div class="col-md-12">
            <div class="card shadow-sm">
                <div class="card-header"
                     style="background:var(--couleur-primaire); color:white">
                    <i class="bi bi-calendar3"></i>
                    Passages par jour —
                    <?= $noms_mois[$mois] ?> <?= $annee ?>
                </div>
                <div class="card-body">
                    <canvas id="graphJour" height="80"></canvas>
                </div>
            </div>
        </div>

    </div>

    <!-- Graphiques ligne 3 -->
    <div class="row g-3 mb-4">

        <!-- Évolution 12 mois -->
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header"
                     style="background:var(--couleur-primaire); color:white">
                    <i class="bi bi-graph-up"></i>
                    Évolution sur 12 mois
                </div>
                <div class="card-body">
                    <canvas id="graphEvolution" height="100"></canvas>
                </div>
            </div>
        </div>

        <!-- Répartition par classe -->
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header"
                     style="background:var(--couleur-primaire); color:white">
                    <i class="bi bi-truck"></i>
                    Répartition par classe
                </div>
                <div class="card-body">
                    <canvas id="graphClasse" height="200"></canvas>
                </div>
            </div>
        </div>

    </div>

    <!-- Top plaques -->
    <div class="card shadow-sm">
        <div class="card-header"
             style="background:var(--couleur-primaire); color:white">
            <i class="bi bi-trophy"></i>
            Top 10 — Véhicules les plus fréquents
        </div>
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Plaque</th>
                        <th>Passages</th>
                        <th>Total payé</th>
                        <th>Fréquence</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $rang = 1;
                    $medailles = ['🥇','🥈','🥉'];
                    while ($tp = $top_plaques->fetch_assoc()):
                    ?>
                    <tr>
                        <td>
                            <?= isset($medailles[$rang-1])
                                ? $medailles[$rang-1]
                                : $rang ?>
                        </td>
                        <td>
                            <span class="badge bg-dark fs-6">
                                <?= $tp['plaque_saisie'] ?>
                            </span>
                        </td>
                        <td class="fw-bold"><?= $tp['nb'] ?></td>
                        <td class="text-success fw-bold">
                            <?= formatMontant($tp['total']) ?>
                        </td>
                        <td style="min-width:150px">
                            <div class="progress" style="height:8px">
                                <div class="progress-bar bg-primary"
                                     style="width:<?= min(100, $tp['nb'] * 10) ?>%">
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php $rang++; endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script>
const COULEUR_PRIMAIRE  = '#1a3c5e';
const COULEUR_SECONDAIRE = '#f0a500';

// -- Graphique passages par heure --
new Chart(document.getElementById('graphHeure'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_keys($heures_data)) ?>,
        datasets: [{
            label: 'Passages',
            data: <?= json_encode(array_values($heures_data)) ?>,
            backgroundColor: COULEUR_PRIMAIRE,
            borderRadius: 4
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            x: { title: { display: true, text: 'Heure' } },
            y: { title: { display: true, text: 'Passages' },
                 beginAtZero: true }
        }
    }
});

// -- Graphique mode paiement (camembert) --
new Chart(document.getElementById('graphMode'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($modes_labels) ?>,
        datasets: [{
            data: <?= json_encode($modes_nb) ?>,
            backgroundColor: [
                COULEUR_PRIMAIRE,
                COULEUR_SECONDAIRE,
                '#28a745'
            ],
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});

// -- Graphique passages par jour --
const jourLabels = Array.from({length:31}, (_,i) => i+1);
new Chart(document.getElementById('graphJour'), {
    type: 'line',
    data: {
        labels: jourLabels,
        datasets: [
            {
                label: 'Passages',
                data: <?= json_encode(array_values($jours_nb)) ?>,
                borderColor: COULEUR_PRIMAIRE,
                backgroundColor: 'rgba(26,60,94,0.1)',
                fill: true,
                tension: 0.3,
                yAxisID: 'y'
            },
            {
                label: 'Recettes (FCFA)',
                data: <?= json_encode(array_values($jours_total)) ?>,
                borderColor: COULEUR_SECONDAIRE,
                backgroundColor: 'rgba(240,165,0,0.1)',
                fill: true,
                tension: 0.3,
                yAxisID: 'y1'
            }
        ]
    },
    options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        scales: {
            y:  { type: 'linear', display: true,
                  position: 'left',
                  title: { display: true, text: 'Passages' } },
            y1: { type: 'linear', display: true,
                  position: 'right',
                  title: { display: true, text: 'Recettes (FCFA)' },
                  grid: { drawOnChartArea: false } }
        }
    }
});

// -- Évolution 12 mois --
new Chart(document.getElementById('graphEvolution'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($evo_labels) ?>,
        datasets: [
            {
                label: 'Passages',
                data: <?= json_encode($evo_nb) ?>,
                backgroundColor: COULEUR_PRIMAIRE,
                borderRadius: 4,
                yAxisID: 'y'
            },
            {
                label: 'Recettes',
                data: <?= json_encode($evo_total) ?>,
                type: 'line',
                borderColor: COULEUR_SECONDAIRE,
                backgroundColor: 'transparent',
                tension: 0.3,
                yAxisID: 'y1'
            }
        ]
    },
    options: {
        responsive: true,
        scales: {
            y:  { position: 'left',
                  title: { display: true, text: 'Passages' } },
            y1: { position: 'right',
                  title: { display: true, text: 'Recettes' },
                  grid: { drawOnChartArea: false } }
        }
    }
});

// -- Répartition par classe --
new Chart(document.getElementById('graphClasse'), {
    type: 'pie',
    data: {
        labels: <?= json_encode($classes_labels) ?>,
        datasets: [{
            data: <?= json_encode($classes_nb) ?>,
            backgroundColor: [
                '#1a3c5e','#f0a500','#28a745','#17a2b8','#dc3545'
            ],
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'bottom' } }
    }
});
</script>
</body>
</html>