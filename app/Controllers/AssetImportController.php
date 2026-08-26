<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Db;
use App\Core\Image;
use App\Core\Org;
use App\Core\View;
use App\Core\XlsxReader;

/**
 * Import majetku z CSV/XLSX - vlastni format i export z AssetTigeru.
 * Stare Asset Tag ID se uklada do vlastniho pole "Původní tag ID",
 * nove Tag ID se generuje z rady organizace. Volitelne stazeni fotek z URL.
 */
final class AssetImportController
{
    private const OLD_TAG_FIELD = 'Původní tag ID';

    public function form(): void
    {
        Auth::requireLogin();
        $this->requireOrgContext();

        $report = null;
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
            && !empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
            $report = $this->import(
                $_FILES['file']['tmp_name'],
                strtolower(pathinfo((string)$_FILES['file']['name'], PATHINFO_EXTENSION)),
                isset($_POST['download_photos'])
            );
        }

        View::render('assets/import', [
            'title' => 'Import majetku',
            'report' => $report,
        ]);
    }

    private function import(string $tmpFile, string $ext, bool $downloadPhotos): array
    {
        $db = Db::instance();
        $orgId = Org::id();
        $report = ['added' => 0, 'photos' => 0, 'skipped' => [], 'total' => 0];

        try {
            $rows = $ext === 'xlsx' ? XlsxReader::rows($tmpFile) : $this->csvRows($tmpFile);
        } catch (\Throwable $e) {
            $report['skipped'][] = 'Soubor nelze načíst: ' . $e->getMessage();
            return $report;
        }
        if (count($rows) < 2) {
            $report['skipped'][] = 'Soubor neobsahuje žádná data (očekává se hlavička + řádky).';
            return $report;
        }

        $header = array_map(fn($h) => $this->normalize((string)$h), $rows[0]);
        $col = function (array $names) use ($header): ?int {
            foreach ($header as $i => $h) {
                if (in_array($h, $names, true)) {
                    return $i;
                }
            }
            return null;
        };

        $map = [
            'old_tag' => $col(['asset tag id']),
            'tag_id' => $col(['tag id']),
            'description' => $col(['description', 'popis']),
            'brand' => $col(['brand', 'znacka']),
            'model' => $col(['model']),
            'serial_no' => $col(['serial no', 'serial number', 'seriove cislo']),
            'purchase_date' => $col(['purchase date', 'datum nakupu']),
            'cost' => $col(['cost', 'cena']),
            'purchased_from' => $col(['purchased from', 'dodavatel', 'vendor']),
            'location' => $col(['location', 'lokace']),
            'category' => $col(['category', 'kategorie']),
            'department' => $col(['department', 'oddeleni']),
            'assigned' => $col(['assigned to', 'prideleno', 'checked out to']),
            'photo' => $col(['asset photo', 'photo', 'fotka']),
            'os_type' => $col(['os type', 'os']),
            'os_sn' => $col(['os sn']),
            'office' => $col(['office']),
            'office_sn' => $col(['office sn']),
            'note' => $col(['note', 'notes', 'poznamka']),
        ];

        if ($map['description'] === null) {
            $report['skipped'][] = 'V hlavičce chybí sloupec Description/Popis. Nalezená hlavička: ' . implode(' | ', array_filter($header));
            return $report;
        }

        $report['total'] = count($rows) - 1;
        $oldTagFieldId = $map['old_tag'] !== null ? $this->ensureOldTagField($orgId) : null;
        $org = $db->one('SELECT * FROM organizations WHERE id = ?', [$orgId]);
        $nextNumber = (int)$org['tag_next_number'];

        foreach (array_slice($rows, 1) as $i => $row) {
            $line = $i + 2;
            $get = fn(?int $idx) => $idx !== null ? trim((string)($row[$idx] ?? '')) : '';

            $description = $get($map['description']);
            $oldTag = $get($map['old_tag']);
            if ($description === '' && $oldTag === '') {
                continue; // prazdny radek
            }
            if ($description === '') {
                $report['skipped'][] = "Řádek {$line}: prázdný popis.";
                continue;
            }

            // dedup: stare tag ID uz importovano
            if ($oldTag !== '' && $oldTagFieldId !== null) {
                $exists = $db->one(
                    'SELECT 1 FROM asset_custom_values v JOIN assets a ON a.id = v.asset_id
                     WHERE v.custom_field_id = ? AND v.value = ? AND a.organization_id = ?',
                    [$oldTagFieldId, $oldTag, $orgId]
                );
                if ($exists !== null) {
                    $report['skipped'][] = "Řádek {$line}: {$oldTag} už je naimportováno.";
                    continue;
                }
            }

            // tag ID: z naseho sloupce, jinak automaticka rada
            $tagId = $get($map['tag_id']);
            if ($tagId !== '') {
                if ($db->one('SELECT 1 FROM assets WHERE tag_id = ?', [$tagId]) !== null) {
                    $report['skipped'][] = "Řádek {$line}: Tag ID {$tagId} už existuje.";
                    continue;
                }
            } else {
                do {
                    $tagId = sprintf('%s-%07d', $org['tag_prefix'], $nextNumber);
                    $nextNumber++;
                } while ($db->one('SELECT 1 FROM assets WHERE tag_id = ?', [$tagId]) !== null);
            }

            $personId = null;
            $assignedName = $get($map['assigned']);
            if ($assignedName !== '') {
                $personId = $this->dialId($db, 'persons', $orgId, $assignedName, true);
            }

            $db->exec(
                'INSERT INTO assets (organization_id, tag_id, description, brand, model, serial_no, purchase_date,
                    cost, purchased_from, location_id, category_id, department_id, status, assigned_person_id,
                    os_type, os_sn, office, office_sn, note, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                [
                    $orgId, $tagId, $description,
                    $get($map['brand']) ?: null, $get($map['model']) ?: null, $get($map['serial_no']) ?: null,
                    $this->parseDate($get($map['purchase_date'])),
                    $this->parseCost($get($map['cost'])),
                    $get($map['purchased_from']) ?: null,
                    $this->dialId($db, 'locations', $orgId, $get($map['location'])),
                    $this->dialId($db, 'categories', $orgId, $get($map['category'])),
                    $this->dialId($db, 'departments', $orgId, $get($map['department'])),
                    $personId !== null ? 'assigned' : 'available',
                    $personId,
                    $this->mapOs($get($map['os_type'])), $get($map['os_sn']) ?: null,
                    $this->mapOffice($get($map['office'])), $get($map['office_sn']) ?: null,
                    $get($map['note']) ?: null,
                    Auth::id(),
                ]
            );
            $assetId = $db->insertId();

            if ($oldTag !== '' && $oldTagFieldId !== null) {
                $db->exec(
                    'INSERT INTO asset_custom_values (asset_id, custom_field_id, value) VALUES (?, ?, ?)',
                    [$assetId, $oldTagFieldId, $oldTag]
                );
            }

            $db->exec(
                'INSERT INTO asset_events (asset_id, type, user_id, event_date, note) VALUES (?, ?, ?, NOW(), ?)',
                [$assetId, 'import', Auth::id(), 'Import' . ($oldTag !== '' ? ' (AssetTiger ' . $oldTag . ')' : '')]
            );
            if ($personId !== null) {
                $db->exec(
                    'INSERT INTO asset_events (asset_id, type, person_id, user_id, event_date, note) VALUES (?, ?, ?, ?, NOW(), ?)',
                    [$assetId, 'checkout', $personId, Auth::id(), 'Výchozí přidělení převzaté z AssetTigeru']
                );
            }

            $photoUrl = $get($map['photo']);
            if ($downloadPhotos && str_starts_with($photoUrl, 'http')) {
                if ($this->downloadPhoto($photoUrl, $orgId, $assetId)) {
                    $report['photos']++;
                } else {
                    $report['skipped'][] = "Řádek {$line}: fotku se nepodařilo stáhnout ({$tagId}).";
                }
            }

            $report['added']++;
        }

        if ($nextNumber > (int)$org['tag_next_number']) {
            $db->exec('UPDATE organizations SET tag_next_number = ? WHERE id = ?', [$nextNumber, $orgId]);
        }
        return $report;
    }

    private function csvRows(string $file): array
    {
        $raw = (string)file_get_contents($file);
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }
        if (!mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1250');
        }
        $firstLine = strtok($raw, "\n") ?: '';
        $delimiter = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';
        $rows = [];
        $fh = fopen('php://temp', 'r+');
        fwrite($fh, $raw);
        rewind($fh);
        while (($row = fgetcsv($fh, 0, $delimiter, '"', '\\')) !== false) {
            if ($row === [null]) {
                continue;
            }
            $rows[] = $row;
        }
        fclose($fh);
        return $rows;
    }

    private function normalize(string $h): string
    {
        $h = mb_strtolower(trim($h));
        return trim((string)iconv('UTF-8', 'ASCII//TRANSLIT', $h));
    }

    /** "17,000.00" / "17 000,50" / "17000" / excel serial -> float|null */
    private function parseCost(string $value): ?float
    {
        if ($value === '') {
            return null;
        }
        $value = str_replace([' ', "\u{a0}", 'Kč', 'CZK'], '', $value);
        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = str_replace(',', '', $value); // 17,000.00
        } elseif (preg_match('/,\d{3}(\D|$)/', $value)) {
            $value = str_replace(',', '', $value); // 17,000
        } else {
            $value = str_replace(',', '.', $value); // 17000,50
        }
        return is_numeric($value) ? (float)$value : null;
    }

    /** Y-m-d / d.m.Y / m/d/Y / excel serial -> Y-m-d|null */
    private function parseDate(string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            return substr($value, 0, 10);
        }
        if (preg_match('/^(\d{1,2})\.\s*(\d{1,2})\.\s*(\d{4})$/', $value, $m)) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $value, $m)) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[1], $m[2]); // US m/d/Y (AssetTiger)
        }
        if (is_numeric($value) && (float)$value > 20000 && (float)$value < 80000) {
            return XlsxReader::excelDate((float)$value);
        }
        return null;
    }

    private function mapOs(string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        foreach (AssetController::OS_TYPES as $os) {
            if (mb_strtolower($os) === mb_strtolower($value)) {
                return $os;
            }
        }
        return null;
    }

    private function mapOffice(string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        foreach (AssetController::OFFICE_TYPES as $of) {
            if (str_contains(mb_strtolower($of), mb_strtolower($value)) || str_contains(mb_strtolower($value), mb_strtolower($of))) {
                return $of;
            }
        }
        return null;
    }

    /** Najde/zalozi polozku ciselniku ci osobu podle nazvu */
    private function dialId(Db $db, string $table, int $orgId, string $name, bool $isPerson = false): ?int
    {
        if ($name === '') {
            return null;
        }
        $row = $db->one("SELECT id FROM {$table} WHERE organization_id = ? AND name = ?", [$orgId, $name]);
        if ($row !== null) {
            return (int)$row['id'];
        }
        $db->exec("INSERT INTO {$table} (organization_id, name, active) VALUES (?, ?, 1)", [$orgId, $name]);
        return $db->insertId();
    }

    private function ensureOldTagField(int $orgId): int
    {
        $db = Db::instance();
        $row = $db->one('SELECT id FROM custom_fields WHERE organization_id = ? AND name = ?', [$orgId, self::OLD_TAG_FIELD]);
        if ($row !== null) {
            return (int)$row['id'];
        }
        $db->exec(
            'INSERT INTO custom_fields (organization_id, name, type, sort, active) VALUES (?, ?, ?, 99, 1)',
            [$orgId, self::OLD_TAG_FIELD, 'text']
        );
        return $db->insertId();
    }

    /** Stazeni fotky z URL + zmenseni + zapis do DB */
    private function downloadPhoto(string $url, int $orgId, int $assetId): bool
    {
        $tmp = tempnam(sys_get_temp_dir(), 'imp');
        $ok = false;
        try {
            if (function_exists('curl_init')) {
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => false,
                    CURLOPT_FILE => $fp = fopen($tmp, 'w'),
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_TIMEOUT => 15,
                    CURLOPT_USERAGENT => 'Mozilla/5.0 (EvidenceMajetku import)',
                ]);
                $ok = curl_exec($ch) === true && (int)curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200;
                curl_close($ch);
                fclose($fp);
            } else {
                $ctx = stream_context_create(['http' => ['timeout' => 15, 'user_agent' => 'Mozilla/5.0']]);
                $data = @file_get_contents($url, false, $ctx);
                $ok = $data !== false && @file_put_contents($tmp, $data) !== false;
            }
            if (!$ok || filesize($tmp) < 100) {
                return false;
            }
            $dir = DATA_PATH . '/photos/' . $orgId . '/' . $assetId;
            if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
                return false;
            }
            $name = 'p' . time() . '-' . bin2hex(random_bytes(6)) . '.jpg';
            if (!Image::resize($tmp, $dir . '/' . $name, 1600)) {
                return false;
            }
            Image::resize($tmp, $dir . '/t-' . $name, 320);
            Db::instance()->exec(
                'INSERT INTO asset_photos (asset_id, filename, is_primary) VALUES (?, ?, 1)',
                [$assetId, $name]
            );
            return true;
        } finally {
            @unlink($tmp);
        }
    }

    private function requireOrgContext(): void
    {
        if (Org::isAll()) {
            flash('error', 'Pro import přepněte na konkrétní organizaci.');
            redirect('/majetek');
        }
    }
}
