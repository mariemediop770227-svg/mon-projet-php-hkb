<?php
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'agent') {
    header('Location: ../auth/login.php');
    exit();
}
$page_active = basename($_SERVER['PHP_SELF']);
?>

<div class="sidebar" id="sidebar">
    <div class="sidebar-titre">
        <div style="font-size:2rem">🌉</div>
        <h5>Pont HKB</h5>
        <small style="color:rgba(255,255,255,0.6); font-size:0.75rem">Espace Agent</small>
    </div>

    <!-- Infos agent -->
    <div class="px-3 py-2 border-bottom"
         style="border-color:rgba(255,255,255,0.15) !important">
        <small style="color:rgba(255,255,255,0.6)">Connecté en tant que</small>
        <div style="color:white; font-weight:600; font-size:0.9rem">
            <?= $_SESSION['prenom'] . ' ' . $_SESSION['nom'] ?>
        </div>
    </div>

    <nav class="mt-2">
        <ul class="nav flex-column px-2">

            <li class="nav-item">
                <a class="nav-link <?= $page_active === 'index.php' ? 'active' : '' ?>"
                   href="../agent/index.php">
                    <i class="bi bi-speedometer2"></i> Tableau de bord
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link <?= $page_active === 'passage.php' ? 'active' : '' ?>"
                   href="../agent/passage.php">
                    <i class="bi bi-car-front"></i> Enregistrer passage
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link <?= $page_active === 'historique.php' ? 'active' : '' ?>"
                   href="../agent/historique.php">
                    <i class="bi bi-clock-history"></i> Historique
                </a>
            </li>

            
            <li class="nav-item">
                   <a class="nav-link <?= $page_active === 'capture.php' ? 'active' : '' ?>"
                         href="../agent/capture.php">
                         <i class="bi bi-camera-video"></i> Capture caméra
                    </a>
            </li>

            <li class="nav-item mt-3">
                <a class="nav-link text-danger"
                   href="../auth/logout.php"
                   onclick="return confirm('Voulez-vous vous déconnecter ?')">
                    <i class="bi bi-box-arrow-right"></i> Déconnexion
                </a>
            </li>

        </ul>
    </nav>
</div>