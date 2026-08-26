<h1><?= e($title) ?></h1>

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

<div class="card">
    <h2>Poslední pohyby</h2>
    <?php if ($recentEvents === []): ?>
        <p class="muted">Zatím žádné pohyby. Majetek a pohyby přibudou v dalších fázích.</p>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>Datum</th><th>Typ</th><th>Majetek</th><th>Osoba</th><th>Provedl</th></tr></thead>
            <tbody>
            <?php foreach ($recentEvents as $ev): ?>
                <tr>
                    <td><?= e(format_date($ev['event_date'], true)) ?></td>
                    <td><?= e($ev['type']) ?></td>
                    <td><?= e($ev['tag_id']) ?> — <?= e($ev['asset_description']) ?></td>
                    <td><?= e($ev['person_name'] ?? '—') ?></td>
                    <td><?= e($ev['user_name'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
