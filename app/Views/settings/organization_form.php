<?php use App\Core\Csrf; ?>
<h1><?= e($title) ?></h1>

<?php foreach ($errors as $err): ?>
    <div class="banner banner-error"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="card form-card">
    <form method="post" enctype="multipart/form-data">
        <?= Csrf::field() ?>

        <label for="name">Název *</label>
        <input type="text" name="name" id="name" value="<?= e($values['name']) ?>" required>

        <label for="accent_color">Akcentní barva *</label>
        <div class="color-row">
            <input type="color" id="accent_color_picker" value="<?= e($values['accent_color']) ?>"
                   oninput="document.getElementById('accent_color').value = this.value">
            <input type="text" name="accent_color" id="accent_color" value="<?= e($values['accent_color']) ?>"
                   pattern="#[0-9a-fA-F]{6}" required>
        </div>

        <label for="tag_prefix">Prefix Tag ID * <span class="muted">(např. EKOSPOL → EKOSPOL-0000001)</span></label>
        <input type="text" name="tag_prefix" id="tag_prefix" value="<?= e($values['tag_prefix']) ?>" required>

        <label for="tag_next_number">Další číslo v řadě</label>
        <input type="number" name="tag_next_number" id="tag_next_number" min="1" value="<?= e($values['tag_next_number']) ?>">

        <label for="logo">Logo <span class="muted">(PNG/JPG/SVG/WebP, max 2 MB)</span></label>
        <?php if (!empty($values['logo_file']) && $org !== null): ?>
            <p><img src="<?= e(url('/soubor/logo/' . $org['id'])) ?>" alt="logo" style="max-height: 48px"></p>
        <?php endif; ?>
        <input type="file" name="logo" id="logo" accept=".png,.jpg,.jpeg,.svg,.webp">

        <label class="checkbox-row">
            <input type="checkbox" name="active" <?= $values['active'] ? 'checked' : '' ?>> Aktivní
        </label>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Uložit</button>
            <a class="btn btn-ghost" href="<?= e(url('/nastaveni/organizace')) ?>">Zrušit</a>
        </div>
    </form>
</div>
