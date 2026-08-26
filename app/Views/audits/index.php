<?php use App\Core\Csrf; ?>
<h1><?= e($title) ?></h1>

<div class="card form-card">
    <h2>Nová inventura</h2>
    <form method="post" action="<?= e(url('/inventury/nova')) ?>" class="dial-add" style="flex-wrap: wrap">
        <?= Csrf::field() ?>
        <input type="text" name="name" placeholder="název (např. Inventura Q3 2026)">
        <select name="location_id" style="max-width: 14rem">
            <option value="">— celá organizace —</option>
            <?php foreach ($locations as $l): ?>
                <option value="<?= (int)$l['id'] ?>"><?= e($l['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary">Založit</button>
    </form>
</div>

<div class="card">
    <table class="table">
        <thead><tr><th>Název</th><th>Lokace</th><th>Založena</th><th>Stav</th><th class="num">Nalezeno</th><th class="num">Nenalezeno</th><th class="num">Zbývá</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($audits as $a): ?>
            <tr>
                <td><a href="<?= e(url('/inventury/' . $a['id'])) ?>"><?= e($a['name']) ?></a></td>
                <td><?= e($a['location_name'] ?? 'celá organizace') ?></td>
                <td><?= e(format_date($a['created_at'], true)) ?></td>
                <td><?= $a['closed_at'] !== null ? 'uzavřena ' . e(format_date($a['closed_at'])) : '<strong>probíhá</strong>' ?></td>
                <td class="num"><?= (int)$a['found_count'] ?></td>
                <td class="num <?= (int)$a['missing_count'] > 0 ? 'overdue' : '' ?>"><?= (int)$a['missing_count'] ?></td>
                <td class="num"><?= (int)$a['total'] - (int)$a['found_count'] - (int)$a['missing_count'] ?></td>
                <td class="row-actions"><a href="<?= e(url('/inventury/' . $a['id'])) ?>">otevřít</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($audits === []): ?>
            <tr><td colspan="8" class="muted">Zatím žádné inventury.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
