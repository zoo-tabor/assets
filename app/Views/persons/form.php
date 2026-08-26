<?php use App\Core\Csrf; ?>
<h1><?= e($title) ?></h1>

<?php foreach ($errors as $err): ?>
    <div class="banner banner-error"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="card form-card">
    <form method="post">
        <?= Csrf::field() ?>

        <label for="name">Jméno *</label>
        <input type="text" name="name" id="name" value="<?= e($values['name']) ?>" required>

        <label for="employee_id">Osobní číslo</label>
        <input type="text" name="employee_id" id="employee_id" value="<?= e($values['employee_id']) ?>">

        <label for="title">Pozice</label>
        <input type="text" name="title" id="title" value="<?= e($values['title']) ?>">

        <label for="email">E-mail</label>
        <input type="email" name="email" id="email" value="<?= e($values['email']) ?>">

        <label for="phone">Telefon</label>
        <input type="text" name="phone" id="phone" value="<?= e($values['phone']) ?>">

        <label for="location_id">Lokace</label>
        <select name="location_id" id="location_id">
            <option value="">—</option>
            <?php foreach ($locations as $l): ?>
                <option value="<?= (int)$l['id'] ?>" <?= (int)($values['location_id'] ?? 0) === (int)$l['id'] ? 'selected' : '' ?>><?= e($l['name']) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="department_id">Oddělení</label>
        <select name="department_id" id="department_id">
            <option value="">—</option>
            <?php foreach ($departments as $d): ?>
                <option value="<?= (int)$d['id'] ?>" <?= (int)($values['department_id'] ?? 0) === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="notes">Poznámka</label>
        <textarea name="notes" id="notes" rows="3"><?= e($values['notes']) ?></textarea>

        <label class="checkbox-row">
            <input type="checkbox" name="active" <?= $values['active'] ? 'checked' : '' ?>> Aktivní
        </label>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Uložit</button>
            <a class="btn btn-ghost" href="<?= e(url('/zamestnanci')) ?>">Zrušit</a>
        </div>
    </form>
</div>
