<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Db;
use App\Core\Env;
use App\Core\Mailer;

/**
 * Denni planovana uloha spoustena pres GET /cron/run?key=CRON_KEY
 * (GitHub Actions schedule -> curl). Posila souhrnny e-mail vsem
 * aktivnim uzivatelum, jen kdyz je co hlasit.
 */
final class CronController
{
    public function run(): void
    {
        header('Content-Type: text/plain; charset=utf-8');

        $cronKey = (string)Env::get('CRON_KEY', '');
        if ($cronKey === '') {
            http_response_code(503);
            echo "CRON_KEY neni nastaven v .env - cron je vypnuty.\n";
            return;
        }
        if (!hash_equals($cronKey, (string)($_GET['key'] ?? ''))) {
            http_response_code(403);
            echo "403\n";
            return;
        }

        $db = Db::instance();
        $sections = [];

        // 1) Terminy vraceni/rezervaci - dnes, po terminu, nebo do 3 dnu
        $dues = $db->all(
            "SELECT a.tag_id, a.description, o.name AS org_name, e.due_date, e.type, p.name AS person_name
             FROM assets a
             JOIN organizations o ON o.id = a.organization_id
             JOIN asset_events e ON e.id = (
                 SELECT MAX(e2.id) FROM asset_events e2 WHERE e2.asset_id = a.id AND e2.type IN ('checkout', 'reserve')
             )
             LEFT JOIN persons p ON p.id = a.assigned_person_id
             WHERE o.active = 1 AND a.status IN ('assigned', 'reserved') AND e.due_date IS NOT NULL
               AND e.due_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)
             ORDER BY e.due_date"
        );
        if ($dues !== []) {
            $lines = [];
            foreach ($dues as $r) {
                $overdue = $r['due_date'] < date('Y-m-d') ? ' (PO TERMÍNU)' : '';
                $typ = $r['type'] === 'reserve' ? 'rezervace' : 'vrácení';
                $lines[] = sprintf('- %s: %s %s — %s, %s%s', format_date($r['due_date']), $typ, $r['tag_id'], $r['description'], $r['person_name'] ?? $r['org_name'], $overdue);
            }
            $sections[] = "TERMÍNY VRÁCENÍ A REZERVACÍ (do 3 dnů):\n" . implode("\n", $lines);
        }

        // 2) Zaruky koncici do 30 dnu (jeste platne)
        $warranties = $db->all(
            "SELECT a.tag_id, a.description, o.name AS org_name, w.expires_at
             FROM warranties w
             JOIN assets a ON a.id = w.asset_id
             JOIN organizations o ON o.id = a.organization_id
             WHERE o.active = 1 AND a.status <> 'disposed'
               AND w.expires_at BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
             ORDER BY w.expires_at"
        );
        if ($warranties !== []) {
            $lines = [];
            foreach ($warranties as $r) {
                $lines[] = sprintf('- %s: %s — %s (%s)', format_date($r['expires_at']), $r['tag_id'], $r['description'], $r['org_name']);
            }
            $sections[] = "ZÁRUKY KONČÍCÍ DO 30 DNŮ:\n" . implode("\n", $lines);
        }

        // 3) Udrzba do 7 dnu nebo po terminu
        $maintenance = $db->all(
            "SELECT a.tag_id, a.description, o.name AS org_name, m.title, m.due_date
             FROM maintenances m
             JOIN assets a ON a.id = m.asset_id
             JOIN organizations o ON o.id = a.organization_id
             WHERE o.active = 1 AND a.status <> 'disposed' AND m.status <> 'done'
               AND m.due_date IS NOT NULL AND m.due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
             ORDER BY m.due_date"
        );
        if ($maintenance !== []) {
            $lines = [];
            foreach ($maintenance as $r) {
                $overdue = $r['due_date'] < date('Y-m-d') ? ' (PO TERMÍNU)' : '';
                $lines[] = sprintf('- %s: %s — %s / %s (%s)%s', format_date($r['due_date']), $r['title'], $r['tag_id'], $r['description'], $r['org_name'], $overdue);
            }
            $sections[] = "PLÁNOVANÁ ÚDRŽBA (do 7 dnů):\n" . implode("\n", $lines);
        }

        if ($sections === []) {
            echo "OK - nic k hlaseni, e-mail se neposila.\n";
            return;
        }

        $body = "Denní přehled z Evidence majetku (" . date('j. n. Y') . "):\n\n"
            . implode("\n\n", $sections)
            . "\n\n—\n" . Env::get('APP_URL', 'https://assets.ekospol.cz');

        $recipients = $db->all('SELECT email FROM users WHERE active = 1');
        $sent = 0;
        foreach ($recipients as $r) {
            if (filter_var($r['email'], FILTER_VALIDATE_EMAIL) && Mailer::send($r['email'], 'Evidence majetku — denní přehled', $body)) {
                $sent++;
            }
        }
        echo 'OK - odeslano e-mailu: ' . $sent . ' (sekci: ' . count($sections) . ")\n";
    }
}
