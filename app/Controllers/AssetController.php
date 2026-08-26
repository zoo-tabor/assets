<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Db;
use App\Core\Org;
use App\Core\View;

final class AssetController
{
    public const STATUSES = [
        'available' => 'K dispozici',
        'assigned' => 'Přiděleno',
        'reserved' => 'Rezervováno',
        'disposed' => 'Vyřazeno',
    ];

    public const OS_TYPES = ['Linux', 'Win 10', 'Win 11 Home', 'Win 11 Pro'];
    public const OFFICE_TYPES = ['Office 2021', 'Office 2024'];

    private const PER_PAGE = 25;

    public function index(): void
    {
        Auth::requireLogin();
        $db = Db::instance();
        $isAll = Org::isAll();
        $customFields = $isAll ? [] : \App\Core\CustomFields::forOrg(Org::id());

        [$whereSql, $params, $filters, $q] = $this->listWhere($isAll, $customFields);

        $sorts = [
            'tag' => 'a.tag_id', 'popis' => 'a.description', 'cena' => 'a.cost',
            'nakup' => 'a.purchase_date', 'stav' => 'a.status', 'kategorie' => 'c.name',
        ];
        $sortKey = (string)($_GET['razeni'] ?? 'tag');
        $sortCol = $sorts[$sortKey] ?? 'a.tag_id';
        $desc = ($_GET['smer'] ?? '') === 'desc';
        $orderSql = $sortCol . ($desc ? ' DESC' : ' ASC');

        $total = (int)$db->scalar(
            "SELECT COUNT(*) FROM assets a JOIN organizations o ON o.id = a.organization_id WHERE {$whereSql}",
            $params
        );
        $pages = max(1, (int)ceil($total / self::PER_PAGE));
        $page = min($pages, max(1, (int)($_GET['strana'] ?? 1)));
        $offset = ($page - 1) * self::PER_PAGE;

        $assets = $db->all(
            "SELECT a.*, o.name AS org_name, o.accent_color, c.name AS category_name,
                    l.name AS location_name, d.name AS department_name, p.name AS person_name
             FROM assets a
             JOIN organizations o ON o.id = a.organization_id
             LEFT JOIN categories c ON c.id = a.category_id
             LEFT JOIN locations l ON l.id = a.location_id
             LEFT JOIN departments d ON d.id = a.department_id
             LEFT JOIN persons p ON p.id = a.assigned_person_id
             WHERE {$whereSql}
             ORDER BY {$orderSql}
             LIMIT " . self::PER_PAGE . " OFFSET {$offset}",
            $params
        );

        View::render('assets/index', [
            'title' => 'Majetek',
            'assets' => $assets,
            'q' => $q,
            'filters' => $filters,
            'sortKey' => $sortKey,
            'desc' => $desc,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'isAll' => $isAll,
            'dials' => $isAll ? null : $this->dials(),
            'organizations' => $isAll ? Org::allActive() : [],
            'customFields' => $customFields,
        ]);
    }

