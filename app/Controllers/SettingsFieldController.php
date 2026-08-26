<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\CustomFields;
use App\Core\Db;
use App\Core\Org;
use App\Core\View;

final class SettingsFieldController
{
    public function index(): void
    {
        Auth::requireLogin();
        $this->requireOrgContext();
        $db = Db::instance();
        $fields = $db->all(
            'SELECT cf.*, (SELECT COUNT(*) FROM asset_custom_values v WHERE v.custom_field_id = cf.id) AS usage_count
             FROM custom_fields cf WHERE cf.organization_id = ? ORDER BY cf.sort, cf.name',
            [Org::id()]
        );
        View::render('settings/custom_fields', [
            'title' => 'Vlastní pole',
            'fields' => $fields,
        ]);
    }

    /** POST /nastaveni/vlastni-pole/pridat */
    public function add(): void
    {
        Auth::requireLogin();
        $this->requireOrgContext();
        $this->save(null);
    }

    /** POST /nastaveni/vlastni-pole/{id}/upravit */
    public function edit(string $id): void
    {
        Auth::requireLogin();
        $this->requireOrgContext();
        $field = Db::instance()->one('SELECT * FROM custom_fields WHERE id = ? AND organization_id = ?', [(int)$id, Org::id()]);
        if ($field === null) {
            flash('error', 'Pole nenalezeno.');
            redirect('/nastaveni/vlastni-pole');
        }
        $this->save($field);
    }

    /** POST /nastaveni/vlastni-pole/{id}/smazat */
    public function delete(string $id): void
    {
        Auth::requireLogin();
        $this->requireOrgContext();
        $db = Db::instance();
        $field = $db->one('SELECT * FROM custom_fields WHERE id = ? AND organization_id = ?', [(int)$id, Org::id()]);
        if ($field !== null) {
            $used = (int)$db->scalar('SELECT COUNT(*) FROM asset_custom_values WHERE custom_field_id = ?', [$field['id']]);
            if ($used > 0) {
                flash('error', 'Pole „' . $field['name'] . '“ má vyplněné hodnoty u ' . $used . ' položek — místo smazání jej deaktivujte.');
            } else {
                $db->exec('DELETE FROM custom_fields WHERE id = ?', [$field['id']]);
                flash('success', 'Pole smazáno.');
            }
        }
        redirect('/nastaveni/vlastni-pole');
    }

    private function save(?array $field): void
    {
        $db = Db::instance();
        $name = trim((string)($_POST['name'] ?? ''));
        $type = (string)($_POST['type'] ?? 'text');
        $sort = (int)($_POST['sort'] ?? 0);
        $active = isset($_POST['active']) ? 1 : 0;
        $optionsRaw = trim((string)($_POST['options'] ?? ''));

        if ($name === '') {
            flash('error', 'Vyplňte název pole.');
            redirect('/nastaveni/vlastni-pole');
        }
        if (!isset(CustomFields::TYPES[$type])) {
            $type = 'text';
        }
        $options = null;
        if ($type === 'select') {
            $list = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $optionsRaw) ?: []), fn($v) => $v !== ''));
            if ($list === []) {
                flash('error', 'U pole typu „Výběr ze seznamu“ zadejte možnosti (každou na nový řádek).');
                redirect('/nastaveni/vlastni-pole');
            }
            $options = json_encode($list, JSON_UNESCAPED_UNICODE);
            if ($options === false) {
                flash('error', 'Možnosti obsahují neplatné znaky (kódování musí být UTF-8).');
                redirect('/nastaveni/vlastni-pole');
            }
        }

        $dupe = $db->one(
            'SELECT id FROM custom_fields WHERE organization_id = ? AND name = ? AND id <> ?',
            [Org::id(), $name, (int)($field['id'] ?? 0)]
        );
        if ($dupe !== null) {
            flash('error', 'Pole s tímto názvem už existuje.');
            redirect('/nastaveni/vlastni-pole');
        }

        if ($field === null) {
            $db->exec(
                'INSERT INTO custom_fields (organization_id, name, type, options, sort, active) VALUES (?, ?, ?, ?, ?, ?)',
                [Org::id(), $name, $type, $options, $sort, $active]
            );
            flash('success', 'Pole přidáno.');
        } else {
            // typ menit jen dokud pole nema hodnoty
            $used = (int)$db->scalar('SELECT COUNT(*) FROM asset_custom_values WHERE custom_field_id = ?', [$field['id']]);
            if ($used > 0 && $type !== $field['type']) {
                $type = (string)$field['type'];
                flash('info', 'Typ pole s vyplněnými hodnotami nelze měnit — ponechán původní.');
            }
            $db->exec(
                'UPDATE custom_fields SET name = ?, type = ?, options = ?, sort = ?, active = ? WHERE id = ?',
                [$name, $type, $options ?? $field['options'], $sort, $active, $field['id']]
            );
            flash('success', 'Pole uloženo.');
        }
        redirect('/nastaveni/vlastni-pole');
    }

    private function requireOrgContext(): void
    {
        if (Org::isAll()) {
            flash('error', 'Pro správu vlastních polí přepněte na konkrétní organizaci.');
            redirect('/');
        }
    }
}
