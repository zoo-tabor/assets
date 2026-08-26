<?php
use App\Controllers\AssetController;

$eventLabels = [
    'create' => 'Založení', 'edit' => 'Úprava', 'checkout' => 'Výdej', 'checkin' => 'Vrácení',
    'move' => 'Přesun', 'dispose' => 'Vyřazení', 'reserve' => 'Rezervace', 'unreserve' => 'Zrušení rezervace',
    'maintenance' => 'Údržba', 'audit' => 'Inventura',
];
?>
<div class="page-head">
    <h1><code><?= e($asset['tag_id']) ?></code> — <?= e($asset['description']) ?></h1>
    <div class="page-head-actions">
        <?php $ids = '?ids=' . (int)$asset['id']; ?>
        <?php if (in_array($asset['status'], ['available', 'reserved'], true)): ?>
            <a class="btn btn-ghost" href="<?= e(url('/vydej') . $ids) ?>">Vydat</a>
        <?php endif; ?>
        <?php if (in_array($asset['status'], ['assigned', 'reserved'], true)): ?>
            <a class="btn btn-ghost" href="<?= e(url('/vraceni') . $ids) ?>"><?= $asset['status'] === 'reserved' ? 'Zrušit rezervaci' : 'Vrátit' ?></a>
        <?php endif; ?>
        <?php if ($asset['status'] === 'available'): ?>
            <a class="btn btn-ghost" href="<?= e(url('/rezervace') . $ids) ?>">Rezervovat</a>
        <?php endif; ?>
        <?php if ($asset['status'] !== 'disposed'): ?>
            <a class="btn btn-ghost" href="<?= e(url('/presun') . $ids) ?>">Přesunout</a>
            <a class="btn btn-ghost" href="<?= e(url('/vyrazeni') . $ids) ?>">Vyřadit</a>
        <?php endif; ?>
        <a class="btn btn-primary" href="<?= e(url('/majetek/' . $asset['id'] . '/upravit')) ?>">Upravit</a>
        <a class="btn btn-ghost" href="<?= e(url('/majetek')) ?>">Zpět</a>
    </div>
</div>

<div class="detail-grid">
    <div class="card">
        <h2>Detaily</h2>
        <table class="table table-detail">
            <tr><th>Stav</th><td><span class="status status-<?= e($asset['status']) ?>"><?= e(AssetController::STATUSES[$asset['status']] ?? $asset['status']) ?></span></td></tr>
            <tr><th>Značka</th><td><?= e($asset['brand'] ?? '—') ?></td></tr>
            <tr><th>Model</th><td><?= e($asset['model'] ?? '—') ?></td></tr>
            <tr><th>Sériové číslo</th><td><?= e($asset['serial_no'] ?? '—') ?></td></tr>
            <tr><th>Datum nákupu</th><td><?= e(format_date($asset['purchase_date'])) ?></td></tr>
            <tr><th>Cena</th><td><?= e(format_money($asset['cost'])) ?></td></tr>
            <tr><th>Dodavatel</th><td><?= e($asset['purchased_from'] ?? '—') ?></td></tr>
        </table>
    </div>

    <div class="card">
        <h2>Zařazení a IT</h2>
        <table class="table table-detail">
            <?php
            $db = App\Core\Db::instance();
            $dialName = fn(?string $id, string $table) => $id ? ($db->scalar("SELECT name FROM {$table} WHERE id = ?", [(int)$id]) ?? '—') : '—';
            ?>
            <tr><th>Kategorie</th><td><?= e($dialName($asset['category_id'], 'categories')) ?></td></tr>
            <tr><th>Lokace</th><td><?= e($dialName($asset['location_id'], 'locations')) ?></td></tr>
            <tr><th>Oddělení</th><td><?= e($dialName($asset['department_id'], 'departments')) ?></td></tr>
            <tr><th>Přiděleno</th><td><?= e($asset['assigned_person_id'] ? ($db->scalar('SELECT name FROM persons WHERE id = ?', [(int)$asset['assigned_person_id']]) ?? '—') : '—') ?></td></tr>
            <tr><th>OS</th><td><?= e($asset['os_type'] ?? '—') ?><?= $asset['os_sn'] ? ' <span class="muted">SN: ' . e($asset['os_sn']) . '</span>' : '' ?></td></tr>
            <tr><th>Office</th><td><?= e($asset['office'] ?? '—') ?><?= $asset['office_sn'] ? ' <span class="muted">SN: ' . e($asset['office_sn']) . '</span>' : '' ?></td></tr>
            <tr><th>Poznámka</th><td><?= nl2br(e($asset['note'] ?? '—')) ?></td></tr>
            <?php foreach ($customFields as $cf): ?>
                <tr><th><?= e($cf['name']) ?></th><td><?= e(App\Core\CustomFields::display($cf, $customValues[(int)$cf['id']] ?? null)) ?></td></tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>

