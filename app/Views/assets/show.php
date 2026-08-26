<?php
use App\Controllers\AssetController;

$eventLabels = [
    'create' => 'Založení', 'edit' => 'Úprava', 'checkout' => 'Výdej', 'checkin' => 'Vrácení',
    'move' => 'Přesun', 'dispose' => 'Vyřazení', 'reserve' => 'Rezervace', 'unreserve' => 'Zrušení rezervace',
    'maintenance' => 'Údržba', 'audit' => 'Inventura',
];
?>
<div class="page-head">
    <h1><code><?= e($asset['tag_id']) ?></code> — <?= e($asset['description']) ?></h1>
    <div class="page-head-actions">
        <a class="btn btn-primary" href="<?= e(url('/majetek/' . $asset['id'] . '/upravit')) ?>">Upravit</a>
        <a class="btn btn-ghost" href="<?= e(url('/majetek')) ?>">Zpět na seznam</a>
    </div>
</div>

<div class="detail-grid">
    <div class="card">
        <h2>Detaily</h2>
        <table class="table table-detail">
            <tr><th>Stav</th><td><span class="status status-<?= e($asset['status']) ?>"><?= e(AssetController::STATUSES[$asset['status']] ?? $asset['status']) ?></span></td></tr>
            <tr><th>Značka</th><td><?= e($asset['brand'] ?? '—') ?></td></tr>
            <tr><th>Model</th><td><?= e($asset['model'] ?? '—') ?></td></tr>
            <tr><th>Sériové číslo</th><td><?= e($asset['serial_no'] ?? '—') ?></td></tr>
            <tr><th>Datum nákupu</th><td><?= e(format_date($asset['purchase_date'])) ?></td></tr>
            <tr><th>Cena</th><td><?= e(format_money($asset['cost'])) ?></td></tr>
            <tr><th>Dodavatel</th><td><?= e($asset['purchased_from'] ?? '—') ?></td></tr>
        </table>
    </div>

    <div class="card">
        <h2>Zařazení a IT</h2>
        <table class="table table-detail">
            <?php
            $db = App\Core\Db::instance();
            $dialName = fn(?string $id, string $table) => $id ? ($db->scalar("SELECT name FROM {$table} WHERE id = ?", [(int)$id]) ?? '—') : '—';
            ?>
            <tr><th>Kategorie</th><td><?= e($dialName($asset['category_id'], 'categories')) ?></td></tr>
            <tr><th>Lokace</th><td><?= e($dialName($asset['location_id'], 'locations')) ?></td></tr>
            <tr><th>Oddělení</th><td><?= e($dialName($asset['department_id'], 'departments')) ?></td></tr>
            <tr><th>Přiděleno</th><td><?= e($asset['assigned_person_id'] ? ($db->scalar('SELECT name FROM persons WHERE id = ?', [(int)$asset['assigned_person_id']]) ?? '—') : '—') ?></td></tr>
            <tr><th>OS</th><td><?= e($asset['os_type'] ?? '—') ?><?= $asset['os_sn'] ? ' <span class="muted">SN: ' . e($asset['os_sn']) . '</span>' : '' ?></td></tr>
            <tr><th>Office</th><td><?= e($asset['office'] ?? '—') ?><?= $asset['office_sn'] ? ' <span class="muted">SN: ' . e($asset['office_sn']) . '</span>' : '' ?></td></tr>
            <tr><th>Poznámka</th><td><?= nl2br(e($asset['note'] ?? '—')) ?></td></tr>
        </table>
    </div>
</div>

<div class="card">
    <h2>Historie</h2>
    <?php if ($events === []): ?>
        <p class="muted">Žádné události.</p>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>Datum</th><th>Událost</th><th>Osoba</th><th>Termín</th><th>Poznámka</th><th>Provedl</th></tr></thead>
            <tbody>
            <?php foreach ($events as $ev): ?>
                <tr>
                    <td><?= e(format_date($ev['event_date'], true)) ?></td>
                    <td><?= e($eventLabels[$ev['type']] ?? $ev['type']) ?></td>
                    <td><?= e($ev['person_name'] ?? '—') ?></td>
                    <td><?= e(format_date($ev['due_date'])) ?></td>
                    <td><?= e($ev['note'] ?? '—') ?></td>
                    <td><?= e($ev['user_name'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
