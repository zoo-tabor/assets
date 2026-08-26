<?php
declare(strict_types=1);

namespace App\Core;

final class Auth
{
    private static ?array $user = null;
    private static bool $loaded = false;

    private const MAX_ATTEMPTS = 5;
    private const WINDOW_MINUTES = 15;

    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$user;
        }
        self::$loaded = true;
        $id = $_SESSION['user_id'] ?? null;
        if ($id === null) {
            return null;
        }
        $user = Db::instance()->one('SELECT * FROM users WHERE id = ? AND active = 1', [$id]);
        self::$user = $user;
        if ($user === null) {
            unset($_SESSION['user_id']);
        }
        return self::$user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): int
    {
        return (int)(self::user()['id'] ?? 0);
    }

    /** Vyzaduje prihlaseni, jinak redirect na login */
    public static function requireLogin(): void
    {
        if (!self::check()) {
            $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? '/';
            redirect('/login');
        }
    }

    /**
     * Pokus o prihlaseni jmenem/e-mailem a heslem, s rate-limitem.
     * @return string|null null = uspech, jinak chybova hlaska
     */
    public static function attempt(string $login, string $password): ?string
    {
        $db = Db::instance();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        // rate-limit: max N neuspesnych pokusu z IP / na ucet za okno
        $since = date('Y-m-d H:i:s', time() - self::WINDOW_MINUTES * 60);
        $count = (int)$db->scalar(
            'SELECT COUNT(*) FROM login_attempts WHERE (ip = ? OR login = ?) AND attempted_at > ?',
            [$ip, $login, $since]
        );
        if ($count >= self::MAX_ATTEMPTS) {
            return 'Příliš mnoho neúspěšných pokusů. Zkuste to znovu za ' . self::WINDOW_MINUTES . ' minut.';
        }

        $user = $db->one(
            'SELECT * FROM users WHERE (email = ? OR name = ?) AND active = 1',
            [$login, $login]
        );

        if ($user === null || !password_verify($password, $user['password_hash'])) {
            $db->exec('INSERT INTO login_attempts (ip, login, attempted_at) VALUES (?, ?, NOW())', [$ip, $login]);
            return 'Nesprávné přihlašovací jméno nebo heslo.';
        }

        // uspech
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        self::$user = $user;
        self::$loaded = true;

        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $db->exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }
        $db->exec('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);
        $db->exec('DELETE FROM login_attempts WHERE ip = ? OR login = ?', [$ip, $login]);
        // uklid starych pokusu
        $db->exec('DELETE FROM login_attempts WHERE attempted_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);

        return null;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }
}
