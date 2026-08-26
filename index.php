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
use App\Controllers\AssetController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\DialController;
use App\Controllers\PersonController;
use App\Controllers\FileController;
use App\Controllers\MigrateController;
use App\Controllers\OrgController;
use App\Controllers\SettingsOrgController;
use App\Controllers\SettingsUserController;

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

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

// Majetek
$router->add('GET', '/majetek', [AssetController::class, 'index']);
$router->add('GET|POST', '/majetek/novy', [AssetController::class, 'create']);
$router->add('GET', '/majetek/{id}', [AssetController::class, 'show']);
$router->add('GET|POST', '/majetek/{id}/upravit', [AssetController::class, 'edit']);

// Zamestnanci
$router->add('GET', '/zamestnanci', [PersonController::class, 'index']);
$router->add('GET|POST', '/zamestnanci/novy', [PersonController::class, 'create']);
$router->add('GET|POST', '/zamestnanci/import', [PersonController::class, 'import']);
$router->add('GET|POST', '/zamestnanci/{id}/upravit', [PersonController::class, 'edit']);

// Nastaveni - ciselniky
$router->add('GET', '/nastaveni/ciselniky', [DialController::class, 'index']);
$router->add('POST', '/nastaveni/ciselniky/{typ}/pridat', [DialController::class, 'add']);
$router->add('POST', '/nastaveni/ciselniky/{typ}/{id}/upravit', [DialController::class, 'edit']);
$router->add('POST', '/nastaveni/ciselniky/{typ}/{id}/smazat', [DialController::class, 'delete']);

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
