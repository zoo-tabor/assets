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
use App\Controllers\AssetExtrasController;
use App\Controllers\AssetFileController;
use App\Controllers\AuditController;
use App\Controllers\AuthController;
use App\Controllers\CronController;
use App\Controllers\ReportController;
use App\Controllers\SettingsFieldController;
use App\Controllers\DashboardController;
use App\Controllers\DialController;
use App\Controllers\PersonController;
use App\Controllers\FileController;
use App\Controllers\MigrateController;
use App\Controllers\MovementController;
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
$router->add('GET', '/majetek/export.{format}', [AssetController::class, 'export']);
$router->add('GET', '/majetek/{id}', [AssetController::class, 'show']);
$router->add('GET|POST', '/majetek/{id}/upravit', [AssetController::class, 'edit']);
$router->add('POST', '/majetek/{id}/fotky', [AssetFileController::class, 'uploadPhotos']);
$router->add('POST', '/majetek/{id}/fotky/{photoId}/smazat', [AssetFileController::class, 'deletePhoto']);
$router->add('POST', '/majetek/{id}/fotky/{photoId}/hlavni', [AssetFileController::class, 'setPrimaryPhoto']);
$router->add('POST', '/majetek/{id}/dokumenty', [AssetFileController::class, 'uploadDocument']);
$router->add('POST', '/majetek/{id}/dokumenty/{docId}/smazat', [AssetFileController::class, 'deleteDocument']);
$router->add('POST', '/majetek/{id}/zaruka', [AssetExtrasController::class, 'saveWarranty']);
$router->add('POST', '/majetek/{id}/zaruka/smazat', [AssetExtrasController::class, 'deleteWarranty']);
$router->add('POST', '/majetek/{id}/udrzba', [AssetExtrasController::class, 'addMaintenance']);
$router->add('POST', '/majetek/{id}/udrzba/{mid}/dokoncit', [AssetExtrasController::class, 'completeMaintenance']);
$router->add('POST', '/majetek/{id}/udrzba/{mid}/smazat', [AssetExtrasController::class, 'deleteMaintenance']);
$router->add('POST', '/majetek/{id}/vazby', [AssetExtrasController::class, 'addLink']);
$router->add('POST', '/majetek/{id}/vazby/{childId}/smazat', [AssetExtrasController::class, 'deleteLink']);

// Soubory majetku (chraneny vydej)
$router->add('GET', '/soubor/foto/{photoId}', [AssetFileController::class, 'servePhoto']);
$router->add('GET', '/soubor/dokument/{docId}', [AssetFileController::class, 'serveDocument']);

// Nastaveni - vlastni pole
$router->add('GET', '/nastaveni/vlastni-pole', [SettingsFieldController::class, 'index']);
$router->add('POST', '/nastaveni/vlastni-pole/pridat', [SettingsFieldController::class, 'add']);
$router->add('POST', '/nastaveni/vlastni-pole/{id}/upravit', [SettingsFieldController::class, 'edit']);
$router->add('POST', '/nastaveni/vlastni-pole/{id}/smazat', [SettingsFieldController::class, 'delete']);

// Pohyby
foreach (['vydej', 'vraceni', 'presun', 'vyrazeni', 'rezervace'] as $mv) {
    $router->add('GET|POST', '/' . $mv, [MovementController::class, $mv]);
}
$router->add('GET', '/pohyby', [MovementController::class, 'history']);

// Inventury
$router->add('GET', '/inventury', [AuditController::class, 'index']);
$router->add('POST', '/inventury/nova', [AuditController::class, 'create']);
$router->add('GET', '/inventury/{id}', [AuditController::class, 'show']);
$router->add('POST', '/inventury/{id}/polozka/{assetId}', [AuditController::class, 'mark']);
$router->add('POST', '/inventury/{id}/uzavrit', [AuditController::class, 'close']);

// Reporty
$router->add('GET', '/reporty', [ReportController::class, 'index']);
$router->add('GET', '/reporty/vydej', [ReportController::class, 'checkouts']);
$router->add('GET', '/reporty/zaruky', [ReportController::class, 'warranties']);
$router->add('GET', '/reporty/udrzba', [ReportController::class, 'maintenance']);

// Cron (klic v query i v ceste - Wedos cron neumi query string)
$router->add('GET', '/cron/run', [CronController::class, 'run']);
$router->add('GET', '/cron/run/{key}', [CronController::class, 'run']);

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
