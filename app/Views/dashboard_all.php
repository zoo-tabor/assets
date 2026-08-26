<h1><?= e($title) ?></h1>

<div class="cards-grid">
    <div class="card stat-card">
        <div class="stat-value"><?= (int)$totalCount ?></div>
        <div class="stat-label">položek majetku celkem</div>
    </div>
    <div class="card stat-card">
        <div class="stat-value"><?= e(format_money($totalCost)) ?></div>
        <div class="stat-label">hodnota celkem</div>
    </div>
</div>

<div class="card">
    <h2>Po organizacích</h2>
    <table class="table">
        <thead><tr><th>Organizace</th><th class="num">Položek</th><th class="num">Hodnota</th></tr></thead>
        <tbody>
        <?php foreach ($perOrg as $row): ?>
            <tr>
                <td><span class="org-dot" style="background: <?= e($row['accent_color']) ?>"></span> <?= e($row['name']) ?></td>
                <td class="num"><?= (int)$row['asset_count'] ?></td>
                <td class="num"><?= e(format_money($row['total_cost'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
