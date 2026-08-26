<?php
/**
 * Evidence majetku EKOSPOL - front controller.
 * Jediny vstupni bod: .htaccess sem prepisuje vsechny neexistujici cesty.
 * Minimalni try/catch obal - detaily resi bootstrap (error handlery, logovani).
 */
declare(strict_types=1);

try {
    require __DIR__ . '/app/Core/bootstrap.php';
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Chyba při startu aplikace.';
    error_log('[BOOT] ' . $e->getMessage());
    exit;
}

use App\Core\Csrf;
use App\Core\Router;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\FileController;
use App\Controllers\MigrateController;
use App\Controllers\OrgController;
use App\Controllers\SettingsOrgController;
use App\Controllers\SettingsUserController;

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// Test pruchodu rewrite pravidel (Faze 0 sonda) - odstranime spolu s probe.php
if ($path === '/probe-rewrite-test') {
    header('Content-Type: text/plain; charset=utf-8');
    echo 'REWRITE OK';
    exit;
}

// Globalni CSRF ochrana vsech POST pozadavku (mimo cron)
if ($method === 'POST' && !str_starts_with($path, '/cron/')) {
    Csrf::verify();
}

$router = new Router();

// Auth
$router->add('GET|POST', '/login', [AuthController::class, 'login']);
$router->add('POST', '/logout', [AuthController::class, 'logout']);

// Dashboard
$router->add('GET', '/', [DashboardController::class, 'index']);

// Prepinani organizace + tema
$router->add('POST', '/org/switch', [OrgController::class, 'switch']);
$router->add('POST', '/theme', [AuthController::class, 'theme']);

// Nastaveni - organizace
$router->add('GET', '/nastaveni', [SettingsOrgController::class, 'index']);
$router->add('GET', '/nastaveni/organizace', [SettingsOrgController::class, 'index']);
$router->add('GET|POST', '/nastaveni/organizace/nova', [SettingsOrgController::class, 'create']);
$router->add('GET|POST', '/nastaveni/organizace/{id}/upravit', [SettingsOrgController::class, 'edit']);

// Nastaveni - uzivatele
$router->add('GET', '/nastaveni/uzivatele', [SettingsUserController::class, 'index']);
$router->add('GET|POST', '/nastaveni/uzivatele/novy', [SettingsUserController::class, 'create']);
$router->add('GET|POST', '/nastaveni/uzivatele/{id}/upravit', [SettingsUserController::class, 'edit']);
$router->add('GET|POST', '/nastaveni/heslo', [SettingsUserController::class, 'password']);

// Soubory (chraneny vydej uploadu)
$router->add('GET', '/soubor/logo/{id}', [FileController::class, 'logo']);

// Migrator
$router->add('GET|POST', '/admin/migrate', [MigrateController::class, 'index']);

$router->dispatch($method, $path);
