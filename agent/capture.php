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
$capture_result = null;

// ============================================================
// SIMULATION CAPTURE CAMÉRA
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // -- CAPTURE PHOTO PLAQUE --
    if ($_POST['action'] === 'capturer') {
        $plaque_saisie = strtoupper(securiser($_POST['plaque_manuelle'] ?? ''));
        $id_passage    = (int)($_POST['id_passage'] ?? 0);

        // Simuler une détection OCR (score aléatoire entre 75 et 99)
        $score = rand(75, 99);

        // Générer un nom de fichier simulé
        $timestamp       = date('YmdHis');
        $nom_plaque      = 'plaque_'    . $timestamp . '_' . rand(1000,9999) . '.jpg';
        $nom_conducteur  = 'conducteur_'. $timestamp . '_' . rand(1000,9999) . '.jpg';

        // Simuler la plaque détectée (parfois avec une légère erreur)
        $plaque_detectee = $plaque_saisie;
        if ($score < 85) {
            // Simuler une erreur de lecture OCR
            $chars = str_split($plaque_saisie);
            $pos   = rand(0, count($chars) - 1);
            $chars[$pos] = chr(rand(65, 90));
            $plaque_detectee = implode('', $chars);
        }

        // Enregistrer la capture
        if ($id_passage > 0) {
            // Vérifier si capture déjà existante
            $check = $conn->prepare("
                SELECT id_capture FROM capture_camera
                WHERE id_passage = ?
            ");
            $check->bind_param('i', $id_passage);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                // Mettre à jour
                $stmt = $conn->prepare("
                    UPDATE capture_camera
                    SET photo_plaque     = ?,
                        photo_conducteur = ?,
                        plaque_detectee  = ?,
                        score_detection  = ?,
                        statut           = 'traitee'
                    WHERE id_passage = ?
                ");
                $stmt->bind_param(
                    'sssdi',
                    $nom_plaque, $nom_conducteur,
                    $plaque_detectee, $score, $id_passage
                );
            } else {
                // Insérer
                $stmt = $conn->prepare("
                    INSERT INTO capture_camera
                        (id_passage, photo_plaque, photo_conducteur,
                         plaque_detectee, score_detection, statut)
                    VALUES (?, ?, ?, ?, ?, 'traitee')
                ");
                $stmt->bind_param(
                    'isssd',
                    $id_passage, $nom_plaque,
                    $nom_conducteur, $plaque_detectee, $score
                );
            }
            $check->close();

            if ($stmt->execute()) {
                $capture_result = [
                    'plaque_saisie'   => $plaque_saisie,
                    'plaque_detectee' => $plaque_detectee,
                    'score'           => $score,
                    'nom_plaque'      => $nom_plaque,
                    'nom_conducteur'  => $nom_conducteur,
                    'correspond'      => ($plaque_saisie === $plaque_detectee),
                    'id_passage'      => $id_passage
                ];
                $succes = 'Capture enregistrée avec succès !';
            }
            $stmt->close();
        } else {
            // Capture sans passage (test)
            $capture_result = [
                'plaque_saisie'   => $plaque_saisie,
                'plaque_detectee' => $plaque_detectee,
                'score'           => $score,
                'nom_plaque'      => $nom_plaque,
                'nom_conducteur'  => $nom_conducteur,
                'correspond'      => ($plaque_saisie === $plaque_detectee),
                'id_passage'      => 0
            ];
            $succes = 'Simulation de capture effectuée !';
        }
    }

    // -- UPLOAD PHOTO MANUELLE --
    if ($_POST['action'] === 'upload_photo') {
        $id_passage = (int)($_POST['id_passage'] ?? 0);
        $type_photo = securiser($_POST['type_photo'] ?? 'plaque');

        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
            $extension  = strtolower(pathinfo(
                $_FILES['photo']['name'], PATHINFO_EXTENSION
            ));
            $extensions_ok = ['jpg', 'jpeg', 'png'];

            if (!in_array($extension, $extensions_ok)) {
                $erreur = 'Format non accepté. Utilisez JPG ou PNG.';
            } else {
                $dossier   = ($type_photo === 'plaque')
                    ? UPLOAD_PLAQUES : UPLOAD_CONDUCTEURS;
                $nom_fichier = $type_photo . '_' . date('YmdHis')
                    . '_' . rand(1000,9999) . '.' . $extension;
                $chemin = $dossier . $nom_fichier;

                if (move_uploaded_file($_FILES['photo']['tmp_name'], $chemin)) {
                    // Enregistrer en base
                    $champ = ($type_photo === 'plaque')
                        ? 'photo_plaque' : 'photo_conducteur';

                    // Vérifier si capture existe
                    $check = $conn->prepare("
                        SELECT id_capture FROM capture_camera
                        WHERE id_passage = ?
                    ");
                    $check->bind_param('i', $id_passage);
                    $check->execute();
                    $check->store_result();

                    if ($check->num_rows > 0) {
                        $stmt = $conn->prepare("
                            UPDATE capture_camera
                            SET $champ = ?
                            WHERE id_passage = ?
                        ");
                        $stmt->bind_param('si', $nom_fichier, $id_passage);
                    } else {
                        $stmt = $conn->prepare("
                            INSERT INTO capture_camera
                                (id_passage, $champ, statut)
                            VALUES (?, ?, 'en_attente')
                        ");
                        $stmt->bind_param('is', $id_passage, $nom_fichier);
                    }
                    $check->close();

                    if ($stmt->execute()) {
                        $succes = 'Photo uploadée avec succès !';
                    }
                    $stmt->close();
                } else {
                    $erreur = 'Erreur lors de l\'upload.';
                }
            }
        } else {
            $erreur = 'Veuillez sélectionner une photo.';
        }
    }
}

// Dernières captures
$captures = $conn->query("
    SELECT cc.*, p.plaque_saisie, p.date_heure,
           g.nom_guichet
    FROM capture_camera cc
    JOIN passage p  ON cc.id_passage  = p.id_passage
    JOIN guichet g  ON p.id_guichet   = g.id_guichet
    WHERE p.id_agent = $id_agent
    ORDER BY cc.date_capture DESC
    LIMIT 20
");

// Derniers passages pour lier une capture
$passages_recents = $conn->query("
    SELECT p.id_passage, p.plaque_saisie,
           p.date_heure, g.nom_guichet,
           cc.id_capture
    FROM passage p
    JOIN guichet g ON p.id_guichet = g.id_guichet
    LEFT JOIN capture_camera cc ON p.id_passage = cc.id_passage
    WHERE p.id_agent = $id_agent
    ORDER BY p.date_heure DESC
    LIMIT 10
");

$conn->close();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Capture Caméra - Agent Pont HKB</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .camera-box {
            background: #1a1a2e;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .camera-feed {
            width: 100%;
            height: 220px;
            background: #0d1117;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            border: 2px solid #f0a500;
        }
        .camera-feed video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 8px;
        }
        .scan-line {
            position: absolute;
            width: 100%;
            height: 2px;
            background: rgba(240,165,0,0.8);
            animation: scan 2s linear infinite;
        }
        @keyframes scan {
            0%   { top: 0; }
            100% { top: 100%; }
        }
        .coin {
            position: absolute;
            width: 20px;
            height: 20px;
            border-color: #f0a500;
            border-style: solid;
        }
        .coin-tl { top:8px; left:8px;
            border-width: 2px 0 0 2px; }
        .coin-tr { top:8px; right:8px;
            border-width: 2px 2px 0 0; }
        .coin-bl { bottom:8px; left:8px;
            border-width: 0 0 2px 2px; }
        .coin-br { bottom:8px; right:8px;
            border-width: 0 2px 2px 0; }
        .plaque-simulee {
            background: #f8f4e6;
            border: 3px solid #333;
            border-radius: 8px;
            padding: 12px 24px;
            font-family: 'Courier New', monospace;
            font-size: 1.8rem;
            font-weight: 900;
            letter-spacing: 4px;
            color: #1a1a1a;
            display: inline-block;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.2);
        }
        .score-bar {
            height: 12px;
            border-radius: 6px;
            transition: width 1s ease;
        }
        .capture-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 8px;
            transition: box-shadow 0.2s;
        }
        .capture-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>

<?php include '../includes/navbar_agent.php'; ?>

<div class="contenu-principal">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="titre-page mb-0">
            <i class="bi bi-camera-video"></i>
            Système de Capture Caméra
        </h3>
        <span class="badge bg-success fs-6">
            <i class="bi bi-circle-fill" style="font-size:0.6rem"></i>
            Caméra active (simulation)
        </span>
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

    <div class="row g-3">

        <!-- Zone caméra -->
        <div class="col-md-5">

            <!-- Simulation caméra -->
            <div class="camera-box mb-3">
                <div class="text-white-50 small mb-2">
                    <i class="bi bi-camera-video-fill text-warning"></i>
                    CAMÉRA PÉAGE — PONT HKB
                </div>
                <div class="camera-feed" id="cameraFeed">
                    <div class="coin coin-tl"></div>
                    <div class="coin coin-tr"></div>
                    <div class="coin coin-bl"></div>
                    <div class="coin coin-br"></div>

                    <!-- Vue caméra webcam si disponible -->
                    <video id="videoStream" autoplay muted
                           style="display:none"></video>

                    <!-- Vue simulée -->
                    <div id="vueSimulee" class="text-center">
                        <div class="scan-line" id="scanLine"></div>
                        <div class="text-warning opacity-50 mb-3">
                            <i class="bi bi-camera-video fs-1"></i>
                        </div>
                        <div class="text-white-50 small">
                            Flux caméra simulé
                        </div>
                        <div class="plaque-simulee mt-3" id="plaqueAffichee">
                            — — — —
                        </div>
                    </div>
                </div>

                <!-- Contrôles caméra -->
                <div class="d-flex gap-2 justify-content-center mt-3">
                    <button class="btn btn-warning btn-sm"
                            onclick="activerWebcam()">
                        <i class="bi bi-camera-video"></i> Activer webcam
                    </button>
                    <button class="btn btn-secondary btn-sm"
                            onclick="arreterWebcam()">
                        <i class="bi bi-camera-video-off"></i> Arrêter
                    </button>
                </div>
            </div>

            <!-- Formulaire capture -->
            <div class="card shadow-sm">
                <div class="card-header"
                     style="background:var(--couleur-primaire); color:white">
                    <i class="bi bi-upc-scan"></i>
                    Simulation OCR — Lecture de plaque
                </div>
                <div class="card-body">
                    <form method="POST" id="formCapture">
                        <input type="hidden" name="action" value="capturer">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                Plaque à lire / simuler
                                <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                   class="form-control form-control-lg
                                          text-uppercase fw-bold text-center"
                                   name="plaque_manuelle"
                                   id="plaque_manuelle"
                                   placeholder="AB 1234 CI"
                                   style="letter-spacing:3px; font-size:1.3rem"
                                   oninput="this.value=this.value.toUpperCase();
                                            mettreAJourAffichage(this.value)"
                                   required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                Lier à un passage récent
                            </label>
                            <select class="form-select"
                                    name="id_passage" id="sel_passage">
                                <option value="0">
                                    — Test sans passage —
                                </option>
                                <?php while ($p = $passages_recents->fetch_assoc()): ?>
                                <option value="<?= $p['id_passage'] ?>"
                                        data-plaque="<?= $p['plaque_saisie'] ?>">
                                    #<?= $p['id_passage'] ?> —
                                    <?= $p['plaque_saisie'] ?> —
                                    <?= formatDate($p['date_heure'] ?? '') ?>
                                    <?= $p['id_capture'] ? '✅' : '' ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="d-grid">
                            <button type="submit"
                                    class="btn btn-warning btn-lg fw-bold"
                                    id="btnCapture">
                                <i class="bi bi-camera"></i>
                                Simuler la capture OCR
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Upload photo manuelle -->
            <div class="card shadow-sm mt-3">
                <div class="card-header bg-secondary text-white">
                    <i class="bi bi-upload"></i>
                    Upload photo manuelle
                </div>
                <div class="card-body">
                    <form method="POST"
                          enctype="multipart/form-data">
                        <input type="hidden" name="action"
                               value="upload_photo">

                        <div class="mb-2">
                            <label class="form-label fw-semibold small">
                                Passage concerné
                            </label>
                            <select class="form-select form-select-sm"
                                    name="id_passage">
                                <option value="0">— Choisir —</option>
                                <?php
                                $passages_recents->data_seek(0);
                                while ($p = $passages_recents->fetch_assoc()):
                                ?>
                                <option value="<?= $p['id_passage'] ?>">
                                    #<?= $p['id_passage'] ?> —
                                    <?= $p['plaque_saisie'] ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="mb-2">
                            <label class="form-label fw-semibold small">
                                Type de photo
                            </label>
                            <select class="form-select form-select-sm"
                                    name="type_photo">
                                <option value="plaque">Photo plaque</option>
                                <option value="conducteur">
                                    Photo conducteur
                                </option>
                            </select>
                        </div>

                        <div class="mb-2">
                            <input type="file"
                                   class="form-control form-control-sm"
                                   name="photo"
                                   accept=".jpg,.jpeg,.png"
                                   required>
                        </div>

                        <button type="submit"
                                class="btn btn-secondary btn-sm w-100">
                            <i class="bi bi-upload"></i> Uploader
                        </button>
                    </form>
                </div>
            </div>

        </div>

        <!-- Résultat capture + historique -->
        <div class="col-md-7">

            <!-- Résultat OCR -->
            <?php if ($capture_result): ?>
            <div class="card shadow-sm mb-3 border-<?=
                $capture_result['correspond'] ? 'success' : 'warning'
            ?>">
                <div class="card-header bg-<?=
                    $capture_result['correspond'] ? 'success' : 'warning'
                ?> text-<?=
                    $capture_result['correspond'] ? 'white' : 'dark'
                ?>">
                    <i class="bi bi-<?=
                        $capture_result['correspond']
                            ? 'check-circle-fill' : 'exclamation-triangle-fill'
                    ?>"></i>
                    Résultat de la capture OCR
                </div>
                <div class="card-body">
                    <div class="row text-center mb-3">
                        <div class="col-5">
                            <div class="text-muted small mb-1">
                                Plaque saisie
                            </div>
                            <div class="plaque-simulee">
                                <?= $capture_result['plaque_saisie'] ?>
                            </div>
                        </div>
                        <div class="col-2 d-flex align-items-center
                                    justify-content-center">
                            <i class="bi bi-arrow-right fs-2 text-muted"></i>
                        </div>
                        <div class="col-5">
                            <div class="text-muted small mb-1">
                                Plaque détectée (OCR)
                            </div>
                            <div class="plaque-simulee"
                                 style="border-color:<?=
                                    $capture_result['correspond']
                                        ? '#28a745' : '#f0a500'
                                 ?>; color:<?=
                                    $capture_result['correspond']
                                        ? '#155724' : '#856404'
                                 ?>">
                                <?= $capture_result['plaque_detectee'] ?>
                            </div>
                        </div>
                    </div>

                    <!-- Score OCR -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="fw-semibold small">
                                Score de détection
                            </span>
                            <span class="fw-bold"
                                  style="color:<?=
                                    $capture_result['score'] >= 90
                                        ? '#28a745'
                                        : ($capture_result['score'] >= 80
                                            ? '#f0a500' : '#dc3545')
                                  ?>">
                                <?= $capture_result['score'] ?>%
                            </span>
                        </div>
                        <div class="progress" style="height:12px">
                            <div class="progress-bar score-bar"
                                 style="width:<?= $capture_result['score'] ?>%;
                                        background:<?=
                                            $capture_result['score'] >= 90
                                                ? '#28a745'
                                                : ($capture_result['score'] >= 80
                                                    ? '#f0a500' : '#dc3545')
                                        ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Infos fichiers simulés -->
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="p-2 rounded text-center"
                                 style="background:#f8f9fa; border:1px dashed #dee2e6">
                                <i class="bi bi-image text-primary fs-4"></i>
                                <div class="small fw-semibold mt-1">
                                    Photo plaque
                                </div>
                                <div style="font-size:0.65rem; color:#6c757d;
                                            word-break:break-all">
                                    <?= $capture_result['nom_plaque'] ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 rounded text-center"
                                 style="background:#f8f9fa; border:1px dashed #dee2e6">
                                <i class="bi bi-person-bounding-box
                                          text-success fs-4"></i>
                                <div class="small fw-semibold mt-1">
                                    Photo conducteur
                                </div>
                                <div style="font-size:0.65rem; color:#6c757d;
                                            word-break:break-all">
                                    <?= $capture_result['nom_conducteur'] ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if (!$capture_result['correspond']): ?>
                    <div class="alert alert-warning mt-3 mb-0 py-2">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Attention :</strong>
                        La plaque détectée diffère de la plaque saisie.
                        Vérifiez manuellement.
                    </div>
                    <?php else: ?>
                    <div class="alert alert-success mt-3 mb-0 py-2">
                        <i class="bi bi-check-circle"></i>
                        Plaque confirmée avec succès !
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Historique captures -->
            <div class="card shadow-sm">
                <div class="card-header"
                     style="background:var(--couleur-primaire); color:white">
                    <i class="bi bi-clock-history"></i>
                    Historique des captures
                    <span class="badge bg-light text-dark ms-1">
                        <?= $captures->num_rows ?>
                    </span>
                </div>
                <div class="card-body p-0">
                    <?php if ($captures->num_rows > 0): ?>
                        <?php while ($c = $captures->fetch_assoc()): ?>
                        <div class="capture-card mx-2 my-2">
                            <div class="d-flex justify-content-between
                                        align-items-start">
                                <div>
                                    <span class="badge bg-dark me-1">
                                        <?= $c['plaque_saisie'] ?>
                                    </span>
                                    <?php if ($c['plaque_detectee']): ?>
                                    <span class="badge bg-<?=
                                        $c['plaque_detectee'] === $c['plaque_saisie']
                                            ? 'success' : 'warning text-dark'
                                    ?>">
                                        OCR: <?= $c['plaque_detectee'] ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <?php if ($c['score_detection']): ?>
                                    <span class="badge bg-<?=
                                        $c['score_detection'] >= 90 ? 'success'
                                        : ($c['score_detection'] >= 80
                                            ? 'warning text-dark' : 'danger')
                                    ?>">
                                        <?= $c['score_detection'] ?>%
                                    </span>
                                    <?php endif; ?>
                                    <span class="badge
                                          bg-<?= couleurStatut($c['statut']) ?>">
                                        <?= $c['statut'] ?>
                                    </span>
                                </div>
                            </div>
                            <div class="d-flex gap-3 mt-2">
                                <?php if ($c['photo_plaque']): ?>
                                <small class="text-muted">
                                    <i class="bi bi-image text-primary"></i>
                                    <?= $c['photo_plaque'] ?>
                                </small>
                                <?php endif; ?>
                                <?php if ($c['photo_conducteur']): ?>
                                <small class="text-muted">
                                    <i class="bi bi-person-bounding-box
                                              text-success"></i>
                                    <?= $c['photo_conducteur'] ?>
                                </small>
                                <?php endif; ?>
                            </div>
                            <div class="text-muted mt-1"
                                 style="font-size:0.75rem">
                                <i class="bi bi-building"></i>
                                <?= $c['nom_guichet'] ?> —
                                <i class="bi bi-clock"></i>
                                <?= formatDate($c['date_capture']) ?>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-camera-video-off fs-3 d-block mb-2"></i>
                            Aucune capture enregistrée.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let streamActif = null;

// Mettre à jour affichage plaque en temps réel
function mettreAJourAffichage(valeur) {
    document.getElementById('plaqueAffichee').textContent =
        valeur || '— — — —';
}

// Remplir plaque depuis sélection passage
document.getElementById('sel_passage')?.addEventListener('change', function() {
    const opt   = this.options[this.selectedIndex];
    const plaque = opt.dataset.plaque || '';
    if (plaque) {
        document.getElementById('plaque_manuelle').value = plaque;
        mettreAJourAffichage(plaque);
    }
});

// Animation bouton capture
document.getElementById('formCapture')?.addEventListener('submit', function() {
    const btn = document.getElementById('btnCapture');
    btn.innerHTML = '<i class="bi bi-camera-fill"></i> Capture en cours...';
    btn.disabled  = true;

    // Simuler un délai de traitement
    setTimeout(() => {
        btn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Traitement OCR...';
    }, 500);
});

// Activer la webcam réelle
async function activerWebcam() {
    try {
        const stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment' }
        });
        streamActif = stream;
        const video = document.getElementById('videoStream');
        video.srcObject = stream;
        video.style.display = 'block';
        document.getElementById('vueSimulee').style.display = 'none';
    } catch (e) {
        alert('Webcam non disponible ou accès refusé.\n'
            + 'La simulation continue normalement.');
    }
}

// Arrêter la webcam
function arreterWebcam() {
    if (streamActif) {
        streamActif.getTracks().forEach(t => t.stop());
        streamActif = null;
    }
    document.getElementById('videoStream').style.display = 'none';
    document.getElementById('vueSimulee').style.display  = 'block';
}
</script>
</body>
</html>