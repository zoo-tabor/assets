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
            // Centralni prehled: agregace pres vsechny organizace (jen cteni)
            $perOrg = $db->all(
                'SELECT o.id, o.name, o.accent_color,
                        COUNT(a.id) AS asset_count,
                        COALESCE(SUM(a.cost), 0) AS total_cost
                 FROM organizations o
                 LEFT JOIN assets a ON a.organization_id = o.id AND a.status <> ?
                 WHERE o.active = 1
                 GROUP BY o.id, o.name, o.accent_color
                 ORDER BY o.name',
                ['disposed']
            );
            View::render('dashboard_all', [
                'title' => 'Všechny organizace — přehled',
                'perOrg' => $perOrg,
                'totalCount' => array_sum(array_column($perOrg, 'asset_count')),
                'totalCost' => array_sum(array_map('floatval', array_column($perOrg, 'total_cost'))),
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

        $recentEvents = $db->all(
            'SELECT e.*, a.tag_id, a.description AS asset_description, p.name AS person_name, u.name AS user_name
             FROM asset_events e
             JOIN assets a ON a.id = e.asset_id
             LEFT JOIN persons p ON p.id = e.person_id
             LEFT JOIN users u ON u.id = e.user_id
             WHERE a.organization_id = ?
             ORDER BY e.event_date DESC, e.id DESC
             LIMIT 10',
            [$orgId]
        );

        View::render('dashboard', [
            'title' => 'Dashboard',
            'stats' => $stats,
            'recentEvents' => $recentEvents,
        ]);
    }
}
