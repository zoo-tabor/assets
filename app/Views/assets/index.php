<?php
use App\Controllers\AssetController;

$qs = function (array $overrides = []) use ($q, $filters, $sortKey, $desc): string {
    $base = array_filter([
        'q' => $q, 'kategorie' => $filters['kategorie'], 'lokace' => $filters['lokace'],
        'oddeleni' => $filters['oddeleni'], 'osoba' => $filters['osoba'], 'stav' => $filters['stav'],
        'organizace' => $filters['organizace'], 'razeni' => $sortKey, 'smer' => $desc ? 'desc' : '',
    ], fn($v) => $v !== '' && $v !== null);
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
    <?php if (!$isAll): ?>
        <a class="btn btn-primary" href="<?= e(url('/majetek/novy')) ?>">+ Nový majetek</a>
    <?php endif; ?>
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
        <button type="submit" class="btn btn-ghost">Filtrovat</button>
        <?php if ($q !== '' || array_filter($filters)): ?>
            <a class="muted-link" href="<?= e(url('/majetek')) ?>">zrušit filtry</a>
        <?php endif; ?>
    </form>

    <table class="table">
        <thead>
        <tr>
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
            <tr><td colspan="<?= $isAll ? 9 : 8 ?>" class="muted">Žádný majetek<?= $q !== '' ? ' pro hledaný výraz' : '' ?>.</td></tr>
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
