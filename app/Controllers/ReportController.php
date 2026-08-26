<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Db;
use App\Core\Org;
use App\Core\View;
use App\Core\Xlsx;

/**
 * Reporty - per organizace i centralne (rezim "Vsechny organizace").
 */
final class ReportController
{
    public function index(): void
    {
        Auth::requireLogin();
        [$orgCond, $params] = $this->orgScope();
        $db = Db::instance();

        $byStatus = $db->all(
            "SELECT a.status, COUNT(*) AS cnt, COALESCE(SUM(a.cost), 0) AS total
             FROM assets a JOIN organizations o ON o.id = a.organization_id
             WHERE {$orgCond} AND o.active = 1 GROUP BY a.status ORDER BY cnt DESC",
            $params
        );
        $byCategory = $db->all(
            "SELECT COALESCE(c.name, '(bez kategorie)') AS name, COUNT(*) AS cnt, COALESCE(SUM(a.cost), 0) AS total
             FROM assets a JOIN organizations o ON o.id = a.organization_id
             LEFT JOIN categories c ON c.id = a.category_id
             WHERE {$orgCond} AND o.active = 1 AND a.status <> 'disposed'
             GROUP BY c.name ORDER BY total DESC",
            $params
        );
        $byDepartment = $db->all(
            "SELECT COALESCE(d.name, '(bez oddělení)') AS name, COUNT(*) AS cnt, COALESCE(SUM(a.cost), 0) AS total
             FROM assets a JOIN organizations o ON o.id = a.organization_id
             LEFT JOIN departments d ON d.id = a.department_id
             WHERE {$orgCond} AND o.active = 1 AND a.status <> 'disposed'
             GROUP BY d.name ORDER BY total DESC",
            $params
        );

        View::render('reports/index', [
            'title' => 'Reporty',
            'byStatus' => $byStatus,
            'byCategory' => $byCategory,
            'byDepartment' => $byDepartment,
        ]);
    }

    /** Vydejovy report: co ma kdo prideleno */
    public function checkouts(): void
    {
        Auth::requireLogin();
        [$orgCond, $params] = $this->orgScope();
        $db = Db::instance();
        $rows = $db->all(
            "SELECT p.name AS person_name, p.employee_id, o.name AS org_name,
                    a.id, a.tag_id, a.description, a.cost, e.due_date, e.event_date
             FROM assets a
             JOIN organizations o ON o.id = a.organization_id
             JOIN persons p ON p.id = a.assigned_person_id
             LEFT JOIN asset_events e ON e.id = (
                 SELECT MAX(e2.id) FROM asset_events e2 WHERE e2.asset_id = a.id AND e2.type = 'checkout'
             )
             WHERE {$orgCond} AND o.active = 1 AND a.status = 'assigned'
             ORDER BY p.name, a.tag_id",
            $params
        );

        if (($_GET['format'] ?? '') !== '') {
            $this->export('vydejovy-report', ['Zaměstnanec', 'Os. číslo', 'Organizace', 'Tag ID', 'Popis', 'Cena', 'Vydáno', 'Termín vrácení'],
                array_map(fn($r) => [$r['person_name'], $r['employee_id'], $r['org_name'], $r['tag_id'], $r['description'],
                    $r['cost'] !== null ? (float)$r['cost'] : '', format_date($r['event_date']), format_date($r['due_date'])], $rows));
        }

        // seskupeni podle osoby
        $grouped = [];
        foreach ($rows as $r) {
            $grouped[$r['person_name']][] = $r;
        }
        View::render('reports/checkouts', [
            'title' => 'Výdejový report',
            'grouped' => $grouped,
            'isAll' => Org::isAll(),
        ]);
    }

    /** Zaruky */
    public function warranties(): void
    {
        Auth::requireLogin();
        [$orgCond, $params] = $this->orgScope();
        $db = Db::instance();
        $rows = $db->all(
            "SELECT a.id, a.tag_id, a.description, o.name AS org_name, w.expires_at, w.notes
             FROM warranties w
             JOIN assets a ON a.id = w.asset_id
             JOIN organizations o ON o.id = a.organization_id
             WHERE {$orgCond} AND o.active = 1 AND a.status <> 'disposed'
             ORDER BY w.expires_at",
            $params
        );
        if (($_GET['format'] ?? '') !== '') {
            $this->export('zaruky', ['Tag ID', 'Popis', 'Organizace', 'Záruka do', 'Poznámka'],
                array_map(fn($r) => [$r['tag_id'], $r['description'], $r['org_name'], format_date($r['expires_at']), $r['notes']], $rows));
        }
        View::render('reports/warranties', [
            'title' => 'Záruky',
            'rows' => $rows,
            'isAll' => Org::isAll(),
        ]);
    }

    /** Udrzba */
    public function maintenance(): void
    {
        Auth::requireLogin();
        [$orgCond, $params] = $this->orgScope();
        $db = Db::instance();
        $rows = $db->all(
            "SELECT a.id, a.tag_id, a.description, o.name AS org_name,
                    m.title, m.status, m.due_date, m.completed_at, m.cost, m.notes
             FROM maintenances m
             JOIN assets a ON a.id = m.asset_id
             JOIN organizations o ON o.id = a.organization_id
             WHERE {$orgCond} AND o.active = 1
             ORDER BY m.status = 'done', m.due_date IS NULL, m.due_date",
            $params
        );
        if (($_GET['format'] ?? '') !== '') {
            $this->export('udrzba', ['Tag ID', 'Popis majetku', 'Organizace', 'Údržba', 'Stav', 'Termín', 'Dokončeno', 'Cena', 'Poznámka'],
                array_map(fn($r) => [$r['tag_id'], $r['description'], $r['org_name'], $r['title'],
                    $r['status'] === 'done' ? 'dokončeno' : 'plánováno', format_date($r['due_date']), format_date($r['completed_at']),
                    $r['cost'] !== null ? (float)$r['cost'] : '', $r['notes']], $rows));
        }
        View::render('reports/maintenance', [
            'title' => 'Údržba',
            'rows' => $rows,
            'isAll' => Org::isAll(),
        ]);
    }

    /** @return array{0: string, 1: array} */
    private function orgScope(): array
    {
        if (Org::isAll()) {
            return ['1=1', []];
        }
        return ['a.organization_id = ?', [Org::id()]];
    }

    private function export(string $name, array $header, array $rows): never
    {
        $format = (string)($_GET['format'] ?? 'csv');
        $filename = $name . '-' . date('Y-m-d');
        if ($format === 'xlsx') {
            Xlsx::download($filename . '.xlsx', 'Report', $header, $rows);
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $header, ';', '"', '\\');
        foreach ($rows as $row) {
            fputcsv($out, array_map(fn($v) => is_float($v) ? str_replace('.', ',', (string)$v) : (string)($v ?? ''), $row), ';', '"', '\\');
        }
        fclose($out);
        exit;
    }
}
