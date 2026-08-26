<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Db;
use App\Core\Org;
use App\Core\View;

/**
 * Pohyby majetku: vydej, vraceni, presun, vyrazeni, rezervace.
 * Jeden controller rizeny konfiguraci akci; kazdy pohyb meni stav
 * majetku a zapisuje udalost do asset_events.
 */
final class MovementController
{
    /** @var array<string, array<string, mixed>> */
    public const ACTIONS = [
        'vydej' => [
            'title' => 'Výdej majetku',
            'event' => 'checkout',
            'from' => ['available', 'reserved'],
            'to' => 'assigned',
            'person' => 'required',
            'due' => true,
            'button' => 'Vydat',
        ],
        'vraceni' => [
            'title' => 'Vrácení majetku',
            'event' => 'checkin',
            'from' => ['assigned', 'reserved'],
            'to' => 'available',
            'person' => 'none',
            'due' => false,
            'button' => 'Vrátit',
        ],
        'presun' => [
            'title' => 'Přesun majetku',
            'event' => 'move',
            'from' => ['available', 'assigned', 'reserved'],
            'to' => null,
            'person' => 'none',
            'due' => false,
            'button' => 'Přesunout',
        ],
        'vyrazeni' => [
            'title' => 'Vyřazení majetku',
            'event' => 'dispose',
            'from' => ['available', 'assigned', 'reserved'],
            'to' => 'disposed',
            'person' => 'none',
            'due' => false,
            'button' => 'Vyřadit',
        ],
        'rezervace' => [
            'title' => 'Rezervace majetku',
            'event' => 'reserve',
            'from' => ['available'],
            'to' => 'reserved',
            'person' => 'optional',
            'due' => true,
            'button' => 'Rezervovat',
        ],
    ];

    public function vydej(): void { $this->form('vydej'); }
    public function vraceni(): void { $this->form('vraceni'); }
    public function presun(): void { $this->form('presun'); }
    public function vyrazeni(): void { $this->form('vyrazeni'); }
    public function rezervace(): void { $this->form('rezervace'); }

    public function form(string $action): void
    {
        Auth::requireLogin();
        $this->requireOrgContext();
        $cfg = $this->config($action);
        $db = Db::instance();
        $orgId = Org::id();

        $preselected = array_values(array_filter(array_map('intval', explode(',', (string)($_GET['ids'] ?? '')))));

        $errors = [];
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            $ids = array_values(array_filter(array_map('intval', (array)($_POST['asset_ids'] ?? []))));
            $personId = ($_POST['person_id'] ?? '') !== '' ? (int)$_POST['person_id'] : null;
            $eventDate = (string)($_POST['event_date'] ?? date('Y-m-d'));
            $dueDate = trim((string)($_POST['due_date'] ?? ''));
            $note = trim((string)($_POST['note'] ?? ''));
            $newLocationId = ($_POST['new_location_id'] ?? '') !== '' ? (int)$_POST['new_location_id'] : null;
            $newDepartmentId = ($_POST['new_department_id'] ?? '') !== '' ? (int)$_POST['new_department_id'] : null;

            if ($ids === []) {
                $errors[] = 'Vyberte alespoň jednu položku majetku.';
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate)) {
                $errors[] = 'Neplatné datum.';
            }
            if ($dueDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
                $errors[] = 'Neplatný termín.';
            }
            if ($cfg['person'] === 'required' && $personId === null) {
                $errors[] = 'Vyberte zaměstnance.';
            }
            if ($personId !== null
                && $db->one('SELECT id FROM persons WHERE id = ? AND organization_id = ? AND active = 1', [$personId, $orgId]) === null) {
                $errors[] = 'Neplatný zaměstnanec.';
            }
            if ($action === 'presun') {
                if ($newLocationId === null && $newDepartmentId === null) {
                    $errors[] = 'Vyberte novou lokaci nebo oddělení.';
                }
                foreach ([[$newLocationId, 'locations'], [$newDepartmentId, 'departments']] as [$val, $table]) {
                    if ($val !== null && $db->one("SELECT id FROM {$table} WHERE id = ? AND organization_id = ?", [$val, $orgId]) === null) {
                        $errors[] = 'Neplatná hodnota číselníku.';
                    }
                }
            }

