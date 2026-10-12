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

// -- AJOUTER UN AGENT --
if (isset($_POST['action']) && $_POST['action'] === 'ajouter') {
    $nom          = securiser($_POST['nom'] ?? '');
    $prenom       = securiser($_POST['prenom'] ?? '');
    $email        = securiser($_POST['email'] ?? '');
    $telephone    = securiser($_POST['telephone'] ?? '');
    $mot_de_passe = $_POST['mot_de_passe'] ?? '';

    if (empty($nom) || empty($prenom) || empty($email) || empty($mot_de_passe)) {
        $erreur = 'Veuillez remplir tous les champs obligatoires.';
    } else {
        // Vérifier email unique
        $check = $conn->prepare("SELECT id_utilisateur FROM utilisateur WHERE email = ?");
        $check->bind_param('s', $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $erreur = 'Cet email est déjà utilisé.';
        } else {
            $hash = password_hash($mot_de_passe, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("
                INSERT INTO utilisateur (id_role, nom, prenom, email, mot_de_passe, telephone, statut)
                VALUES (2, ?, ?, ?, ?, ?, 'actif')
            ");
            $stmt->bind_param('sssss', $nom, $prenom, $email, $hash, $telephone);
            if ($stmt->execute()) {
                $succes = 'Agent ajouté avec succès !';
            } else {
                $erreur = 'Erreur lors de l\'ajout.';
            }
            $stmt->close();
        }
        $check->close();
    }
}

// -- MODIFIER UN AGENT --
if (isset($_POST['action']) && $_POST['action'] === 'modifier') {
    $id        = (int)$_POST['id_utilisateur'];
    $nom       = securiser($_POST['nom'] ?? '');
    $prenom    = securiser($_POST['prenom'] ?? '');
    $email     = securiser($_POST['email'] ?? '');
    $telephone = securiser($_POST['telephone'] ?? '');
    $statut    = securiser($_POST['statut'] ?? 'actif');

    $stmt = $conn->prepare("
        UPDATE utilisateur
        SET nom = ?, prenom = ?, email = ?, telephone = ?, statut = ?
        WHERE id_utilisateur = ? AND id_role = 2
    ");
    $stmt->bind_param('sssssi', $nom, $prenom, $email, $telephone, $statut, $id);
    if ($stmt->execute()) {
        $succes = 'Agent modifié avec succès !';
    } else {
        $erreur = 'Erreur lors de la modification.';
    }
    $stmt->close();
}

// -- SUPPRIMER UN AGENT --
if (isset($_GET['supprimer'])) {
    $id = (int)$_GET['supprimer'];
    $stmt = $conn->prepare("
        UPDATE utilisateur SET statut = 'inactif'
        WHERE id_utilisateur = ? AND id_role = 2
    ");
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        $succes = 'Agent désactivé avec succès !';
    } else {
        $erreur = 'Erreur lors de la suppression.';
    }
    $stmt->close();
}

// -- RÉINITIALISER MOT DE PASSE --
if (isset($_POST['action']) && $_POST['action'] === 'reset_mdp') {
    $id          = (int)$_POST['id_utilisateur'];
    $nouveau_mdp = $_POST['nouveau_mdp'] ?? '';
    if (!empty($nouveau_mdp)) {
        $hash = password_hash($nouveau_mdp, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("
            UPDATE utilisateur SET mot_de_passe = ?
            WHERE id_utilisateur = ? AND id_role = 2
        ");
        $stmt->bind_param('si', $hash, $id);
        if ($stmt->execute()) {
            $succes = 'Mot de passe réinitialisé avec succès !';
        }
        $stmt->close();
    }
}

// -- LISTE DES AGENTS --
$agents = $conn->query("
    SELECT u.*,
           ag.id_guichet,
           g.nom_guichet,
           ag.statut as statut_affectation
    FROM utilisateur u
    LEFT JOIN agent_guichet ag ON u.id_utilisateur = ag.id_utilisateur
                               AND ag.statut = 'actif'
    LEFT JOIN guichet g ON ag.id_guichet = g.id_guichet
    WHERE u.id_role = 2
    ORDER BY u.date_creation DESC
");

// -- LISTE DES GUICHETS pour affectation --
$guichets = $conn->query("SELECT * FROM guichet ORDER BY numero_guichet");
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Agents - Pont HKB</title>
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
            <i class="bi bi-people"></i> Gestion des Agents
        </h3>
        <button class="btn btn-pont" data-bs-toggle="modal" data-bs-target="#modalAjout">
            <i class="bi bi-person-plus"></i> Nouvel Agent
        </button>
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

    <!-- Tableau des agents -->
    <div class="card shadow-sm">
        <div class="card-header" style="background:var(--couleur-primaire); color:white">
            <i class="bi bi-list-ul"></i> Liste des agents
            <span class="badge bg-light text-dark ms-2">
                <?= $agents->num_rows ?> agent(s)
            </span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nom complet</th>
                            <th>Email</th>
                            <th>Téléphone</th>
                            <th>Guichet affecté</th>
                            <th>Statut</th>
                            <th>Date création</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($agents->num_rows > 0): ?>
                            <?php while ($a = $agents->fetch_assoc()): ?>
                            <tr>
                                <td><?= $a['id_utilisateur'] ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-circle d-flex align-items-center
                                                    justify-content-center text-white fw-bold"
                                             style="width:36px; height:36px;
                                                    background:var(--couleur-primaire);
                                                    font-size:0.85rem">
                                            <?= strtoupper(substr($a['prenom'], 0, 1) . substr($a['nom'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="fw-semibold">
                                                <?= $a['prenom'] . ' ' . $a['nom'] ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td><?= $a['email'] ?></td>
                                <td><?= $a['telephone'] ?: '-' ?></td>
                                <td>
                                    <?php if ($a['nom_guichet']): ?>
                                        <span class="badge bg-success">
                                            <i class="bi bi-building"></i>
                                            <?= $a['nom_guichet'] ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Non affecté</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?= couleurStatut($a['statut']) ?>">
                                        <?= $a['statut'] ?>
                                    </span>
                                </td>
                                <td>
                                    <small><?= formatDateCourte($a['date_creation']) ?></small>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <!-- Modifier -->
                                        <button class="btn btn-outline-primary"
                                                title="Modifier"
                                                onclick="ouvrirModification(
                                                    <?= $a['id_utilisateur'] ?>,
                                                    '<?= addslashes($a['nom']) ?>',
                                                    '<?= addslashes($a['prenom']) ?>',
                                                    '<?= $a['email'] ?>',
                                                    '<?= $a['telephone'] ?>',
                                                    '<?= $a['statut'] ?>'
                                                )">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <!-- Reset MDP -->
                                        <button class="btn btn-outline-warning"
                                                title="Réinitialiser mot de passe"
                                                onclick="ouvrirResetMdp(<?= $a['id_utilisateur'] ?>,
                                                '<?= addslashes($a['prenom'] . ' ' . $a['nom']) ?>')">
                                            <i class="bi bi-key"></i>
                                        </button>
                                        <!-- Supprimer -->
                                        <?php if ($a['statut'] === 'actif'): ?>
                                        <a href="?supprimer=<?= $a['id_utilisateur'] ?>"
                                           class="btn btn-outline-danger"
                                           title="Désactiver"
                                           onclick="return confirm('Désactiver cet agent ?')">
                                            <i class="bi bi-person-x"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    <i class="bi bi-people fs-3 d-block mb-2"></i>
                                    Aucun agent enregistré.
                                    <a href="#" data-bs-toggle="modal"
                                       data-bs-target="#modalAjout">
                                        Ajouter le premier agent
                                    </a>
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
            <div class="modal-header" style="background:var(--couleur-primaire); color:white">
                <h5 class="modal-title">
                    <i class="bi bi-person-plus"></i> Ajouter un agent
                </h5>
                <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="ajouter">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">
                                Nom <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control"
                                   name="nom" placeholder="KOUASSI" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">
                                Prénom <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control"
                                   name="prenom" placeholder="Jean" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Email <span class="text-danger">*</span>
                        </label>
                        <input type="email" class="form-control"
                               name="email" placeholder="agent@ponthkb.ci" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Téléphone</label>
                        <input type="tel" class="form-control"
                               name="telephone" placeholder="07 00 00 00 00">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Mot de passe <span class="text-danger">*</span>
                        </label>
                        <input type="password" class="form-control"
                               name="mot_de_passe"
                               placeholder="Minimum 6 caractères" required>
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
            <div class="modal-header" style="background:#f0a500; color:white">
                <h5 class="modal-title">
                    <i class="bi bi-pencil"></i> Modifier l'agent
                </h5>
                <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="modifier">
                <input type="hidden" name="id_utilisateur" id="mod_id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Nom</label>
                            <input type="text" class="form-control"
                                   name="nom" id="mod_nom" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Prénom</label>
                            <input type="text" class="form-control"
                                   name="prenom" id="mod_prenom" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Email</label>
                        <input type="email" class="form-control"
                               name="email" id="mod_email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Téléphone</label>
                        <input type="tel" class="form-control"
                               name="telephone" id="mod_telephone">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Statut</label>
                        <select class="form-select" name="statut" id="mod_statut">
                            <option value="actif">Actif</option>
                            <option value="inactif">Inactif</option>
                            <option value="suspendu">Suspendu</option>
                        </select>
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

<!-- ===== MODAL RESET MDP ===== -->
<div class="modal fade" id="modalResetMdp" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">
                    <i class="bi bi-key"></i> Réinitialiser le mot de passe
                </h5>
                <button type="button" class="btn-close"
                        data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="reset_mdp">
                <input type="hidden" name="id_utilisateur" id="reset_id">
                <div class="modal-body">
                    <p>Réinitialiser le mot de passe de :
                        <strong id="reset_nom"></strong>
                    </p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Nouveau mot de passe <span class="text-danger">*</span>
                        </label>
                        <input type="password" class="form-control"
                               name="nouveau_mdp"
                               placeholder="Minimum 6 caractères" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"
                            data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-check-lg"></i> Réinitialiser
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function ouvrirModification(id, nom, prenom, email, telephone, statut) {
    document.getElementById('mod_id').value        = id;
    document.getElementById('mod_nom').value       = nom;
    document.getElementById('mod_prenom').value    = prenom;
    document.getElementById('mod_email').value     = email;
    document.getElementById('mod_telephone').value = telephone;
    document.getElementById('mod_statut').value    = statut;
    new bootstrap.Modal(document.getElementById('modalModification')).show();
}

function ouvrirResetMdp(id, nom) {
    document.getElementById('reset_id').value  = id;
    document.getElementById('reset_nom').textContent = nom;
    new bootstrap.Modal(document.getElementById('modalResetMdp')).show();
}
</script>
</body>
</html>