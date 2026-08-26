<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Webovy spoustec SQL migraci (na Wedosu neni shell).
 * Soubory /migrations/NNN_nazev.sql, evidence v tabulce `migrations`.
 */
final class Migrator
{
    public static function ensureTable(): void
    {
        Db::instance()->exec(
            'CREATE TABLE IF NOT EXISTS migrations (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                filename VARCHAR(255) NOT NULL UNIQUE,
                applied_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci'
        );
    }

    /** @return string[] vsechny migracni soubory (serazene) */
    public static function allFiles(): array
    {
        $files = glob(BASE_PATH . '/migrations/*.sql') ?: [];
        $names = array_map('basename', $files);
        sort($names);
        return $names;
    }

    /** @return string[] jeste neaplikovane migrace */
    public static function pending(): array
    {
        self::ensureTable();
        $applied = array_column(Db::instance()->all('SELECT filename FROM migrations'), 'filename');
        return array_values(array_diff(self::allFiles(), $applied));
    }

    /**
     * Aplikuje vsechny cekajici migrace.
     * @return array{applied:string[],error:?string}
     */
    public static function run(): array
    {
        $db = Db::instance();
        $applied = [];
        foreach (self::pending() as $filename) {
            $sql = (string)file_get_contents(BASE_PATH . '/migrations/' . $filename);
            try {
                foreach (self::splitStatements($sql) as $statement) {
                    $db->exec($statement);
                }
                $db->exec('INSERT INTO migrations (filename, applied_at) VALUES (?, NOW())', [$filename]);
                $applied[] = $filename;
            } catch (\Throwable $e) {
                return ['applied' => $applied, 'error' => "Migrace {$filename} selhala: " . $e->getMessage()];
            }
        }
        return ['applied' => $applied, 'error' => null];
    }

    /**
     * Rozdeleni SQL souboru na jednotlive prikazy.
     * Migrace pisime bez semicolonu uvnitr stringu - staci delit na ';' na konci radku.
     * @return string[]
     */
    private static function splitStatements(string $sql): array
    {
        // odstraneni radkovych komentaru
        $lines = array_filter(
            explode("\n", $sql),
            fn(string $l): bool => !str_starts_with(ltrim($l), '--')
        );
        $statements = [];
        foreach (preg_split('/;\s*(\r?\n|$)/', implode("\n", $lines)) ?: [] as $part) {
            $part = trim($part);
            if ($part !== '') {
                $statements[] = $part;
            }
        }
        return $statements;
    }

    /** Setup rezim: users tabulka neexistuje nebo je prazdna -> migrator bez prihlaseni */
    public static function isSetupMode(): bool
    {
        $db = Db::instance();
        if (!$db->tableExists('users')) {
            return true;
        }
        try {
            return (int)$db->scalar('SELECT COUNT(*) FROM users') === 0;
        } catch (\Throwable) {
            return true;
        }
    }

    /** Po migraci: zalozeni vychozich organizaci a prvniho admina (jen kdyz nic neexistuje) */
    public static function seedDefaults(): array
    {
        $db = Db::instance();
        $log = [];

        if ((int)$db->scalar('SELECT COUNT(*) FROM organizations') === 0) {
            $db->exec(
                "INSERT INTO organizations (name, accent_color, tag_prefix, tag_next_number, active) VALUES
                 ('EKOSPOL', '#1e7e34', 'EKOSPOL', 1, 1),
                 ('ZOO Tábor', '#e8630a', 'ZOOTABOR', 1, 1)"
            );
            $log[] = 'Založeny organizace EKOSPOL a ZOO Tábor.';
        }

        if ((int)$db->scalar('SELECT COUNT(*) FROM users') === 0) {
            $db->exec(
                'INSERT INTO users (name, email, password_hash, role, active, created_at) VALUES (?, ?, ?, ?, 1, NOW())',
                ['admin', 'admin@ekospol.cz', password_hash('admin123', PASSWORD_DEFAULT), 'admin']
            );
            $log[] = 'Založen uživatel „admin“ s výchozím heslem — po přihlášení jej změňte.';
        }

        return $log;
    }
}
