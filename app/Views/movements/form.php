<?php
use App\Controllers\AssetController;
use App\Core\Csrf;
?>
<h1><?= e($title) ?></h1>

<?php foreach ($errors as $err): ?>
    <div class="banner banner-error"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="card form-card form-wide">
    <?php if ($eligible === []): ?>
        <p class="muted">Žádný majetek ve stavu umožňujícím tuto akci.</p>
    <?php else: ?>
    <form method="post">
        <?= Csrf::field() ?>

        <label>Majetek * <span class="muted">(<span id="mv-count">0</span> vybráno)</span></label>
        <div class="asset-picker">
            <input type="text" id="mv-filter" placeholder="Filtrovat seznam…" oninput="mvFilter(this.value)">
            <div class="asset-picker-list" id="mv-list">
                <?php foreach ($eligible as $a): ?>
                    <label class="asset-picker-item" data-text="<?= e(mb_strtolower($a['tag_id'] . ' ' . $a['description'] . ' ' . ($a['person_name'] ?? ''))) ?>">
                        <input type="checkbox" name="asset_ids[]" value="<?= (int)$a['id'] ?>" <?= in_array((int)$a['id'], $preselected, true) ? 'checked' : '' ?> onchange="mvCount()">
                        <code><?= e($a['tag_id']) ?></code> <?= e($a['description']) ?>
                        <span class="muted"><?= e(AssetController::STATUSES[$a['status']] ?? $a['status']) ?><?= $a['person_name'] ? ' · ' . e($a['person_name']) : '' ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="form-grid">
            <div>
                <?php if ($cfg['person'] !== 'none'): ?>
                    <label for="person_id">Zaměstnanec <?= $cfg['person'] === 'required' ? '*' : '(volitelné)' ?></label>
                    <select name="person_id" id="person_id" <?= $cfg['person'] === 'required' ? 'required' : '' ?>>
                        <option value="">—</option>
                        <?php foreach ($persons as $p): ?>
                            <option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>

                <?php if ($action === 'presun'): ?>
                    <label for="new_location_id">Nová lokace</label>
                    <select name="new_location_id" id="new_location_id">
                        <option value="">— beze změny —</option>
                        <?php foreach ($locations as $l): ?>
                            <option value="<?= (int)$l['id'] ?>"><?= e($l['name']) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label for="new_department_id">Nové oddělení</label>
                    <select name="new_department_id" id="new_department_id">
                        <option value="">— beze změny —</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>
            <div>
                <label for="event_date">Datum *</label>
                <input type="date" name="event_date" id="event_date" value="<?= e(date('Y-m-d')) ?>" required>

                <?php if ($cfg['due']): ?>
                    <label for="due_date"><?= $action === 'rezervace' ? 'Rezervováno do' : 'Termín vrácení' ?> <span class="muted">(volitelné)</span></label>
                    <input type="date" name="due_date" id="due_date">
                <?php endif; ?>

                <label for="note">Poznámka</label>
                <textarea name="note" id="note" rows="2"></textarea>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= e($cfg['button']) ?></button>
            <a class="btn btn-ghost" href="<?= e(url('/majetek')) ?>">Zrušit</a>
        </div>
    </form>
    <?php endif; ?>
</div>

<script>
function mvCount() {
    document.getElementById('mv-count').textContent =
        document.querySelectorAll('#mv-list input[type="checkbox"]:checked').length;
}
function mvFilter(value) {
    value = value.toLowerCase();
    document.querySelectorAll('#mv-list .asset-picker-item').forEach(function (item) {
        item.style.display = item.dataset.text.indexOf(value) === -1 ? 'none' : '';
    });
}
mvCount();
</script>
