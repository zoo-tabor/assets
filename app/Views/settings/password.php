<?php use App\Core\Csrf; ?>
<h1><?= e($title) ?></h1>

<?php foreach ($errors as $err): ?>
    <div class="banner banner-error"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="card form-card">
    <form method="post">
        <?= Csrf::field() ?>

        <label for="current">Současné heslo *</label>
        <input type="password" name="current" id="current" required autocomplete="current-password">

        <label for="password">Nové heslo * <span class="muted">(min. 8 znaků)</span></label>
        <input type="password" name="password" id="password" required autocomplete="new-password">

        <label for="password2">Nové heslo znovu *</label>
        <input type="password" name="password2" id="password2" required autocomplete="new-password">

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Změnit heslo</button>
            <a class="btn btn-ghost" href="<?= e(url('/')) ?>">Zrušit</a>
        </div>
    </form>
</div>
