<?php use App\Core\Csrf; ?>
<h1><?= e($title) ?></h1>

<?php foreach ($errors as $err): ?>
    <div class="banner banner-error"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="card form-card">
    <form method="post">
        <?= Csrf::field() ?>

        <label for="name">Jméno *</label>
        <input type="text" name="name" id="name" value="<?= e($values['name']) ?>" required>

        <label for="email">E-mail *</label>
        <input type="email" name="email" id="email" value="<?= e($values['email']) ?>" required>

        <label for="password"><?= $user === null ? 'Heslo *' : 'Nové heslo (nechte prázdné pro zachování)' ?></label>
        <input type="password" name="password" id="password" autocomplete="new-password" <?= $user === null ? 'required' : '' ?>>

        <label for="password2">Heslo znovu</label>
        <input type="password" name="password2" id="password2" autocomplete="new-password">

        <label class="checkbox-row">
            <input type="checkbox" name="active" <?= $values['active'] ? 'checked' : '' ?>> Aktivní
        </label>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Uložit</button>
            <a class="btn btn-ghost" href="<?= e(url('/nastaveni/uzivatele')) ?>">Zrušit</a>
        </div>
    </form>
</div>
