<?php
use App\Core\Auth;
use App\Core\Migrator;
use App\Core\Org;

$user = null;
$currentOrg = null;
$isAll = false;
$accent = '#1e7e34';
$allOrgs = [];
try {
    $user = Auth::user();
    $currentOrg = Org::current();
    $isAll = Org::isAll();
    $accent = Org::accentColor();
    $allOrgs = Org::allActive();
} catch (\Throwable) {
    // DB jeste nemusi existovat (pred instalaci) - layout se vykresli neutralne
}

$cookieTheme = $_COOKIE['theme'] ?? '';
$theme = in_array($cookieTheme, ['light', 'dark'], true)
    ? $cookieTheme
    : (in_array($user['theme_pref'] ?? '', ['light', 'dark'], true) ? $user['theme_pref'] : '');

$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$isActive = fn(string $prefix): string =>
    ($prefix === '/' ? $currentPath === '/' : str_starts_with($currentPath, $prefix)) ? ' class="active"' : '';

$pendingMigrations = [];
try {
    $pendingMigrations = Migrator::pending();
} catch (\Throwable) {
    // DB nemusi byt dostupna - banner proste nezobrazime
}
?>
<!doctype html>
<html lang="cs"<?= $theme !== '' ? ' data-theme="' . e($theme) . '"' : '' ?> style="--accent: <?= e($accent) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'Evidence majetku') ?> · Evidence majetku</title>
<link rel="stylesheet" href="<?= e(asset_url('assets/css/app.css')) ?>">
<script>
// pred vykreslenim: auto tema dle systemu (kdyz neni explicitni volba)
(function () {
    if (!document.documentElement.dataset.theme
        && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
        document.documentElement.dataset.theme = 'dark';
        document.documentElement.dataset.themeAuto = '1';
    }
})();
</script>
</head>
<body>
<div class="app">
    <aside class="sidebar">
        <div class="sidebar-brand">
            <?php if ($currentOrg !== null && !empty($currentOrg['logo_file'])): ?>
                <img src="<?= e(url('/soubor/logo/' . $currentOrg['id'])) ?>" alt="" class="brand-logo">
            <?php endif; ?>
            <div class="brand-name"><?= e($isAll ? 'Všechny organizace' : ($currentOrg['name'] ?? 'Evidence majetku')) ?></div>
            <div class="brand-sub">Evidence majetku</div>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section">Majetek</div>
            <a href="<?= e(url('/')) ?>"<?= $isActive('/') ?>>Dashboard</a>
            <a href="<?= e(url('/majetek')) ?>"<?= $isActive('/majetek') ?>>Majetek</a>
            <a href="<?= e(url('/zamestnanci')) ?>"<?= $isActive('/zamestnanci') ?>>Zaměstnanci</a>

            <div class="nav-section">Nastavení</div>
            <a href="<?= e(url('/nastaveni/organizace')) ?>"<?= $isActive('/nastaveni/organizace') ?>>Organizace</a>
            <a href="<?= e(url('/nastaveni/ciselniky')) ?>"<?= $isActive('/nastaveni/ciselniky') ?>>Číselníky</a>
            <a href="<?= e(url('/nastaveni/vlastni-pole')) ?>"<?= $isActive('/nastaveni/vlastni-pole') ?>>Vlastní pole</a>
            <a href="<?= e(url('/nastaveni/uzivatele')) ?>"<?= $isActive('/nastaveni/uzivatele') ?>>Uživatelé</a>
            <a href="<?= e(url('/admin/migrate')) ?>"<?= $isActive('/admin/migrate') ?>>Migrace DB</a>
        </nav>
    </aside>

    <div class="main">
        <header class="topbar">
            <?php if ($user !== null): ?>
            <form method="post" action="<?= e(url('/org/switch')) ?>" class="org-switcher">
                <?= App\Core\Csrf::field() ?>
                <input type="hidden" name="back" value="<?= e($currentPath) ?>">
                <select name="organization" onchange="this.form.submit()" aria-label="Organizace">
                    <?php foreach ($allOrgs as $o): ?>
                        <option value="<?= (int)$o['id'] ?>" <?= (!$isAll && $currentOrg !== null && (int)$currentOrg['id'] === (int)$o['id']) ? 'selected' : '' ?>>
                            <?= e($o['name']) ?>
                        </option>
                    <?php endforeach; ?>
                    <option value="all" <?= $isAll ? 'selected' : '' ?>>Všechny organizace</option>
                </select>
            </form>
            <?php endif; ?>

            <div class="topbar-spacer"></div>

            <button type="button" class="icon-btn" id="theme-toggle" title="Přepnout světlý/tmavý vzhled">◐</button>

            <?php if ($user !== null): ?>
            <div class="user-menu">
                <span class="user-name"><?= e($user['name']) ?></span>
                <a href="<?= e(url('/nastaveni/heslo')) ?>" class="muted-link">změna hesla</a>
                <form method="post" action="<?= e(url('/logout')) ?>">
                    <?= App\Core\Csrf::field() ?>
                    <button type="submit" class="btn btn-ghost">Odhlásit</button>
                </form>
            </div>
            <?php endif; ?>
        </header>

        <?php if ($isAll): ?>
            <div class="banner banner-info">Režim „Všechny organizace“ — pouze pro čtení. Pro úpravy přepněte na konkrétní organizaci.</div>
        <?php endif; ?>

        <?php if ($pendingMigrations !== [] && $currentPath !== '/admin/migrate'): ?>
            <div class="banner banner-warn">
                Čeká databázová migrace (<?= count($pendingMigrations) ?>).
                <a href="<?= e(url('/admin/migrate')) ?>">Spustit migraci</a>
            </div>
        <?php endif; ?>

        <?php foreach (flash_pull() as $msg): ?>
            <div class="banner banner-<?= e($msg['type']) ?>"><?= e($msg['text']) ?></div>
        <?php endforeach; ?>

        <main class="content">
            <?= $content ?>
        </main>
    </div>
</div>
<script src="<?= e(asset_url('assets/js/app.js')) ?>"></script>
</body>
</html>
