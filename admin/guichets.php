<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

$conn = getConnexion();
$erreur = '';
$succes = '';

// ============================================================
// TRAITEMENT DES ACTIONS
// ============================================================

// -- AJOUTER UN GUICHET --
if (isset($_POST['action']) && $_POST['action'] === 'ajouter') {
    $numero  = securiser($_POST['numero_guichet'] ?? '');
    $nom     = securiser($_POST['nom_guichet'] ?? '');
    $sens    = securiser($_POST['sens'] ?? 'bidirectionnel');
    $statut  = securiser($_POST['statut'] ?? 'ferme');

    if (empty($numero) || empty($nom)) {
        $erreur = 'Veuillez remplir tous les champs obligatoires.';
    } else {
        $check = $conn->prepare("SELECT id_guichet FROM guichet WHERE numero_guichet = ?");
        $check->bind_param('s', $numero);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $erreur = 'Ce numéro de guichet existe déjà.';
        } else {
            $stmt = $conn->prepare("
                INSERT INTO guichet (numero_guichet, nom_guichet, sens, statut)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param('ssss', $numero, $nom, $sens, $statut);
            if ($stmt->execute()) {
                $succes = 'Guichet ajouté avec succès !';
            } else {
                $erreur = 'Erreur lors de l\'ajout.';
            }
            $stmt->close();
        }
        $check->close();
    }
}

// -- MODIFIER UN GUICHET --
if (isset($_POST['action']) && $_POST['action'] === 'modifier') {
    $id     = (int)$_POST['id_guichet'];
    $numero = securiser($_POST['numero_guichet'] ?? '');
    $nom    = securiser($_POST['nom_guichet'] ?? '');
    $sens   = securiser($_POST['sens'] ?? 'bidirectionnel');
    $statut = securiser($_POST['statut'] ?? 'ferme');

    $stmt = $conn->prepare("
        UPDATE guichet
        SET numero_guichet = ?, nom_guichet = ?, sens = ?, statut = ?
        WHERE id_guichet = ?
    ");
    $stmt->bind_param('ssssi', $numero, $nom, $sens, $statut, $id);
    if ($stmt->execute()) {
        $succes = 'Guichet modifié avec succès !';
    } else {
        $erreur = 'Erreur lors de la modification.';
    }
    $stmt->close();
}

// -- SUPPRIMER UN GUICHET --
if (isset($_GET['supprimer'])) {
    $id = (int)$_GET['supprimer'];

    // Vérifier si le guichet a des passages
    $check = $conn->prepare("SELECT COUNT(*) as nb FROM passage WHERE id_guichet = ?");
    $check->bind_param('i', $id);
    $check->execute();
    $res = $check->get_result()->fetch_assoc();

    if ($res['nb'] > 0) {
        $erreur = 'Impossible de supprimer ce guichet : il contient des passages enregistrés.';
    } else {
        $stmt = $conn->prepare("DELETE FROM guichet WHERE id_guichet = ?");
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            $succes = 'Guichet supprimé avec succès !';
        } else {
            $erreur = 'Erreur lors de la suppression.';
        }
        $stmt->close();
    }
    $check->close();
}

// -- CHANGER STATUT RAPIDE --
if (isset($_GET['statut']) && isset($_GET['id'])) {
    $id     = (int)$_GET['id'];
    $statut = securiser($_GET['statut']);
    if (in_array($statut, ['ouvert', 'ferme', 'maintenance'])) {
        $stmt = $conn->prepare("UPDATE guichet SET statut = ? WHERE id_guichet = ?");
        $stmt->bind_param('si', $statut, $id);
        if ($stmt->execute()) {
            $succes = 'Statut du guichet mis à jour !';
        }
        $stmt->close();
    }
}

