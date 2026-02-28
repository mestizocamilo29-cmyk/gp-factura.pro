<?php
// check_setup.php - Diagnostic Tool
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>🔍 Diagnóstico de Servidor</h1>";

// 1. PHP Version
echo "<h2>1. Versión de PHP</h2>";
echo "PHP Version: " . phpversion() . "<br>";

// 2. Server Vars
echo "<h2>2. Variables de Servidor</h2>";
echo "HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'No definido') . "<br>";
echo "SCRIPT_NAME: " . $_SERVER['SCRIPT_NAME'] . "<br>";
echo "DOCUMENT_ROOT: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";

// 3. File System Check
echo "<h2>3. Sistema de Archivos</h2>";
$files = [
    'index.php',
    '.htaccess',
    'app/Core/config.php',
    'app/Modules/Nomina/Controllers/DashboardController.php', // CRITICAL CHECK
    'app/Modules/Nomina/Views/dashboard.php'
];
foreach ($files as $file) {
    echo "$file: " . (file_exists(__DIR__ . '/' . $file) ? '✅ Existe' : '❌ FALTA (Revisar subida FTP)') . "<br>";
}

// 4. Config & Database Check
echo "<h2>4. Configuración y Base de Datos</h2>";
try {
    if (file_exists(__DIR__ . '/app/Core/config.php')) {
        require_once __DIR__ . '/app/Core/config.php';
        echo "Carga de config.php: ✅ Correcta<br>";

        // Check Constants from config
        echo "BASE_URL check: " . (defined('BASE_URL') ? BASE_URL : 'No definido') . "<br>";
        echo "APP_ENV check: " . (defined('APP_ENV') ? APP_ENV : 'No definido') . "<br>";

        // Test DB Connection manually using same credentials
        // (We can't easily access the internal variables of config.php unless we modify it or use the defined PDO if exposed)
        // config.php usually creates $pdo or defines constants? 
        // Let's check if $pdo is available in scope after require
        if (isset($pdo)) {
            echo "Conexión PDO (desde config.php): ✅ Exitosa<br>";

            // Test Query
            $stmt = $pdo->query("SELECT count(*) FROM users");
            // Assuming 'users' table exists, or try a generic one if not sure. 
            // Actually, we don't know if 'users' exists for sure, maybe 'usuarios'?
            // Let's try simple select 1.
            $stmt = $pdo->query("SELECT 1");
            echo "Consulta de prueba (SELECT 1): ✅ Exitosa<br>";
        } else {
            echo "Conexión PDO: ❌ Variable \$pdo no definida después de incluir config.php<br>";
        }

    } else {
        echo "❌ No se puede probar DB porque falta config.php<br>";
    }
} catch (Exception $e) {
    echo "❌ Error Crítico: " . $e->getMessage() . "<br>";
}

echo "<hr><p>Si ves esto, PHP está funcionando. Si la conexión a BD falla, revisa las credenciales en config.php.</p>";
