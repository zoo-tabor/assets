<div class="page-head">
    <h1><?= e($title) ?></h1>
    <div class="page-head-actions">
        <a class="btn btn-ghost" href="<?= e(url('/zamestnanci/import')) ?>">Import CSV</a>
        <a class="btn btn-primary" href="<?= e(url('/zamestnanci/novy')) ?>">+ Nový zaměstnanec</a>
    </div>
</div>

<div class="card">
    <form method="get" action="<?= e(url('/zamestnanci')) ?>" class="filter-row">
        <input type="text" name="q" value="<?= e($q) ?>" placeholder="Hledat jméno, číslo, e-mail…">
        <label class="checkbox-row">
            <input type="checkbox" name="neaktivni" value="1" <?= $showInactive ? 'checked' : '' ?> onchange="this.form.submit()">
            včetně neaktivních
        </label>
        <button type="submit" class="btn btn-ghost">Hledat</button>
    </form>

    <table class="table">
        <thead><tr><th>Jméno</th><th>Os. číslo</th><th>Pozice</th><th>E-mail</th><th>Telefon</th><th>Lokace</th><th>Oddělení</th><th class="num">Majetek</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($persons as $p): ?>
            <tr<?= $p['active'] ? '' : ' class="row-inactive"' ?>>
                <td><?= e($p['name']) ?><?= $p['active'] ? '' : ' <span class="muted">(neaktivní)</span>' ?></td>
                <td><?= e($p['employee_id'] ?? '—') ?></td>
                <td><?= e($p['title'] ?? '—') ?></td>
                <td><?= e($p['email'] ?? '—') ?></td>
                <td><?= e($p['phone'] ?? '—') ?></td>
                <td><?= e($p['location_name'] ?? '—') ?></td>
                <td><?= e($p['department_name'] ?? '—') ?></td>
                <td class="num"><?= (int)$p['asset_count'] ?></td>
                <td class="row-actions"><a href="<?= e(url('/zamestnanci/' . $p['id'] . '/upravit')) ?>">upravit</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($persons === []): ?>
            <tr><td colspan="9" class="muted">Žádní zaměstnanci<?= $q !== '' ? ' pro hledaný výraz' : '' ?>.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
