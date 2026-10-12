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

// Badge actif
$r = $conn->query("
    SELECT * FROM badge
    WHERE id_utilisateur = $id_utilisateur
    AND statut = 'actif' LIMIT 1
");
$badge = $r->num_rows > 0 ? $r->fetch_assoc() : null;

// -- RECHARGER --
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $badge) {
    $montant      = (float)($_POST['montant'] ?? 0);
    $mode_recharge = securiser($_POST['mode_recharge'] ?? 'espece');

    if ($montant < 500) {
        $erreur = 'Le montant minimum de recharge est de 500 FCFA.';
    } elseif ($montant > 500000) {
        $erreur = 'Le montant maximum de recharge est de 500 000 FCFA.';
    } else {
        $solde_avant = $badge['solde'];
        $solde_apres = $solde_avant + $montant;

        // Mettre à jour le solde
        $stmt = $conn->prepare("
            UPDATE badge SET solde = ? WHERE id_badge = ?
        ");
        $stmt->bind_param('di', $solde_apres, $badge['id_badge']);

        if ($stmt->execute()) {
            // Enregistrer la recharge
            $stmt2 = $conn->prepare("
                INSERT INTO recharge_badge
                    (id_badge, id_utilisateur, montant,
                     solde_avant, solde_apres, mode_recharge)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt2->bind_param(
                'iiddds',
                $badge['id_badge'], $id_utilisateur,
                $montant, $solde_avant, $solde_apres, $mode_recharge
            );
            $stmt2->execute();
            $stmt2->close();

            $succes = 'Recharge de ' . formatMontant($montant)
                    . ' effectuée ! Nouveau solde : '
                    . formatMontant($solde_apres);

            // Recharger le badge
            $badge['solde'] = $solde_apres;
        }
        $stmt->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recharge Badge - Pont HKB</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>

<?php include '../includes/navbar_usager.php'; ?>

<div class="contenu-principal">

    <h3 class="titre-page mb-4">
        <i class="bi bi-plus-circle"></i> Recharger mon badge
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

    <?php if (!$badge): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle-fill"></i>
            Vous n'avez pas de badge actif.
            <a href="badge.php" class="btn btn-warning btn-sm ms-2">
                Souscrire un badge
            </a>
        </div>
    <?php else: ?>

    <div class="row g-3 justify-content-center">
        <div class="col-md-6">

            <!-- Solde actuel -->
            <div class="card mb-3 text-center"
                 style="background:linear-gradient(135deg,#1a3c5e,#2d6a9f);
                        color:white; border-radius:12px">
                <div class="card-body py-4">
                    <div class="small opacity-75">Badge : <?= $badge['numero_badge'] ?></div>
                    <div class="small opacity-75 mb-2">Solde actuel</div>
                    <div style="font-size:2.5rem; font-weight:700; color:#f0a500">
                        <?= formatMontant($badge['solde']) ?>
                    </div>
                </div>
            </div>

            <!-- Formulaire recharge -->
            <div class="card shadow-sm">
                <div class="card-header"
                     style="background:var(--couleur-primaire); color:white">
                    <i class="bi bi-plus-circle"></i> Effectuer une recharge
                </div>
                <div class="card-body">
                    <form method="POST">

                        <!-- Montants rapides -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                Montant rapide
                            </label>
                            <div class="d-flex gap-2 flex-wrap">
                                <?php foreach ([2000,5000,10000,20000,50000] as $m): ?>
                                <button type="button"
                                        class="btn btn-outline-primary btn-sm"
                                        onclick="document.getElementById('montant').value=<?= $m ?>">
                                    <?= number_format($m, 0, ',', ' ') ?> F
                                </button>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                Montant (FCFA)
                                <span class="text-danger">*</span>
                            </label>
                            <input type="number"
                                   class="form-control form-control-lg"
                                   name="montant"
                                   id="montant"
                                   min="500"
                                   max="500000"
                                   placeholder="Ex: 5000"
                                   required>
                            <div class="form-text">
                                Minimum : 500 FCFA — Maximum : 500 000 FCFA
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">
                                Mode de recharge
                            </label>
                            <select class="form-select" name="mode_recharge">
                                <option value="espece">Espèces</option>
                                <option value="mobile_money">Mobile Money</option>
                                <option value="virement">Virement bancaire</option>
                            </select>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="bi bi-check-circle"></i>
                                Confirmer la recharge
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>