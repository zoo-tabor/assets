<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Db;
use App\Core\Image;
use App\Core\Org;

/**
 * Fotky a dokumenty majetku + chraneny vydej souboru z /data.
 */
final class AssetFileController
{
    private const DOC_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'odt', 'ods', 'png', 'jpg', 'jpeg'];
    private const MAX_DOC_SIZE = 20 * 1024 * 1024;

    /** POST /majetek/{id}/fotky - upload jedne ci vice fotek */
    public function uploadPhotos(string $id): void
    {
        $asset = $this->assetForWrite((int)$id);
        $db = Db::instance();
        $dir = DATA_PATH . '/photos/' . $asset['organization_id'] . '/' . $asset['id'];
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            flash('error', 'Nelze vytvořit adresář pro fotky.');
            redirect('/majetek/' . $asset['id']);
        }

        $count = 0;
        foreach ($this->normalizeFiles($_FILES['photos'] ?? []) as $file) {
            if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
                continue;
            }
            $name = 'p' . time() . '-' . bin2hex(random_bytes(6)) . '.jpg';
            if (!Image::resize($file['tmp_name'], $dir . '/' . $name, 1600)) {
                flash('error', 'Soubor „' . $file['name'] . '“ není podporovaný obrázek (JPEG/PNG/WebP).');
                continue;
            }
            Image::resize($file['tmp_name'], $dir . '/t-' . $name, 320);
            $isPrimary = (int)$db->scalar('SELECT COUNT(*) FROM asset_photos WHERE asset_id = ?', [$asset['id']]) === 0 ? 1 : 0;
            $db->exec('INSERT INTO asset_photos (asset_id, filename, is_primary) VALUES (?, ?, ?)', [$asset['id'], $name, $isPrimary]);
            $count++;
        }
        if ($count > 0) {
            flash('success', "Nahráno fotek: {$count}.");
        }
        redirect('/majetek/' . $asset['id']);
    }

    /** POST /majetek/{id}/fotky/{photoId}/smazat */
    public function deletePhoto(string $id, string $photoId): void
    {
        $asset = $this->assetForWrite((int)$id);
        $db = Db::instance();
        $photo = $db->one('SELECT * FROM asset_photos WHERE id = ? AND asset_id = ?', [(int)$photoId, $asset['id']]);
        if ($photo !== null) {
            $dir = DATA_PATH . '/photos/' . $asset['organization_id'] . '/' . $asset['id'];
            @unlink($dir . '/' . $photo['filename']);
            @unlink($dir . '/t-' . $photo['filename']);
            $db->exec('DELETE FROM asset_photos WHERE id = ?', [$photo['id']]);
            if ((int)$photo['is_primary'] === 1) {
                $db->exec('UPDATE asset_photos SET is_primary = 1 WHERE asset_id = ? ORDER BY id LIMIT 1', [$asset['id']]);
            }
            flash('success', 'Fotka smazána.');
        }
        redirect('/majetek/' . $asset['id']);
    }

    /** POST /majetek/{id}/fotky/{photoId}/hlavni */
    public function setPrimaryPhoto(string $id, string $photoId): void
    {
        $asset = $this->assetForWrite((int)$id);
        $db = Db::instance();
        $db->exec('UPDATE asset_photos SET is_primary = 0 WHERE asset_id = ?', [$asset['id']]);
        $db->exec('UPDATE asset_photos SET is_primary = 1 WHERE id = ? AND asset_id = ?', [(int)$photoId, $asset['id']]);
        redirect('/majetek/' . $asset['id']);
    }

    /** POST /majetek/{id}/dokumenty */
    public function uploadDocument(string $id): void
    {
        $asset = $this->assetForWrite((int)$id);
        $db = Db::instance();
        $file = $_FILES['document'] ?? null;
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            flash('error', 'Vyberte soubor.');
            redirect('/majetek/' . $asset['id']);
        }
        if ($file['size'] > self::MAX_DOC_SIZE) {
            flash('error', 'Dokument je příliš velký (max 20 MB).');
            redirect('/majetek/' . $asset['id']);
        }
        $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::DOC_EXTENSIONS, true)) {
            flash('error', 'Nepodporovaný typ souboru (' . e($ext) . ').');
            redirect('/majetek/' . $asset['id']);
        }
        $dir = DATA_PATH . '/docs/' . $asset['organization_id'] . '/' . $asset['id'];
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            flash('error', 'Nelze vytvořit adresář pro dokumenty.');
            redirect('/majetek/' . $asset['id']);
        }
        $name = 'd' . time() . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
            flash('error', 'Uložení dokumentu selhalo.');
            redirect('/majetek/' . $asset['id']);
        }
        $db->exec(
            'INSERT INTO asset_documents (asset_id, filename, original_name, uploaded_by, uploaded_at) VALUES (?, ?, ?, ?, NOW())',
            [$asset['id'], $name, (string)$file['name'], Auth::id()]
        );
        flash('success', 'Dokument nahrán.');
        redirect('/majetek/' . $asset['id']);
    }

    /** POST /majetek/{id}/dokumenty/{docId}/smazat */
    public function deleteDocument(string $id, string $docId): void
    {
        $asset = $this->assetForWrite((int)$id);
        $db = Db::instance();
        $doc = $db->one('SELECT * FROM asset_documents WHERE id = ? AND asset_id = ?', [(int)$docId, $asset['id']]);
        if ($doc !== null) {
            @unlink(DATA_PATH . '/docs/' . $asset['organization_id'] . '/' . $asset['id'] . '/' . $doc['filename']);
            $db->exec('DELETE FROM asset_documents WHERE id = ?', [$doc['id']]);
            flash('success', 'Dokument smazán.');
        }
        redirect('/majetek/' . $asset['id']);
    }

    /** GET /soubor/foto/{photoId}?nahled=1 */
    public function servePhoto(string $photoId): void
    {
        Auth::requireLogin();
        $db = Db::instance();
        $photo = $db->one(
            'SELECT ap.*, a.organization_id, a.id AS asset_id FROM asset_photos ap JOIN assets a ON a.id = ap.asset_id WHERE ap.id = ?',
            [(int)$photoId]
        );
        if ($photo === null) {
            http_response_code(404);
            exit;
        }
        $prefix = (($_GET['nahled'] ?? '') === '1') ? 't-' : '';
        $file = DATA_PATH . '/photos/' . $photo['organization_id'] . '/' . $photo['asset_id'] . '/' . $prefix . $photo['filename'];
        if (!is_file($file)) {
            $file = DATA_PATH . '/photos/' . $photo['organization_id'] . '/' . $photo['asset_id'] . '/' . $photo['filename'];
        }
        $this->serve($file, 'image/jpeg');
    }

    /** GET /soubor/dokument/{docId} */
    public function serveDocument(string $docId): void
    {
        Auth::requireLogin();
        $db = Db::instance();
        $doc = $db->one(
            'SELECT d.*, a.organization_id, a.id AS asset_id FROM asset_documents d JOIN assets a ON a.id = d.asset_id WHERE d.id = ?',
            [(int)$docId]
        );
        if ($doc === null) {
            http_response_code(404);
            exit;
        }
        $file = DATA_PATH . '/docs/' . $doc['organization_id'] . '/' . $doc['asset_id'] . '/' . $doc['filename'];
        $mime = function_exists('mime_content_type') && is_file($file)
            ? (mime_content_type($file) ?: 'application/octet-stream')
            : 'application/octet-stream';
        header('Content-Disposition: attachment; filename="' . rawurlencode((string)$doc['original_name']) . '"');
        $this->serve($file, $mime);
    }

    private function serve(string $file, string $mime): never
    {
        if (!is_file($file)) {
            http_response_code(404);
            exit;
        }
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string)filesize($file));
        header('Cache-Control: private, max-age=86400');
        readfile($file);
        exit;
    }

    /** Majetek pro zapis - vyzaduje login a kontext organizace majetku */
    private function assetForWrite(int $id): array
    {
        Auth::requireLogin();
        $asset = Db::instance()->one('SELECT * FROM assets WHERE id = ?', [$id]);
        if ($asset === null) {
            http_response_code(404);
            echo 'Majetek nenalezen.';
            exit;
        }
        if (Org::isAll() || (int)$asset['organization_id'] !== Org::id()) {
            Org::switch((string)$asset['organization_id']);
        }
        return $asset;
    }

    /** $_FILES pole s multiple -> seznam souboru */
    private function normalizeFiles(array $files): array
    {
        if ($files === [] || !isset($files['name'])) {
            return [];
        }
        if (!is_array($files['name'])) {
            return [$files];
        }
        $out = [];
        foreach ($files['name'] as $i => $name) {
            $out[] = [
                'name' => $name,
                'tmp_name' => $files['tmp_name'][$i] ?? '',
                'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size' => $files['size'][$i] ?? 0,
            ];
        }
        return $out;
    }
}
