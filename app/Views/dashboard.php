<?php
$eventLabels = [
    'create' => 'Založení', 'edit' => 'Úprava', 'checkout' => 'Výdej', 'checkin' => 'Vrácení',
    'move' => 'Přesun', 'dispose' => 'Vyřazení', 'reserve' => 'Rezervace', 'unreserve' => 'Zrušení rezervace',
    'maintenance' => 'Údržba', 'audit' => 'Inventura', 'import' => 'Import',
];
$today = date('Y-m-d');
?>
<div class="page-head">
    <h1><?= e($title) ?></h1>
    <div class="page-head-actions">
        <a class="btn btn-primary" href="<?= e(url('/vydej')) ?>">Výdej</a>
        <a class="btn btn-ghost" href="<?= e(url('/vraceni')) ?>">Vrácení</a>
        <a class="btn btn-ghost" href="<?= e(url('/majetek/novy')) ?>">+ Majetek</a>
    </div>
</div>

<div class="cards-grid">
    <div class="card stat-card">
        <div class="stat-value"><?= (int)$stats['count'] ?></div>
        <div class="stat-label">položek majetku</div>
    </div>
    <div class="card stat-card">
        <div class="stat-value"><?= e(format_money($stats['cost'])) ?></div>
        <div class="stat-label">celková hodnota</div>
    </div>
    <div class="card stat-card">
        <div class="stat-value"><?= (int)$stats['assigned'] ?></div>
        <div class="stat-label">přiděleno</div>
    </div>
    <div class="card stat-card">
        <div class="stat-value"><?= (int)$stats['available'] ?></div>
        <div class="stat-label">k dispozici</div>
    </div>
    <div class="card stat-card">
        <div class="stat-value"><?= (int)$stats['persons'] ?></div>
        <div class="stat-label">zaměstnanců</div>
    </div>
</div>

<div class="detail-grid">
    <div class="card">
        <h2>Hodnota dle kategorií</h2>
        <?php if ($byCategory === []): ?>
            <p class="muted">Žádný majetek.</p>
        <?php else: ?>
            <?php $max = max(array_map('floatval', array_column($byCategory, 'total'))) ?: 1; ?>
            <div class="bar-chart">
                <?php foreach ($byCategory as $row): ?>
                    <div class="bar-row">
                        <span class="bar-label"><?= e($row['name']) ?> <span class="muted">(<?= (int)$row['cnt'] ?>)</span></span>
                        <div class="bar-track">
                            <div class="bar-fill" style="width: <?= max(2, round((float)$row['total'] / $max * 100)) ?>%"></div>
                        </div>
                        <span class="bar-value"><?= e(format_money($row['total'])) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Blížící se termíny</h2>
        <?php if ($upcoming === []): ?>
            <p class="muted">Žádné termíny vrácení ani rezervací.</p>
        <?php else: ?>
            <table class="table">
                <thead><tr><th>Termín</th><th>Majetek</th><th>Osoba</th><th>Typ</th></tr></thead>
                <tbody>
                <?php foreach ($upcoming as $u): ?>
                    <tr>
                        <td class="<?= $u['due_date'] < $today ? 'overdue' : '' ?>"><?= e(format_date($u['due_date'])) ?><?= $u['due_date'] < $today ? ' ⚠' : '' ?></td>
                        <td><a href="<?= e(url('/majetek/' . $u['id'])) ?>"><code><?= e($u['tag_id']) ?></code></a> <?= e($u['description']) ?></td>
                        <td><?= e($u['person_name'] ?? '—') ?></td>
                        <td><?= e(['checkout' => 'vrácení', 'reserve' => 'rezervace', 'warranty' => 'záruka', 'maintenance' => 'údržba'][$u['kind']] ?? $u['kind']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <h2>Poslední pohyby</h2>
    <?php if ($recentEvents === []): ?>
        <p class="muted">Zatím žádné pohyby.</p>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>Datum</th><th>Typ</th><th>Majetek</th><th>Osoba</th><th>Provedl</th></tr></thead>
            <tbody>
            <?php foreach ($recentEvents as $ev): ?>
                <tr>
                    <td><?= e(format_date($ev['event_date'], true)) ?></td>
                    <td><?= e($eventLabels[$ev['type']] ?? $ev['type']) ?></td>
                    <td><a href="<?= e(url('/majetek/' . $ev['asset_id'])) ?>"><code><?= e($ev['tag_id']) ?></code></a> <?= e($ev['asset_description']) ?></td>
                    <td><?= e($ev['person_name'] ?? '—') ?></td>
                    <td><?= e($ev['user_name'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p><a class="muted-link" href="<?= e(url('/pohyby')) ?>">celá historie pohybů →</a></p>
    <?php endif; ?>
</div>
