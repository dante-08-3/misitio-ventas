<?php
// Datos de conexion tomados de variables de entorno (configuradas en Render).
// Asi la contrasena nunca queda escrita en el codigo ni en GitHub.
$host     = getenv('DB_HOST');
$dbname   = getenv('DB_NAME');
$user     = getenv('DB_USER');
$password = getenv('DB_PASS');

try {
    $pdo = new PDO(
        "pgsql:host=$host;port=5432;dbname=$dbname;sslmode=require",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => true // compatible con el pooler de Neon
        ]
    );
} catch (PDOException $e) {
    die("Error de conexion: " . $e->getMessage());
}
