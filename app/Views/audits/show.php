<?php use App\Core\Csrf; ?>
<div class="page-head">
    <h1><?= e($title) ?> <?= $audit['closed_at'] !== null ? '<span class="muted">(uzavřena)</span>' : '' ?></h1>
    <div class="page-head-actions">
        <?php if ($audit['closed_at'] === null): ?>
            <form method="post" action="<?= e(url('/inventury/' . $audit['id'] . '/uzavrit')) ?>"
                  onsubmit="return confirm('Uzavřít inventuru? Nenalezené položky se zapíší do historie majetku.')">
                <?= Csrf::field() ?>
                <button type="submit" class="btn btn-primary">Uzavřít inventuru</button>
            </form>
        <?php endif; ?>
        <a class="btn btn-ghost" href="<?= e(url('/inventury')) ?>">Zpět</a>
    </div>
</div>

<div class="cards-grid">
    <div class="card stat-card"><div class="stat-value"><?= (int)$counts['found'] ?></div><div class="stat-label">nalezeno</div></div>
    <div class="card stat-card"><div class="stat-value <?= $counts['missing'] > 0 ? 'overdue' : '' ?>"><?= (int)$counts['missing'] ?></div><div class="stat-label">nenalezeno</div></div>
    <div class="card stat-card"><div class="stat-value"><?= (int)$counts['pending'] ?></div><div class="stat-label">zbývá zkontrolovat</div></div>
</div>

<div class="card">
    <table class="table">
        <thead><tr><th>Tag ID</th><th>Popis</th><th>Lokace</th><th>Přiděleno</th><th>Stav kontroly</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($items as $item): ?>
            <tr>
                <td><a href="<?= e(url('/majetek/' . $item['asset_id'])) ?>"><code><?= e($item['tag_id']) ?></code></a></td>
                <td><?= e($item['description']) ?></td>
                <td><?= e($item['location_name'] ?? '—') ?></td>
                <td><?= e($item['person_name'] ?? '—') ?></td>
                <td>
                    <?php if ($item['status'] === 'found'): ?>
                        <span class="status status-available">nalezeno</span>
                    <?php elseif ($item['status'] === 'missing'): ?>
                        <span class="status status-disposed overdue">nenalezeno</span>
                    <?php else: ?>
                        <span class="muted">čeká</span>
                    <?php endif; ?>
                    <?php if ($item['checked_at'] !== null): ?>
                        <span class="muted"><?= e(format_date($item['checked_at'], true)) ?> · <?= e($item['checked_by_name'] ?? '') ?></span>
                    <?php endif; ?>
                </td>
                <td class="row-actions">
                    <?php if ($audit['closed_at'] === null): ?>
                        <form method="post" action="<?= e(url('/inventury/' . $audit['id'] . '/polozka/' . $item['asset_id'])) ?>" style="display:inline">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="status" value="found">
                            <button type="submit" class="btn btn-ghost btn-sm" title="Nalezeno">✓</button>
                        </form>
                        <form method="post" action="<?= e(url('/inventury/' . $audit['id'] . '/polozka/' . $item['asset_id'])) ?>" style="display:inline">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="status" value="missing">
                            <button type="submit" class="btn btn-ghost btn-sm" title="Nenalezeno">✗</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
