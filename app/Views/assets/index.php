<?php
use App\Controllers\AssetController;

$qs = function (array $overrides = []) use ($q, $filters, $sortKey, $desc): string {
    $base = array_filter(
        array_merge($filters, ['q' => $q, 'razeni' => $sortKey, 'smer' => $desc ? 'desc' : '']),
        fn($v) => $v !== '' && $v !== null
    );
    return http_build_query(array_merge($base, $overrides));
};
$sortLink = function (string $key, string $label) use ($qs, $sortKey, $desc): string {
    $arrow = $sortKey === $key ? ($desc ? ' ↓' : ' ↑') : '';
    $dir = ($sortKey === $key && !$desc) ? 'desc' : '';
    return '<a href="' . e(url('/majetek') . '?' . $qs(['razeni' => $key, 'smer' => $dir, 'strana' => 1])) . '">' . e($label) . $arrow . '</a>';
};
?>
<div class="page-head">
    <h1><?= e($title) ?> <span class="muted">(<?= (int)$total ?>)</span></h1>
    <div class="page-head-actions">
        <a class="btn btn-ghost" href="<?= e(url('/majetek/export.csv') . '?' . $qs()) ?>">Export CSV</a>
        <a class="btn btn-ghost" href="<?= e(url('/majetek/export.xlsx') . '?' . $qs()) ?>">Export XLSX</a>
        <?php if (!$isAll): ?>
            <a class="btn btn-primary" href="<?= e(url('/majetek/novy')) ?>">+ Nový majetek</a>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <form method="get" action="<?= e(url('/majetek')) ?>" class="filter-row">
        <input type="text" name="q" value="<?= e($q) ?>" placeholder="Hledat tag, popis, značku, model, SN…">
        <?php if ($isAll): ?>
            <select name="organizace" onchange="this.form.submit()">
                <option value="">— všechny organizace —</option>
                <?php foreach ($organizations as $o): ?>
                    <option value="<?= (int)$o['id'] ?>" <?= $filters['organizace'] === (string)$o['id'] ? 'selected' : '' ?>><?= e($o['name']) ?></option>
                <?php endforeach; ?>
            </select>
        <?php else: ?>
            <select name="kategorie" onchange="this.form.submit()">
                <option value="">— kategorie —</option>
                <?php foreach ($dials['categories'] as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= $filters['kategorie'] === (string)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="lokace" onchange="this.form.submit()">
                <option value="">— lokace —</option>
                <?php foreach ($dials['locations'] as $l): ?>
                    <option value="<?= (int)$l['id'] ?>" <?= $filters['lokace'] === (string)$l['id'] ? 'selected' : '' ?>><?= e($l['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="oddeleni" onchange="this.form.submit()">
                <option value="">— oddělení —</option>
                <?php foreach ($dials['departments'] as $d): ?>
                    <option value="<?= (int)$d['id'] ?>" <?= $filters['oddeleni'] === (string)$d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="osoba" onchange="this.form.submit()">
                <option value="">— osoba —</option>
                <?php foreach ($dials['persons'] as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= $filters['osoba'] === (string)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
        <select name="stav" onchange="this.form.submit()">
            <option value="">— stav —</option>
            <?php foreach (AssetController::STATUSES as $key => $label): ?>
                <option value="<?= e($key) ?>" <?= $filters['stav'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <?php foreach ($customFields as $cf): ?>
            <?php if ($cf['type'] === 'select'): $key = 'cf_' . $cf['id']; ?>
                <select name="<?= e($key) ?>" onchange="this.form.submit()">
                    <option value="">— <?= e(mb_strtolower($cf['name'])) ?> —</option>
                    <?php foreach ($cf['options_list'] as $opt): ?>
                        <option value="<?= e($opt) ?>" <?= ($filters[$key] ?? '') === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php elseif ($cf['type'] === 'bool'): $key = 'cf_' . $cf['id']; ?>
                <select name="<?= e($key) ?>" onchange="this.form.submit()">
                    <option value="">— <?= e(mb_strtolower($cf['name'])) ?> —</option>
                    <option value="1" <?= ($filters[$key] ?? '') === '1' ? 'selected' : '' ?>>Ano</option>
                </select>
            <?php endif; ?>
        <?php endforeach; ?>
        <button type="submit" class="btn btn-ghost">Filtrovat</button>
        <?php if ($q !== '' || array_filter($filters)): ?>
            <a class="muted-link" href="<?= e(url('/majetek')) ?>">zrušit filtry</a>
        <?php endif; ?>
    </form>

    <?php if (!$isAll): ?>
        <div class="bulk-bar" id="bulk-bar" style="display:none">
            <span><span id="bulk-count">0</span> vybráno:</span>
            <a class="btn btn-ghost btn-sm" data-bulk="/vydej">Vydat</a>
            <a class="btn btn-ghost btn-sm" data-bulk="/vraceni">Vrátit</a>
            <a class="btn btn-ghost btn-sm" data-bulk="/presun">Přesunout</a>
            <a class="btn btn-ghost btn-sm" data-bulk="/rezervace">Rezervovat</a>
            <a class="btn btn-ghost btn-sm" data-bulk="/vyrazeni">Vyřadit</a>
        </div>
    <?php endif; ?>

    <table class="table">
        <thead>
        <tr>
            <?php if (!$isAll): ?><th class="col-check"><input type="checkbox" id="bulk-all" title="Vybrat vše"></th><?php endif; ?>
            <th><?= $sortLink('tag', 'Tag ID') ?></th>
            <?php if ($isAll): ?><th>Organizace</th><?php endif; ?>
            <th><?= $sortLink('popis', 'Popis') ?></th>
            <th><?= $sortLink('kategorie', 'Kategorie') ?></th>
            <th>Lokace</th>
            <th>Osoba</th>
            <th><?= $sortLink('stav', 'Stav') ?></th>
            <th class="num"><?= $sortLink('cena', 'Cena') ?></th>
            <th class="num"><?= $sortLink('nakup', 'Nákup') ?></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($assets as $a): ?>
            <tr>
                <?php if (!$isAll): ?><td class="col-check"><input type="checkbox" class="bulk-check" value="<?= (int)$a['id'] ?>"></td><?php endif; ?>
                <td><a href="<?= e(url('/majetek/' . $a['id'])) ?>"><code><?= e($a['tag_id']) ?></code></a></td>
                <?php if ($isAll): ?>
                    <td><span class="org-dot" style="background: <?= e($a['accent_color']) ?>"></span> <?= e($a['org_name']) ?></td>
                <?php endif; ?>
                <td><?= e($a['description']) ?><?= $a['brand'] || $a['model'] ? ' <span class="muted">' . e(trim($a['brand'] . ' ' . $a['model'])) . '</span>' : '' ?></td>
                <td><?= e($a['category_name'] ?? '—') ?></td>
                <td><?= e($a['location_name'] ?? '—') ?></td>
                <td><?= e($a['person_name'] ?? '—') ?></td>
                <td><span class="status status-<?= e($a['status']) ?>"><?= e(AssetController::STATUSES[$a['status']] ?? $a['status']) ?></span></td>
                <td class="num"><?= e(format_money($a['cost'])) ?></td>
                <td class="num"><?= e(format_date($a['purchase_date'])) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($assets === []): ?>
            <tr><td colspan="<?= $isAll ? 9 : 9 ?>" class="muted">Žádný majetek<?= $q !== '' ? ' pro hledaný výraz' : '' ?>.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <?php if ($pages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $pages; $i++): ?>
                <?php if ($i === $page): ?>
                    <span class="page-current"><?= $i ?></span>
                <?php else: ?>
                    <a href="<?= e(url('/majetek') . '?' . $qs(['strana' => $i])) ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>
