<?php
require_once 'database.php';

$conn = getConnexion();

echo "<h2 style='color:green; font-family:Arial'>
    ✅ Connexion réussie à la base de données <strong>pont_bkh</strong>
</h2>";

// Afficher les tables existantes
$result = $conn->query("SHOW TABLES");
echo "<h3 style='font-family:Arial'>Tables trouvées :</h3>";
echo "<ul style='font-family:Arial'>";
while ($row = $result->fetch_array()) {
    echo "<li>📋 " . $row[0] . "</li>";
}
echo "</ul>";

$conn->close();
?>