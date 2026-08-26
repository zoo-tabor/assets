<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Db;
use App\Core\Org;
use App\Core\View;

final class PersonController
{
    public function index(): void
    {
        Auth::requireLogin();
        $this->requireOrgContext();
        $db = Db::instance();
        $orgId = Org::id();

        $q = trim((string)($_GET['q'] ?? ''));
        $showInactive = ($_GET['neaktivni'] ?? '') === '1';

        $sql = 'SELECT p.*, l.name AS location_name, d.name AS department_name,
                       (SELECT COUNT(*) FROM assets a WHERE a.assigned_person_id = p.id) AS asset_count
                FROM persons p
                LEFT JOIN locations l ON l.id = p.location_id
                LEFT JOIN departments d ON d.id = p.department_id
                WHERE p.organization_id = ?';
        $params = [$orgId];
        if (!$showInactive) {
            $sql .= ' AND p.active = 1';
        }
        if ($q !== '') {
            $sql .= ' AND (p.name LIKE ? OR p.employee_id LIKE ? OR p.email LIKE ? OR p.title LIKE ?)';
            $like = '%' . $q . '%';
            array_push($params, $like, $like, $like, $like);
        }
        $sql .= ' ORDER BY p.name';

        View::render('persons/index', [
            'title' => 'Zaměstnanci',
            'persons' => $db->all($sql, $params),
            'q' => $q,
            'showInactive' => $showInactive,
        ]);
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
        $this->requireOrgContext();
        $person = Db::instance()->one(
            'SELECT * FROM persons WHERE id = ? AND organization_id = ?',
            [(int)$id, Org::id()]
        );
        if ($person === null) {
            http_response_code(404);
            View::render('errors/404', ['title' => 'Nenalezeno']);
            return;
        }
        $this->form($person);
    }

    private function form(?array $person): void
    {
        $db = Db::instance();
        $orgId = Org::id();
        $errors = [];
        $values = $person ?? [
            'name' => '', 'employee_id' => '', 'title' => '', 'email' => '', 'phone' => '',
            'location_id' => null, 'department_id' => null, 'notes' => '', 'active' => 1,
        ];

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            foreach (['name', 'employee_id', 'title', 'email', 'phone', 'notes'] as $f) {
                $values[$f] = trim((string)($_POST[$f] ?? ''));
            }
            $values['location_id'] = ($_POST['location_id'] ?? '') !== '' ? (int)$_POST['location_id'] : null;
            $values['department_id'] = ($_POST['department_id'] ?? '') !== '' ? (int)$_POST['department_id'] : null;
            $values['active'] = isset($_POST['active']) ? 1 : 0;

            if ($values['name'] === '') {
                $errors[] = 'Vyplňte jméno.';
            }
            if ($values['email'] !== '' && !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'E-mail není platný.';
            }
            // ciselniky musi patrit teto organizaci
            foreach ([['location_id', 'locations'], ['department_id', 'departments']] as [$field, $table]) {
                if ($values[$field] !== null
                    && $db->one("SELECT id FROM {$table} WHERE id = ? AND organization_id = ?", [$values[$field], $orgId]) === null) {
                    $errors[] = 'Neplatná hodnota číselníku.';
                }
            }

            if ($errors === []) {
                $params = [
                    $values['name'], $values['employee_id'] ?: null, $values['title'] ?: null,
                    $values['email'] ?: null, $values['phone'] ?: null,
                    $values['location_id'], $values['department_id'], $values['notes'] ?: null, $values['active'],
                ];
                if ($person === null) {
                    $db->exec(
                        'INSERT INTO persons (name, employee_id, title, email, phone, location_id, department_id, notes, active, organization_id)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                        [...$params, $orgId]
                    );
                    flash('success', 'Zaměstnanec založen.');
                } else {
                    $db->exec(
                        'UPDATE persons SET name = ?, employee_id = ?, title = ?, email = ?, phone = ?,
                         location_id = ?, department_id = ?, notes = ?, active = ? WHERE id = ?',
                        [...$params, $person['id']]
                    );
                    flash('success', 'Zaměstnanec uložen.');
                }
                redirect('/zamestnanci');
            }
        }

