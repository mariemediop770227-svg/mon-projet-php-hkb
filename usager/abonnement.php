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
$succes         = '';
$erreur         = '';

// Badge actif
$r = $conn->query("
    SELECT * FROM badge
    WHERE id_utilisateur = $id_utilisateur
    AND statut = 'actif' LIMIT 1
");
$badge = $r->num_rows > 0 ? $r->fetch_assoc() : null;

// Abonnement actif
$r2 = $conn->query("
    SELECT a.*, cv.nom_classe, b.numero_badge
    FROM abonnement a
    JOIN classe_vehicule cv ON a.id_classe = cv.id_classe
    JOIN badge b            ON a.id_badge  = b.id_badge
    WHERE a.id_utilisateur = $id_utilisateur
    AND a.statut = 'actif'
    LIMIT 1
");
$abonnement_actif = $r2->num_rows > 0 ? $r2->fetch_assoc() : null;

// Classes
$classes = $conn->query("SELECT * FROM classe_vehicule ORDER BY ordre");

// Tarifs abonnements
$tarifs_abo = [
    'mensuel'      => ['duree' => 1,  'remise' => 0.10],
    'trimestriel'  => ['duree' => 3,  'remise' => 0.15],
    'annuel'       => ['duree' => 12, 'remise' => 0.20],
];

// -- SOUSCRIRE --
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_classe      = (int)($_POST['id_classe']       ?? 0);
    $type           = securiser($_POST['type_abonnement'] ?? 'mensuel');

    if (!$badge) {
        $erreur = 'Vous devez avoir un badge actif pour souscrire un abonnement.';
    } elseif ($abonnement_actif) {
        $erreur = 'Vous avez déjà un abonnement actif.';
    } elseif ($id_classe === 0) {
        $erreur = 'Veuillez sélectionner une classe.';
    } else {
        // Calculer montant
        $r_c = $conn->prepare("
            SELECT * FROM classe_vehicule WHERE id_classe = ?
        ");
        $r_c->bind_param('i', $id_classe);
        $r_c->execute();
        $classe_info = $r_c->get_result()->fetch_assoc();
        $r_c->close();

        $duree   = $tarifs_abo[$type]['duree'];
        $remise  = $tarifs_abo[$type]['remise'];
        $montant = $classe_info['tarif_badge']
                   * 30 * $duree * (1 - $remise);

        $date_debut = date('Y-m-d');
        $date_fin   = date('Y-m-d', strtotime("+$duree months"));

        $stmt = $conn->prepare("
            INSERT INTO abonnement
                (id_utilisateur, id_badge, id_classe,
                 type_abonnement, montant,
                 date_debut, date_fin, statut)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'en_attente')
        ");
        $stmt->bind_param(
            'iiisdss',
            $id_utilisateur, $badge['id_badge'], $id_classe,
            $type, $montant, $date_debut, $date_fin
        );
        if ($stmt->execute()) {
            $succes = 'Demande d\'abonnement soumise !
                       En attente de validation par l\'administrateur.';
        } else {
            $erreur = 'Erreur lors de la souscription.';
        }
        $stmt->close();
    }
}

// Historique abonnements
$historique = $conn->query("
    SELECT a.*, cv.nom_classe
    FROM abonnement a
    JOIN classe_vehicule cv ON a.id_classe = cv.id_classe
    WHERE a.id_utilisateur = $id_utilisateur
    ORDER BY a.date_souscription DESC
");

$conn->close();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Abonnement - Pont HKB</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>

<?php include '../includes/navbar_usager.php'; ?>

<div class="contenu-principal">

    <h3 class="titre-page mb-4">
        <i class="bi bi-card-checklist"></i> Mon abonnement
    </h3>

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

    <div class="row g-3">

        <!-- Abonnement actif -->
        <div class="col-md-5">
            <?php if ($abonnement_actif): ?>
            <div class="card shadow-sm mb-3"
                 style="border-top:4px solid #28a745">
                <div class="card-header bg-success text-white">
                    <i class="bi bi-check-circle"></i>
                    Abonnement actif
                </div>
                <div class="card-body">
                    <table class="table table-borderless table-sm">
                        <tr>
                            <td class="text-muted">Type</td>
                            <td class="fw-bold">
                                <?= $abonnement_actif['type_abonnement'] ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">Classe</td>
                            <td><?= $abonnement_actif['nom_classe'] ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Badge</td>
                            <td>
                                <span class="badge bg-info text-dark">
                                    <?= $abonnement_actif['numero_badge'] ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">Montant</td>
                            <td class="fw-bold text-success">
                                <?= formatMontant($abonnement_actif['montant']) ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">Début</td>
                            <td>
                                <?= formatDateCourte($abonnement_actif['date_debut']) ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">Expire le</td>
                            <td class="fw-bold text-danger">
                                <?= formatDateCourte($abonnement_actif['date_fin']) ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            <?php else: ?>

            <!-- Formulaire souscription -->
            <?php if (!$badge): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    Vous devez d'abord
                    <a href="badge.php">souscrire un badge</a>
                    pour accéder aux abonnements.
                </div>
            <?php else: ?>
            <div class="card shadow-sm">
                <div class="card-header"
                     style="background:var(--couleur-primaire); color:white">
                    <i class="bi bi-plus-circle"></i>
                    Souscrire un abonnement
                </div>
                <div class="card-body">
                    <form method="POST" id="formAbonnement">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                Classe de véhicule
                                <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" name="id_classe"
                                    id="sel_classe"
                                    onchange="calculerTarif()"
                                    required>
                                <option value="">-- Choisir --</option>
                                <?php while ($c = $classes->fetch_assoc()): ?>
                                <option value="<?= $c['id_classe'] ?>"
                                        data-tarif="<?= $c['tarif_badge'] ?>">
                                    <?= $c['nom_classe'] ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                Type d'abonnement
                            </label>
                            <div class="row g-2">
                                <?php
                                $types = [
                                    'mensuel'     => ['Mensuel',     '1 mois',   '10%'],
                                    'trimestriel' => ['Trimestriel', '3 mois',   '15%'],
                                    'annuel'      => ['Annuel',      '12 mois',  '20%'],
                                ];
                                foreach ($types as $val => $info):
                                ?>
                                <div class="col-12">
                                    <div class="form-check border rounded p-3">
                                        <input class="form-check-input"
                                               type="radio"
                                               name="type_abonnement"
                                               value="<?= $val ?>"
                                               id="type_<?= $val ?>"
                                               onchange="calculerTarif()"
                                               <?= $val === 'mensuel' ? 'checked' : '' ?>>
                                        <label class="form-check-label w-100"
                                               for="type_<?= $val ?>">
                                            <div class="d-flex
                                                        justify-content-between">
                                                <span class="fw-semibold">
                                                    <?= $info[0] ?>
                                                </span>
                                                <span class="badge bg-success">
                                                    -<?= $info[2] ?>
                                                </span>
                                            </div>
                                            <small class="text-muted">
                                                Durée : <?= $info[1] ?>
                                            </small>
                                        </label>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Aperçu tarif -->
                        <div id="apercu_tarif"
                             class="alert alert-info d-none">
                            <i class="bi bi-info-circle"></i>
                            Montant estimé :
                            <strong id="montant_estime"></strong>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-pont btn-lg">
                                <i class="bi bi-check-circle"></i>
                                Soumettre la demande
                            </button>
                        </div>

                    </form>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Historique -->
        <div class="col-md-7">
            <div class="card shadow-sm">
                <div class="card-header"
                     style="background:var(--couleur-primaire); color:white">
                    <i class="bi bi-clock-history"></i>
                    Historique des abonnements
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Classe</th>
                                <th>Montant</th>
                                <th>Début</th>
                                <th>Fin</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($historique->num_rows > 0): ?>
                                <?php while ($h = $historique->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-primary">
                                            <?= $h['type_abonnement'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small><?= $h['nom_classe'] ?></small>
                                    </td>
                                    <td class="text-success fw-bold">
                                        <?= formatMontant($h['montant']) ?>
                                    </td>
                                    <td>
                                        <small>
                                            <?= formatDateCourte($h['date_debut']) ?>
                                        </small>
                                    </td>
                                    <td>
                                        <small>
                                            <?= formatDateCourte($h['date_fin']) ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge
                                              bg-<?= couleurStatut($h['statut']) ?>">
                                            <?= $h['statut'] ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6"
                                        class="text-center text-muted py-4">
                                        Aucun abonnement souscrit.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const remises = {
    mensuel:     { duree: 1,  remise: 0.10 },
    trimestriel: { duree: 3,  remise: 0.15 },
    annuel:      { duree: 12, remise: 0.20 }
};

function calculerTarif() {
    const sel    = document.getElementById('sel_classe');
    const tarif  = parseFloat(sel.options[sel.selectedIndex]?.dataset?.tarif || 0);
    const type   = document.querySelector('input[name="type_abonnement"]:checked')?.value;

    if (!tarif || !type) return;

    const r       = remises[type];
    const montant = tarif * 30 * r.duree * (1 - r.remise);
    const formate = new Intl.NumberFormat('fr-FR').format(Math.round(montant));

    document.getElementById('montant_estime').textContent = formate + ' FCFA';
    document.getElementById('apercu_tarif').classList.remove('d-none');
}
</script>
</body>
</html>