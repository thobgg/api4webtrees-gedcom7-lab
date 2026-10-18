<?php

declare(strict_types=1);

namespace Api4Webtrees;

use Fisharebest\Webtrees\Gedcom7\Gedcom7Converter;
use Fisharebest\Webtrees\Gedcom7\Gedcom7Media;
use Fisharebest\Webtrees\Gedcom7\Gedcom7Schema;

use function in_array;
use function mb_strtolower;
use function strtolower;
use function strtoupper;
use function trim;

/**
 * Die Art eines Mediums: in der API klein wie in GEDCOM 5.5.1 (photo, document ...), in GEDCOM 7.0 "MEDI PHOTO" oder,
 * fuer Arten, die 7.0 nicht kennt, "MEDI OTHER" + "PHRASE Document".
 */
final class Medium
{
    /**
     * @return array{0:string,1:string} MEDI und PHRASE
     */
    public static function gedcom7(string $type): array
    {
        $medium = strtoupper(trim($type));

        if ($medium === '' || in_array($medium, Gedcom7Schema::ENUMERATIONS['MEDI'], true)) {
            return [$medium, ''];
        }

        return ['OTHER', Gedcom7Converter::humanize($medium)];
    }

    /**
     * "<level> MEDI" (+ PHRASE) fuer eine Art der API, leer ohne Art.
     */
    public static function lines(string $type, int $level): string
    {
        [$medium, $phrase] = self::gedcom7($type);

        if ($medium === '') {
            return '';
        }

        return "\n" . $level . ' MEDI ' . $medium . ($phrase === '' ? '' : "\n" . ($level + 1) . ' PHRASE ' . $phrase);
    }

    public static function api(string $medium, string $phrase): string|null
    {
        if ($medium === '') {
            return null;
        }

        $type = Gedcom7Media::type($medium, $phrase === '' ? null : $phrase);

        return $type === $phrase ? mb_strtolower($phrase) : strtolower($type);
    }
}
