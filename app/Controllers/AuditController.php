<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Db;
use App\Core\Org;
use App\Core\View;

/**
 * Inventury: snapshot majetku -> checklist nalezeno/nenalezeno -> uzavreni.
 */
final class AuditController
{
    public function index(): void
    {
        Auth::requireLogin();
        $this->requireOrgContext();
        $db = Db::instance();
        $audits = $db->all(
            'SELECT au.*, l.name AS location_name,
                    (SELECT COUNT(*) FROM audit_items ai WHERE ai.audit_id = au.id) AS total,
                    (SELECT COUNT(*) FROM audit_items ai WHERE ai.audit_id = au.id AND ai.status = \'found\') AS found_count,
                    (SELECT COUNT(*) FROM audit_items ai WHERE ai.audit_id = au.id AND ai.status = \'missing\') AS missing_count
             FROM audits au
             LEFT JOIN locations l ON l.id = au.location_id
             WHERE au.organization_id = ?
             ORDER BY au.created_at DESC',
            [Org::id()]
        );
        View::render('audits/index', [
            'title' => 'Inventury',
            'audits' => $audits,
            'locations' => $db->all('SELECT * FROM locations WHERE organization_id = ? AND active = 1 ORDER BY name', [Org::id()]),
        ]);
    }

    /** POST /inventury/nova */
    public function create(): void
    {
        Auth::requireLogin();
        $this->requireOrgContext();
        $db = Db::instance();
        $orgId = Org::id();
        $name = trim((string)($_POST['name'] ?? ''));
        $locationId = ($_POST['location_id'] ?? '') !== '' ? (int)$_POST['location_id'] : null;

        if ($name === '') {
            $name = 'Inventura ' . date('j. n. Y');
        }
        if ($locationId !== null
            && $db->one('SELECT id FROM locations WHERE id = ? AND organization_id = ?', [$locationId, $orgId]) === null) {
            flash('error', 'Neplatná lokace.');
            redirect('/inventury');
        }

        $where = 'organization_id = ? AND status <> ?';
        $params = [$orgId, 'disposed'];
        if ($locationId !== null) {
            $where .= ' AND location_id = ?';
            $params[] = $locationId;
        }
        $assetIds = array_column($db->all("SELECT id FROM assets WHERE {$where}", $params), 'id');
        if ($assetIds === []) {
            flash('error', 'Pro zadaná kritéria není žádný majetek.');
            redirect('/inventury');
        }

        $db->exec(
            'INSERT INTO audits (organization_id, name, location_id, created_at) VALUES (?, ?, ?, NOW())',
            [$orgId, $name, $locationId]
        );
        $auditId = $db->insertId();
        foreach ($assetIds as $assetId) {
            $db->exec('INSERT INTO audit_items (audit_id, asset_id, status) VALUES (?, ?, ?)', [$auditId, $assetId, 'pending']);
        }
        flash('success', 'Inventura založena (' . count($assetIds) . ' položek).');
        redirect('/inventury/' . $auditId);
    }

    public function show(string $id): void
    {
        Auth::requireLogin();
        $this->requireOrgContext();
        $db = Db::instance();
        $audit = $db->one('SELECT * FROM audits WHERE id = ? AND organization_id = ?', [(int)$id, Org::id()]);
        if ($audit === null) {
            http_response_code(404);
            View::render('errors/404', ['title' => 'Nenalezeno']);
            return;
        }
        $items = $db->all(
            'SELECT ai.*, a.tag_id, a.description, a.status AS asset_status, l.name AS location_name,
                    p.name AS person_name, u.name AS checked_by_name
             FROM audit_items ai
             JOIN assets a ON a.id = ai.asset_id
             LEFT JOIN locations l ON l.id = a.location_id
             LEFT JOIN persons p ON p.id = a.assigned_person_id
             LEFT JOIN users u ON u.id = ai.checked_by
             WHERE ai.audit_id = ?
             ORDER BY ai.status = \'pending\' DESC, a.tag_id',
            [$audit['id']]
        );
        $counts = ['found' => 0, 'missing' => 0, 'pending' => 0];
        foreach ($items as $item) {
            $counts[$item['status']] = ($counts[$item['status']] ?? 0) + 1;
        }
        View::render('audits/show', [
            'title' => $audit['name'],
            'audit' => $audit,
            'items' => $items,
            'counts' => $counts,
        ]);
    }

    /** POST /inventury/{id}/polozka/{assetId} - oznaceni found/missing/pending */
    public function mark(string $id, string $assetId): void
    {
        Auth::requireLogin();
        $this->requireOrgContext();
        $db = Db::instance();
        $audit = $db->one('SELECT * FROM audits WHERE id = ? AND organization_id = ? AND closed_at IS NULL', [(int)$id, Org::id()]);
        if ($audit === null) {
            flash('error', 'Inventura neexistuje nebo je uzavřená.');
            redirect('/inventury');
        }
        $status = (string)($_POST['status'] ?? '');
        if (!in_array($status, ['found', 'missing', 'pending'], true)) {
            redirect('/inventury/' . $audit['id']);
        }
        $db->exec(
            'UPDATE audit_items SET status = ?, checked_at = NOW(), checked_by = ? WHERE audit_id = ? AND asset_id = ?',
            [$status, Auth::id(), $audit['id'], (int)$assetId]
        );
        redirect('/inventury/' . $audit['id']);
    }

    /** POST /inventury/{id}/uzavrit */
    public function close(string $id): void
    {
        Auth::requireLogin();
        $this->requireOrgContext();
        $db = Db::instance();
        $audit = $db->one('SELECT * FROM audits WHERE id = ? AND organization_id = ? AND closed_at IS NULL', [(int)$id, Org::id()]);
        if ($audit === null) {
            flash('error', 'Inventura neexistuje nebo už je uzavřená.');
            redirect('/inventury');
        }
        $db->exec('UPDATE audits SET closed_at = NOW() WHERE id = ?', [$audit['id']]);
        // nenalezene polozky -> udalost v historii majetku
        $missing = $db->all(
            'SELECT ai.asset_id, a.tag_id FROM audit_items ai JOIN assets a ON a.id = ai.asset_id
             WHERE ai.audit_id = ? AND ai.status = \'missing\'',
            [$audit['id']]
        );
        foreach ($missing as $m) {
            $db->exec(
                'INSERT INTO asset_events (asset_id, type, user_id, event_date, note) VALUES (?, ?, ?, NOW(), ?)',
                [$m['asset_id'], 'audit', Auth::id(), 'Inventura „' . $audit['name'] . '“: NENALEZENO']
            );
        }
        flash('success', 'Inventura uzavřena.' . ($missing !== [] ? ' Nenalezených položek: ' . count($missing) . ' (zapsáno do historie).' : ''));
        redirect('/inventury/' . $audit['id']);
    }

    private function requireOrgContext(): void
    {
        if (Org::isAll()) {
            flash('error', 'Pro inventury přepněte na konkrétní organizaci.');
            redirect('/');
        }
    }
}
