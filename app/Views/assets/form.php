<?php
use App\Controllers\AssetController;
use App\Core\Csrf;
?>
<h1><?= e($title) ?></h1>

<?php foreach ($errors as $err): ?>
    <div class="banner banner-error"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="card form-card form-wide">
    <form method="post">
        <?= Csrf::field() ?>

        <div class="form-grid">
            <div>
                <label for="tag_id">Tag ID <span class="muted">(prázdné = automaticky <?= e($suggestedTag) ?>)</span></label>
                <input type="text" name="tag_id" id="tag_id" value="<?= e($values['tag_id']) ?>">

                <label for="description">Popis *</label>
                <input type="text" name="description" id="description" value="<?= e($values['description']) ?>" required>

                <label for="brand">Značka</label>
                <input type="text" name="brand" id="brand" value="<?= e($values['brand']) ?>">

                <label for="model">Model</label>
                <input type="text" name="model" id="model" value="<?= e($values['model']) ?>">

                <label for="serial_no">Sériové číslo</label>
                <input type="text" name="serial_no" id="serial_no" value="<?= e($values['serial_no']) ?>">

                <label for="purchase_date">Datum nákupu</label>
                <input type="date" name="purchase_date" id="purchase_date" value="<?= e($values['purchase_date']) ?>">

                <label for="cost">Cena (Kč)</label>
                <input type="text" name="cost" id="cost" value="<?= e($values['cost']) ?>" inputmode="decimal">

                <label for="purchased_from">Dodavatel</label>
                <input type="text" name="purchased_from" id="purchased_from" value="<?= e($values['purchased_from']) ?>">
            </div>
            <div>
                <label for="category_id">Kategorie</label>
                <select name="category_id" id="category_id">
                    <option value="">—</option>
                    <?php foreach ($dials['categories'] as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= (int)($values['category_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="location_id">Lokace</label>
                <select name="location_id" id="location_id">
                    <option value="">—</option>
                    <?php foreach ($dials['locations'] as $l): ?>
                        <option value="<?= (int)$l['id'] ?>" <?= (int)($values['location_id'] ?? 0) === (int)$l['id'] ? 'selected' : '' ?>><?= e($l['name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="department_id">Oddělení</label>
                <select name="department_id" id="department_id">
                    <option value="">—</option>
                    <?php foreach ($dials['departments'] as $d): ?>
                        <option value="<?= (int)$d['id'] ?>" <?= (int)($values['department_id'] ?? 0) === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="os_type">OS</label>
                <select name="os_type" id="os_type">
                    <option value="">—</option>
                    <?php foreach (AssetController::OS_TYPES as $os): ?>
                        <option value="<?= e($os) ?>" <?= $values['os_type'] === $os ? 'selected' : '' ?>><?= e($os) ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="os_sn">OS sériové číslo</label>
                <input type="text" name="os_sn" id="os_sn" value="<?= e($values['os_sn']) ?>">

                <label for="office">Office</label>
                <select name="office" id="office">
                    <option value="">—</option>
                    <?php foreach (AssetController::OFFICE_TYPES as $of): ?>
                        <option value="<?= e($of) ?>" <?= $values['office'] === $of ? 'selected' : '' ?>><?= e($of) ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="office_sn">Office sériové číslo</label>
                <input type="text" name="office_sn" id="office_sn" value="<?= e($values['office_sn']) ?>">

                <label for="note">Poznámka</label>
                <textarea name="note" id="note" rows="4"><?= e($values['note']) ?></textarea>

                <?php foreach ($customFields as $cf): $key = 'cf_' . $cf['id']; $val = $customValues[(int)$cf['id']] ?? ''; ?>
                    <?php if ($cf['type'] === 'bool'): ?>
                        <label class="checkbox-row" style="margin-top: 0.9rem">
                            <input type="checkbox" name="<?= e($key) ?>" <?= $val === '1' ? 'checked' : '' ?>> <?= e($cf['name']) ?>
                        </label>
                    <?php else: ?>
                        <label for="<?= e($key) ?>"><?= e($cf['name']) ?></label>
                        <?php if ($cf['type'] === 'select'): ?>
                            <select name="<?= e($key) ?>" id="<?= e($key) ?>">
                                <option value="">—</option>
                                <?php foreach ($cf['options_list'] as $opt): ?>
                                    <option value="<?= e($opt) ?>" <?= $val === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif ($cf['type'] === 'date'): ?>
                            <input type="date" name="<?= e($key) ?>" id="<?= e($key) ?>" value="<?= e($val) ?>">
                        <?php elseif ($cf['type'] === 'number'): ?>
                            <input type="text" name="<?= e($key) ?>" id="<?= e($key) ?>" value="<?= e($val) ?>" inputmode="decimal">
                        <?php else: ?>
                            <input type="text" name="<?= e($key) ?>" id="<?= e($key) ?>" value="<?= e($val) ?>">
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Uložit</button>
            <a class="btn btn-ghost" href="<?= e($asset !== null ? url('/majetek/' . $asset['id']) : url('/majetek')) ?>">Zrušit</a>
        </div>
    </form>
</div>
