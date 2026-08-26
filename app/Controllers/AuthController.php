<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Db;
use App\Core\Migrator;
use App\Core\Org;
use App\Core\View;

final class AuthController
{
    public function login(): void
    {
        // Prazdna/nezmigrovana DB -> setup pres migrator
        if (!Db::instance()->tableExists('users') || Migrator::isSetupMode()) {
            redirect('/admin/migrate');
        }

        if (Auth::check()) {
            redirect('/');
        }

        $error = null;
        $loginValue = '';

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            $loginValue = trim((string)($_POST['login'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            $orgChoice = (string)($_POST['organization'] ?? '');

            if ($loginValue === '' || $password === '') {
                $error = 'Vyplňte přihlašovací jméno i heslo.';
            } else {
                $error = Auth::attempt($loginValue, $password);
            }

            if ($error === null) {
                if ($orgChoice !== '') {
                    Org::switch($orgChoice);
                }
                $after = $_SESSION['after_login'] ?? '/';
                unset($_SESSION['after_login']);
                // ochrana proti open redirectu - povolime jen lokalni cesty
                if (!is_string($after) || $after === '' || $after[0] !== '/' || str_starts_with($after, '//')) {
                    $after = '/';
                }
                redirect($after);
            }
        }

        View::render('login', [
            'title' => 'Přihlášení',
            'layout' => false,
            'error' => $error,
            'loginValue' => $loginValue,
            'organizations' => Org::allActive(),
        ]);
    }

    public function logout(): void
    {
        Auth::logout();
        redirect('/login');
    }

    /** Ulozeni volby tematu (light/dark/auto) - cookie + preference uzivatele */
    public function theme(): void
    {
        $theme = (string)($_POST['theme'] ?? 'auto');
        if (!in_array($theme, ['light', 'dark', 'auto'], true)) {
            $theme = 'auto';
        }
        setcookie('theme', $theme, [
            'expires' => time() + 86400 * 365,
            'path' => '/',
            'secure' => (($_SERVER['HTTPS'] ?? '') === 'on'),
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
        if (Auth::check()) {
            Db::instance()->exec('UPDATE users SET theme_pref = ? WHERE id = ?', [$theme, Auth::id()]);
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true]);
    }
}
