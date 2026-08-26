<?php
/**
 * Docasna sonda prostredi (Faze 0) - po overeni SMAZAT z repa.
 * Pristup: /probe.php?key=<APP_KEY z .env>
 */
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');

$envFile = __DIR__ . '/.env';
if (!is_file($envFile)) {
    echo ".env na serveru CHYBI - nahrajte jej rucne pres FTP dle .env_example.\n";
    exit;
}

// mini parser .env
$env = [];
foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
    [$k, $v] = explode('=', $line, 2);
    $env[trim($k)] = trim(trim($v), "\"'");
}

if (($env['APP_KEY'] ?? '') === '' || ($_GET['key'] ?? '') !== $env['APP_KEY']) {
    http_response_code(403);
    echo "403\n";
    exit;
}

echo "=== Sonda prostredi Wedos ===\n\n";
echo 'PHP verze: ' . PHP_VERSION . "\n";
echo 'SAPI: ' . PHP_SAPI . "\n\n";

foreach (['pdo_mysql', 'mysqli', 'gd', 'mbstring', 'fileinfo', 'json', 'session', 'openssl', 'zip'] as $ext) {
    echo "ext {$ext}: " . (extension_loaded($ext) ? 'ANO' : 'NE') . "\n";
}
echo 'mail(): ' . (function_exists('mail') ? 'ANO' : 'NE') . "\n\n";

foreach (['upload_max_filesize', 'post_max_size', 'max_execution_time', 'max_file_uploads', 'memory_limit', 'display_errors', 'log_errors', 'session.save_path'] as $k) {
    echo "ini {$k}: " . var_export(ini_get($k), true) . "\n";
}

echo "\nini_set display_errors: ";
$old = ini_get('display_errors');
echo ini_set('display_errors', '0') !== false ? 'FUNGUJE' : 'NEFUNGUJE';
ini_set('display_errors', (string)$old);
echo "\n";

echo "\nZapis do /data: ";
$dir = __DIR__ . '/data/logs';
if (!is_dir($dir)) @mkdir($dir, 0775, true);
$f = $dir . '/probe-test.txt';
echo (@file_put_contents($f, date('c')) !== false) ? 'OK' : 'SELHAL';
@unlink($f);
echo "\n";

echo "\nDB connect ({$env['DB_HOST']} / {$env['DB_NAME']}): ";
try {
    if (extension_loaded('pdo_mysql')) {
        $pdo = new PDO(
            "mysql:host={$env['DB_HOST']};dbname={$env['DB_NAME']};charset=" . ($env['DB_CHARSET'] ?? 'utf8mb4'),
            $env['DB_USER'], $env['DB_PASS'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
        );
        $v = $pdo->query('SELECT VERSION()')->fetchColumn();
        echo "OK pres PDO, server: {$v}\n";
        $cs = $pdo->query("SHOW VARIABLES LIKE 'character_set_database'")->fetch(PDO::FETCH_NUM);
        echo 'charset DB: ' . ($cs[1] ?? '?') . "\n";
    } else {
        $my = @mysqli_connect($env['DB_HOST'], $env['DB_USER'], $env['DB_PASS'], $env['DB_NAME']);
        echo $my ? ('OK pres mysqli, server: ' . mysqli_get_server_info($my) . "\n") : ('SELHAL: ' . mysqli_connect_error() . "\n");
    }
} catch (Throwable $e) {
    echo 'SELHAL: ' . $e->getMessage() . "\n";
}

echo "\nRewrite test: otevrete /probe-rewrite-test - musi vratit 'REWRITE OK'\n";
echo "\n=== konec sondy ===\n";