        View::render('persons/form', [
            'title' => $person === null ? 'Nový zaměstnanec' : 'Upravit zaměstnance',
            'person' => $person,
            'values' => $values,
            'errors' => $errors,
            'locations' => $db->all('SELECT * FROM locations WHERE organization_id = ? AND active = 1 ORDER BY name', [$orgId]),
            'departments' => $db->all('SELECT * FROM departments WHERE organization_id = ? AND active = 1 ORDER BY name', [$orgId]),
        ]);
    }

    /** GET/POST /zamestnanci/import - import z CSV */
    public function import(): void
    {
        Auth::requireLogin();
        $this->requireOrgContext();
        $db = Db::instance();
        $orgId = Org::id();
        $report = null;

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
            && !empty($_FILES['csv']['tmp_name']) && is_uploaded_file($_FILES['csv']['tmp_name'])) {

            $raw = (string)file_get_contents($_FILES['csv']['tmp_name']);
            // kodovani: UTF-8 (i s BOM), jinak zkusime Windows-1250
            if (str_starts_with($raw, "\xEF\xBB\xBF")) {
                $raw = substr($raw, 3);
            }
            if (!mb_check_encoding($raw, 'UTF-8')) {
                $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1250');
            }

            $rows = $this->parseCsv($raw);
            $report = ['added' => 0, 'skipped' => [], 'total' => max(0, count($rows) - 1)];

            if (count($rows) < 2) {
                $report['skipped'][] = 'Soubor neobsahuje žádná data (očekává se hlavička + řádky).';
            } else {
                $header = array_map(fn($h) => $this->normalizeHeader((string)$h), $rows[0]);
                $col = fn(array $names) => $this->findColumn($header, $names);
                $map = [
                    'name' => $col(['jmeno', 'name', 'zamestnanec', 'osoba', 'full name']),
                    'employee_id' => $col(['employee id', 'employeeid', 'osobni cislo', 'cislo']),
                    'title' => $col(['pozice', 'title', 'funkce']),
                    'email' => $col(['email', 'e-mail']),
                    'phone' => $col(['telefon', 'phone', 'mobil']),
                    'location' => $col(['lokace', 'location', 'site']),
                    'department' => $col(['oddeleni', 'department']),
                    'notes' => $col(['poznamka', 'notes', 'note', 'pozn']),
                ];

                if ($map['name'] === null) {
                    $report['skipped'][] = 'V hlavičce chybí sloupec „Jméno“ (name).';
                } else {
                    foreach (array_slice($rows, 1) as $i => $row) {
                        $line = $i + 2;
                        $get = fn(?int $idx) => $idx !== null ? trim((string)($row[$idx] ?? '')) : '';
                        $name = $get($map['name']);
                        if ($name === '') {
                            $report['skipped'][] = "Řádek {$line}: prázdné jméno.";
                            continue;
                        }
                        if ($db->one('SELECT id FROM persons WHERE organization_id = ? AND name = ?', [$orgId, $name]) !== null) {
                            $report['skipped'][] = "Řádek {$line}: „{$name}“ už existuje.";
                            continue;
                        }
                        $db->exec(
                            'INSERT INTO persons (organization_id, name, employee_id, title, email, phone, location_id, department_id, notes, active)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)',
                            [
                                $orgId, $name,
                                $get($map['employee_id']) ?: null,
                                $get($map['title']) ?: null,
                                $get($map['email']) ?: null,
                                $get($map['phone']) ?: null,
                                $this->dialId($db, 'locations', $orgId, $get($map['location'])),
                                $this->dialId($db, 'departments', $orgId, $get($map['department'])),
                                $get($map['notes']) ?: null,
                            ]
                        );
                        $report['added']++;
                    }
                }
            }
        }

        View::render('persons/import', [
            'title' => 'Import zaměstnanců z CSV',
            'report' => $report,
        ]);
    }

    /** @return array<int, array<int, string>> */
    private function parseCsv(string $raw): array
    {
        $firstLine = strtok($raw, "\n") ?: '';
        $delimiter = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';
        $rows = [];
        $fh = fopen('php://temp', 'r+');
        fwrite($fh, $raw);
        rewind($fh);
        while (($row = fgetcsv($fh, 0, $delimiter, '"', '\\')) !== false) {
            if ($row === [null]) {
                continue;
            }
            $rows[] = $row;
        }
        fclose($fh);
        return $rows;
    }

    private function normalizeHeader(string $h): string
    {
        $h = mb_strtolower(trim($h));
        $h = (string)iconv('UTF-8', 'ASCII//TRANSLIT', $h);
        return trim($h);
    }

    /** @param string[] $names */
    private function findColumn(array $header, array $names): ?int
    {
        foreach ($header as $i => $h) {
            if (in_array($h, $names, true)) {
                return $i;
            }
        }
        return null;
    }

    /** Najde/zalozi polozku ciselniku podle nazvu, vraci id nebo null */
    private function dialId(Db $db, string $table, int $orgId, string $name): ?int
    {
        if ($name === '') {
            return null;
        }
        $row = $db->one("SELECT id FROM {$table} WHERE organization_id = ? AND name = ?", [$orgId, $name]);
        if ($row !== null) {
            return (int)$row['id'];
        }
        $db->exec("INSERT INTO {$table} (organization_id, name, active) VALUES (?, ?, 1)", [$orgId, $name]);
        return $db->insertId();
    }

    private function requireOrgContext(): void
    {
        if (Org::isAll()) {
            flash('error', 'Pro správu zaměstnanců přepněte na konkrétní organizaci.');
            redirect('/');
        }
    }
}
