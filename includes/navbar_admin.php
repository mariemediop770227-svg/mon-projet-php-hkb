<?php
// Vérifier que l'admin est connecté
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

$page_active = basename($_SERVER['PHP_SELF']);

// Page active
$page_active = basename($_SERVER['PHP_SELF']);
?>

<div class="sidebar" id="sidebar">
    <div class="sidebar-titre">
        <div style="font-size:2rem">🌉</div>
        <h5>Pont HKB</h5>
        <small style="color:rgba(255,255,255,0.6); font-size:0.75rem">Administration</small>
    </div>

    <!-- Infos admin -->
    <div class="px-3 py-2 border-bottom" style="border-color:rgba(255,255,255,0.15) !important">
        <small style="color:rgba(255,255,255,0.6)">Connecté en tant que</small>
        <div style="color:white; font-weight:600; font-size:0.9rem">
            <?= $_SESSION['prenom'] . ' ' . $_SESSION['nom'] ?>
        </div>
    </div>

    <!-- Menu -->
    <nav class="mt-2">
        <ul class="nav flex-column px-2">

            <li class="nav-item">
                <a class="nav-link <?= $page_active === 'index.php' ? 'active' : '' ?>"
                   href="../admin/index.php">
                    <i class="bi bi-speedometer2"></i> Tableau de bord
                </a>
            </li>

            <li class="nav-item mt-1">
                <small class="text-uppercase px-3"
                       style="color:rgba(255,255,255,0.4); font-size:0.7rem; letter-spacing:1px">
                    Gestion
                </small>
            </li>

            <li class="nav-item">
                <a class="nav-link <?= $page_active === 'agents.php' ? 'active' : '' ?>"
                   href="../admin/agents.php">
                    <i class="bi bi-people"></i> Agents
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link <?= $page_active === 'guichets.php' ? 'active' : '' ?>"
                   href="../admin/guichets.php">
                    <i class="bi bi-building"></i> Guichets
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link <?= $page_active === 'abonnements.php' ? 'active' : '' ?>"
                   href="../admin/abonnements.php">
                    <i class="bi bi-card-checklist"></i> Abonnements
                </a>
            </li>

            <li class="nav-item mt-1">
                <small class="text-uppercase px-3"
                       style="color:rgba(255,255,255,0.4); font-size:0.7rem; letter-spacing:1px">
                    Finances
                </small>
            </li>

            <li class="nav-item">
                <a class="nav-link <?= $page_active === 'recettes.php' ? 'active' : '' ?>"
                   href="../admin/recettes.php">
                    <i class="bi bi-cash-stack"></i> Recettes
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link <?= $page_active === 'rapports.php' ? 'active' : '' ?>"
                   href="../admin/rapports.php">
                    <i class="bi bi-file-earmark-bar-graph"></i> Rapports
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link <?= $page_active === 'statistiques.php' ? 'active' : '' ?>"
                   href="../admin/statistiques.php">
                    <i class="bi bi-bar-chart-line"></i> Statistiques
                </a>
            </li>

            <li class="nav-item mt-3">
                <a class="nav-link text-danger"
                   href="../auth/logout.php"
                   onclick="return confirm('Voulez-vous vraiment vous déconnecter ?')">
                    <i class="bi bi-box-arrow-right"></i> Déconnexion
                </a>
            </li>

        </ul>
    </nav>
</div>