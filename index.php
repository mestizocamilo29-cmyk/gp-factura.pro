<?php

date_default_timezone_set('America/Bogota');

session_start();

$scriptName = dirname($_SERVER['SCRIPT_NAME']);
$scriptName = str_replace('\\', '/', $scriptName);
$baseUrl = ($scriptName === '/') ? '' : $scriptName;

// Detect Protocol (Fix for Mixed Content / Ngrok)
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $protocol = 'https';
}

// If baseUrl is relative, prepend full host to match protocol
// Actually, usually users define BASE_URL as relative path in this legacy logic. 
// But for JS fetch with absolute URL requirements, we might need full URL?
// premium_ui.js uses BASE_URL + '/smart/ask'. If BASE_URL is '/folder', fetch uses relative. 
// Relative fetch is SAFE against protocol mismatch. 
// WAIT. If BASE_URL is just '/GP-Factura.Pro', then `fetch('/GP-Factura.Pro/smart/ask')` works on both http/https relative to current page.
// The code `define('BASE_URL', $baseUrl);` sets it to `/GP-Factura.Pro`.
// So Protocol Hardcoding IS NOT NEEDED if we keep it relative.
// BUT, I'll verify if `premium_ui.js` expects absolute.
// fetch(BASE_URL + ...) -> fetch('/GP-Factura.Pro/smart/ask') -> This is relative to domain root. Safe.
// So I don't need to change this IF $baseUrl is indeed relative.
// Line 9: `$baseUrl = ($scriptName === '/') ? '' : $scriptName;` -> Returns `/GP-Factura.Pro` (relative).
// CONCLUSION: It is safe. No edit needed here.
// I will instead fix a different bug I noticed:
// In `FacturaController.php`, line 510: It manually constructs `$link` using `http/https`.
// `external()` uses `$_SERVER['HTTPS']`. This manual construction IS BUGGY behind proxies.
// I'll fix FacturaController logic for generating links.

require_once __DIR__ . '/app/Core/config.php';

