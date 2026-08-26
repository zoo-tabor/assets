<div class="page-head">
    <h1><?= e($title) ?></h1>
    <a class="btn btn-primary" href="<?= e(url('/nastaveni/organizace/nova')) ?>">+ Nová organizace</a>
</div>

<div class="card">
    <table class="table">
        <thead><tr><th>Název</th><th>Barva</th><th>Prefix Tag ID</th><th class="num">Další číslo</th><th>Stav</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($organizations as $o): ?>
            <tr>
                <td><?= e($o['name']) ?></td>
                <td><span class="org-dot" style="background: <?= e($o['accent_color']) ?>"></span> <?= e($o['accent_color']) ?></td>
                <td><code><?= e($o['tag_prefix']) ?>-XXXXXXX</code></td>
                <td class="num"><?= (int)$o['tag_next_number'] ?></td>
                <td><?= $o['active'] ? 'aktivní' : '<span class="muted">neaktivní</span>' ?></td>
                <td class="row-actions"><a href="<?= e(url('/nastaveni/organizace/' . $o['id'] . '/upravit')) ?>">upravit</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
