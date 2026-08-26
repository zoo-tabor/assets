<?php use App\Core\Csrf; ?>
<h1><?= e($title) ?></h1>

<div class="dials-grid">
<?php foreach ($lists as $type => $list): ?>
    <div class="card">
        <h2><?= e($list['meta']['label']) ?></h2>

        <table class="table">
            <tbody>
            <?php foreach ($list['items'] as $item): ?>
                <tr>
                    <td colspan="2">
                        <form method="post" action="<?= e(url("/nastaveni/ciselniky/{$type}/{$item['id']}/upravit")) ?>" class="dial-row">
                            <?= Csrf::field() ?>
                            <input type="text" name="name" value="<?= e($item['name']) ?>" required>
                            <label class="checkbox-row" title="Aktivní">
                                <input type="checkbox" name="active" <?= $item['active'] ? 'checked' : '' ?>>
                            </label>
                            <button type="submit" class="btn btn-ghost btn-sm" title="Uložit">💾</button>
                        </form>
                    </td>
                    <td class="row-actions">
                        <?php if ((int)$item['usage_assets'] === 0): ?>
                            <form method="post" action="<?= e(url("/nastaveni/ciselniky/{$type}/{$item['id']}/smazat")) ?>"
                                  onsubmit="return confirm('Opravdu smazat „<?= e($item['name']) ?>“?')">
                                <?= Csrf::field() ?>
                                <button type="submit" class="btn btn-ghost btn-sm" title="Smazat">🗑</button>
                            </form>
                        <?php else: ?>
                            <span class="muted" title="Používá se u majetku"><?= (int)$item['usage_assets'] ?>×</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($list['items'] === []): ?>
                <tr><td class="muted">Zatím žádné položky.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>

        <form method="post" action="<?= e(url("/nastaveni/ciselniky/{$type}/pridat")) ?>" class="dial-add">
            <?= Csrf::field() ?>
            <input type="text" name="name" placeholder="Nová položka…" required>
            <button type="submit" class="btn btn-primary btn-sm">Přidat</button>
        </form>
    </div>
<?php endforeach; ?>
</div>