<div class="detail-grid">
    <div class="card">
        <h2>Fotky</h2>
        <?php if ($photos !== []): ?>
            <div class="photo-grid">
                <?php foreach ($photos as $ph): ?>
                    <div class="photo-item">
                        <a href="<?= e(url('/soubor/foto/' . $ph['id'])) ?>" target="_blank">
                            <img src="<?= e(url('/soubor/foto/' . $ph['id']) . '?nahled=1') ?>" alt="" loading="lazy">
                        </a>
                        <div class="photo-actions">
                            <?php if ((int)$ph['is_primary'] === 1): ?>
                                <span class="muted" title="Hlavní fotka">★</span>
                            <?php else: ?>
                                <form method="post" action="<?= e(url('/majetek/' . $asset['id'] . '/fotky/' . $ph['id'] . '/hlavni')) ?>">
                                    <?= App\Core\Csrf::field() ?>
                                    <button type="submit" class="btn btn-ghost btn-sm" title="Nastavit jako hlavní">☆</button>
                                </form>
                            <?php endif; ?>
                            <form method="post" action="<?= e(url('/majetek/' . $asset['id'] . '/fotky/' . $ph['id'] . '/smazat')) ?>"
                                  onsubmit="return confirm('Smazat fotku?')">
                                <?= App\Core\Csrf::field() ?>
                                <button type="submit" class="btn btn-ghost btn-sm" title="Smazat">🗑</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="muted">Žádné fotky.</p>
        <?php endif; ?>
        <form method="post" action="<?= e(url('/majetek/' . $asset['id'] . '/fotky')) ?>" enctype="multipart/form-data" class="dial-add">
            <?= App\Core\Csrf::field() ?>
            <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple required>
            <button type="submit" class="btn btn-primary btn-sm">Nahrát</button>
        </form>
    </div>

    <div class="card">
        <h2>Dokumenty</h2>
        <?php if ($documents !== []): ?>
            <table class="table">
                <tbody>
                <?php foreach ($documents as $doc): ?>
                    <tr>
                        <td><a href="<?= e(url('/soubor/dokument/' . $doc['id'])) ?>"><?= e($doc['original_name']) ?></a></td>
                        <td class="muted"><?= e(format_date($doc['uploaded_at'], true)) ?> · <?= e($doc['user_name'] ?? '—') ?></td>
                        <td class="row-actions">
                            <form method="post" action="<?= e(url('/majetek/' . $asset['id'] . '/dokumenty/' . $doc['id'] . '/smazat')) ?>"
                                  onsubmit="return confirm('Smazat dokument „<?= e($doc['original_name']) ?>“?')">
                                <?= App\Core\Csrf::field() ?>
                                <button type="submit" class="btn btn-ghost btn-sm" title="Smazat">🗑</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="muted">Žádné dokumenty.</p>
        <?php endif; ?>
        <form method="post" action="<?= e(url('/majetek/' . $asset['id'] . '/dokumenty')) ?>" enctype="multipart/form-data" class="dial-add">
            <?= App\Core\Csrf::field() ?>
            <input type="file" name="document" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.odt,.ods,.png,.jpg,.jpeg" required>
            <button type="submit" class="btn btn-primary btn-sm">Nahrát</button>
        </form>
    </div>
</div>

<div class="card">
    <h2>Historie</h2>
    <?php if ($events === []): ?>
        <p class="muted">Žádné události.</p>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>Datum</th><th>Událost</th><th>Osoba</th><th>Termín</th><th>Poznámka</th><th>Provedl</th></tr></thead>
            <tbody>
            <?php foreach ($events as $ev): ?>
                <tr>
                    <td><?= e(format_date($ev['event_date'], true)) ?></td>
                    <td><?= e($eventLabels[$ev['type']] ?? $ev['type']) ?></td>
                    <td><?= e($ev['person_name'] ?? '—') ?></td>
                    <td><?= e(format_date($ev['due_date'])) ?></td>
                    <td><?= e($ev['note'] ?? '—') ?></td>
                    <td><?= e($ev['user_name'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
