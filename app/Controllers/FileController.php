<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Db;

/**
 * Chraneny vydej uploadovanych souboru z /data (primy pristup blokuje .htaccess).
 */
final class FileController
{
    public function logo(string $id): void
    {
        Auth::requireLogin();
        $org = Db::instance()->one('SELECT logo_file FROM organizations WHERE id = ?', [(int)$id]);
        if ($org === null || empty($org['logo_file'])) {
            http_response_code(404);
            exit;
        }
        $file = DATA_PATH . '/logos/' . basename((string)$org['logo_file']);
        $this->serve($file);
    }

    private function serve(string $file): never
    {
        if (!is_file($file)) {
            http_response_code(404);
            exit;
        }
        $mime = function_exists('mime_content_type') ? (mime_content_type($file) ?: 'application/octet-stream') : 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string)filesize($file));
        header('Cache-Control: private, max-age=86400');
        readfile($file);
        exit;
    }
}
