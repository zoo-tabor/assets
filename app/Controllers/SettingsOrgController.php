<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Db;
use App\Core\View;

final class SettingsOrgController
{
    public function index(): void
    {
        Auth::requireLogin();
        $organizations = Db::instance()->all('SELECT * FROM organizations ORDER BY name');
        View::render('settings/organizations', [
            'title' => 'Organizace',
            'organizations' => $organizations,
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
        $org = Db::instance()->one('SELECT * FROM organizations WHERE id = ?', [(int)$id]);
        if ($org === null) {
            http_response_code(404);
            View::render('errors/404', ['title' => 'Nenalezeno']);
            return;
        }
        $this->form($org);
    }

    private function form(?array $org): void
    {
        $db = Db::instance();
        $errors = [];
        $values = $org ?? [
            'name' => '', 'accent_color' => '#1e7e34', 'tag_prefix' => '',
            'tag_next_number' => 1, 'active' => 1, 'logo_file' => null,
        ];

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            $values['name'] = trim((string)($_POST['name'] ?? ''));
            $values['accent_color'] = trim((string)($_POST['accent_color'] ?? ''));
            $values['tag_prefix'] = strtoupper(trim((string)($_POST['tag_prefix'] ?? '')));
            $values['tag_next_number'] = max(1, (int)($_POST['tag_next_number'] ?? 1));
            $values['active'] = isset($_POST['active']) ? 1 : 0;

            if ($values['name'] === '') {
                $errors[] = 'Vyplňte název organizace.';
            }
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $values['accent_color'])) {
                $errors[] = 'Barva musí být ve formátu #RRGGBB.';
            }
            if (!preg_match('/^[A-Z0-9]{2,20}$/', $values['tag_prefix'])) {
                $errors[] = 'Prefix Tag ID: 2–20 velkých písmen/číslic bez mezer.';
            }
            $dupe = $db->one(
                'SELECT id FROM organizations WHERE name = ? AND id <> ?',
                [$values['name'], (int)($org['id'] ?? 0)]
            );
            if ($dupe !== null) {
                $errors[] = 'Organizace s tímto názvem už existuje.';
            }

            // upload loga (volitelny)
            $logoFile = $org['logo_file'] ?? null;
            if (!empty($_FILES['logo']['tmp_name']) && is_uploaded_file($_FILES['logo']['tmp_name'])) {
                $newLogo = $this->storeLogo($_FILES['logo'], $errors);
                if ($newLogo !== null) {
                    $logoFile = $newLogo;
                }
            }

            if ($errors === []) {
                if ($org === null) {
                    $db->exec(
                        'INSERT INTO organizations (name, accent_color, tag_prefix, tag_next_number, active, logo_file) VALUES (?, ?, ?, ?, ?, ?)',
                        [$values['name'], $values['accent_color'], $values['tag_prefix'], $values['tag_next_number'], $values['active'], $logoFile]
                    );
                    flash('success', 'Organizace založena.');
                } else {
                    $db->exec(
                        'UPDATE organizations SET name = ?, accent_color = ?, tag_prefix = ?, tag_next_number = ?, active = ?, logo_file = ? WHERE id = ?',
                        [$values['name'], $values['accent_color'], $values['tag_prefix'], $values['tag_next_number'], $values['active'], $logoFile, $org['id']]
                    );
                    flash('success', 'Organizace uložena.');
                }
                redirect('/nastaveni/organizace');
            }
        }

        View::render('settings/organization_form', [
            'title' => $org === null ? 'Nová organizace' : 'Upravit organizaci',
            'org' => $org,
            'values' => $values,
            'errors' => $errors,
        ]);
    }

    /** Ulozi logo do /data/logos, vraci nazev souboru nebo null */
    private function storeLogo(array $file, array &$errors): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $errors[] = 'Nahrání loga selhalo (chyba ' . (int)$file['error'] . ').';
            return null;
        }
        if ($file['size'] > 2 * 1024 * 1024) {
            $errors[] = 'Logo je příliš velké (max 2 MB).';
            return null;
        }
        $mime = mime_content_type($file['tmp_name']) ?: '';
        $ext = match ($mime) {
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/svg+xml' => 'svg',
            'image/webp' => 'webp',
            default => null,
        };
        if ($ext === null) {
            $errors[] = 'Logo musí být PNG, JPG, SVG nebo WebP.';
            return null;
        }
        $dir = DATA_PATH . '/logos';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            $errors[] = 'Nelze vytvořit adresář pro loga.';
            return null;
        }
        $name = 'logo-' . bin2hex(random_bytes(8)) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
            $errors[] = 'Uložení loga selhalo.';
            return null;
        }
        return $name;
    }
}
