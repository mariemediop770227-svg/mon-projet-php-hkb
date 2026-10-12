<?php
require_once 'database.php';

// Générer le bon hash pour "password"
$nouveau_hash = password_hash('password', PASSWORD_BCRYPT);

$conn = getConnexion();

$stmt = $conn->prepare("
    UPDATE utilisateur 
    SET mot_de_passe = ? 
    WHERE email = 'admin@ponthkb.ci'
");
$stmt->bind_param('s', $nouveau_hash);

if ($stmt->execute()) {
    echo "<div style='font-family:Arial; background:#d4edda; color:#155724; 
                      padding:20px; margin:20px; border-radius:8px;'>
        <h3>✅ Mot de passe mis à jour avec succès !</h3>
        <p><strong>Email :</strong> admin@ponthkb.ci</p>
        <p><strong>Mot de passe :</strong> password</p>
        <p><strong>Hash généré :</strong> " . $nouveau_hash . "</p>
        <br>
        <a href='../auth/login.php' 
           style='background:#1a3c5e; color:white; padding:10px 20px; 
                  border-radius:6px; text-decoration:none;'>
            👉 Aller à la page de connexion
        </a>
    </div>";
} else {
    echo "<div style='font-family:Arial; background:#f8d7da; color:#721c24; 
                      padding:20px; margin:20px; border-radius:8px;'>
        <h3>❌ Erreur lors de la mise à jour</h3>
        <p>" . $conn->error . "</p>
    </div>";
}

$stmt->close();
$conn->close();
?>