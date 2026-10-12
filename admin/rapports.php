<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

$conn           = getConnexion();
$id_utilisateur = $_SESSION['id_utilisateur'];
$succes         = '';
$erreur         = '';
$rapport_genere = null;

// -- GÉNÉRER UN RAPPORT --
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type        = securiser($_POST['type_rapport'] ?? 'journalier');
    $date_debut  = securiser($_POST['date_debut']   ?? date('Y-m-d'));
    $date_fin    = securiser($_POST['date_fin']     ?? date('Y-m-d'));
    $titre       = securiser($_POST['titre']        ?? 'Rapport');

    // Données du rapport
    $donnees = [];

    // Totaux
    $r = $conn->query("
        SELECT
            COUNT(*)                    as nb_passages,
            COALESCE(SUM(montant), 0)  as total_recette,
            COALESCE(SUM(CASE WHEN mode_paiement='espece'
                         THEN montant END), 0) as espece,
            COALESCE(SUM(CASE WHEN mode_paiement='badge'
                         THEN montant END), 0) as badge
        FROM passage
        WHERE DATE(date_heure) BETWEEN '$date_debut' AND '$date_fin'
        AND statut = 'valide'
    ");
    $donnees['totaux'] = $r->fetch_assoc();

    // Par classe
    $r2 = $conn->query("
        SELECT cv.nom_classe,
               COUNT(*) as nb,
               SUM(p.montant) as total
        FROM passage p
        JOIN classe_vehicule cv ON p.id_classe = cv.id_classe
        WHERE DATE(p.date_heure) BETWEEN '$date_debut' AND '$date_fin'
        AND p.statut = 'valide'
        GROUP BY cv.id_classe
    ");
    while ($row = $r2->fetch_assoc()) {
        $donnees['par_classe'][] = $row;
    }

    // Par guichet
    $r3 = $conn->query("
        SELECT g.numero_guichet, g.nom_guichet,
               COUNT(*) as nb,
               SUM(p.montant) as total
        FROM passage p
        JOIN guichet g ON p.id_guichet = g.id_guichet
        WHERE DATE(p.date_heure) BETWEEN '$date_debut' AND '$date_fin'
        AND p.statut = 'valide'
        GROUP BY g.id_guichet
    ");
    while ($row = $r3->fetch_assoc()) {
        $donnees['par_guichet'][] = $row;
    }

    // Sauvegarder en base
    $contenu = json_encode($donnees);
    $stmt = $conn->prepare("
        INSERT INTO rapport
            (id_utilisateur, type_rapport, titre,
             date_debut, date_fin, contenu)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        'isssss',
        $id_utilisateur, $type, $titre,
        $date_debut, $date_fin, $contenu
    );
    if ($stmt->execute()) {
        $rapport_genere = [
            'titre'      => $titre,
            'type'       => $type,
            'date_debut' => $date_debut,
            'date_fin'   => $date_fin,
            'donnees'    => $donnees,
        ];
        $succes = 'Rapport généré avec succès !';
    }
    $stmt->close();
}

// Historique des rapports
$rapports = $conn->query("
    SELECT r.*, CONCAT(u.prenom,' ',u.nom) as nom_generateur
    FROM rapport r
    JOIN utilisateur u ON r.id_utilisateur = u.id_utilisateur
    ORDER BY r.date_generation DESC
    LIMIT 20
");

$conn->close();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapports - Admin Pont HKB</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        @media print {
            .sidebar, .btn, form, .card-header,
            .no-print { display: none !important; }
            .contenu-principal { margin-left: 0 !important; }
            .rapport-print { display: block !important; }
        }
    </style>
</head>
<body>

<?php include '../includes/navbar_admin.php'; ?>

<div class="contenu-principal">

    <h3 class="titre-page mb-4">
        <i class="bi bi-file-earmark-bar-graph"></i> Génération de Rapports
    </h3>

    <?php if (!empty($succes)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle-fill"></i> <?= $succes ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-3">

        <!-- Formulaire génération -->
        <div class="col-md-4 no-print">
            <div class="card shadow-sm">
                <div class="card-header"
                     style="background:var(--couleur-primaire); color:white">
                    <i class="bi bi-plus-circle"></i> Nouveau rapport
                </div>
                <div class="card-body">
                    <form method="POST">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                Titre du rapport
                            </label>
                            <input type="text" class="form-control"
                                   name="titre"
                                   placeholder="Rapport mensuel Mai 2024"
                                   required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                Type de rapport
                            </label>
                            <select class="form-select" name="type_rapport"
                                    onchange="setDates(this.value)">
                                <option value="journalier">Journalier</option>
                                <option value="hebdomadaire">Hebdomadaire</option>
                                <option value="mensuel" selected>Mensuel</option>
                                <option value="annuel">Annuel</option>
                                <option value="personnalise">Personnalisé</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Date début</label>
                            <input type="date" class="form-control"
                                   name="date_debut" id="date_debut"
                                   value="<?= date('Y-m-01') ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Date fin</label>
                            <input type="date" class="form-control"
                                   name="date_fin" id="date_fin"
                                   value="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-pont">
                                <i class="bi bi-file-earmark-bar-graph"></i>
                                Générer le rapport
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Historique rapports -->
            <div class="card shadow-sm mt-3">
                <div class="card-header"
                     style="background:var(--couleur-primaire); color:white">
                    <i class="bi bi-clock-history"></i> Rapports précédents
                </div>
                <div class="list-group list-group-flush">
                    <?php if ($rapports->num_rows > 0): ?>
                        <?php while ($r = $rapports->fetch_assoc()): ?>
                        <div class="list-group-item">
                            <div class="fw-semibold small">
                                <?= $r['titre'] ?>
                            </div>
                            <div class="text-muted" style="font-size:0.75rem">
                                <?= formatDateCourte($r['date_debut']) ?>
                                au <?= formatDateCourte($r['date_fin']) ?>
                            </div>
                            <div class="d-flex justify-content-between
                                        align-items-center mt-1">
                                <span class="badge bg-secondary">
                                    <?= $r['type_rapport'] ?>
                                </span>
                                <small class="text-muted">
                                    <?= formatDate($r['date_generation']) ?>
                                </small>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="list-group-item text-muted text-center py-3">
                            Aucun rapport généré.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Aperçu du rapport -->
        <div class="col-md-8">
            <?php if ($rapport_genere): ?>
            <div id="rapport-apercu">

                <!-- En-tête rapport -->
                <div class="card shadow-sm mb-3">
                    <div class="card-body text-center py-4"
                         style="background:linear-gradient(135deg,#1a3c5e,#2d6a9f);
                                color:white; border-radius:8px">
                        <div style="font-size:2rem">🌉</div>
                        <h4 class="mt-2"><?= $rapport_genere['titre'] ?></h4>
                        <div class="opacity-75">
                            Période : <?= formatDateCourte($rapport_genere['date_debut']) ?>
                            au <?= formatDateCourte($rapport_genere['date_fin']) ?>
                        </div>
                        <div class="opacity-75 small">
                            Généré le <?= date('d/m/Y à H:i') ?>
                            par <?= $_SESSION['prenom'].' '.$_SESSION['nom'] ?>
                        </div>
                    </div>
                </div>

                <?php $d = $rapport_genere['donnees']; ?>

                <!-- Totaux -->
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <div class="card text-center border-primary">
                            <div class="card-body py-3">
                                <div class="text-muted small">Passages</div>
                                <div class="fs-3 fw-bold text-primary">
                                    <?= number_format($d['totaux']['nb_passages']) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center border-success">
                            <div class="card-body py-3">
                                <div class="text-muted small">Total recettes</div>
                                <div class="fs-6 fw-bold text-success">
                                    <?= formatMontant($d['totaux']['total_recette']) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center border-warning">
                            <div class="card-body py-3">
                                <div class="text-muted small">Espèces</div>
                                <div class="fs-6 fw-bold text-warning">
                                    <?= formatMontant($d['totaux']['espece']) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center border-info">
                            <div class="card-body py-3">
                                <div class="text-muted small">Badge</div>
                                <div class="fs-6 fw-bold text-info">
                                    <?= formatMontant($d['totaux']['badge']) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Par classe -->
                <?php if (!empty($d['par_classe'])): ?>
                <div class="card shadow-sm mb-3">
                    <div class="card-header fw-semibold">
                        <i class="bi bi-truck"></i>
                        Répartition par classe de véhicule
                    </div>
                    <div class="card-body p-0">
                        <table class="table mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Classe</th>
                                    <th>Passages</th>
                                    <th>Recette</th>
                                    <th>%</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($d['par_classe'] as $c): ?>
                                <tr>
                                    <td><?= $c['nom_classe'] ?></td>
                                    <td><?= $c['nb'] ?></td>
                                    <td class="text-success fw-bold">
                                        <?= formatMontant($c['total']) ?>
                                    </td>
                                    <td>
                                        <?= $d['totaux']['total_recette'] > 0
                                            ? round(($c['total'] /
                                              $d['totaux']['total_recette']) * 100, 1)
                                            : 0 ?>%
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Par guichet -->
                <?php if (!empty($d['par_guichet'])): ?>
                <div class="card shadow-sm mb-3">
                    <div class="card-header fw-semibold">
                        <i class="bi bi-building"></i>
                        Répartition par guichet
                    </div>
                    <div class="card-body p-0">
                        <table class="table mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Guichet</th>
                                    <th>Passages</th>
                                    <th>Recette</th>
                                    <th>%</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($d['par_guichet'] as $g): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-primary me-1">
                                            <?= $g['numero_guichet'] ?>
                                        </span>
                                        <?= $g['nom_guichet'] ?>
                                    </td>
                                    <td><?= $g['nb'] ?></td>
                                    <td class="text-success fw-bold">
                                        <?= formatMontant($g['total']) ?>
                                    </td>
                                    <td>
                                        <?= $d['totaux']['total_recette'] > 0
                                            ? round(($g['total'] /
                                              $d['totaux']['total_recette']) * 100, 1)
                                            : 0 ?>%
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Bouton imprimer -->
                <div class="text-center no-print">
                    <button class="btn btn-pont btn-lg"
                            onclick="window.print()">
                        <i class="bi bi-printer"></i>
                        Imprimer / Exporter PDF
                    </button>
                </div>

            </div>
            <?php else: ?>
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex align-items-center
                            justify-content-center text-center py-5">
                    <div>
                        <i class="bi bi-file-earmark-bar-graph fs-1
                                  text-muted d-block mb-3"></i>
                        <h5 class="text-muted">Aucun rapport généré</h5>
                        <p class="text-muted">
                            Remplissez le formulaire à gauche
                            pour générer un rapport.
                        </p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function setDates(type) {
    const debut = document.getElementById('date_debut');
    const fin   = document.getElementById('date_fin');
    const now   = new Date();

    if (type === 'journalier') {
        debut.value = fin.value = now.toISOString().split('T')[0];
    } else if (type === 'hebdomadaire') {
        const lundi = new Date(now);
        lundi.setDate(now.getDate() - now.getDay() + 1);
        debut.value = lundi.toISOString().split('T')[0];
        fin.value   = now.toISOString().split('T')[0];
    } else if (type === 'mensuel') {
        debut.value = now.getFullYear() + '-'
            + String(now.getMonth()+1).padStart(2,'0') + '-01';
        fin.value   = now.toISOString().split('T')[0];
    } else if (type === 'annuel') {
        debut.value = now.getFullYear() + '-01-01';
        fin.value   = now.getFullYear() + '-12-31';
    }
}
</script>
</body>
</html>