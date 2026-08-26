<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Db;
use App\Core\Org;

/**
 * Rozsirujici moduly na detailu majetku: zaruky, udrzba, vazby rodic-potomek.
 */
final class AssetExtrasController
{
    // --- Zaruka ---------------------------------------------------------

    /** POST /majetek/{id}/zaruka - ulozeni/zmena zaruky */
    public function saveWarranty(string $id): void
    {
        $asset = $this->assetForWrite((int)$id);
        $db = Db::instance();
        $expiresAt = trim((string)($_POST['expires_at'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiresAt)) {
            flash('error', 'Zadejte platné datum konce záruky.');
            redirect('/majetek/' . $asset['id']);
        }
        $existing = $db->one('SELECT id FROM warranties WHERE asset_id = ?', [$asset['id']]);
        if ($existing !== null) {
            $db->exec('UPDATE warranties SET expires_at = ?, notes = ? WHERE id = ?', [$expiresAt, $notes ?: null, $existing['id']]);
        } else {
            $db->exec('INSERT INTO warranties (asset_id, expires_at, notes) VALUES (?, ?, ?)', [$asset['id'], $expiresAt, $notes ?: null]);
        }
        flash('success', 'Záruka uložena.');
        redirect('/majetek/' . $asset['id']);
    }

    /** POST /majetek/{id}/zaruka/smazat */
    public function deleteWarranty(string $id): void
    {
        $asset = $this->assetForWrite((int)$id);
        Db::instance()->exec('DELETE FROM warranties WHERE asset_id = ?', [$asset['id']]);
        flash('success', 'Záruka odstraněna.');
        redirect('/majetek/' . $asset['id']);
    }

    // --- Udrzba ---------------------------------------------------------

    /** POST /majetek/{id}/udrzba - zalozeni ukolu udrzby */
    public function addMaintenance(string $id): void
    {
        $asset = $this->assetForWrite((int)$id);
        $db = Db::instance();
        $title = trim((string)($_POST['title'] ?? ''));
        $dueDate = trim((string)($_POST['due_date'] ?? ''));
        $cost = trim((string)($_POST['cost'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));

        if ($title === '') {
            flash('error', 'Vyplňte popis údržby.');
            redirect('/majetek/' . $asset['id']);
        }
        if ($dueDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
            flash('error', 'Neplatný termín údržby.');
            redirect('/majetek/' . $asset['id']);
        }
        $db->exec(
            'INSERT INTO maintenances (asset_id, title, status, due_date, cost, notes) VALUES (?, ?, ?, ?, ?, ?)',
            [$asset['id'], $title, 'planned', $dueDate ?: null, $cost !== '' ? (float)str_replace(',', '.', $cost) : null, $notes ?: null]
        );
        flash('success', 'Údržba naplánována.');
        redirect('/majetek/' . $asset['id']);
    }

    /** POST /majetek/{id}/udrzba/{mid}/dokoncit */
    public function completeMaintenance(string $id, string $mid): void
    {
        $asset = $this->assetForWrite((int)$id);
        $db = Db::instance();
        $m = $db->one('SELECT * FROM maintenances WHERE id = ? AND asset_id = ?', [(int)$mid, $asset['id']]);
        if ($m !== null && $m['status'] !== 'done') {
            $cost = trim((string)($_POST['cost'] ?? ''));
            $db->exec(
                'UPDATE maintenances SET status = ?, completed_at = CURDATE(), cost = COALESCE(?, cost) WHERE id = ?',
                ['done', $cost !== '' ? (float)str_replace(',', '.', $cost) : null, $m['id']]
            );
            $db->exec(
                'INSERT INTO asset_events (asset_id, type, user_id, event_date, note) VALUES (?, ?, ?, NOW(), ?)',
                [$asset['id'], 'maintenance', Auth::id(), 'Údržba dokončena: ' . $m['title']]
            );
            flash('success', 'Údržba dokončena.');
        }
        redirect('/majetek/' . $asset['id']);
    }

    /** POST /majetek/{id}/udrzba/{mid}/smazat */
    public function deleteMaintenance(string $id, string $mid): void
    {
        $asset = $this->assetForWrite((int)$id);
        Db::instance()->exec('DELETE FROM maintenances WHERE id = ? AND asset_id = ?', [(int)$mid, $asset['id']]);
        flash('success', 'Údržba smazána.');
        redirect('/majetek/' . $asset['id']);
    }

    // --- Vazby rodic-potomek -------------------------------------------

    /** POST /majetek/{id}/vazby - pridani potomka */
    public function addLink(string $id): void
    {
        $asset = $this->assetForWrite((int)$id);
        $db = Db::instance();
        $childId = (int)($_POST['child_asset_id'] ?? 0);
        $child = $db->one('SELECT * FROM assets WHERE id = ? AND organization_id = ?', [$childId, $asset['organization_id']]);

        if ($child === null) {
            flash('error', 'Vyberte platný majetek.');
        } elseif ($childId === (int)$asset['id']) {
            flash('error', 'Majetek nelze navázat sám na sebe.');
        } elseif ($db->one('SELECT 1 FROM asset_links WHERE parent_asset_id = ? AND child_asset_id = ?', [$asset['id'], $childId]) !== null
            || $db->one('SELECT 1 FROM asset_links WHERE parent_asset_id = ? AND child_asset_id = ?', [$childId, $asset['id']]) !== null) {
            flash('error', 'Vazba už existuje.');
        } else {
            $db->exec('INSERT INTO asset_links (parent_asset_id, child_asset_id) VALUES (?, ?)', [$asset['id'], $childId]);
            flash('success', 'Vazba přidána: ' . $child['tag_id'] . ' je nyní součástí ' . $asset['tag_id'] . '.');
        }
        redirect('/majetek/' . $asset['id']);
    }

    /** POST /majetek/{id}/vazby/{childId}/smazat */
    public function deleteLink(string $id, string $childId): void
    {
        $asset = $this->assetForWrite((int)$id);
        Db::instance()->exec(
            'DELETE FROM asset_links WHERE (parent_asset_id = ? AND child_asset_id = ?) OR (parent_asset_id = ? AND child_asset_id = ?)',
            [$asset['id'], (int)$childId, (int)$childId, $asset['id']]
        );
        flash('success', 'Vazba odstraněna.');
        redirect('/majetek/' . $asset['id']);
    }

    private function assetForWrite(int $id): array
    {
        Auth::requireLogin();
        $asset = Db::instance()->one('SELECT * FROM assets WHERE id = ?', [$id]);
        if ($asset === null) {
            http_response_code(404);
            echo 'Majetek nenalezen.';
            exit;
        }
        if (Org::isAll() || (int)$asset['organization_id'] !== Org::id()) {
            Org::switch((string)$asset['organization_id']);
        }
        return $asset;
    }
}
