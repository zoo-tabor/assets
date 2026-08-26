<?php
use App\Core\CustomFields;
use App\Core\Csrf;
?>
<h1><?= e($title) ?></h1>

<div class="card">
    <p class="muted">Vlastní pole se automaticky objeví ve formuláři majetku, na detailu, ve filtrech (výběr/ano-ne) a v exportech.</p>
    <table class="table">
        <thead><tr><th>Název</th><th>Typ</th><th>Možnosti</th><th class="num">Pořadí</th><th>Stav</th><th class="num">Vyplněno</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($fields as $f): ?>
            <tr>
                <td colspan="5">
                    <form method="post" action="<?= e(url('/nastaveni/vlastni-pole/' . $f['id'] . '/upravit')) ?>" class="dial-row">
                        <?= Csrf::field() ?>
                        <input type="text" name="name" value="<?= e($f['name']) ?>" required>
                        <select name="type" <?= (int)$f['usage_count'] > 0 ? 'disabled title="Typ nelze měnit — pole má hodnoty"' : '' ?>>
                            <?php foreach (CustomFields::TYPES as $key => $label): ?>
                                <option value="<?= e($key) ?>" <?= $f['type'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ((int)$f['usage_count'] > 0): ?><input type="hidden" name="type" value="<?= e($f['type']) ?>"><?php endif; ?>
                        <textarea name="options" rows="1" placeholder="možnosti (řádek = 1)" <?= $f['type'] !== 'select' ? 'style="display:none"' : '' ?>><?= e(implode("\n", CustomFields::optionsList($f))) ?></textarea>
                        <input type="number" name="sort" value="<?= (int)$f['sort'] ?>" class="input-sort">
                        <label class="checkbox-row" title="Aktivní"><input type="checkbox" name="active" <?= $f['active'] ? 'checked' : '' ?>></label>
                        <button type="submit" class="btn btn-ghost btn-sm" title="Uložit">💾</button>
                    </form>
                </td>
                <td class="num"><?= (int)$f['usage_count'] ?>×</td>
                <td class="row-actions">
                    <?php if ((int)$f['usage_count'] === 0): ?>
                        <form method="post" action="<?= e(url('/nastaveni/vlastni-pole/' . $f['id'] . '/smazat')) ?>"
                              onsubmit="return confirm('Opravdu smazat pole „<?= e($f['name']) ?>“?')">
                            <?= Csrf::field() ?>
                            <button type="submit" class="btn btn-ghost btn-sm" title="Smazat">🗑</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if ($fields === []): ?>
            <tr><td colspan="7" class="muted">Zatím žádná vlastní pole.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="card form-card">
    <h2>Přidat pole</h2>
    <form method="post" action="<?= e(url('/nastaveni/vlastni-pole/pridat')) ?>">
        <?= Csrf::field() ?>
        <label for="cf-name">Název *</label>
        <input type="text" name="name" id="cf-name" required>

        <label for="cf-type">Typ</label>
        <select name="type" id="cf-type" onchange="document.getElementById('cf-options-wrap').style.display = this.value === 'select' ? '' : 'none'">
            <?php foreach (CustomFields::TYPES as $key => $label): ?>
                <option value="<?= e($key) ?>"><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>

        <div id="cf-options-wrap" style="display:none">
            <label for="cf-options">Možnosti (každá na nový řádek)</label>
            <textarea name="options" id="cf-options" rows="4"></textarea>
        </div>

        <label for="cf-sort">Pořadí</label>
        <input type="number" name="sort" id="cf-sort" value="0">

        <label class="checkbox-row"><input type="checkbox" name="active" checked> Aktivní</label>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Přidat pole</button>
        </div>
    </form>
</div>
