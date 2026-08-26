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
        <thead><tr><th>Záruka do</th><th>Tag ID</th><th>Popis</th><?php if ($isAll): ?><th>Organizace</th><?php endif; ?><th>Poznámka</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td class="<?= $r['expires_at'] < date('Y-m-d') ? 'overdue' : '' ?>"><?= e(format_date($r['expires_at'])) ?><?= $r['expires_at'] < date('Y-m-d') ? ' (po záruce)' : '' ?></td>
                <td><a href="<?= e(url('/majetek/' . $r['id'])) ?>"><code><?= e($r['tag_id']) ?></code></a></td>
                <td><?= e($r['description']) ?></td>
                <?php if ($isAll): ?><td><?= e($r['org_name']) ?></td><?php endif; ?>
                <td><?= e($r['notes'] ?? '—') ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($rows === []): ?>
            <tr><td colspan="5" class="muted">Žádné evidované záruky.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
