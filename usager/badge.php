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

// Badge actif de l'usager
$r = $conn->query("
    SELECT * FROM badge
    WHERE id_utilisateur = $id_utilisateur
    ORDER BY date_creation DESC
    LIMIT 1
");
$badge = $r->num_rows > 0 ? $r->fetch_assoc() : null;

// -- SOUSCRIRE UN BADGE --
if (isset($_POST['action']) && $_POST['action'] === 'souscrire') {
    if ($badge && $badge['statut'] === 'actif') {
        $erreur = 'Vous avez déjà un badge actif.';
    } else {
        $numero = genererNumeroBadge();
        $expiration = date('Y-m-d H:i:s', strtotime('+2 years'));

        $stmt = $conn->prepare("
            INSERT INTO badge
                (id_utilisateur, numero_badge, solde, statut, date_expiration)
            VALUES (?, ?, 0, 'actif', ?)
        ");
        $stmt->bind_param('iss', $id_utilisateur, $numero, $expiration);
        if ($stmt->execute()) {
            $succes = 'Badge créé avec succès ! Numéro : ' . $numero;
            // Recharger le badge
            $r2 = $conn->query("
                SELECT * FROM badge
                WHERE id_utilisateur = $id_utilisateur
                AND statut = 'actif' LIMIT 1
            ");
            $badge = $r2->fetch_assoc();
        } else {
            $erreur = 'Erreur lors de la création du badge.';
        }
        $stmt->close();
    }
}

// -- BLOQUER/ACTIVER BADGE --
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id_badge = (int)$_GET['id'];
    $action   = $_GET['action'];
    if ($action === 'bloquer') {
        $stmt = $conn->prepare("
            UPDATE badge SET statut = 'bloque'
            WHERE id_badge = ? AND id_utilisateur = ?
        ");
        $stmt->bind_param('ii', $id_badge, $id_utilisateur);
        $stmt->execute();
        $succes = 'Badge bloqué.';
        $stmt->close();
    } elseif ($action === 'activer') {
        $stmt = $conn->prepare("
            UPDATE badge SET statut = 'actif'
            WHERE id_badge = ? AND id_utilisateur = ?
        ");
        $stmt->bind_param('ii', $id_badge, $id_utilisateur);
        $stmt->execute();
        $succes = 'Badge activé.';
        $stmt->close();
    }
    // Recharger
    $r = $conn->query("
        SELECT * FROM badge
        WHERE id_utilisateur = $id_utilisateur
        ORDER BY date_creation DESC LIMIT 1
    ");
    $badge = $r->num_rows > 0 ? $r->fetch_assoc() : null;
}

// Historique recharges
$recharges = $conn->query("
    SELECT r.*, b.numero_badge
    FROM recharge_badge r
    JOIN badge b ON r.id_badge = b.id_badge
    WHERE r.id_utilisateur = $id_utilisateur
    ORDER BY r.date_recharge DESC
    LIMIT 10
");

$conn->close();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Badge - Pont HKB</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .carte-badge {
            background: linear-gradient(135deg, #1a3c5e, #2d6a9f);
            color: white;
            border-radius: 16px;
            padding: 30px;
            position: relative;
            overflow: hidden;
        }
        .carte-badge::before {
            content: '';
            position: absolute;
            top: -50px; right: -50px;
            width: 200px; height: 200px;
            border-radius: 50%;
            background: rgba(255,255,255,0.05);
        }
        .badge-numero {
            font-size: 1.4rem;
            font-weight: 700;
            letter-spacing: 3px;
            color: #f0a500;
        }
        .badge-solde {
            font-size: 2.5rem;
            font-weight: 700;
        }
    </style>
</head>
<body>

<?php include '../includes/navbar_usager.php'; ?>

<div class="contenu-principal">

    <h3 class="titre-page mb-4">
        <i class="bi bi-credit-card"></i> Mon badge de télépéage
    </h3>

    <!-- Messages -->
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
        <div class="col-md-6">

            <?php if ($badge): ?>
            <!-- Carte badge -->
            <div class="carte-badge mb-3">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="small opacity-75">🌉 PONT HKB — Badge télépéage</div>
                        <div class="badge-numero mt-1">
                            <?= $badge['numero_badge'] ?>
                        </div>
                    </div>
                    <span class="badge bg-<?= couleurStatut($badge['statut']) ?> fs-6">
                        <?= $badge['statut'] ?>
                    </span>
                </div>

                <div class="mb-3">
                    <div class="small opacity-75">Solde disponible</div>
                    <div class="badge-solde">
                        <?= formatMontant($badge['solde']) ?>
                    </div>
                </div>

                <div class="d-flex justify-content-between opacity-75 small">
                    <div>
                        <div>Créé le</div>
                        <div><?= formatDateCourte($badge['date_creation']) ?></div>
                    </div>
                    <div class="text-end">
                        <div>Expire le</div>
                        <div><?= formatDateCourte($badge['date_expiration']) ?></div>
                    </div>
                </div>
            </div>

            <!-- Actions badge -->
            <div class="d-flex gap-2 mb-3">
                <a href="../usager/recharge.php"
                   class="btn btn-success flex-fill">
                    <i class="bi bi-plus-circle"></i> Recharger
                </a>
                <?php if ($badge['statut'] === 'actif'): ?>
                <a href="?action=bloquer&id=<?= $badge['id_badge'] ?>"
                   class="btn btn-outline-danger"
                   onclick="return confirm('Bloquer ce badge ?')">
                    <i class="bi bi-lock"></i> Bloquer
                </a>
                <?php else: ?>
                <a href="?action=activer&id=<?= $badge['id_badge'] ?>"
                   class="btn btn-outline-success">
                    <i class="bi bi-unlock"></i> Activer
                </a>
                <?php endif; ?>
            </div>

            <?php else: ?>
            <!-- Pas de badge -->
            <div class="card text-center py-5">
                <div class="card-body">
                    <i class="bi bi-credit-card fs-1 text-muted d-block mb-3"></i>
                    <h5>Vous n'avez pas encore de badge</h5>
                    <p class="text-muted">
                        Le badge vous permet de payer automatiquement
                        et de bénéficier de tarifs réduits.
                    </p>
                    <form method="POST">
                        <input type="hidden" name="action" value="souscrire">
                        <button type="submit" class="btn btn-pont btn-lg">
                            <i class="bi bi-credit-card"></i>
                            Souscrire un badge gratuit
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>

        </div>

        <!-- Historique recharges -->
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header"
                     style="background:var(--couleur-primaire); color:white">
                    <i class="bi bi-clock-history"></i>
                    Historique des recharges
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Montant</th>
                                    <th>Solde après</th>
                                    <th>Mode</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($recharges->num_rows > 0): ?>
                                    <?php while ($r = $recharges->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <small>
                                                <?= formatDate($r['date_recharge']) ?>
                                            </small>
                                        </td>
                                        <td class="fw-bold text-success">
                                            +<?= formatMontant($r['montant']) ?>
                                        </td>
                                        <td><?= formatMontant($r['solde_apres']) ?></td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?= $r['mode_recharge'] ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4"
                                            class="text-center text-muted py-3">
                                            Aucune recharge effectuée.
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
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>