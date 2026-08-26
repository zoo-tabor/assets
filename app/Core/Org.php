<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Kontext aktivni organizace (ulozeny v session).
 * Specialni hodnota 'all' = centralni prehled pres vsechny organizace (jen cteni).
 */
final class Org
{
    public const ALL = 'all';

    private static ?array $current = null;
    private static bool $loaded = false;

    /** @return array[] vsechny aktivni organizace */
    public static function allActive(): array
    {
        return Db::instance()->all('SELECT * FROM organizations WHERE active = 1 ORDER BY name');
    }

    /** Je aktivni rezim "Vsechny organizace"? */
    public static function isAll(): bool
    {
        return ($_SESSION['org_id'] ?? null) === self::ALL;
    }

    /** Aktualni organizace (null v rezimu "Vsechny organizace") */
    public static function current(): ?array
    {
        if (self::$loaded) {
            return self::$current;
        }
        self::$loaded = true;

        if (self::isAll()) {
            return self::$current = null;
        }

        $id = $_SESSION['org_id'] ?? null;
        if ($id !== null) {
            self::$current = Db::instance()->one('SELECT * FROM organizations WHERE id = ? AND active = 1', [$id]);
        }
        if (self::$current === null) {
            // fallback: prvni aktivni organizace
            $orgs = self::allActive();
            self::$current = $orgs[0] ?? null;
            if (self::$current !== null) {
                $_SESSION['org_id'] = (int)self::$current['id'];
            }
        }
        return self::$current;
    }

    /** ID aktualni organizace - v rezimu "vse" vyhodi vyjimku (ochrana proti zapisu bez organizace) */
    public static function id(): int
    {
        $org = self::current();
        if ($org === null) {
            throw new \RuntimeException('Operace vyžaduje kontext konkrétní organizace.');
        }
        return (int)$org['id'];
    }

    /** Prepnuti organizace (int id nebo 'all') */
    public static function switch(string $target): void
    {
        self::$loaded = false;
        self::$current = null;
        if ($target === self::ALL) {
            $_SESSION['org_id'] = self::ALL;
            return;
        }
        $org = Db::instance()->one('SELECT id FROM organizations WHERE id = ? AND active = 1', [(int)$target]);
        if ($org !== null) {
            $_SESSION['org_id'] = (int)$org['id'];
        }
    }

    /** Akcentni barva pro aktualni kontext (neutralni zelena pro "vse") */
    public static function accentColor(): string
    {
        $org = self::current();
        $color = $org['accent_color'] ?? '';
        return preg_match('/^#[0-9a-fA-F]{6}$/', (string)$color) ? $color : '#1e7e34';
    }
}
