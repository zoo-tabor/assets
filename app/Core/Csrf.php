<?php
declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /** Skryte pole do formulare */
    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::token()) . '">';
    }

    /** Overeni POST pozadavku - pri selhani konci 403 */
    public static function verify(): void
    {
        $sent = $_POST['_csrf'] ?? '';
        if (!is_string($sent) || $sent === '' || !hash_equals(self::token(), $sent)) {
            http_response_code(403);
            echo 'Neplatný bezpečnostní token (CSRF). Vraťte se zpět a zkuste to znovu.';
            exit;
        }
    }
}
