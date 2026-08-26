<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Vlastni pole majetku - definice per organizace, hodnoty per majetek.
 */
final class CustomFields
{
    public const TYPES = [
        'text' => 'Text',
        'number' => 'Číslo',
        'date' => 'Datum',
        'select' => 'Výběr ze seznamu',
        'bool' => 'Ano / Ne',
    ];

    /** @return array[] aktivni definice poli organizace (serazene) */
    public static function forOrg(int $orgId, bool $onlyActive = true): array
    {
        $sql = 'SELECT * FROM custom_fields WHERE organization_id = ?' . ($onlyActive ? ' AND active = 1' : '') . ' ORDER BY sort, name';
        $fields = Db::instance()->all($sql, [$orgId]);
        foreach ($fields as &$f) {
            $f['options_list'] = self::optionsList($f);
        }
        return $fields;
    }

    /** @return string[] moznosti select pole */
    public static function optionsList(array $field): array
    {
        if ($field['type'] !== 'select' || empty($field['options'])) {
            return [];
        }
        $decoded = json_decode((string)$field['options'], true);
        return is_array($decoded) ? array_values(array_filter(array_map('strval', $decoded), fn($v) => $v !== '')) : [];
    }

    /** @return array<int,string> hodnoty [custom_field_id => value] pro majetek */
    public static function valuesFor(int $assetId): array
    {
        $rows = Db::instance()->all('SELECT custom_field_id, value FROM asset_custom_values WHERE asset_id = ?', [$assetId]);
        $out = [];
        foreach ($rows as $r) {
            $out[(int)$r['custom_field_id']] = (string)($r['value'] ?? '');
        }
        return $out;
    }

    /**
     * Ulozi hodnoty z POST (klice cf_{id}) pro majetek.
     * @param array[] $fields definice poli (forOrg)
     */
    public static function saveFromPost(int $assetId, array $fields, array $post): void
    {
        $db = Db::instance();
        foreach ($fields as $f) {
            $key = 'cf_' . $f['id'];
            $value = trim((string)($post[$key] ?? ''));
            if ($f['type'] === 'bool') {
                $value = isset($post[$key]) ? '1' : '0';
            }
            if ($value === '' || ($f['type'] === 'bool' && $value === '0')) {
                $db->exec('DELETE FROM asset_custom_values WHERE asset_id = ? AND custom_field_id = ?', [$assetId, $f['id']]);
            } else {
                $db->exec(
                    'INSERT INTO asset_custom_values (asset_id, custom_field_id, value) VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE value = VALUES(value)',
                    [$assetId, $f['id'], $value]
                );
            }
        }
    }

    /** Zobrazitelna hodnota */
    public static function display(array $field, ?string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        return match ($field['type']) {
            'bool' => $value === '1' ? 'Ano' : 'Ne',
            'date' => format_date($value),
            default => $value,
        };
    }
}
