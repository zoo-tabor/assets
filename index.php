<?php
/**
 * Evidence majetku EKOSPOL - front controller.
 * Jediny vstupni bod: .htaccess sem prepisuje vsechny neexistujici cesty.
 */
declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($path === '/probe-rewrite-test') {
    header('Content-Type: text/plain; charset=utf-8');
    echo 'REWRITE OK';
    exit;
}

header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="cs"><head><meta charset="utf-8"><title>Evidence majetku</title></head>'
   . '<body style="font-family:sans-serif;text-align:center;padding-top:4rem">'
   . '<h1>Evidence majetku EKOSPOL</h1><p>Aplikace se připravuje…</p></body></html>';
