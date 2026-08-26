<?php
declare(strict_types=1);

use App\Core\Env;

/** HTML escapovani vystupu */
function e(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/** Absolutni URL v aplikaci - jedine misto skladani base URL */
function url(string $path = '/'): string
{
    $base = rtrim(Env::get('APP_URL', ''), '/');
    return $base . '/' . ltrim($path, '/');
}

/** URL statickeho souboru s verzi (cache busting proti immutable cache) */
function asset_url(string $path): string
{
    $file = BASE_PATH . '/' . ltrim($path, '/');
    $v = is_file($file) ? (string)filemtime($file) : '0';
    return url($path) . '?v=' . $v;
}

function redirect(string $path): never
{
    header('Location: ' . url($path), true, 302);
    exit;
}

/** Flash zpravy (info/success/error) pres session */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'text' => $message];
}

/** @return array<array{type:string,text:string}> */
function flash_pull(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/** Formatovani ceny: 12 345 Kc */
function format_money(null|int|float|string $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    return number_format((float)$value, 0, ',', ' ') . ' Kč';
}

/** Formatovani data z DB (Y-m-d nebo datetime) na cesky format */
function format_date(?string $value, bool $withTime = false): string
{
    if ($value === null || $value === '' || str_starts_with($value, '0000')) {
        return '—';
    }
    $ts = strtotime($value);
    if ($ts === false) {
        return e($value);
    }
    return date($withTime ? 'j. n. Y H:i' : 'j. n. Y', $ts);
}
