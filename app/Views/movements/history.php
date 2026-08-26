<?php
$eventLabels = [
    'create' => 'Založení', 'edit' => 'Úprava', 'checkout' => 'Výdej', 'checkin' => 'Vrácení',
    'move' => 'Přesun', 'dispose' => 'Vyřazení', 'reserve' => 'Rezervace', 'unreserve' => 'Zrušení rezervace',
    'maintenance' => 'Údržba', 'audit' => 'Inventura', 'import' => 'Import',
];
?>
<h1><?= e($title) ?> <span class="muted">(<?= (int)$total ?>)</span></h1>

<div class="card">
    <form method="get" action="<?= e(url('/pohyby')) ?>" class="filter-row">
        <select name="typ" onchange="this.form.submit()">
            <option value="">— všechny typy —</option>
            <?php foreach ($eventLabels as $key => $label): ?>
                <option value="<?= e($key) ?>" <?= $typeFilter === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </form>

    <table class="table">
        <thead><tr>
            <th>Datum</th><th>Událost</th><th>Majetek</th>
            <?php if ($isAll): ?><th>Organizace</th><?php endif; ?>
            <th>Osoba</th><th>Termín</th><th>Poznámka</th><th>Provedl</th>
        </tr></thead>
        <tbody>
        <?php foreach ($events as $ev): ?>
            <tr>
                <td><?= e(format_date($ev['event_date'], true)) ?></td>
                <td><?= e($eventLabels[$ev['type']] ?? $ev['type']) ?></td>
                <td><a href="<?= e(url('/majetek/' . $ev['asset_id'])) ?>"><code><?= e($ev['tag_id']) ?></code></a> <?= e($ev['asset_description']) ?></td>
                <?php if ($isAll): ?><td><?= e($ev['org_name']) ?></td><?php endif; ?>
                <td><?= e($ev['person_name'] ?? '—') ?></td>
                <td><?= e(format_date($ev['due_date'])) ?></td>
                <td><?= e($ev['note'] ?? '—') ?></td>
                <td><?= e($ev['user_name'] ?? '—') ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($events === []): ?>
            <tr><td colspan="<?= $isAll ? 8 : 7 ?>" class="muted">Žádné pohyby.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <?php if ($pages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $pages; $i++): ?>
                <?php if ($i === $page): ?>
                    <span class="page-current"><?= $i ?></span>
                <?php else: ?>
                    <a href="<?= e(url('/pohyby') . '?' . http_build_query(array_filter(['typ' => $typeFilter, 'strana' => $i]))) ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>
