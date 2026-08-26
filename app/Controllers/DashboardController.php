<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Db;
use App\Core\Org;
use App\Core\View;

final class DashboardController
{
    public function index(): void
    {
        Auth::requireLogin();
        $db = Db::instance();

        if (Org::isAll()) {
            $perOrg = $db->all(
                'SELECT o.id, o.name, o.accent_color,
                        COUNT(a.id) AS asset_count,
                        COALESCE(SUM(a.cost), 0) AS total_cost,
                        SUM(a.status = ?) AS assigned_count
                 FROM organizations o
                 LEFT JOIN assets a ON a.organization_id = o.id AND a.status <> ?
                 WHERE o.active = 1
                 GROUP BY o.id, o.name, o.accent_color
                 ORDER BY o.name',
                ['assigned', 'disposed']
            );
            View::render('dashboard_all', [
                'title' => 'Všechny organizace — přehled',
                'perOrg' => $perOrg,
                'totalCount' => array_sum(array_column($perOrg, 'asset_count')),
                'totalCost' => array_sum(array_map('floatval', array_column($perOrg, 'total_cost'))),
                'upcoming' => $this->upcomingDues(null),
                'recentEvents' => $this->recentEvents(null),
            ]);
            return;
        }

        $orgId = Org::id();
        $stats = [
            'count' => (int)$db->scalar('SELECT COUNT(*) FROM assets WHERE organization_id = ? AND status <> ?', [$orgId, 'disposed']),
            'cost' => (float)($db->scalar('SELECT COALESCE(SUM(cost),0) FROM assets WHERE organization_id = ? AND status <> ?', [$orgId, 'disposed']) ?? 0),
            'assigned' => (int)$db->scalar('SELECT COUNT(*) FROM assets WHERE organization_id = ? AND status = ?', [$orgId, 'assigned']),
            'available' => (int)$db->scalar('SELECT COUNT(*) FROM assets WHERE organization_id = ? AND status = ?', [$orgId, 'available']),
            'persons' => (int)$db->scalar('SELECT COUNT(*) FROM persons WHERE organization_id = ? AND active = 1', [$orgId]),
        ];

        $byCategory = $db->all(
            'SELECT COALESCE(c.name, \'(bez kategorie)\') AS name, COUNT(*) AS cnt, COALESCE(SUM(a.cost), 0) AS total
             FROM assets a LEFT JOIN categories c ON c.id = a.category_id
             WHERE a.organization_id = ? AND a.status <> ?
             GROUP BY c.name
             ORDER BY total DESC',
            [$orgId, 'disposed']
        );

        View::render('dashboard', [
            'title' => 'Dashboard',
            'stats' => $stats,
            'byCategory' => $byCategory,
            'upcoming' => $this->upcomingDues($orgId),
            'recentEvents' => $this->recentEvents($orgId),
        ]);
    }

    /** Blizici se terminy: posledni vydej/rezervace s terminem u stale vydanych/rezervovanych */
    private function upcomingDues(?int $orgId): array
    {
        $db = Db::instance();
        $orgCond = $orgId !== null ? 'a.organization_id = ?' : '1=1';
        $params = $orgId !== null ? [$orgId] : [];
        return $db->all(
            "SELECT a.id, a.tag_id, a.description, a.status, e.due_date, e.type,
                    p.name AS person_name, o.name AS org_name
             FROM assets a
             JOIN organizations o ON o.id = a.organization_id
             JOIN asset_events e ON e.id = (
                 SELECT MAX(e2.id) FROM asset_events e2
                 WHERE e2.asset_id = a.id AND e2.type IN ('checkout', 'reserve')
             )
             LEFT JOIN persons p ON p.id = a.assigned_person_id
             WHERE {$orgCond} AND o.active = 1 AND a.status IN ('assigned', 'reserved') AND e.due_date IS NOT NULL
             ORDER BY e.due_date
             LIMIT 10",
            $params
        );
    }

    private function recentEvents(?int $orgId): array
    {
        $db = Db::instance();
        $orgCond = $orgId !== null ? 'a.organization_id = ?' : '1=1';
        $params = $orgId !== null ? [$orgId] : [];
        return $db->all(
            "SELECT e.*, a.id AS asset_id, a.tag_id, a.description AS asset_description,
                    o.name AS org_name, p.name AS person_name, u.name AS user_name
             FROM asset_events e
             JOIN assets a ON a.id = e.asset_id
             JOIN organizations o ON o.id = a.organization_id
             LEFT JOIN persons p ON p.id = e.person_id
             LEFT JOIN users u ON u.id = e.user_id
             WHERE {$orgCond} AND o.active = 1
             ORDER BY e.event_date DESC, e.id DESC
             LIMIT 10",
            $params
        );
    }
}