// -- AFFECTER AGENT AU GUICHET --
if (isset($_POST['action']) && $_POST['action'] === 'affecter') {
    $id_agent   = (int)$_POST['id_agent'];
    $id_guichet = (int)$_POST['id_guichet'];
    $date_debut = securiser($_POST['date_affectation'] ?? date('Y-m-d'));

    // Clôturer l'affectation précédente de cet agent
    $cloture = $conn->prepare("
        UPDATE agent_guichet SET statut = 'termine', date_fin = ?
        WHERE id_utilisateur = ? AND statut = 'actif'
    ");
    $cloture->bind_param('si', $date_debut, $id_agent);
    $cloture->execute();
    $cloture->close();

    // Créer la nouvelle affectation
    $stmt = $conn->prepare("
        INSERT INTO agent_guichet (id_utilisateur, id_guichet, date_affectation, statut)
        VALUES (?, ?, ?, 'actif')
    ");
    $stmt->bind_param('iis', $id_agent, $id_guichet, $date_debut);
    if ($stmt->execute()) {
        $succes = 'Agent affecté au guichet avec succès !';
    } else {
        $erreur = 'Erreur lors de l\'affectation.';
    }
    $stmt->close();
}

// -- LISTE DES GUICHETS --
$guichets = $conn->query("
    SELECT g.*,
           COUNT(DISTINCT p.id_passage) as nb_passages,
           COUNT(DISTINCT ag.id_utilisateur) as nb_agents
    FROM guichet g
    LEFT JOIN passage p     ON g.id_guichet = p.id_guichet
    LEFT JOIN agent_guichet ag ON g.id_guichet = ag.id_guichet
                               AND ag.statut = 'actif'
    GROUP BY g.id_guichet
    ORDER BY g.numero_guichet
");

// -- LISTE DES AGENTS pour affectation --
$agents = $conn->query("
    SELECT u.id_utilisateur,
           CONCAT(u.prenom, ' ', u.nom) as nom_complet,
           g.nom_guichet as guichet_actuel
    FROM utilisateur u
    LEFT JOIN agent_guichet ag ON u.id_utilisateur = ag.id_utilisateur
                               AND ag.statut = 'actif'
    LEFT JOIN guichet g ON ag.id_guichet = g.id_guichet
    WHERE u.id_role = 2 AND u.statut = 'actif'
    ORDER BY u.nom
");
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Guichets - Pont HKB</title>
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
            <i class="bi bi-building"></i> Gestion des Guichets
        </h3>
        <div class="d-flex gap-2">
            <button class="btn btn-success" data-bs-toggle="modal"
                    data-bs-target="#modalAffecter">
                <i class="bi bi-person-check"></i> Affecter un agent
            </button>
            <button class="btn btn-pont" data-bs-toggle="modal"
                    data-bs-target="#modalAjout">
                <i class="bi bi-plus-circle"></i> Nouveau Guichet
            </button>
        </div>
    </div>

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

    <!-- Cartes des guichets -->
    <div class="row g-3 mb-4">
        <?php
        // Rembobiner le résultat
        $guichets->data_seek(0);
        while ($g = $guichets->fetch_assoc()):
            $couleur_statut = [
                'ouvert'      => '#28a745',
                'ferme'       => '#dc3545',
                'maintenance' => '#f0a500'
            ][$g['statut']] ?? '#6c757d';
        ?>
        <div class="col-md-3">
            <div class="card carte-stat h-100"
                 style="border-top: 4px solid <?= $couleur_statut ?>">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="fw-bold fs-5"><?= $g['numero_guichet'] ?></div>
                            <div class="text-muted small"><?= $g['nom_guichet'] ?></div>
                        </div>
                        <span class="badge"
                              style="background:<?= $couleur_statut ?>">
                            <?= $g['statut'] ?>
                        </span>
                    </div>
                    <hr>
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="fw-bold text-primary">
                                <?= $g['nb_passages'] ?>
                            </div>
                            <small class="text-muted">Passages</small>
                        </div>
                        <div class="col-6">
                            <div class="fw-bold text-success">
                                <?= $g['nb_agents'] ?>
                            </div>
                            <small class="text-muted">Agent(s)</small>
                        </div>
                    </div>
                    <hr>
                    <div class="text-muted small mb-2">
                        <i class="bi bi-arrow-left-right"></i>
                        Sens : <?= $g['sens'] ?>
                    </div>
                    <!-- Boutons statut rapide -->
                    <div class="d-flex gap-1 flex-wrap">
                        <?php if ($g['statut'] !== 'ouvert'): ?>
                        <a href="?statut=ouvert&id=<?= $g['id_guichet'] ?>"
                           class="btn btn-sm btn-outline-success"
                           onclick="return confirm('Ouvrir ce guichet ?')">
                            <i class="bi bi-door-open"></i> Ouvrir
                        </a>
                        <?php endif; ?>
                        <?php if ($g['statut'] !== 'ferme'): ?>
                        <a href="?statut=ferme&id=<?= $g['id_guichet'] ?>"
                           class="btn btn-sm btn-outline-danger"
                           onclick="return confirm('Fermer ce guichet ?')">
                            <i class="bi bi-door-closed"></i> Fermer
                        </a>
                        <?php endif; ?>
                        <button class="btn btn-sm btn-outline-warning"
                                onclick="ouvrirModification(
                                    <?= $g['id_guichet'] ?>,
                                    '<?= $g['numero_guichet'] ?>',
                                    '<?= addslashes($g['nom_guichet']) ?>',
                                    '<?= $g['sens'] ?>',
                                    '<?= $g['statut'] ?>'
                                )">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <?php if ($g['nb_passages'] == 0): ?>
                        <a href="?supprimer=<?= $g['id_guichet'] ?>"
                           class="btn btn-sm btn-outline-danger"
                           onclick="return confirm('Supprimer ce guichet ?')">
                            <i class="bi bi-trash"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    </div>

    <!-- Tableau des affectations -->
    <div class="card shadow-sm">
        <div class="card-header"
             style="background:var(--couleur-primaire); color:white">
            <i class="bi bi-person-check"></i> Affectations agents — guichets
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Agent</th>
                            <th>Guichet</th>
                            <th>Date affectation</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $affectations = $conn->query("
                            SELECT ag.*,
                                   CONCAT(u.prenom, ' ', u.nom) as nom_agent,
                                   g.nom_guichet, g.numero_guichet
                            FROM agent_guichet ag
                            JOIN utilisateur u ON ag.id_utilisateur = u.id_utilisateur
                            JOIN guichet g     ON ag.id_guichet = g.id_guichet
                            ORDER BY ag.date_affectation DESC
                            LIMIT 20
                        ");
                        if ($affectations->num_rows > 0):
                            while ($af = $affectations->fetch_assoc()):
                        ?>
                        <tr>
                            <td>
                                <i class="bi bi-person-circle text-primary"></i>
                                <?= $af['nom_agent'] ?>
                            </td>
                            <td>
                                <span class="badge bg-primary">
                                    <?= $af['numero_guichet'] ?>
                                </span>
                                <?= $af['nom_guichet'] ?>
                            </td>
                            <td><?= formatDateCourte($af['date_affectation']) ?></td>
                            <td>
                                <span class="badge bg-<?= $af['statut'] === 'actif' ? 'success' : 'secondary' ?>">
                                    <?= $af['statut'] ?>
                                </span>
                            </td>
                        </tr>
                        <?php
                            endwhile;
                        else:
                        ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted py-3">
                                Aucune affectation enregistrée.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- ===== MODAL AJOUT ===== -->
<div class="modal fade" id="modalAjout" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"
                 style="background:var(--couleur-primaire); color:white">
                <h5 class="modal-title">
                    <i class="bi bi-plus-circle"></i> Ajouter un guichet
                </h5>
                <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="ajouter">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">
                                Numéro <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control"
                                   name="numero_guichet"
                                   placeholder="G05" required>
                        </div>
                        <div class="col-md-8 mb-3">
                            <label class="form-label fw-semibold">
                                Nom <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control"
                                   name="nom_guichet"
                                   placeholder="Guichet 05 - Entrée Est"
                                   required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Sens</label>
                            <select class="form-select" name="sens">
                                <option value="entree">Entrée</option>
                                <option value="sortie">Sortie</option>
                                <option value="bidirectionnel" selected>
                                    Bidirectionnel
                                </option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Statut initial</label>
                            <select class="form-select" name="statut">
                                <option value="ferme" selected>Fermé</option>
                                <option value="ouvert">Ouvert</option>
                                <option value="maintenance">Maintenance</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"
                            data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-pont">
                        <i class="bi bi-check-lg"></i> Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===== MODAL MODIFICATION ===== -->
<div class="modal fade" id="modalModification" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">
                    <i class="bi bi-pencil"></i> Modifier le guichet
                </h5>
                <button type="button" class="btn-close"
                        data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="modifier">
                <input type="hidden" name="id_guichet" id="mod_id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Numéro</label>
                            <input type="text" class="form-control"
                                   name="numero_guichet" id="mod_numero" required>
                        </div>
                        <div class="col-md-8 mb-3">
                            <label class="form-label fw-semibold">Nom</label>
                            <input type="text" class="form-control"
                                   name="nom_guichet" id="mod_nom" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Sens</label>
                            <select class="form-select" name="sens" id="mod_sens">
                                <option value="entree">Entrée</option>
                                <option value="sortie">Sortie</option>
                                <option value="bidirectionnel">Bidirectionnel</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Statut</label>
                            <select class="form-select" name="statut" id="mod_statut">
                                <option value="ouvert">Ouvert</option>
                                <option value="ferme">Fermé</option>
                                <option value="maintenance">Maintenance</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"
                            data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-warning text-white">
                        <i class="bi bi-check-lg"></i> Modifier
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===== MODAL AFFECTER AGENT ===== -->
<div class="modal fade" id="modalAffecter" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="bi bi-person-check"></i> Affecter un agent à un guichet
                </h5>
                <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="affecter">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Agent <span class="text-danger">*</span>
                        </label>
                        <select class="form-select" name="id_agent" required>
                            <option value="">-- Choisir un agent --</option>
                            <?php
                            $agents->data_seek(0);
                            while ($ag = $agents->fetch_assoc()):
                            ?>
                            <option value="<?= $ag['id_utilisateur'] ?>">
                                <?= $ag['nom_complet'] ?>
                                <?= $ag['guichet_actuel']
                                    ? '(Actuel: ' . $ag['guichet_actuel'] . ')'
                                    : '(Non affecté)' ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Guichet <span class="text-danger">*</span>
                        </label>
                        <select class="form-select" name="id_guichet" required>
                            <option value="">-- Choisir un guichet --</option>
                            <?php
                            $guichets->data_seek(0);
                            while ($gu = $guichets->fetch_assoc()):
                            ?>
                            <option value="<?= $gu['id_guichet'] ?>">
                                <?= $gu['numero_guichet'] ?> —
                                <?= $gu['nom_guichet'] ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Date d'affectation
                        </label>
                        <input type="date" class="form-control"
                               name="date_affectation"
                               value="<?= date('Y-m-d') ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"
                            data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-lg"></i> Affecter
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function ouvrirModification(id, numero, nom, sens, statut) {
    document.getElementById('mod_id').value     = id;
    document.getElementById('mod_numero').value = numero;
    document.getElementById('mod_nom').value    = nom;
    document.getElementById('mod_sens').value   = sens;
    document.getElementById('mod_statut').value = statut;
    new bootstrap.Modal(document.getElementById('modalModification')).show();
}
</script>
</body>
</html>