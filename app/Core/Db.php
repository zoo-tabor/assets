<?php
declare(strict_types=1);

namespace App\Core;

/**
 * DB wrapper - jedine misto pristupu k databazi.
 * Preferuje PDO, fallback na mysqli (PHP 8.2+ execute_query).
 * Vsude prepared statements s ? placeholdery.
 */
final class Db
{
    private static ?Db $instance = null;

    private ?\PDO $pdo = null;
    private ?\mysqli $mysqli = null;

    private function __construct()
    {
        $host = Env::require('DB_HOST');
        $name = Env::require('DB_NAME');
        $user = Env::require('DB_USER');
        $pass = Env::require('DB_PASS');
        $charset = Env::get('DB_CHARSET', 'utf8mb4');

        if (extension_loaded('pdo_mysql')) {
            $this->pdo = new \PDO(
                "mysql:host={$host};dbname={$name};charset={$charset}",
                $user,
                $pass,
                [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } elseif (extension_loaded('mysqli')) {
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            $this->mysqli = new \mysqli($host, $user, $pass, $name);
            $this->mysqli->set_charset($charset);
        } else {
            throw new \RuntimeException('Na serveru není dostupné pdo_mysql ani mysqli.');
        }
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    /** SELECT -> vsechny radky */
    public function all(string $sql, array $params = []): array
    {
        if ($this->pdo !== null) {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        }
        $result = $this->mysqli->execute_query($sql, $params);
        return $result instanceof \mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    /** SELECT -> prvni radek nebo null */
    public function one(string $sql, array $params = []): ?array
    {
        $rows = $this->all($sql, $params);
        return $rows[0] ?? null;
    }

    /** SELECT -> jedina hodnota (prvni sloupec prvniho radku) */
    public function scalar(string $sql, array $params = []): mixed
    {
        $row = $this->one($sql, $params);
        return $row === null ? null : array_values($row)[0];
    }

    /** INSERT/UPDATE/DELETE/DDL -> pocet ovlivnenych radku */
    public function exec(string $sql, array $params = []): int
    {
        if ($this->pdo !== null) {
            if ($params === []) {
                return (int)$this->pdo->exec($sql);
            }
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        }
        $this->mysqli->execute_query($sql, $params);
        return max(0, (int)$this->mysqli->affected_rows);
    }

    public function insertId(): int
    {
        if ($this->pdo !== null) {
            return (int)$this->pdo->lastInsertId();
        }
        return (int)$this->mysqli->insert_id;
    }

    public function begin(): void
    {
        $this->pdo !== null ? $this->pdo->beginTransaction() : $this->mysqli->begin_transaction();
    }

    public function commit(): void
    {
        $this->pdo !== null ? $this->pdo->commit() : $this->mysqli->commit();
    }

    public function rollback(): void
    {
        try {
            $this->pdo !== null ? $this->pdo->rollBack() : $this->mysqli->rollback();
        } catch (\Throwable) {
            // transakce uz nemusi existovat (implicitni commit po DDL)
        }
    }

    /** Existuje tabulka? (pro setup rezim a banner migraci) */
    public function tableExists(string $table): bool
    {
        try {
            $row = $this->one('SHOW TABLES LIKE ?', [$table]);
            return $row !== null;
        } catch (\Throwable) {
            return false;
        }
    }
}
