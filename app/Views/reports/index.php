<?php use App\Controllers\AssetController; ?>
<div class="page-head">
    <h1><?= e($title) ?></h1>
    <div class="page-head-actions">
        <a class="btn btn-ghost" href="<?= e(url('/reporty/vydej')) ?>">Výdejový report</a>
        <a class="btn btn-ghost" href="<?= e(url('/reporty/zaruky')) ?>">Záruky</a>
        <a class="btn btn-ghost" href="<?= e(url('/reporty/udrzba')) ?>">Údržba</a>
        <a class="btn btn-ghost" href="<?= e(url('/pohyby')) ?>">Historie pohybů</a>
    </div>
</div>

<div class="detail-grid">
    <div class="card">
        <h2>Dle stavu</h2>
        <table class="table">
            <thead><tr><th>Stav</th><th class="num">Počet</th><th class="num">Hodnota</th></tr></thead>
            <tbody>
            <?php foreach ($byStatus as $r): ?>
                <tr>
                    <td><a href="<?= e(url('/majetek') . '?stav=' . $r['status']) ?>"><?= e(AssetController::STATUSES[$r['status']] ?? $r['status']) ?></a></td>
                    <td class="num"><?= (int)$r['cnt'] ?></td>
                    <td class="num"><?= e(format_money($r['total'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2>Dle kategorie <span class="muted">(bez vyřazených)</span></h2>
        <table class="table">
            <thead><tr><th>Kategorie</th><th class="num">Počet</th><th class="num">Hodnota</th></tr></thead>
            <tbody>
            <?php foreach ($byCategory as $r): ?>
                <tr><td><?= e($r['name']) ?></td><td class="num"><?= (int)$r['cnt'] ?></td><td class="num"><?= e(format_money($r['total'])) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card" style="max-width: 600px">
    <h2>Dle oddělení <span class="muted">(bez vyřazených)</span></h2>
    <table class="table">
        <thead><tr><th>Oddělení</th><th class="num">Počet</th><th class="num">Hodnota</th></tr></thead>
        <tbody>
        <?php foreach ($byDepartment as $r): ?>
            <tr><td><?= e($r['name']) ?></td><td class="num"><?= (int)$r['cnt'] ?></td><td class="num"><?= e(format_money($r['total'])) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<p class="muted">Kompletní data majetku s aktuálními filtry exportuje seznam majetku (CSV/XLSX).</p>
