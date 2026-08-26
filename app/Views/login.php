<?php use App\Core\Csrf; ?>
<!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Přihlášení · Evidence majetku</title>
<link rel="stylesheet" href="<?= e(asset_url('assets/css/app.css')) ?>">
<script>
(function () {
    var saved = document.cookie.match(/(?:^|; )theme=(light|dark)/);
    if (saved) {
        document.documentElement.dataset.theme = saved[1];
    } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
        document.documentElement.dataset.theme = 'dark';
    }
})();
</script>
</head>
<body class="login-page">
<div class="login-box card">
    <h1>Evidence majetku</h1>
    <p class="login-sub">Přihlaste se do systému</p>

    <?php if (!empty($error)): ?>
        <div class="banner banner-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('/login')) ?>">
        <?= Csrf::field() ?>

        <label for="organization">Společnost</label>
        <select name="organization" id="organization">
            <?php foreach ($organizations as $o): ?>
                <option value="<?= (int)$o['id'] ?>"><?= e($o['name']) ?></option>
            <?php endforeach; ?>
            <option value="all">Všechny organizace</option>
        </select>

        <label for="login">Přihlašovací jméno nebo e-mail</label>
        <input type="text" name="login" id="login" value="<?= e($loginValue) ?>" required autofocus autocomplete="username">

        <label for="password">Heslo</label>
        <input type="password" name="password" id="password" required autocomplete="current-password">

        <button type="submit" class="btn btn-primary btn-block">Přihlásit se</button>
    </form>
</div>
</body>
</html>