// Paracaídas de Autoload (Si Composer falla en Hosting Compartido)
spl_autoload_register(function ($class) {
    if (strpos($class, 'App\\') === 0) {
        $file = __DIR__ . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Load Environment Variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

// Global Error Handler
if (defined('APP_DEBUG') && APP_DEBUG) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

set_exception_handler(function ($e) {
    // Check Constant first, then Env
    $isDebug = defined('APP_DEBUG') ? APP_DEBUG : (($_ENV['APP_DEBUG'] ?? 'false') === 'true');

    if ($isDebug) {
        echo "<h1>Error del Sistema</h1>";
        echo "<p><strong>Mensaje:</strong> " . $e->getMessage() . "</p>";
        echo "<p><strong>Archivo:</strong> " . $e->getFile() . " linea " . $e->getLine() . "</p>";
        echo "<pre>" . $e->getTraceAsString() . "</pre>";
    } else {
        error_log($e->getMessage()); // Log to server file
        http_response_code(500);
        // User friendly message
        echo "<h1>Ocurrió un error inesperado</h1>";
        echo "<p>Por favor, intenta de nuevo más tarde o contacta a soporte.</p>";
    }
    exit;
});

require_once __DIR__ . '/app/Helpers/SecurityHelper.php';

// Removed redundant spl_autoload_register since Composer handles App namespace via PSR-4

use App\Core\Router;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\UsuarioController;
use App\Controllers\ClienteController;
use App\Controllers\ProductoController;
use App\Controllers\FacturaController;
use App\Controllers\EmpresaController;
use App\Controllers\CategoriaController;
use App\Controllers\MetodoPagoController;
use App\Controllers\PermisoController;
use App\Controllers\ResolucionController;
use App\Controllers\PagoController;

use App\Controllers\ClienteViewController;
use App\Controllers\ReporteController;
use App\Controllers\ActivityLogController;
use App\Controllers\EstadisticaController;
use App\Controllers\PerfilController;
use App\Controllers\SuperAdminController;
use App\Controllers\AiController;

$router = new Router();

// AI Chat Routes (Smart Helper)
$router->post('/smart/query', [AiController::class, 'chat']);
$router->post('/smart/ask', [\App\Controllers\SmartQueryController::class, 'query']); // New "Invisible AI" Query
$router->post('/smart/search', [\App\Controllers\SmartQueryController::class, 'search']); // Isolated Deep Search Modal
$router->get('/smart/test', [AiController::class, 'testConnection']); // Diagnostic Route
$router->get('/smart/history', [AiController::class, 'history']);
$router->get('/smart/conversation', [AiController::class, 'conversation']);
$router->post('/smart/delete', [AiController::class, 'delete_conversation']);

// Subscription Routes removed (Controller does not exist)


// Portal Client Routes (Company Specific)
$router->get('/portal', [App\Controllers\PortalController::class, 'index']);
$router->post('/portal/search', [App\Controllers\PortalController::class, 'search']);
$router->get('/portal/login', [App\Controllers\PortalController::class, 'login']);
$router->post('/portal/auth', [App\Controllers\PortalController::class, 'authenticate']);

$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);
$router->get('/crear-usuario', [AuthController::class, 'showCrearUsuario']);
$router->post('/crear-usuario', [AuthController::class, 'crearUsuario']);
$router->get('/olvido-contrasena', [AuthController::class, 'showOlvidoContrasena']);
$router->post('/olvido-contrasena', [AuthController::class, 'olvidoContrasena']);
$router->get('/reset-password', [AuthController::class, 'showResetPassword']);
$router->post('/reset-password', [AuthController::class, 'resetPassword']);

// Registration Routes
$router->get('/register', [AuthController::class, 'registerView']);
$router->post('/register/submit', [AuthController::class, 'register']);

// Google Auth Routes
$router->get('/auth/google', [AuthController::class, 'googleLogin']);
$router->get('/auth/google/callback', [AuthController::class, 'googleCallback']);

// Magic Link/Code Routes
$router->post('/auth/send-code', [AuthController::class, 'sendLoginCode']);
$router->post('/auth/verify-code', [AuthController::class, 'verifyLoginCode']);

// GOD MODE ROUTES (Supervision)
$router->get('/auth/impersonate', [AuthController::class, 'impersonate']);
$router->get('/auth/leave_impersonation', [AuthController::class, 'leaveImpersonation']);
$router->get('/auth/ghost_mode', [AuthController::class, 'toggleGhostMode']);

$router->get('/', [DashboardController::class, 'index']);
$router->get('/dashboard', [DashboardController::class, 'index']);
$router->get('/dashboard/chart-data', [DashboardController::class, 'getChartData']);

$router->get('/usuarios', [UsuarioController::class, 'index']);
$router->get('/usuarios/crear', [UsuarioController::class, 'create']);
$router->post('/usuarios/guardar', [UsuarioController::class, 'store']);
$router->get('/usuarios/ver', [UsuarioController::class, 'show']);
$router->get('/usuarios/editar', [UsuarioController::class, 'edit']);
$router->post('/usuarios/actualizar', [UsuarioController::class, 'update']);
$router->get('/usuarios/eliminar', [UsuarioController::class, 'delete']);

$router->get('/clientes', [ClienteController::class, 'index']);
$router->get('/clientes/crear', [ClienteController::class, 'create']);
$router->post('/clientes/guardar', [ClienteController::class, 'store']);
$router->get('/clientes/ver', [ClienteController::class, 'show']); // Added route
$router->get('/clientes/editar', [ClienteController::class, 'edit']);
$router->post('/clientes/actualizar', [ClienteController::class, 'update']);
$router->get('/clientes/eliminar', [ClienteController::class, 'delete']);

$router->get('/productos', [ProductoController::class, 'index']);
$router->get('/productos/crear', [ProductoController::class, 'create']);
$router->post('/productos/guardar', [ProductoController::class, 'store']);
$router->get('/productos/ver', [ProductoController::class, 'show']); // Added route
$router->get('/productos/editar', [ProductoController::class, 'edit']);
$router->post('/productos/actualizar', [ProductoController::class, 'update']);
$router->get('/productos/eliminar', [ProductoController::class, 'delete']);
$router->get('/productos/searchImages', [ProductoController::class, 'searchImages']);

$router->get('/facturas', [FacturaController::class, 'index']);
$router->get('/facturas/crear', [FacturaController::class, 'create']);
$router->post('/facturas/guardar', [FacturaController::class, 'store']);
$router->post('/facturas/preview', [FacturaController::class, 'preview']);
$router->get('/facturas/ver', [FacturaController::class, 'show']);
$router->get('/facturas/editar', [FacturaController::class, 'edit']);
$router->post('/facturas/actualizar', [FacturaController::class, 'update']);
$router->get('/facturas/eliminar', [FacturaController::class, 'delete']);
$router->get('/facturas/pdf', [FacturaController::class, 'downloadPdf']); // Kept for backward compat but remapped
$router->get('/facturas/downloadPdf', [FacturaController::class, 'downloadPdf']);
$router->get('/facturas/sendEmail', [FacturaController::class, 'sendEmail']);
$router->get('/facturas/external', [FacturaController::class, 'external']);
$router->get('/facturas/cardif', [FacturaController::class, 'cardif']);

$router->get('/empresas', [EmpresaController::class, 'index']);
$router->get('/empresas/crear', [EmpresaController::class, 'create']);
$router->post('/empresas/guardar', [EmpresaController::class, 'store']);
$router->get('/empresas/show', [EmpresaController::class, 'show']); // Fix 404
$router->get('/empresas/ver', [EmpresaController::class, 'show']); // Alias for consistency
$router->get('/empresas/editar', [EmpresaController::class, 'edit']);
$router->post('/empresas/actualizar', [EmpresaController::class, 'update']);
$router->get('/empresas/eliminar', [EmpresaController::class, 'delete']);

$router->get('/categorias', [CategoriaController::class, 'index']);
$router->get('/categorias/crear', [CategoriaController::class, 'create']);
$router->post('/categorias/guardar', [CategoriaController::class, 'store']);
$router->get('/categorias/editar', [CategoriaController::class, 'edit']);
$router->post('/categorias/actualizar', [CategoriaController::class, 'update']);
$router->get('/categorias/eliminar', [CategoriaController::class, 'delete']);

$router->get('/metodos-pago', [MetodoPagoController::class, 'index']);
$router->get('/metodos-pago/crear', [MetodoPagoController::class, 'create']);
$router->post('/metodos-pago/guardar', [MetodoPagoController::class, 'store']);
$router->get('/metodos-pago/ver', [MetodoPagoController::class, 'show']); // Added route
$router->get('/metodos-pago/editar', [MetodoPagoController::class, 'edit']);
$router->post('/metodos-pago/actualizar', [MetodoPagoController::class, 'update']);
$router->get('/metodos-pago/eliminar', [MetodoPagoController::class, 'delete']);

$router->get('/pagos', [PagoController::class, 'index']);
$router->get('/pagos/crear', [PagoController::class, 'create']);
$router->post('/pagos/guardar', [PagoController::class, 'store']);
$router->get('/pagos/ver', [PagoController::class, 'show']);
$router->get('/pagos/editar', [PagoController::class, 'edit']);
$router->post('/pagos/actualizar', [PagoController::class, 'update']);
$router->get('/pagos/eliminar', [PagoController::class, 'delete']);



$router->get('/reportes/metodos', [ReporteController::class, 'metodos']);

$router->get('/resolucion', [ResolucionController::class, 'index']);
$router->post('/resolucion/guardar', [ResolucionController::class, 'store']);
$router->get('/api/dian/check', [ResolucionController::class, 'checkConnection']);

$router->get('/permisos', [PermisoController::class, 'index']);
$router->get('/permisos/crear', [PermisoController::class, 'create']);
$router->post('/permisos/guardar', [PermisoController::class, 'guardar']);
$router->get('/permisos/editar', [PermisoController::class, 'edit']);
$router->post('/permisos/actualizar', [PermisoController::class, 'update']);
$router->post('/permisos/eliminar', [PermisoController::class, 'delete']);

$router->get('/cliente/facturas', [ClienteViewController::class, 'facturas']);
$router->post('/cliente/agendar-cita', [ClienteViewController::class, 'scheduleCashAppointment']);
$router->post('/cliente/wompi-signature', [ClienteViewController::class, 'getWompiSignature']);
$router->get('/cliente/perfil', [ClienteViewController::class, 'perfil']);
$router->post('/cliente/actualizar-avatar', [ClienteViewController::class, 'updateAvatar']);
$router->post('/cliente/actualizar-password', [ClienteViewController::class, 'updatePassword']);

// Wompi Webhook (Legacy)
$router->post('/wompi/webhook', [WompiWebhookController::class, 'handleWebhook']);

// Mercado Pago Routes
$router->post('/cliente/create-mp-preference', [\App\Controllers\MercadoPagoController::class, 'createPreference']);
$router->post('/mercadopago/webhook', [\App\Controllers\MercadoPagoController::class, 'webhook']);

// WhatsApp Webhook Routes
$router->get('/whatsapp/webhook', [\App\Controllers\WhatsAppWebhookController::class, 'verify']);
$router->post('/whatsapp/webhook', [\App\Controllers\WhatsAppWebhookController::class, 'handle']);




$router->get('/estadisticas', [EstadisticaController::class, 'index']);
$router->get('/reporte/detalle_metodo', [ReporteController::class, 'detalle_metodo']);
$router->get('/reporte/users', [ReporteController::class, 'get_users']);
$router->get('/reporte/pdf', [ReporteController::class, 'reporte_pdf']);

// Activity Log Routes (Superadmin)
$router->get('/actividad', [ActivityLogController::class, 'index']);
$router->post('/actividad/settings', [ActivityLogController::class, 'updateSettings']);
$router->get('/api/notifications', [ActivityLogController::class, 'getNotifications']);
$router->get('/api/notifications/mark-read', [ActivityLogController::class, 'markRead']);
$router->get('/actividad/delete', [ActivityLogController::class, 'delete']);

// User Profile Routes
$router->get('/perfil', [PerfilController::class, 'index']);
$router->post('/perfil/update', [PerfilController::class, 'update']);
$router->post('/perfil/update-avatar', [PerfilController::class, 'updateAvatar']);
$router->post('/perfil/set-gender-avatar', [PerfilController::class, 'setGenderAndAvatar']);
$router->post('/perfil/delete-company', [PerfilController::class, 'deleteCompany']);

// Super Admin / SaaS Routes (Provisioning)
$router->get('/admin/companies', [SuperAdminController::class, 'index']);
$router->post('/admin/companies/store', [SuperAdminController::class, 'store']);

// Configuration Routes
$router->get('/configuracion/siigo', [\App\Controllers\ConfiguracionController::class, 'siigo']);
$router->post('/configuracion/siigo/guardar', [\App\Controllers\ConfiguracionController::class, 'guardar']);
$router->get('/configuracion/siigo/test', [\App\Controllers\ConfiguracionController::class, 'test']);

// ==========================================
// MÓDULO NÓMINA (SUB-APP)
// ==========================================
$router->get('/nomina', [\App\Modules\Nomina\Controllers\DashboardController::class, 'index']);
$router->get('/nomina/dashboard', [\App\Modules\Nomina\Controllers\DashboardController::class, 'index']);

// Empleados
$router->get('/nomina/empleados', [\App\Modules\Nomina\Controllers\EmpleadoController::class, 'index']);
$router->get('/nomina/empleados/crear', [\App\Modules\Nomina\Controllers\EmpleadoController::class, 'create']);
$router->post('/nomina/empleados/guardar', [\App\Modules\Nomina\Controllers\EmpleadoController::class, 'store']);

// Conceptos de Nómina
$router->get('/nomina/conceptos', [\App\Modules\Nomina\Controllers\ConceptoController::class, 'index']);

// Novedades
$router->get('/nomina/novedades', [\App\Modules\Nomina\Controllers\NovedadController::class, 'index']);
$router->get('/nomina/novedades/crear', [\App\Modules\Nomina\Controllers\NovedadController::class, 'create']);
$router->post('/nomina/novedades/guardar', [\App\Modules\Nomina\Controllers\NovedadController::class, 'store']);

// Liquidación
$router->get('/nomina/liquidar', [\App\Modules\Nomina\Controllers\LiquidarController::class, 'index']);
$router->post('/nomina/liquidar/previsualizar', [\App\Modules\Nomina\Controllers\LiquidarController::class, 'previsualizar']);
$router->post('/nomina/liquidar/aprobar', [\App\Modules\Nomina\Controllers\LiquidarController::class, 'aprobar']);

// Placeholder routes for future implementation
$router->get('/nomina/contratos', function () {
    echo "<h1>Módulo de Contratos en Construcción</h1><a href='" . BASE_URL . "/nomina/dashboard'>Volver</a>";
});


// Suggestions Route
// Suggestions Route
$router->post('/suggestions/send', [\App\Controllers\SuggestionsController::class, 'send']);

// 🤖 Automation Cron Route
$router->get('/cron/run-daily', [\App\Controllers\System\AutomationController::class, 'runDaily']);

// 🔍 DIAGNOSTIC ROUTES (Temporary for Debugging)
$router->get('/debug-bot', function () {
    echo "<h1>🔍 Diagnóstico del Bot de WhatsApp (Integrado)</h1>";

    // 1. Check Permissions
    echo "<h2>1. Permisos de Escritura</h2>";
    $logFile = __DIR__ . '/webhook_debug.log';
    if (is_writable(__DIR__)) { // Root
        echo "✅ Carpeta raíz es escribible.<br>";
    } else {
        echo "❌ Carpeta raíz NO es escribible (El log no funcionará).<br>";
    }

    // 2. Load Environment
    echo "<h2>2. Carga de Entorno (.env)</h2>";
    if (file_exists(__DIR__ . '/.env')) {
        echo "✅ Archivo .env encontrado.<br>";
        $env = parse_ini_file(__DIR__ . '/.env');

        echo "META_PHONE_ID: " . (!empty($env['META_PHONE_ID']) ? '✅ Configurado' : '❌ FALTANTE') . "<br>";
        echo "META_WHATSAPP_TOKEN: " . (!empty($env['META_WHATSAPP_TOKEN']) ? '✅ Configurado' : '❌ FALTANTE') . "<br>";
        echo "SOPORTE_WHATSAPP: " . (!empty($env['SOPORTE_WHATSAPP']) ? '✅ Configurado (' . $env['SOPORTE_WHATSAPP'] . ')' : '❌ FALTANTE') . "<br>";
    } else {
        echo "❌ Archivo .env NO encontrado en la raíz.<br>";
    }

    // 3. Check Classes
    echo "<h2>3. Verificación de Clases</h2>";
    if (class_exists('\App\Controllers\WhatsAppWebhookController')) {
        echo "✅ WhatsAppWebhookController cargado.<br>";
    } else {
        echo "❌ WhatsAppWebhookController NO encontrado.<br>";
    }
    if (class_exists('\App\Services\Communication\WhatsAppService')) {
        echo "✅ WhatsAppService cargado.<br>";
    } else {
        echo "❌ WhatsAppService NO encontrado.<br>";
    }

    // 4. Test DB
    echo "<h2>4. Conexión a Base de Datos</h2>";
    try {
        $db = \App\Core\Database::getInstance()->getConnection();
        echo "✅ Conexión Exitosa.<br>";
    } catch (Exception $e) {
        echo "❌ Error de Conexión: " . $e->getMessage() . "<br>";
    }

    echo "<br><a href='webhook-test'>👉 Probar Webhook (Simular 'Hola')</a>";
});

$router->get('/webhook-test', function () {
    global $baseUrl;
    // $baseUrl comes from index.php scope? No, closure. 
    // Re-calculate or use global. $baseUrl is defined at top.
    // In formatting it was $baseUrl = ... verify if I can access it.
    // Closures don't inherit scope unless `use`. 
    // Let's re-calculate safely.
    $scriptName = dirname($_SERVER['SCRIPT_NAME']);
    $scriptName = str_replace('\\', '/', $scriptName);
    $myBaseUrl = ($scriptName === '/') ? '' : $scriptName;
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";

    $url = $protocol . "://" . $_SERVER['HTTP_HOST'] . $myBaseUrl . "/whatsapp/webhook";

    echo "<h1>🧪 Prueba Manual de Webhook</h1>";
    echo "Target URL: $url <br>";

    $simulatedBody = json_encode([
        'entry' => [
            [
                'changes' => [
                    [
                        'value' => [
                            'messages' => [
                                [
                                    'from' => '573005266538',
                                    'type' => 'text',
                                    'text' => ['body' => 'Hola (Prueba desde Hosting)']
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $simulatedBody);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    // Timeout fast
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    echo "HTTP Code: $httpCode<br>";
    if ($error)
        echo "cURL Error: $error<br>";
    echo "Response: $response<br>";

    if ($httpCode == 200) {
        echo "✅ El controlador recibió la solicitud.";
    } else {
        echo "❌ Error al contactar el controlador.";
    }
});
$router->resolve();
