<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Minimalisticka XLSX ctecka bez zavislosti (ZipArchive + XML).
 * Cte prvni list: sdilene i inline retezce, cisla. Pro sloupce s datem
 * je k dispozici prevod excelovskeho serioveho cisla (excelDate).
 */
final class XlsxReader
{
    /** @return array<int, array<int, string>> radky (mezery doplnene na plnou sirku) */
    public static function rows(string $file): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($file) !== true) {
            throw new \RuntimeException('Soubor nelze otevřít jako XLSX.');
        }

        $shared = [];
        $ssXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssXml !== false) {
            $sst = new \SimpleXMLElement($ssXml);
            foreach ($sst->si as $si) {
                if (isset($si->t)) {
                    $shared[] = (string)$si->t;
                } else {
                    $text = '';
                    foreach ($si->r as $r) {
                        $text .= (string)$r->t;
                    }
                    $shared[] = $text;
                }
            }
        }

        // prvni list dle workbook poradi = sheet1.xml (AssetTiger i nas export maji jeden list)
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if ($sheetXml === false) {
            throw new \RuntimeException('XLSX neobsahuje list sheet1.');
        }

        $sheet = new \SimpleXMLElement($sheetXml);
        $rows = [];
        $maxCols = 0;
        foreach ($sheet->sheetData->row as $row) {
            $cells = [];
            $colIndex = 0;
            foreach ($row->c as $c) {
                $ref = (string)$c['r'];
                if ($ref !== '' && preg_match('/^([A-Z]+)\d+$/', $ref, $m)) {
                    $colIndex = self::colIndex($m[1]);
                }
                $type = (string)$c['t'];
                $value = '';
                if ($type === 's') {
                    $value = $shared[(int)$c->v] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = isset($c->is->t) ? (string)$c->is->t : '';
                } elseif (isset($c->v)) {
                    $value = (string)$c->v;
                }
                $cells[$colIndex] = $value;
                $colIndex++;
            }
            if ($cells !== []) {
                $maxCols = max($maxCols, max(array_keys($cells)) + 1);
            }
            $rows[] = $cells;
        }

        // doplneni der a sjednoceni sirky
        $out = [];
        foreach ($rows as $cells) {
            $full = array_fill(0, $maxCols, '');
            foreach ($cells as $i => $v) {
                $full[$i] = $v;
            }
            $out[] = $full;
        }
        return $out;
    }

    /** Excelovske seriove cislo -> Y-m-d (epocha 1899-12-30) */
    public static function excelDate(float $serial): string
    {
        $ts = (int)round(($serial - 25569) * 86400); // 25569 = dni mezi 1899-12-30 a 1970-01-01
        return gmdate('Y-m-d', $ts);
    }

    private static function colIndex(string $letters): int
    {
        $index = 0;
        foreach (str_split($letters) as $ch) {
            $index = $index * 26 + (ord($ch) - 64);
        }
        return $index - 1;
    }
}