    /** Sestavi WHERE + parametry seznamu z GET filtru (sdileno s exportem) */
    private function listWhere(bool $isAll, array $customFields): array
    {
        $where = [];
        $params = [];
        if ($isAll) {
            $where[] = 'o.active = 1';
        } else {
            $where[] = 'a.organization_id = ?';
            $params[] = Org::id();
        }

        $q = trim((string)($_GET['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(a.tag_id LIKE ? OR a.description LIKE ? OR a.brand LIKE ? OR a.model LIKE ? OR a.serial_no LIKE ?)';
            $like = '%' . $q . '%';
            array_push($params, $like, $like, $like, $like, $like);
        }
        $filters = [];
        foreach ([['kategorie', 'a.category_id'], ['lokace', 'a.location_id'], ['oddeleni', 'a.department_id'], ['osoba', 'a.assigned_person_id']] as [$key, $col]) {
            $val = (string)($_GET[$key] ?? '');
            $filters[$key] = $val;
            if ($val !== '') {
                $where[] = "{$col} = ?";
                $params[] = (int)$val;
            }
        }
        $filters['stav'] = (string)($_GET['stav'] ?? '');
        if ($filters['stav'] !== '' && isset(self::STATUSES[$filters['stav']])) {
            $where[] = 'a.status = ?';
            $params[] = $filters['stav'];
        }
        $filters['organizace'] = (string)($_GET['organizace'] ?? '');
        if ($filters['organizace'] !== '' && $isAll) {
            $where[] = 'a.organization_id = ?';
            $params[] = (int)$filters['organizace'];
        }
        // filtry vlastnich poli (select / ano-ne)
        foreach ($customFields as $cf) {
            if (!in_array($cf['type'], ['select', 'bool'], true)) {
                continue;
            }
            $key = 'cf_' . $cf['id'];
            $val = (string)($_GET[$key] ?? '');
            $filters[$key] = $val;
            if ($val !== '') {
                $where[] = 'EXISTS (SELECT 1 FROM asset_custom_values v WHERE v.asset_id = a.id AND v.custom_field_id = ? AND v.value = ?)';
                array_push($params, (int)$cf['id'], $val);
            }
        }

        return [implode(' AND ', $where), $params, $filters, $q];
    }

    /** GET /majetek/export.csv | /majetek/export.xlsx (stejne filtry jako seznam) */
    public function export(string $format): void
    {
        Auth::requireLogin();
        $db = Db::instance();
        $isAll = Org::isAll();
        $customFields = $isAll ? [] : \App\Core\CustomFields::forOrg(Org::id());
        [$whereSql, $params] = $this->listWhere($isAll, $customFields);

        $assets = $db->all(
            "SELECT a.*, o.name AS org_name, c.name AS category_name, l.name AS location_name,
                    d.name AS department_name, p.name AS person_name
             FROM assets a
             JOIN organizations o ON o.id = a.organization_id
             LEFT JOIN categories c ON c.id = a.category_id
             LEFT JOIN locations l ON l.id = a.location_id
             LEFT JOIN departments d ON d.id = a.department_id
             LEFT JOIN persons p ON p.id = a.assigned_person_id
             WHERE {$whereSql}
             ORDER BY a.tag_id",
            $params
        );

        // hodnoty vlastnich poli jednim dotazem
        $customValues = [];
        if ($customFields !== [] && $assets !== []) {
            $ids = array_column($assets, 'id');
            $in = implode(',', array_fill(0, count($ids), '?'));
            foreach ($db->all("SELECT asset_id, custom_field_id, value FROM asset_custom_values WHERE asset_id IN ({$in})", $ids) as $r) {
                $customValues[(int)$r['asset_id']][(int)$r['custom_field_id']] = (string)($r['value'] ?? '');
            }
        }

        $header = ['Tag ID'];
        if ($isAll) {
            $header[] = 'Organizace';
        }
        array_push($header, 'Popis', 'Značka', 'Model', 'Sériové číslo', 'Kategorie', 'Lokace', 'Oddělení',
            'Stav', 'Přiděleno', 'Cena', 'Datum nákupu', 'Dodavatel', 'OS', 'OS SN', 'Office', 'Office SN', 'Poznámka');
        foreach ($customFields as $cf) {
            $header[] = $cf['name'];
        }

        $rows = [];
        foreach ($assets as $a) {
            $row = [$a['tag_id']];
            if ($isAll) {
                $row[] = $a['org_name'];
            }
            array_push($row,
                $a['description'], $a['brand'], $a['model'], $a['serial_no'],
                $a['category_name'], $a['location_name'], $a['department_name'],
                self::STATUSES[$a['status']] ?? $a['status'], $a['person_name'],
                $a['cost'] !== null ? (float)$a['cost'] : '', $a['purchase_date'], $a['purchased_from'],
                $a['os_type'], $a['os_sn'], $a['office'], $a['office_sn'], $a['note']);
            foreach ($customFields as $cf) {
                $row[] = \App\Core\CustomFields::display($cf, $customValues[(int)$a['id']][(int)$cf['id']] ?? null);
            }
            $rows[] = $row;
        }

        $name = 'majetek-' . date('Y-m-d');
        if ($format === 'xlsx') {
            \App\Core\Xlsx::download($name . '.xlsx', 'Majetek', $header, $rows);
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $name . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM pro Excel
        fputcsv($out, $header, ';', '"', '\\');
        foreach ($rows as $row) {
            fputcsv($out, array_map(fn($v) => is_float($v) ? str_replace('.', ',', (string)$v) : (string)($v ?? ''), $row), ';', '"', '\\');
        }
        fclose($out);
        exit;
    }

    public function create(): void
    {
        Auth::requireLogin();
        $this->requireOrgContext();
        $this->form(null);
    }

    public function edit(string $id): void
    {
        Auth::requireLogin();
        $asset = $this->find((int)$id);
        $this->form($asset);
    }

    public function show(string $id): void
    {
        Auth::requireLogin();
        $asset = $this->find((int)$id);
        $db = Db::instance();

        $events = $db->all(
            'SELECT e.*, p.name AS person_name, u.name AS user_name
             FROM asset_events e
             LEFT JOIN persons p ON p.id = e.person_id
             LEFT JOIN users u ON u.id = e.user_id
             WHERE e.asset_id = ?
             ORDER BY e.event_date DESC, e.id DESC',
            [$asset['id']]
        );

        $customFields = \App\Core\CustomFields::forOrg((int)$asset['organization_id']);
        $customValues = \App\Core\CustomFields::valuesFor((int)$asset['id']);
        $photos = $db->all('SELECT * FROM asset_photos WHERE asset_id = ? ORDER BY is_primary DESC, id', [$asset['id']]);
        $documents = $db->all(
            'SELECT d.*, u.name AS user_name FROM asset_documents d LEFT JOIN users u ON u.id = d.uploaded_by
             WHERE d.asset_id = ? ORDER BY d.uploaded_at DESC',
            [$asset['id']]
        );

        View::render('assets/show', [
            'title' => $asset['tag_id'],
            'asset' => $asset,
            'events' => $events,
            'customFields' => $customFields,
            'customValues' => $customValues,
            'photos' => $photos,
            'documents' => $documents,
        ]);
    }

    private function form(?array $asset): void
    {
        $db = Db::instance();
        $orgId = Org::id();
        $errors = [];

        $org = $db->one('SELECT * FROM organizations WHERE id = ?', [$orgId]);
        $suggestedTag = $asset['tag_id'] ?? $this->formatTag((string)$org['tag_prefix'], (int)$org['tag_next_number']);

        $fields = ['tag_id', 'description', 'brand', 'model', 'serial_no', 'purchase_date', 'cost',
                   'purchased_from', 'os_type', 'os_sn', 'office', 'office_sn', 'note'];
        $values = $asset ?? array_fill_keys($fields, '') + [
            'location_id' => null, 'category_id' => null, 'department_id' => null,
        ];
        if ($asset === null) {
            $values['tag_id'] = $suggestedTag;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            foreach ($fields as $f) {
                $values[$f] = trim((string)($_POST[$f] ?? ''));
            }
            foreach (['location_id', 'category_id', 'department_id'] as $f) {
                $values[$f] = ($_POST[$f] ?? '') !== '' ? (int)$_POST[$f] : null;
            }

            if ($values['description'] === '') {
                $errors[] = 'Vyplňte popis.';
            }
            if ($values['cost'] !== '' && !is_numeric(str_replace(',', '.', $values['cost']))) {
                $errors[] = 'Cena musí být číslo.';
            }
            if ($values['purchase_date'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $values['purchase_date'])) {
                $errors[] = 'Datum nákupu musí být ve formátu RRRR-MM-DD.';
            }
            if ($values['os_type'] !== '' && !in_array($values['os_type'], self::OS_TYPES, true)) {
                $errors[] = 'Neplatný typ OS.';
            }
            if ($values['office'] !== '' && !in_array($values['office'], self::OFFICE_TYPES, true)) {
                $errors[] = 'Neplatná verze Office.';
            }
            foreach ([['location_id', 'locations'], ['category_id', 'categories'], ['department_id', 'departments']] as [$field, $table]) {
                if ($values[$field] !== null
                    && $db->one("SELECT id FROM {$table} WHERE id = ? AND organization_id = ?", [$values[$field], $orgId]) === null) {
                    $errors[] = 'Neplatná hodnota číselníku.';
                }
            }

            // Tag ID: prazdne -> automaticka rada; vyplnene -> unikatnost
            $autoTag = ($values['tag_id'] === '');
            if ($autoTag) {
                $next = (int)$org['tag_next_number'];
                do {
                    $values['tag_id'] = $this->formatTag((string)$org['tag_prefix'], $next);
                    $next++;
                } while ($db->one('SELECT id FROM assets WHERE tag_id = ?', [$values['tag_id']]) !== null);
            } else {
                $dupe = $db->one('SELECT id FROM assets WHERE tag_id = ? AND id <> ?', [$values['tag_id'], (int)($asset['id'] ?? 0)]);
                if ($dupe !== null) {
                    $errors[] = 'Tag ID „' . $values['tag_id'] . '“ už existuje.';
                }
            }

            if ($errors === []) {
                $cost = $values['cost'] !== '' ? (float)str_replace(',', '.', $values['cost']) : null;
                $params = [
                    $values['tag_id'], $values['description'], $values['brand'] ?: null, $values['model'] ?: null,
                    $values['serial_no'] ?: null, $values['purchase_date'] ?: null, $cost,
                    $values['purchased_from'] ?: null, $values['location_id'], $values['category_id'], $values['department_id'],
                    $values['os_type'] ?: null, $values['os_sn'] ?: null, $values['office'] ?: null, $values['office_sn'] ?: null,
                    $values['note'] ?: null,
                ];

                $customFields = \App\Core\CustomFields::forOrg($orgId);
                if ($asset === null) {
                    $db->exec(
                        'INSERT INTO assets (tag_id, description, brand, model, serial_no, purchase_date, cost,
                            purchased_from, location_id, category_id, department_id, os_type, os_sn, office, office_sn,
                            note, organization_id, status, created_by, created_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                        [...$params, $orgId, 'available', Auth::id()]
                    );
                    $assetId = $db->insertId();
                    \App\Core\CustomFields::saveFromPost($assetId, $customFields, $_POST);
                    $this->logEvent($assetId, 'create', 'Majetek založen');
                    // posun automaticke rady (jen pokud jsme tag generovali, nebo rucni tag odpovida rade)
                    $usedNumber = $this->tagNumber((string)$org['tag_prefix'], $values['tag_id']);
                    if ($usedNumber !== null && $usedNumber >= (int)$org['tag_next_number']) {
                        $db->exec('UPDATE organizations SET tag_next_number = ? WHERE id = ?', [$usedNumber + 1, $orgId]);
                    }
                    flash('success', 'Majetek ' . $values['tag_id'] . ' založen.');
                    redirect('/majetek/' . $assetId);
                } else {
                    $db->exec(
                        'UPDATE assets SET tag_id = ?, description = ?, brand = ?, model = ?, serial_no = ?,
                            purchase_date = ?, cost = ?, purchased_from = ?, location_id = ?, category_id = ?,
                            department_id = ?, os_type = ?, os_sn = ?, office = ?, office_sn = ?, note = ?,
                            updated_at = NOW()
                         WHERE id = ?',
                        [...$params, $asset['id']]
                    );
                    \App\Core\CustomFields::saveFromPost((int)$asset['id'], $customFields, $_POST);
                    $this->logEvent((int)$asset['id'], 'edit', 'Údaje upraveny');
                    flash('success', 'Majetek uložen.');
                    redirect('/majetek/' . $asset['id']);
                }
            }
        }

        $formCustomFields = \App\Core\CustomFields::forOrg($orgId);
        $customValues = $asset !== null ? \App\Core\CustomFields::valuesFor((int)$asset['id']) : [];
        // po neuspesnem POSTu zachovat vyplnene hodnoty
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            foreach ($formCustomFields as $cf) {
                $key = 'cf_' . $cf['id'];
                $customValues[(int)$cf['id']] = $cf['type'] === 'bool'
                    ? (isset($_POST[$key]) ? '1' : '0')
                    : trim((string)($_POST[$key] ?? ''));
            }
        }

        View::render('assets/form', [
            'title' => $asset === null ? 'Nový majetek' : 'Upravit ' . $asset['tag_id'],
            'asset' => $asset,
            'values' => $values,
            'errors' => $errors,
            'dials' => $this->dials(),
            'suggestedTag' => $suggestedTag,
            'customFields' => $formCustomFields,
            'customValues' => $customValues,
        ]);
    }

    /** Nacte majetek; v rezimu "vse" prepne kontext na organizaci majetku */
    private function find(int $id): array
    {
        $db = Db::instance();
        $asset = $db->one('SELECT * FROM assets WHERE id = ?', [$id]);
        if ($asset === null) {
            http_response_code(404);
            View::render('errors/404', ['title' => 'Nenalezeno']);
            exit;
        }
        if (Org::isAll()) {
            Org::switch((string)$asset['organization_id']);
            $org = Org::current();
            flash('info', 'Přepnuto do organizace ' . ($org['name'] ?? ''));
        } elseif ((int)$asset['organization_id'] !== Org::id()) {
            Org::switch((string)$asset['organization_id']);
            $org = Org::current();
            flash('info', 'Majetek patří organizaci ' . ($org['name'] ?? '') . ' — kontext přepnut.');
        }
        return $asset;
    }

    private function dials(): array
    {
        $db = Db::instance();
        $orgId = Org::id();
        return [
            'locations' => $db->all('SELECT * FROM locations WHERE organization_id = ? AND active = 1 ORDER BY name', [$orgId]),
            'categories' => $db->all('SELECT * FROM categories WHERE organization_id = ? AND active = 1 ORDER BY name', [$orgId]),
            'departments' => $db->all('SELECT * FROM departments WHERE organization_id = ? AND active = 1 ORDER BY name', [$orgId]),
            'persons' => $db->all('SELECT id, name FROM persons WHERE organization_id = ? AND active = 1 ORDER BY name', [$orgId]),
        ];
    }

    private function formatTag(string $prefix, int $number): string
    {
        return sprintf('%s-%07d', $prefix, $number);
    }

    /** Vrati cislo z tag ID, pokud odpovida rade organizace (PREFIX-1234567) */
    private function tagNumber(string $prefix, string $tag): ?int
    {
        if (preg_match('/^' . preg_quote($prefix, '/') . '-(\d{1,9})$/', $tag, $m)) {
            return (int)$m[1];
        }
        return null;
    }

    private function logEvent(int $assetId, string $type, string $note): void
    {
        Db::instance()->exec(
            'INSERT INTO asset_events (asset_id, type, user_id, event_date, note) VALUES (?, ?, ?, NOW(), ?)',
            [$assetId, $type, Auth::id(), $note]
        );
    }

    private function requireOrgContext(): void
    {
        if (Org::isAll()) {
            flash('error', 'Pro založení majetku přepněte na konkrétní organizaci.');
            redirect('/majetek');
        }
    }
}
