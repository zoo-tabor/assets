<?php
$eventLabels = [
    'create' => 'Založení', 'edit' => 'Úprava', 'checkout' => 'Výdej', 'checkin' => 'Vrácení',
    'move' => 'Přesun', 'dispose' => 'Vyřazení', 'reserve' => 'Rezervace', 'unreserve' => 'Zrušení rezervace',
    'maintenance' => 'Údržba', 'audit' => 'Inventura', 'import' => 'Import',
];
$today = date('Y-m-d');
?>
<h1><?= e($title) ?></h1>

<div class="cards-grid">
    <div class="card stat-card">
        <div class="stat-value"><?= (int)$totalCount ?></div>
        <div class="stat-label">položek majetku celkem</div>
    </div>
    <div class="card stat-card">
        <div class="stat-value"><?= e(format_money($totalCost)) ?></div>
        <div class="stat-label">hodnota celkem</div>
    </div>
</div>

<div class="card">
    <h2>Po organizacích</h2>
    <table class="table">
        <thead><tr><th>Organizace</th><th class="num">Položek</th><th class="num">Přiděleno</th><th class="num">Hodnota</th></tr></thead>
        <tbody>
        <?php foreach ($perOrg as $row): ?>
            <tr>
                <td><span class="org-dot" style="background: <?= e($row['accent_color']) ?>"></span> <?= e($row['name']) ?></td>
                <td class="num"><?= (int)$row['asset_count'] ?></td>
                <td class="num"><?= (int)$row['assigned_count'] ?></td>
                <td class="num"><?= e(format_money($row['total_cost'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="detail-grid">
    <div class="card">
        <h2>Blížící se termíny</h2>
        <?php if ($upcoming === []): ?>
            <p class="muted">Žádné termíny vrácení ani rezervací.</p>
        <?php else: ?>
            <table class="table">
                <thead><tr><th>Termín</th><th>Majetek</th><th>Organizace</th><th>Osoba</th></tr></thead>
                <tbody>
                <?php foreach ($upcoming as $u): ?>
                    <tr>
                        <td class="<?= $u['due_date'] < $today ? 'overdue' : '' ?>"><?= e(format_date($u['due_date'])) ?><?= $u['due_date'] < $today ? ' ⚠' : '' ?></td>
                        <td><a href="<?= e(url('/majetek/' . $u['id'])) ?>"><code><?= e($u['tag_id']) ?></code></a> <?= e($u['description']) ?></td>
                        <td><?= e($u['org_name']) ?></td>
                        <td><?= e($u['person_name'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Poslední pohyby</h2>
        <?php if ($recentEvents === []): ?>
            <p class="muted">Zatím žádné pohyby.</p>
        <?php else: ?>
            <table class="table">
                <thead><tr><th>Datum</th><th>Typ</th><th>Majetek</th><th>Organizace</th></tr></thead>
                <tbody>
                <?php foreach ($recentEvents as $ev): ?>
                    <tr>
                        <td><?= e(format_date($ev['event_date'], true)) ?></td>
                        <td><?= e($eventLabels[$ev['type']] ?? $ev['type']) ?></td>
                        <td><a href="<?= e(url('/majetek/' . $ev['asset_id'])) ?>"><code><?= e($ev['tag_id']) ?></code></a></td>
                        <td><?= e($ev['org_name']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p><a class="muted-link" href="<?= e(url('/pohyby')) ?>">celá historie pohybů →</a></p>
        <?php endif; ?>
    </div>
</div>
