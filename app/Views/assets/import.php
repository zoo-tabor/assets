<?php use App\Core\Csrf; ?>
<h1><?= e($title) ?></h1>

<?php if ($report !== null): ?>
    <div class="banner banner-<?= $report['added'] > 0 ? 'success' : 'warn' ?>">
        Importováno <?= (int)$report['added'] ?> z <?= (int)$report['total'] ?> řádků<?= $report['photos'] > 0 ? ', stažených fotek: ' . (int)$report['photos'] : '' ?>.
    </div>
    <?php if ($report['skipped'] !== []): ?>
        <div class="card">
            <h2>Přeskočené řádky a problémy (<?= count($report['skipped']) ?>)</h2>
            <ul>
                <?php foreach ($report['skipped'] as $s): ?><li><?= e($s) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <p><a class="btn btn-primary" href="<?= e(url('/majetek')) ?>">Zpět na majetek</a></p>
<?php endif; ?>

<div class="card form-card">
    <p>Podporované soubory: <strong>XLSX</strong> nebo <strong>CSV</strong> (oddělovač <code>;</code>/<code>,</code>).
       Funguje přímo s exportem z AssetTigeru — sloupec <em>Asset Tag ID</em> se uloží do vlastního pole
       „Původní tag ID“ a vygeneruje se nové Tag ID z řady organizace. Sloupec <em>Assigned to</em> založí
       zaměstnance a majetek mu rovnou vydá. Neznámé lokace/kategorie/oddělení se automaticky založí.
       Duplicity (stejné původní tag ID nebo Tag ID) se přeskočí.</p>

    <form method="post" enctype="multipart/form-data">
        <?= Csrf::field() ?>
        <label for="file">Soubor (XLSX / CSV)</label>
        <input type="file" name="file" id="file" accept=".xlsx,.csv" required>

        <label class="checkbox-row" style="margin-top: 0.9rem">
            <input type="checkbox" name="download_photos" checked>
            Stáhnout fotky ze sloupce Asset Photo (URL)
        </label>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Importovat</button>
            <a class="btn btn-ghost" href="<?= e(url('/majetek')) ?>">Zrušit</a>
        </div>
    </form>
</div>
