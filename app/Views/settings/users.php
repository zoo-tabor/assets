<div class="page-head">
    <h1><?= e($title) ?></h1>
    <a class="btn btn-primary" href="<?= e(url('/nastaveni/uzivatele/novy')) ?>">+ Nový uživatel</a>
</div>

<div class="card">
    <table class="table">
        <thead><tr><th>Jméno</th><th>E-mail</th><th>Poslední přihlášení</th><th>Stav</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?= e($u['name']) ?></td>
                <td><?= e($u['email']) ?></td>
                <td><?= e(format_date($u['last_login_at'], true)) ?></td>
                <td><?= $u['active'] ? 'aktivní' : '<span class="muted">neaktivní</span>' ?></td>
                <td class="row-actions"><a href="<?= e(url('/nastaveni/uzivatele/' . $u['id'] . '/upravit')) ?>">upravit</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
