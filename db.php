<?php
// ============================================================
// db.php — Connexion PDO à MySQL
// Modifiez DB_USER / DB_PASS selon votre config XAMPP
// ============================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'servilocal');
define('DB_USER', 'root');
define('DB_PASS', '');        // Vide par défaut sur XAMPP
define('DB_CHARSET', 'utf8mb4');

$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,  // Exceptions sur erreur
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,        // Tableaux associatifs
    PDO::ATTR_EMULATE_PREPARES   => false,                   // Vraies requêtes préparées
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // En production : ne pas afficher le message d'erreur brut
    die(json_encode(['error' => 'Connexion à la base de données impossible.']));
}
