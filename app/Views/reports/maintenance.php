<div class="page-head">
    <h1><?= e($title) ?></h1>
    <div class="page-head-actions">
        <a class="btn btn-ghost" href="?format=csv">Export CSV</a>
        <a class="btn btn-ghost" href="?format=xlsx">Export XLSX</a>
        <a class="btn btn-ghost" href="<?= e(url('/reporty')) ?>">Zpět</a>
    </div>
</div>

<div class="card">
    <table class="table">
        <thead><tr><th>Termín</th><th>Údržba</th><th>Majetek</th><?php if ($isAll): ?><th>Organizace</th><?php endif; ?><th>Stav</th><th class="num">Cena</th><th>Poznámka</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td class="<?= $r['status'] !== 'done' && $r['due_date'] !== null && $r['due_date'] < date('Y-m-d') ? 'overdue' : '' ?>"><?= e(format_date($r['due_date'])) ?></td>
                <td><?= e($r['title']) ?></td>
                <td><a href="<?= e(url('/majetek/' . $r['id'])) ?>"><code><?= e($r['tag_id']) ?></code></a> <?= e($r['description']) ?></td>
                <?php if ($isAll): ?><td><?= e($r['org_name']) ?></td><?php endif; ?>
                <td><?= $r['status'] === 'done' ? 'dokončeno ' . e(format_date($r['completed_at'])) : 'plánováno' ?></td>
                <td class="num"><?= e(format_money($r['cost'])) ?></td>
                <td><?= e($r['notes'] ?? '—') ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($rows === []): ?>
            <tr><td colspan="7" class="muted">Žádná údržba.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
