<?php
// setup_atlas.php - Run from GP-Factura.Pro

ini_set('max_execution_time', 300);
ini_set('display_errors', 1);

echo "<h1>🚀 Iniciando Clonación y Rebranding de AtlasERP</h1>";

// 1. Database Cloning
echo "<h2>1. Clonando Base de Datos</h2>";
try {
    $sourceDb = 'facturacion_electronica';
    $targetDb = 'atlas_erp';
    $user = 'root';
    $pass = '';

    $pdo = new PDO("mysql:host=127.0.0.1", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create & Select
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$targetDb` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Base de datos `$targetDb` creada.<br>";

    // Disable FK Checks
    $pdo->exec("USE `$targetDb`");
    $pdo->exec("SET FOREIGN_KEY_CHECKS=0");

    // Get Tables
    $stmt = $pdo->query("SHOW TABLES FROM `$sourceDb`");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        // Drop if exists (clean slate)
        $pdo->exec("DROP TABLE IF EXISTS `$targetDb`.`$table`");

        // Get Create Syntax
        $stmtCreate = $pdo->query("SHOW CREATE TABLE `$sourceDb`.`$table`");
        $row = $stmtCreate->fetch(PDO::FETCH_ASSOC);
        $createSql = $row['Create Table'] ?? $row['Create View']; // Handle Views too? usually Create Table is index 1

        // Fix: 'Create Table' key might be casing dependant.
        if (!$createSql) {
            // Try array values logic
            $vals = array_values($row);
            $createSql = $vals[1];
        }

        // Execute Create in Target
        $pdo->exec("USE `$targetDb`");
        $pdo->exec($createSql);

        // Copy Data
        $pdo->exec("INSERT INTO `$targetDb`.`$table` SELECT * FROM `$sourceDb`.`$table`");
        echo "Tabla `$table` clonada.<br>";
    }

} catch (Exception $e) {
    die("❌ Error Base de Datos: " . $e->getMessage());
}

// 2. Update .env Configuration
echo "<h2>2. Configurando Entorno (.env)</h2>";
$targetDir = __DIR__ . '/../AtlasERP';

if (!is_dir($targetDir)) {
    die("❌ Error: La carpeta ../AtlasERP no existe. Ejecuta xcopy primero.");
}

$envPath = $targetDir . '/.env';
if (file_exists($envPath)) {
    $envContent = file_get_contents($envPath);

    // Replace DB Name
    $envContent = preg_replace('/DB_DATABASE=.*/', "DB_DATABASE=$targetDb", $envContent);
    // Replace App Name
    $envContent = preg_replace('/APP_NAME=.*/', 'APP_NAME="AtlasERP"', $envContent);
    // Replace App URL
    $envContent = str_replace('GP-Factura.Pro', 'AtlasERP', $envContent);

    file_put_contents($envPath, $envContent);
    echo "Archivo .env actualizado.<br>";
} else {
    // If xcopy skipped .env (some xcopy flags skip hidden), copy manually
    if (copy(__DIR__ . '/.env', $envPath)) {
        // Perform replacement on new copy
        $envContent = file_get_contents($envPath);
        $envContent = preg_replace('/DB_DATABASE=.*/', "DB_DATABASE=$targetDb", $envContent);
        $envContent = preg_replace('/APP_NAME=.*/', 'APP_NAME="AtlasERP"', $envContent);
        $envContent = str_replace('GP-Factura.Pro', 'AtlasERP', $envContent);
        file_put_contents($envPath, $envContent);
        echo "Archivo .env creado y configurado.<br>";
    } else {
        echo "⚠️ No se pudo crear .env<br>";
    }
}

// 3. Rebranding (Search & Replace)
echo "<h2>3. Aplicando Rebranding (Buscando y Reemplazando)</h2>";
$rdi = new RecursiveDirectoryIterator($targetDir, RecursiveDirectoryIterator::SKIP_DOTS);
$rii = new RecursiveIteratorIterator($rdi);

$replacements = [
    'GP-Factura.Pro' => 'AtlasERP',
    'GP-Nómina' => 'Atlas Nómina',
    'Nomina Pro' => 'Atlas RH',
    'Bartolito' => 'Atlas'
];

$count = 0;
foreach ($rii as $file) {
    if ($file->isFile()) {
        $ext = $file->getExtension();
        if (in_array($ext, ['php', 'html', 'js', 'css', 'json', 'md', 'sql'])) {
            $path = $file->getPathname();
            $content = file_get_contents($path);
            $newContent = str_replace(array_keys($replacements), array_values($replacements), $content);

            if ($content !== $newContent) {
                file_put_contents($path, $newContent);
                $count++;
            }
        }
    }
}

echo "Se actualizaron $count archivos con la nueva marca 'AtlasERP'.<br>";

echo "<h1>✅ ¡AtlasERP Creado Exitosamente!</h1>";
echo "<p>Accede vía: <a href='http://localhost/AtlasERP'>http://localhost/AtlasERP</a></p>";
