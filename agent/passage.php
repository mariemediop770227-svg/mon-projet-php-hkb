<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'agent') {
    header('Location: ../auth/login.php');
    exit();
}

$conn     = getConnexion();
$id_agent = $_SESSION['id_utilisateur'];
$erreur   = '';
$succes   = '';
$passage_enregistre = null;

// Guichet de l'agent
$r = $conn->query("
    SELECT ag.id_guichet, g.nom_guichet, g.numero_guichet, g.statut
    FROM agent_guichet ag
    JOIN guichet g ON ag.id_guichet = g.id_guichet
    WHERE ag.id_utilisateur = $id_agent
    AND ag.statut = 'actif'
    LIMIT 1
");
$guichet_agent = $r->num_rows > 0 ? $r->fetch_assoc() : null;

// Classes de véhicules
$classes = $conn->query("SELECT * FROM classe_vehicule ORDER BY ordre");

// ============================================================
// ENREGISTREMENT DU PASSAGE
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $plaque        = strtoupper(securiser($_POST['plaque'] ?? ''));
    $id_classe     = (int)($_POST['id_classe'] ?? 0);
    $mode_paiement = securiser($_POST['mode_paiement'] ?? 'espece');
    $sens          = securiser($_POST['sens'] ?? 'entree');
    $montant_recu  = (float)($_POST['montant_recu'] ?? 0);
    $numero_badge  = strtoupper(securiser($_POST['numero_badge'] ?? ''));
    $id_guichet    = $guichet_agent ? $guichet_agent['id_guichet'] : 0;

    if (empty($plaque)) {
        $erreur = 'La plaque d\'immatriculation est obligatoire.';
    } elseif ($id_classe === 0) {
        $erreur = 'Veuillez sélectionner une classe de véhicule.';
    } elseif ($id_guichet === 0) {
        $erreur = 'Vous n\'êtes affecté à aucun guichet. Contactez l\'administrateur.';
    } else {

        // Récupérer le tarif selon la classe
        $r_classe = $conn->prepare("
            SELECT * FROM classe_vehicule WHERE id_classe = ?
        ");
        $r_classe->bind_param('i', $id_classe);
        $r_classe->execute();
        $classe_info = $r_classe->get_result()->fetch_assoc();
        $r_classe->close();

        // Calculer le montant
        $montant = ($mode_paiement === 'badge' || $mode_paiement === 'abonnement')
            ? $classe_info['tarif_badge']
            : $classe_info['tarif_normal'];

        // Chercher le véhicule par plaque
        $r_veh = $conn->prepare("
            SELECT id_vehicule FROM vehicule
            WHERE plaque = ? AND statut = 'actif'
        ");
        $r_veh->bind_param('s', $plaque);
        $r_veh->execute();
        $r_veh->store_result();
        $id_vehicule = null;
        if ($r_veh->num_rows > 0) {
            $r_veh->bind_result($id_vehicule);
            $r_veh->fetch();
        }
        $r_veh->close();

        // Vérifier le badge si paiement badge
        $id_badge = null;
        if ($mode_paiement === 'badge') {
            if (empty($numero_badge)) {
                $erreur = 'Veuillez saisir le numéro de badge.';
            } else {
                $r_badge = $conn->prepare("
                    SELECT id_badge, solde, statut
                    FROM badge WHERE numero_badge = ?
                ");
                $r_badge->bind_param('s', $numero_badge);
                $r_badge->execute();
                $badge_info = $r_badge->get_result()->fetch_assoc();
                $r_badge->close();

                if (!$badge_info) {
                    $erreur = 'Badge introuvable.';
                } elseif ($badge_info['statut'] !== 'actif') {
                    $erreur = 'Ce badge est ' . $badge_info['statut'] . '.';
                } elseif ($badge_info['solde'] < $montant) {
                    $erreur = 'Solde insuffisant. Solde actuel : '
                        . formatMontant($badge_info['solde'])
                        . ' — Montant requis : ' . formatMontant($montant);
                } else {
                    $id_badge = $badge_info['id_badge'];
                }
            }
        }

        if (empty($erreur)) {
        
           // Insérer le passage
                                            
if ($id_vehicule) {
    $stmt = $conn->prepare("
        INSERT INTO passage
            (id_vehicule, id_guichet, id_agent, id_classe,
             plaque_saisie, sens, mode_paiement, montant, statut)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'valide')
    ");
    $stmt->bind_param(
        'iiiiissd',
        $id_vehicule, $id_guichet, $id_agent, $id_classe,
        $plaque, $sens, $mode_paiement, $montant
    );
} else {
    $stmt = $conn->prepare("
        INSERT INTO passage
            (id_guichet, id_agent, id_classe,
             plaque_saisie, sens, mode_paiement, montant, statut)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'valide')
    ");
    $stmt->bind_param(
        'iiiissd',
        $id_guichet, $id_agent, $id_classe,
        $plaque, $sens, $mode_paiement, $montant
    );
}

            if ($stmt->execute()) {
                $id_passage = $conn->insert_id;

                // Insérer le paiement
                $monnaie = ($mode_paiement === 'espece')
                    ? max(0, $montant_recu - $montant) : 0;

                $stmt2 = $conn->prepare("
                    INSERT INTO paiement
                        (id_passage, id_badge, mode_paiement,
                         montant, montant_rendu, statut)
                    VALUES (?, ?, ?, ?, ?, 'effectue')
                ");
                $stmt2->bind_param(
                    'iisdd',
                    $id_passage, $id_badge,
                    $mode_paiement, $montant, $monnaie
                );
                $stmt2->execute();
                $stmt2->close();

                // Débiter le badge si paiement badge
                if ($mode_paiement === 'badge' && $id_badge) {
                    $debit = $conn->prepare("
                        UPDATE badge SET solde = solde - ?
                        WHERE id_badge = ?
                    ");
                    $debit->bind_param('di', $montant, $id_badge);
                    $debit->execute();
                    $debit->close();
                }

                // Récupérer infos du passage pour reçu
                $r_recap = $conn->query("
                    SELECT p.*, cv.nom_classe,
                           g.nom_guichet, g.numero_guichet
                    FROM passage p
                    JOIN classe_vehicule cv ON p.id_classe  = cv.id_classe
                    JOIN guichet g          ON p.id_guichet = g.id_guichet
                    WHERE p.id_passage = $id_passage
                ");
                $passage_enregistre = $r_recap->fetch_assoc();
                $passage_enregistre['montant_recu']   = $montant_recu;
                $passage_enregistre['monnaie_rendue'] = $monnaie;
                $passage_enregistre['numero_badge']   = $numero_badge;

                $succes = 'Passage enregistré avec succès !';
            } else {
                $erreur = 'Erreur lors de l\'enregistrement.';
            }
            $stmt->close();
        }
    }
}

$conn->close();

// Récupérer les classes pour le JS
$conn2   = getConnexion();
$classes2 = $conn2->query("SELECT * FROM classe_vehicule ORDER BY ordre");
$tarifs  = [];
while ($c = $classes2->fetch_assoc()) {
    $tarifs[$c['id_classe']] = [
        'normal' => $c['tarif_normal'],
        'badge'  => $c['tarif_badge'],
        'nom'    => $c['nom_classe']
    ];
}
$conn2->close();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enregistrer Passage - Pont HKB</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .carte-montant {
            background: linear-gradient(135deg, #1a3c5e, #2d6a9f);
            color: white;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
        }
        .montant-affiche {
            font-size: 2.5rem;
            font-weight: 700;
            color: #f0a500;
        }
        .recu {
            border: 2px dashed #1a3c5e;
            border-radius: 12px;
            padding: 20px;
            background: #f8f9fa;
        }
        .recu-titre {
            text-align: center;
            border-bottom: 2px solid #1a3c5e;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .zone-badge-active {
            background: #e8f4fd;
            border: 2px solid #17a2b8;
            border-radius: 8px;
            padding: 15px;
        }
    </style>
</head>
<body>

<?php include '../includes/navbar_agent.php'; ?>

<div class="contenu-principal">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="titre-page mb-0">
            <i class="bi bi-car-front"></i> Enregistrer un passage
        </h3>
        <?php if ($guichet_agent): ?>
            <span class="badge bg-success fs-6">
                <i class="bi bi-building"></i>
                <?= $guichet_agent['numero_guichet'] ?> —
                <?= $guichet_agent['nom_guichet'] ?>
            </span>
        <?php endif; ?>
    </div>

    <!-- Alerte si pas de guichet -->
    <?php if (!$guichet_agent): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle-fill"></i>
            Vous n'êtes affecté à aucun guichet.
            Veuillez contacter l'administrateur.
        </div>
    <?php endif; ?>

    <!-- Messages -->
    <?php if (!empty($erreur)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle-fill"></i> <?= $erreur ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- REÇU après enregistrement -->
    <?php if ($passage_enregistre): ?>
        <div class="recu mb-4" id="recu">
            <div class="recu-titre">
                <h5 style="color:var(--couleur-primaire)">
                    🌉 PONT HENRI KONAN BÉDIÉ
                </h5>
                <small>Reçu de péage</small>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-sm table-borderless">
                        <tr>
                            <td class="text-muted">N° Passage</td>
                            <td class="fw-bold">
                                #<?= $passage_enregistre['id_passage'] ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">Date/Heure</td>
                            <td><?= formatDate($passage_enregistre['date_heure']) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Plaque</td>
                            <td>
                                <span class="badge bg-dark fs-6">
                                    <?= $passage_enregistre['plaque_saisie'] ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">Classe</td>
                            <td><?= $passage_enregistre['nom_classe'] ?></td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-sm table-borderless">
                        <tr>
                            <td class="text-muted">Guichet</td>
                            <td><?= $passage_enregistre['nom_guichet'] ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Mode</td>
                            <td>
                                <span class="badge bg-<?=
                                    $passage_enregistre['mode_paiement'] === 'espece'
                                        ? 'warning text-dark' : 'info'
                                ?>">
                                    <?= $passage_enregistre['mode_paiement'] ?>
                                </span>
                            </td>
                        </tr>
                        <?php if ($passage_enregistre['mode_paiement'] === 'badge'): ?>
                        <tr>
                            <td class="text-muted">Badge</td>
                            <td>
                                <span class="badge bg-info text-dark">
                                    <?= $passage_enregistre['numero_badge'] ?>
                                </span>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td class="text-muted">Montant</td>
                            <td class="fw-bold text-success fs-5">
                                <?= formatMontant($passage_enregistre['montant']) ?>
                            </td>
                        </tr>
                        <?php if ($passage_enregistre['mode_paiement'] === 'espece'): ?>
                        <tr>
                            <td class="text-muted">Reçu</td>
                            <td>
                                <?= formatMontant($passage_enregistre['montant_recu']) ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">Monnaie</td>
                            <td class="fw-bold text-primary">
                                <?= formatMontant($passage_enregistre['monnaie_rendue']) ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
            <div class="text-center mt-2">
                <div class="alert alert-success py-2">
                    ✅ Passage enregistré avec succès !
                </div>
                <button class="btn btn-pont"
                        onclick="window.location.reload()">
                    <i class="bi bi-plus-circle"></i>
                    Nouveau passage
                </button>
                <button class="btn btn-outline-secondary ms-2"
                        onclick="window.print()">
                    <i class="bi bi-printer"></i> Imprimer
                </button>
            </div>
        </div>
    <?php endif; ?>

    <!-- FORMULAIRE DE PASSAGE -->
    <?php if (!$passage_enregistre): ?>
    <div class="row g-3">

        <!-- Formulaire -->
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header"
                     style="background:var(--couleur-primaire); color:white">
                    <i class="bi bi-pencil-square"></i>
                    Informations du passage
                </div>
                <div class="card-body">
                    <form method="POST" id="formPassage">

                        <!-- Plaque -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-card-text"></i>
                                Plaque d'immatriculation
                                <span class="text-danger">*</span>
                            </label>
                            <input
                                type="text"
                                class="form-control form-control-lg
                                       text-uppercase fw-bold"
                                name="plaque"
                                id="plaque"
                                placeholder="Ex: AB 1234 CI"
                                style="letter-spacing:3px; font-size:1.3rem"
                                oninput="this.value=this.value.toUpperCase()"
                                required
                            >
                        </div>

                        <!-- Classe -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-truck"></i>
                                Classe de véhicule
                                <span class="text-danger">*</span>
                            </label>
                            <select class="form-select form-select-lg"
                                    name="id_classe"
                                    id="id_classe"
                                    required>
                                <option value="">
                                    -- Sélectionner une classe --
                                </option>
                                <?php
                                $classes->data_seek(0);
                                while ($c = $classes->fetch_assoc()):
                                ?>
                                <option value="<?= $c['id_classe'] ?>"
                                        data-normal="<?= $c['tarif_normal'] ?>"
                                        data-badge="<?= $c['tarif_badge'] ?>">
                                    <?= $c['nom_classe'] ?>
                                    — Espèces:
                                    <?= formatMontant($c['tarif_normal']) ?>
                                    | Badge:
                                    <?= formatMontant($c['tarif_badge']) ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <!-- Sens -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-arrow-left-right"></i>
                                Sens
                            </label>
                            <div class="d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input"
                                           type="radio"
                                           name="sens"
                                           value="entree"
                                           id="sens_entree"
                                           checked>
                                    <label class="form-check-label"
                                           for="sens_entree">
                                        <i class="bi bi-arrow-right-circle
                                                  text-success"></i>
                                        Entrée
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input"
                                           type="radio"
                                           name="sens"
                                           value="sortie"
                                           id="sens_sortie">
                                    <label class="form-check-label"
                                           for="sens_sortie">
                                        <i class="bi bi-arrow-left-circle
                                                  text-danger"></i>
                                        Sortie
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Mode de paiement -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-credit-card"></i>
                                Mode de paiement
                            </label>
                            <div class="d-flex gap-4">
                                <div class="form-check">
                                    <input class="form-check-input"
                                           type="radio"
                                           name="mode_paiement"
                                           value="espece"
                                           id="mode_espece"
                                           checked>
                                    <label class="form-check-label"
                                           for="mode_espece">
                                        <i class="bi bi-cash text-warning"></i>
                                        <strong>Espèces</strong>
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input"
                                           type="radio"
                                           name="mode_paiement"
                                           value="badge"
                                           id="mode_badge">
                                    <label class="form-check-label"
                                           for="mode_badge">
                                        <i class="bi bi-credit-card
                                                  text-info"></i>
                                        <strong>Badge</strong>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Champ espèces -->
                        <div id="zone_espece" class="mb-3">
                            <label class="form-label fw-semibold">
                                Montant reçu (FCFA)
                            </label>
                            <input type="number"
                                   class="form-control"
                                   name="montant_recu"
                                   id="montant_recu"
                                   placeholder="0"
                                   oninput="calculerMonnaie()">
                            <div id="monnaie_info" class="mt-2"></div>
                        </div>

                        <!-- Champ badge -->
                        <div id="zone_badge" class="mb-3 zone-badge-active"
                             style="display:none">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-upc-scan text-info"></i>
                                Numéro de badge
                                <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                   class="form-control form-control-lg
                                          text-uppercase fw-bold"
                                   name="numero_badge"
                                   id="numero_badge"
                                   placeholder="Ex: HKB-ABCD-1234"
                                   style="letter-spacing:2px"
                                   oninput="this.value=
                                            this.value.toUpperCase()">
                            <div class="form-text text-info">
                                <i class="bi bi-info-circle"></i>
                                Entrez le numéro de badge de l'usager
                            </div>
                        </div>

                        <div class="d-grid mt-4">
                            <button type="submit"
                                    class="btn btn-pont btn-lg"
                                    <?= !$guichet_agent ? 'disabled' : '' ?>>
                                <i class="bi bi-check-circle-fill"></i>
                                Valider le passage
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        </div>

        <!-- Carte montant -->
        <div class="col-md-4">
            <div class="carte-montant mb-3">
                <div class="text-white-50 small mb-1">
                    Montant à percevoir
                </div>
                <div class="montant-affiche" id="affichage_montant">
                    — FCFA
                </div>
                <div class="text-white-50 small mt-2"
                     id="affichage_classe">
                    Sélectionnez une classe
                </div>
            </div>

            <!-- Monnaie à rendre -->
            <div class="card shadow-sm mb-3"
                 id="carte_monnaie">
                <div class="card-body text-center">
                    <div class="text-muted small">Monnaie à rendre</div>
                    <div class="fs-2 fw-bold text-primary"
                         id="affichage_monnaie">
                        0 FCFA
                    </div>
                </div>
            </div>

            <!-- Raccourcis classes -->
            <div class="card shadow-sm">
                <div class="card-header small fw-semibold"
                     style="background:#f8f9fa">
                    <i class="bi bi-lightning"></i>
                    Sélection rapide
                </div>
                <div class="card-body p-2">
                    <?php
                    $classes->data_seek(0);
                    $couleurs = [
                        'secondary','primary',
                        'success','warning','danger'
                    ];
                    $i = 0;
                    while ($c = $classes->fetch_assoc()):
                    ?>
                    <button type="button"
                            class="btn btn-outline-<?= $couleurs[$i] ?>
                                   btn-sm w-100 mb-1 text-start"
                            onclick="selectionnerClasse(
                                <?= $c['id_classe'] ?>
                            )">
                        <?= $c['nom_classe'] ?>
                    </button>
                    <?php $i++; endwhile; ?>
                </div>
            </div>
        </div>

    </div>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const tarifs = <?= json_encode($tarifs) ?>;

// Écouter changement mode paiement
document.getElementById('mode_espece').addEventListener('change', function() {
    if (this.checked) {
        document.getElementById('zone_espece').style.display = 'block';
        document.getElementById('zone_badge').style.display  = 'none';
        document.getElementById('carte_monnaie').style.display = 'block';
        mettreAJourMontant();
    }
});

document.getElementById('mode_badge').addEventListener('change', function() {
    if (this.checked) {
        document.getElementById('zone_espece').style.display = 'none';
        document.getElementById('zone_badge').style.display  = 'block';
        document.getElementById('carte_monnaie').style.display = 'none';
        mettreAJourMontant();
    }
});

// Écouter changement classe
document.getElementById('id_classe').addEventListener('change', function() {
    mettreAJourMontant();
});

function mettreAJourMontant() {
    const select   = document.getElementById('id_classe');
    const id       = select.value;
    const estBadge = document.getElementById('mode_badge').checked;

    if (!id || !tarifs[id]) {
        document.getElementById('affichage_montant').textContent = '— FCFA';
        document.getElementById('affichage_classe').textContent =
            'Sélectionnez une classe';
        return;
    }

    const montant = estBadge ? tarifs[id].badge : tarifs[id].normal;
    document.getElementById('affichage_montant').textContent =
        new Intl.NumberFormat('fr-FR').format(montant) + ' FCFA';
    document.getElementById('affichage_classe').textContent =
        tarifs[id].nom;
    calculerMonnaie();
}

function calculerMonnaie() {
    const select  = document.getElementById('id_classe');
    const id      = select.value;
    const recu    = parseFloat(
        document.getElementById('montant_recu').value
    ) || 0;
    if (!id || !tarifs[id]) return;

    const montant = tarifs[id].normal;
    const monnaie = Math.max(0, recu - montant);

    document.getElementById('affichage_monnaie').textContent =
        new Intl.NumberFormat('fr-FR').format(monnaie) + ' FCFA';
}

function selectionnerClasse(id) {
    document.getElementById('id_classe').value = id;
    mettreAJourMontant();
}
</script>
</body>
</html>