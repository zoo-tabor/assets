<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Db;
use App\Core\Org;
use App\Core\View;

/**
 * Ciselniky: lokace, kategorie, oddeleni (per organizace).
 * Vsechny tri tabulky maji shodnou strukturu -> jeden controller s whitelistem.
 */
final class DialController
{
    /** @var array<string, array{table:string,label:string,label_one:string}> */
    private const TYPES = [
        'lokace' => ['table' => 'locations', 'label' => 'Lokace', 'label_one' => 'lokaci'],
        'kategorie' => ['table' => 'categories', 'label' => 'Kategorie', 'label_one' => 'kategorii'],
        'oddeleni' => ['table' => 'departments', 'label' => 'Oddělení', 'label_one' => 'oddělení'],
    ];

    public function index(): void
    {
        Auth::requireLogin();
        $this->requireOrgContext();
        $db = Db::instance();
        $orgId = Org::id();

        $lists = [];
        foreach (self::TYPES as $key => $meta) {
            $lists[$key] = [
                'meta' => $meta,
                'items' => $db->all(
                    "SELECT d.*, (
                        SELECT COUNT(*) FROM assets a WHERE a." . $this->fkColumn($meta['table']) . " = d.id
                     ) AS usage_assets
                     FROM {$meta['table']} d WHERE d.organization_id = ? ORDER BY d.name",
                    [$orgId]
                ),
            ];
        }

        View::render('settings/dials', [
            'title' => 'Číselníky',
            'lists' => $lists,
        ]);
    }

    /** POST /nastaveni/ciselniky/{typ}/pridat */
    public function add(string $type): void
    {
        Auth::requireLogin();
        $this->requireOrgContext();
        $meta = $this->meta($type);
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') {
            flash('error', 'Vyplňte název.');
            redirect('/nastaveni/ciselniky');
        }
        $db = Db::instance();
        $dupe = $db->one("SELECT id FROM {$meta['table']} WHERE organization_id = ? AND name = ?", [Org::id(), $name]);
        if ($dupe !== null) {
            flash('error', ucfirst($meta['label']) . ' „' . $name . '“ už existuje.');
        } else {
            $db->exec("INSERT INTO {$meta['table']} (organization_id, name, active) VALUES (?, ?, 1)", [Org::id(), $name]);
            flash('success', 'Položka přidána.');
        }
        redirect('/nastaveni/ciselniky');
    }

    /** POST /nastaveni/ciselniky/{typ}/{id}/upravit  (prejmenovani / aktivace) */
    public function edit(string $type, string $id): void
    {
        Auth::requireLogin();
        $this->requireOrgContext();
        $meta = $this->meta($type);
        $db = Db::instance();
        $item = $db->one("SELECT * FROM {$meta['table']} WHERE id = ? AND organization_id = ?", [(int)$id, Org::id()]);
        if ($item === null) {
            flash('error', 'Položka nenalezena.');
            redirect('/nastaveni/ciselniky');
        }
        $name = trim((string)($_POST['name'] ?? ''));
        $active = isset($_POST['active']) ? 1 : 0;
        if ($name === '') {
            flash('error', 'Vyplňte název.');
            redirect('/nastaveni/ciselniky');
        }
        $dupe = $db->one(
            "SELECT id FROM {$meta['table']} WHERE organization_id = ? AND name = ? AND id <> ?",
            [Org::id(), $name, (int)$id]
        );
        if ($dupe !== null) {
            flash('error', 'Položka s tímto názvem už existuje.');
        } else {
            $db->exec("UPDATE {$meta['table']} SET name = ?, active = ? WHERE id = ?", [$name, $active, (int)$id]);
            flash('success', 'Uloženo.');
        }
        redirect('/nastaveni/ciselniky');
    }

    /** POST /nastaveni/ciselniky/{typ}/{id}/smazat */
    public function delete(string $type, string $id): void
    {
        Auth::requireLogin();
        $this->requireOrgContext();
        $meta = $this->meta($type);
        $db = Db::instance();
        $item = $db->one("SELECT * FROM {$meta['table']} WHERE id = ? AND organization_id = ?", [(int)$id, Org::id()]);
        if ($item === null) {
            flash('error', 'Položka nenalezena.');
            redirect('/nastaveni/ciselniky');
        }
        try {
            $db->exec("DELETE FROM {$meta['table']} WHERE id = ?", [(int)$id]);
            flash('success', 'Položka smazána.');
        } catch (\Throwable) {
            // FK RESTRICT - polozka je pouzita
            flash('error', 'Nelze smazat ' . $meta['label_one'] . ' „' . $item['name'] . '“ — je používaná. Místo smazání ji deaktivujte.');
        }
        redirect('/nastaveni/ciselniky');
    }

    private function meta(string $type): array
    {
        $meta = self::TYPES[$type] ?? null;
        if ($meta === null) {
            http_response_code(404);
            View::render('errors/404', ['title' => 'Nenalezeno']);
            exit;
        }
        return $meta;
    }

    private function fkColumn(string $table): string
    {
        return match ($table) {
            'locations' => 'location_id',
            'categories' => 'category_id',
            'departments' => 'department_id',
        };
    }

    private function requireOrgContext(): void
    {
        if (Org::isAll()) {
            flash('error', 'Pro správu číselníků přepněte na konkrétní organizaci.');
            redirect('/');
        }
    }
}
