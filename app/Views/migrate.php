<?php use App\Core\Csrf; ?>
<?php if (!empty($setupMode)): ?>
<!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Instalace · Evidence majetku</title>
<link rel="stylesheet" href="<?= e(asset_url('assets/css/app.css')) ?>">
</head>
<body class="login-page">
<div class="login-box card migrate-box">
<?php endif; ?>

<h1><?= !empty($setupMode) ? 'Instalace aplikace' : 'Databázové migrace' ?></h1>

<?php if (!empty($setupMode)): ?>
    <p class="login-sub">První spuštění: založí se databázové schéma, organizace EKOSPOL a ZOO Tábor a účet <strong>admin</strong>.</p>
<?php endif; ?>

<?php if ($result !== null): ?>
    <?php if ($result['error'] !== null): ?>
        <div class="banner banner-error"><?= e($result['error']) ?></div>
    <?php endif; ?>
    <?php if ($result['applied'] !== []): ?>
        <div class="banner banner-success">Aplikováno: <?= e(implode(', ', $result['applied'])) ?></div>
    <?php endif; ?>
    <?php foreach ($seedLog as $line): ?>
        <div class="banner banner-info"><?= e($line) ?></div>
    <?php endforeach; ?>
    <?php if ($result['error'] === null && !empty($setupMode)): ?>
        <p><a class="btn btn-primary" href="<?= e(url('/login')) ?>">Přejít na přihlášení</a></p>
    <?php endif; ?>
<?php endif; ?>

<?php if ($pending === []): ?>
    <p class="muted">Žádné čekající migrace — databáze je aktuální.</p>
<?php else: ?>
    <p>Čekající migrace:</p>
    <ul>
        <?php foreach ($pending as $f): ?><li><code><?= e($f) ?></code></li><?php endforeach; ?>
    </ul>
    <form method="post" action="<?= e(url('/admin/migrate')) ?>">
        <?= Csrf::field() ?>
        <button type="submit" class="btn btn-primary"><?= !empty($setupMode) ? 'Nainstalovat' : 'Spustit migrace' ?></button>
    </form>
<?php endif; ?>

<?php if (empty($setupMode)): ?>
    <p class="muted" style="margin-top: 1rem">Všechny migrace v repozitáři: <?= e(implode(', ', $applied)) ?></p>
<?php endif; ?>

<?php if (!empty($setupMode)): ?>
</div>
</body>
</html>
<?php endif; ?>
