<?php
/**
 * Bootstrap aplikace - nahrazuje veci, ktere na Wedosu nelze resit
 * pres php.ini ani .htaccess (php_flag je blokovan).
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));
define('DATA_PATH', BASE_PATH . '/data');

mb_internal_encoding('UTF-8');
date_default_timezone_set('Europe/Prague');

// --- Autoloader: App\Core\Db -> app/Core/Db.php ---
spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

require __DIR__ . '/helpers.php';

// --- .env ---
App\Core\Env::load(BASE_PATH . '/.env');

define('APP_DEBUG', App\Core\Env::get('APP_ENV', 'production') === 'development');

// --- Chyby: nikdy nevypisovat navstevnikovi (mimo development), logovat do /data/logs ---
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

$logDir = DATA_PATH . '/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}
ini_set('error_log', $logDir . '/php-' . date('Y-m-d') . '.log');

set_exception_handler(function (Throwable $e): void {
    error_log(sprintf('[EXC] %s: %s in %s:%d\n%s', get_class($e), $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString()));
    http_response_code(500);
    if (APP_DEBUG) {
        header('Content-Type: text/plain; charset=utf-8');
        echo (string)$e;
    } else {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="cs"><meta charset="utf-8"><title>Chyba</title>'
           . '<body style="font-family:sans-serif;text-align:center;padding-top:4rem">'
           . '<h1>Došlo k chybě serveru</h1><p>Zkuste to prosím znovu, případně kontaktujte správce.</p></body></html>';
    }
    exit;
});

// --- Session ---
session_name('ekass');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'secure' => (($_SERVER['HTTPS'] ?? '') === 'on'),
    'samesite' => 'Lax',
]);
session_start();

header('Content-Type: text/html; charset=utf-8');
