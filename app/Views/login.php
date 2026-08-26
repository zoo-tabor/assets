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
    <div class="login-logo-wrap" id="login-logo-wrap" style="display:none">
        <img id="login-logo" src="" alt="">
    </div>
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
                <option value="<?= (int)$o['id'] ?>" data-logo="<?= !empty($o['logo_file']) ? e(url('/soubor/logo/' . $o['id'])) : '' ?>"><?= e($o['name']) ?></option>
            <?php endforeach; ?>
            <option value="all" data-logo="">Všechny organizace</option>
        </select>

        <label for="login">Přihlašovací jméno nebo e-mail</label>
        <input type="text" name="login" id="login" value="<?= e($loginValue) ?>" required autofocus autocomplete="username">

        <label for="password">Heslo</label>
        <input type="password" name="password" id="password" required autocomplete="current-password">

        <button type="submit" class="btn btn-primary btn-block">Přihlásit se</button>
    </form>
</div>
<script>
(function () {
    var select = document.getElementById('organization');
    var wrap = document.getElementById('login-logo-wrap');
    var img = document.getElementById('login-logo');
    function updateLogo() {
        var logo = select.options[select.selectedIndex].dataset.logo;
        if (logo) {
            img.src = logo;
            wrap.style.display = '';
        } else {
            wrap.style.display = 'none';
        }
    }
    img.addEventListener('error', function () { wrap.style.display = 'none'; });
    select.addEventListener('change', updateLogo);
    updateLogo();
})();
</script>
</body>
</html>
