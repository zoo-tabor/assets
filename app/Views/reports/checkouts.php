<div class="page-head">
    <h1><?= e($title) ?></h1>
    <div class="page-head-actions">
        <a class="btn btn-ghost" href="?format=csv">Export CSV</a>
        <a class="btn btn-ghost" href="?format=xlsx">Export XLSX</a>
        <a class="btn btn-ghost" href="<?= e(url('/reporty')) ?>">Zpět</a>
    </div>
</div>

<?php if ($grouped === []): ?>
    <div class="card"><p class="muted">Nikdo nemá nic přiděleno.</p></div>
<?php endif; ?>

<?php foreach ($grouped as $person => $rows): ?>
    <div class="card">
        <h2><?= e($person) ?> <span class="muted">(<?= count($rows) ?> ks · <?= e(format_money(array_sum(array_map(fn($r) => (float)($r['cost'] ?? 0), $rows)))) ?>)</span></h2>
        <table class="table">
            <thead><tr><th>Tag ID</th><th>Popis</th><?php if ($isAll): ?><th>Organizace</th><?php endif; ?><th class="num">Cena</th><th>Vydáno</th><th>Termín vrácení</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><a href="<?= e(url('/majetek/' . $r['id'])) ?>"><code><?= e($r['tag_id']) ?></code></a></td>
                    <td><?= e($r['description']) ?></td>
                    <?php if ($isAll): ?><td><?= e($r['org_name']) ?></td><?php endif; ?>
                    <td class="num"><?= e(format_money($r['cost'])) ?></td>
                    <td><?= e(format_date($r['event_date'])) ?></td>
                    <td class="<?= $r['due_date'] !== null && $r['due_date'] < date('Y-m-d') ? 'overdue' : '' ?>"><?= e(format_date($r['due_date'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endforeach; ?>
