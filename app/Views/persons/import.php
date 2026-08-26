<?php use App\Core\Csrf; ?>
<h1><?= e($title) ?></h1>

<?php if ($report !== null): ?>
    <div class="banner banner-<?= $report['added'] > 0 ? 'success' : 'warn' ?>">
        Importováno <?= (int)$report['added'] ?> z <?= (int)$report['total'] ?> řádků.
    </div>
    <?php if ($report['skipped'] !== []): ?>
        <div class="card">
            <h2>Přeskočené řádky (<?= count($report['skipped']) ?>)</h2>
            <ul>
                <?php foreach ($report['skipped'] as $s): ?><li><?= e($s) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <p><a class="btn btn-primary" href="<?= e(url('/zamestnanci')) ?>">Zpět na zaměstnance</a></p>
<?php endif; ?>

<div class="card form-card">
    <p>CSV soubor s hlavičkou (oddělovač <code>;</code> nebo <code>,</code>, kódování UTF-8 nebo Windows-1250).
       Rozpoznávané sloupce: <strong>Jméno</strong> (povinné), Osobní číslo / Employee ID, Pozice, E-mail,
       Telefon, Lokace, Oddělení, Poznámka. Neznámé lokace a oddělení se automaticky založí,
       existující zaměstnanci (podle jména) se přeskočí.</p>

    <form method="post" enctype="multipart/form-data">
        <?= Csrf::field() ?>
        <label for="csv">CSV soubor</label>
        <input type="file" name="csv" id="csv" accept=".csv,text/csv" required>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Importovat</button>
            <a class="btn btn-ghost" href="<?= e(url('/zamestnanci')) ?>">Zrušit</a>
        </div>
    </form>
</div>
