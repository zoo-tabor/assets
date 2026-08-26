<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Db;
use App\Core\View;

final class SettingsUserController
{
    public function index(): void
    {
        Auth::requireLogin();
        $users = Db::instance()->all('SELECT id, name, email, active, last_login_at FROM users ORDER BY name');
        View::render('settings/users', [
            'title' => 'Uživatelé',
            'users' => $users,
        ]);
    }

    public function create(): void
    {
        Auth::requireLogin();
        $this->form(null);
    }

    public function edit(string $id): void
    {
        Auth::requireLogin();
        $user = Db::instance()->one('SELECT * FROM users WHERE id = ?', [(int)$id]);
        if ($user === null) {
            http_response_code(404);
            View::render('errors/404', ['title' => 'Nenalezeno']);
            return;
        }
        $this->form($user);
    }

    private function form(?array $user): void
    {
        $db = Db::instance();
        $errors = [];
        $values = $user ?? ['name' => '', 'email' => '', 'active' => 1];

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            $values['name'] = trim((string)($_POST['name'] ?? ''));
            $values['email'] = trim((string)($_POST['email'] ?? ''));
            $values['active'] = isset($_POST['active']) ? 1 : 0;
            $password = (string)($_POST['password'] ?? '');
            $password2 = (string)($_POST['password2'] ?? '');

            if ($values['name'] === '') {
                $errors[] = 'Vyplňte jméno.';
            }
            if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Zadejte platný e-mail.';
            }
            $dupe = $db->one(
                'SELECT id FROM users WHERE (email = ? OR name = ?) AND id <> ?',
                [$values['email'], $values['name'], (int)($user['id'] ?? 0)]
            );
            if ($dupe !== null) {
                $errors[] = 'Uživatel s tímto jménem nebo e-mailem už existuje.';
            }
            if ($user === null && $password === '') {
                $errors[] = 'Zadejte heslo nového uživatele.';
            }
            if ($password !== '') {
                if (mb_strlen($password) < 8) {
                    $errors[] = 'Heslo musí mít alespoň 8 znaků.';
                }
                if ($password !== $password2) {
                    $errors[] = 'Hesla se neshodují.';
                }
            }
            if ($user !== null && (int)$user['id'] === Auth::id() && $values['active'] === 0) {
                $errors[] = 'Nemůžete deaktivovat sami sebe.';
            }

            if ($errors === []) {
                if ($user === null) {
                    $db->exec(
                        'INSERT INTO users (name, email, password_hash, role, active, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
                        [$values['name'], $values['email'], password_hash($password, PASSWORD_DEFAULT), 'admin', $values['active']]
                    );
                    flash('success', 'Uživatel založen.');
                } else {
                    $db->exec(
                        'UPDATE users SET name = ?, email = ?, active = ? WHERE id = ?',
                        [$values['name'], $values['email'], $values['active'], $user['id']]
                    );
                    if ($password !== '') {
                        $db->exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
                    }
                    flash('success', 'Uživatel uložen.');
                }
                redirect('/nastaveni/uzivatele');
            }
        }

        View::render('settings/user_form', [
            'title' => $user === null ? 'Nový uživatel' : 'Upravit uživatele',
            'user' => $user,
            'values' => $values,
            'errors' => $errors,
        ]);
    }

    /** Zmena vlastniho hesla */
    public function password(): void
    {
        Auth::requireLogin();
        $errors = [];

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            $current = (string)($_POST['current'] ?? '');
            $password = (string)($_POST['password'] ?? '');
            $password2 = (string)($_POST['password2'] ?? '');
            $me = Auth::user();

            if (!password_verify($current, (string)$me['password_hash'])) {
                $errors[] = 'Současné heslo není správné.';
            }
            if (mb_strlen($password) < 8) {
                $errors[] = 'Nové heslo musí mít alespoň 8 znaků.';
            }
            if ($password !== $password2) {
                $errors[] = 'Nová hesla se neshodují.';
            }

            if ($errors === []) {
                Db::instance()->exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), Auth::id()]);
                flash('success', 'Heslo změněno.');
                redirect('/');
            }
        }

        View::render('settings/password', [
            'title' => 'Změna hesla',
            'errors' => $errors,
        ]);
    }
}
