<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Load seed data
$seed = json_decode(file_get_contents(DATA_PATH . 'seed.json'), true);

$pdo = getDbConnection();
if ($pdo) {
    ensureMysqlSchema($pdo);
    echo "✅ Schéma MySQL initialisé<br>";
} else {
    echo "ℹ️ MySQL non configuré, utilisation du stockage JSON<br>";
}

// Ensure invoice counter starts at 123 so next generated is 124
$counters = loadData('counters');
$desired_start = 123;
if (!isset($counters['fa']) || (int)$counters['fa'] < $desired_start) {
    $counters['fa'] = $desired_start;
    saveData('counters', $counters);
    // Also ensure DB counter if using MySQL
    if ($pdo) {
        $stmt = $pdo->prepare('INSERT INTO counters (name, value) VALUES (:name, :value) ON DUPLICATE KEY UPDATE value = :value');
        $stmt->execute([':name' => 'fa', ':value' => $desired_start]);
        echo "✅ Compteur facture initialisé à {$desired_start} dans la BDD<br>";
    }
    echo "✅ Compteur facture initialisé à {$desired_start} (JSON)<br>";
} else {
    echo "ℹ️ Compteur facture déjà initialisé ({$counters['fa']})<br>";
}

// Only seed if empty
$clients = loadData('clients');
if (empty($clients)) {
    saveData('clients', $seed['clients']);
    echo "✅ Clients chargés (" . count($seed['clients']) . ")<br>";
} else {
    echo "ℹ️ Clients déjà présents (" . count($clients) . ")<br>";
}

$materiels = loadData('materiels');
if (empty($materiels)) {
    saveData('materiels', $seed['materiels']);
    echo "✅ Matériels chargés (" . count($seed['materiels']) . ")<br>";
} else {
    echo "ℹ️ Matériels déjà présents (" . count($materiels) . ")<br>";
}

// Initialize default admin user
$users = loadData('users');
if (empty($users)) {
    createUser('admin@technolocation.tn', 'admin123', 'Administrateur', 'admin');
    echo "✅ Utilisateur administrateur créé (admin@technolocation.tn / admin123)<br>";
} else {
    echo "ℹ️ Utilisateurs déjà présents (" . count($users) . ")<br>";
}

echo "<br><strong>✅ Base initialisée !</strong><br>";
echo '<a href="login.php" style="color:#1a56db">→ Aller à la connexion</a>';