            if ($errors === []) {
                $done = 0;
                $skipped = [];
                foreach ($ids as $assetId) {
                    $asset = $db->one('SELECT * FROM assets WHERE id = ? AND organization_id = ?', [$assetId, $orgId]);
                    if ($asset === null) {
                        $skipped[] = "#{$assetId}: nenalezen";
                        continue;
                    }
                    if (!in_array($asset['status'], $cfg['from'], true)) {
                        $skipped[] = $asset['tag_id'] . ': stav „' . (AssetController::STATUSES[$asset['status']] ?? $asset['status']) . '“ akci neumožňuje';
                        continue;
                    }

                    $eventPersonId = $personId;
                    $extra = null;
                    switch ($action) {
                        case 'vydej':
                            $db->exec('UPDATE assets SET status = ?, assigned_person_id = ?, updated_at = NOW() WHERE id = ?', ['assigned', $personId, $assetId]);
                            break;
                        case 'vraceni':
                            $eventPersonId = $asset['assigned_person_id'] !== null ? (int)$asset['assigned_person_id'] : null;
                            $db->exec('UPDATE assets SET status = ?, assigned_person_id = NULL, updated_at = NOW() WHERE id = ?', ['available', $assetId]);
                            break;
                        case 'presun':
                            $changes = [];
                            if ($newLocationId !== null && $newLocationId !== (int)$asset['location_id']) {
                                $old = $asset['location_id'] ? $db->scalar('SELECT name FROM locations WHERE id = ?', [(int)$asset['location_id']]) : null;
                                $new = $db->scalar('SELECT name FROM locations WHERE id = ?', [$newLocationId]);
                                $changes[] = 'lokace: ' . ($old ?? '—') . ' → ' . $new;
                                $db->exec('UPDATE assets SET location_id = ?, updated_at = NOW() WHERE id = ?', [$newLocationId, $assetId]);
                            }
                            if ($newDepartmentId !== null && $newDepartmentId !== (int)$asset['department_id']) {
                                $old = $asset['department_id'] ? $db->scalar('SELECT name FROM departments WHERE id = ?', [(int)$asset['department_id']]) : null;
                                $new = $db->scalar('SELECT name FROM departments WHERE id = ?', [$newDepartmentId]);
                                $changes[] = 'oddělení: ' . ($old ?? '—') . ' → ' . $new;
                                $db->exec('UPDATE assets SET department_id = ?, updated_at = NOW() WHERE id = ?', [$newDepartmentId, $assetId]);
                            }
                            if ($changes === []) {
                                $skipped[] = $asset['tag_id'] . ': žádná změna';
                                continue 2;
                            }
                            $extra = implode(', ', $changes);
                            break;
                        case 'vyrazeni':
                            $db->exec('UPDATE assets SET status = ?, assigned_person_id = NULL, updated_at = NOW() WHERE id = ?', ['disposed', $assetId]);
                            break;
                        case 'rezervace':
                            $db->exec('UPDATE assets SET status = ?, assigned_person_id = ?, updated_at = NOW() WHERE id = ?', ['reserved', $personId, $assetId]);
                            break;
                    }

                    $eventType = ($action === 'vraceni' && $asset['status'] === 'reserved') ? 'unreserve' : $cfg['event'];
                    $eventNote = $note;
                    if ($extra !== null) {
                        $eventNote = $eventNote !== '' ? $extra . ' — ' . $eventNote : $extra;
                    }
                    $db->exec(
                        'INSERT INTO asset_events (asset_id, type, person_id, user_id, event_date, due_date, note)
                         VALUES (?, ?, ?, ?, ?, ?, ?)',
                        [$assetId, $eventType, $eventPersonId, Auth::id(), $eventDate . ' ' . date('H:i:s'), $dueDate !== '' ? $dueDate : null, $eventNote !== '' ? $eventNote : null]
                    );
                    $done++;
                }

                if ($done > 0) {
                    flash('success', $cfg['title'] . ': zpracováno položek: ' . $done . '.');
                }
                foreach ($skipped as $s) {
                    flash('error', 'Přeskočeno — ' . $s);
                }
                if (count($ids) === 1 && $done === 1) {
                    redirect('/majetek/' . $ids[0]);
                }
                redirect('/majetek');
            }
        }

        // majetek zpusobily pro akci
        $in = implode(',', array_map(fn($s) => "'" . $s . "'", $cfg['from']));
        $eligible = $db->all(
            "SELECT a.id, a.tag_id, a.description, a.status, p.name AS person_name
             FROM assets a LEFT JOIN persons p ON p.id = a.assigned_person_id
             WHERE a.organization_id = ? AND a.status IN ({$in})
             ORDER BY a.tag_id",
            [$orgId]
        );

        View::render('movements/form', [
            'title' => $cfg['title'],
            'action' => $action,
            'cfg' => $cfg,
            'eligible' => $eligible,
            'preselected' => $preselected,
            'errors' => $errors,
            'persons' => $db->all('SELECT id, name FROM persons WHERE organization_id = ? AND active = 1 ORDER BY name', [$orgId]),
            'locations' => $db->all('SELECT * FROM locations WHERE organization_id = ? AND active = 1 ORDER BY name', [$orgId]),
            'departments' => $db->all('SELECT * FROM departments WHERE organization_id = ? AND active = 1 ORDER BY name', [$orgId]),
        ]);
    }

    /** GET /pohyby - globalni historie pohybu */
    public function history(): void
    {
        Auth::requireLogin();
        $db = Db::instance();
        $isAll = Org::isAll();

        $where = [];
        $params = [];
        if ($isAll) {
            $where[] = 'o.active = 1';
        } else {
            $where[] = 'a.organization_id = ?';
            $params[] = Org::id();
        }
        $typeFilter = (string)($_GET['typ'] ?? '');
        if ($typeFilter !== '' && preg_match('/^[a-z_]+$/', $typeFilter)) {
            $where[] = 'e.type = ?';
            $params[] = $typeFilter;
        }
        $whereSql = implode(' AND ', $where);

        $perPage = 50;
        $total = (int)$db->scalar(
            "SELECT COUNT(*) FROM asset_events e JOIN assets a ON a.id = e.asset_id JOIN organizations o ON o.id = a.organization_id WHERE {$whereSql}",
            $params
        );
        $pages = max(1, (int)ceil($total / $perPage));
        $page = min($pages, max(1, (int)($_GET['strana'] ?? 1)));

        $events = $db->all(
            "SELECT e.*, a.tag_id, a.description AS asset_description, a.id AS asset_id,
                    o.name AS org_name, p.name AS person_name, u.name AS user_name
             FROM asset_events e
             JOIN assets a ON a.id = e.asset_id
             JOIN organizations o ON o.id = a.organization_id
             LEFT JOIN persons p ON p.id = e.person_id
             LEFT JOIN users u ON u.id = e.user_id
             WHERE {$whereSql}
             ORDER BY e.event_date DESC, e.id DESC
             LIMIT {$perPage} OFFSET " . (($page - 1) * $perPage),
            $params
        );

        View::render('movements/history', [
            'title' => 'Historie pohybů',
            'events' => $events,
            'typeFilter' => $typeFilter,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'isAll' => $isAll,
        ]);
    }

    private function config(string $action): array
    {
        $cfg = self::ACTIONS[$action] ?? null;
        if ($cfg === null) {
            http_response_code(404);
            View::render('errors/404', ['title' => 'Nenalezeno']);
            exit;
        }
        return $cfg;
    }

    private function requireOrgContext(): void
    {
        if (Org::isAll()) {
            flash('error', 'Pro pohyby majetku přepněte na konkrétní organizaci.');
            redirect('/');
        }
    }
}
