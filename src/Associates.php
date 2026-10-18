<?php

declare(strict_types=1);

namespace Api4Webtrees;

use Fisharebest\Webtrees\Gedcom7\Gedcom7Associates;
use Fisharebest\Webtrees\Gedcom7\Gedcom7Converter;

use function in_array;
use function mb_strtolower;
use function preg_match;
use function preg_replace;
use function strcasecmp;
use function strtolower;
use function strtoupper;
use function strtr;
use function trim;

/**
 * Bezugspersonen (Paten, Zeugen ...) in GEDCOM 7.0: "ASSO @I1@" oder "ASSO @VOID@" mit PHRASE (Person ohne Datensatz),
 * darunter "ROLE GODP" (+ PHRASE). Aeltere Daten, die webtrees7 nicht umwandeln konnte, tragen "_ASSO" mit "RELA".
 */
final class Associates
{
    // ROLE -> Rolle der API
    private const array ROLES = ['GODP' => 'godparent', 'WITN' => 'witness'];

    /**
     * Alle Bezugspersonen "<level> ASSO" in einem Block.
     *
     * @return list<array{xref:string|null,phrase:string,rela:string,role:string,sub:string}>
     */
    public static function fromBlock(string $block, int $level): array
    {
        $data = [];

        foreach (['ASSO', '_ASSO'] as $tag) {
            foreach (GedcomText::subrecords($block, $level, $tag) as [$value, $sub]) {
                if (preg_match('/^@([^@]+)@$/', trim($value), $match) !== 1) {
                    continue;
                }

                [$rela, $role] = self::relation($sub, $level + 1);

                $data[] = [
                    'xref'   => $match[1] === 'VOID' ? null : $match[1],
                    'phrase' => trim(GedcomText::subrecords($sub, $level + 1, 'PHRASE')[0][0] ?? ''),
                    'rela'   => $rela,
                    'role'   => $role,
                    'sub'    => $sub,
                ];
            }
        }

        return $data;
    }

    /**
     * RELA wie in webtrees (z. B. "godmother") und die Rolle der API (godparent, witness, other).
     *
     * @return array{0:string,1:string}
     */
    private static function relation(string $sub, int $level): array
    {
        $role = GedcomText::subrecords($sub, $level, 'ROLE')[0] ?? null;

        if ($role === null) {
            $rela = trim(GedcomText::subrecords($sub, $level, 'RELA')[0][0] ?? '');

            return [$rela, self::role($rela)];
        }

        $value  = trim($role[0]);
        $phrase = GedcomText::subrecords($role[1], $level + 1, 'PHRASE')[0][0] ?? null;
        $phrase = $phrase === null ? null : trim($phrase);
        $plain  = Gedcom7Associates::relationship($value, null);

        // "ROLE GODP" + "PHRASE Godparent" ist nur godparent
        if ($phrase !== null && $plain !== null && strcasecmp($phrase, $plain) === 0) {
            $phrase = null;
        }

        $rela = Gedcom7Associates::relationship($value, $phrase) ?? (string) $phrase;

        return [$rela === '' ? strtolower($value) : $rela, self::ROLES[$value] ?? self::role($rela)];
    }

    /**
     * Eine Bezugsperson als GEDCOM 7.0: "<level> ASSO @xref@" (ohne Datensatz: @VOID@ mit PHRASE), darunter ROLE
     * (+ PHRASE) fuer die RELA, wie webtrees sie kennt (godmother -> GODP + Godmother, Gevatter -> OTHER + Gevatter).
     * $sub sind weitere Unterzeilen (NOTE, SOUR ...) eine Ebene tiefer.
     */
    public static function gedcom(string|null $xref, string $phrase, string $rela, string $sub, int $level): string
    {
        $key = strtoupper(strtr(trim($rela), [' ' => '_']));

        [$role, $role_phrase] = match (true) {
            isset(Gedcom7Converter::ROLES[$key])             => [Gedcom7Converter::ROLES[$key], ''],
            isset(Gedcom7Converter::ROLES_WITH_PHRASE[$key]) => [Gedcom7Converter::ROLES_WITH_PHRASE[$key], Gedcom7Converter::humanize($key)],
            default                                          => ['OTHER', trim($rela)],
        };

        return "\n" . $level . ' ASSO @' . ($xref ?? 'VOID') . '@'
            . ($xref === null ? "\n" . ($level + 1) . ' PHRASE ' . $phrase : '')
            . "\n" . ($level + 1) . ' ROLE ' . $role
            . ($role_phrase === '' ? '' : "\n" . ($level + 2) . ' PHRASE ' . $role_phrase)
            . $sub;
    }

    /**
     * Die Unterzeilen einer Bezugsperson ohne ihre Rolle (ROLE + PHRASE, RELA) und ohne die PHRASE von @VOID@.
     */
    public static function withoutRole(string $sub, int $level): string
    {
        return (string) preg_replace('/\n' . $level . ' (ROLE|RELA|PHRASE)( [^\n]*)?(\n[' . ($level + 1) . '-9] [^\n]*)*/', '', $sub);
    }

    /**
     * RELA normalisiert, ohne Ruecksicht auf Gross-/Kleinschreibung: godparent, witness oder other.
     */
    public static function role(string $rela): string
    {
        $rela = mb_strtolower(trim($rela));

        if (in_array($rela, ['godparent', 'godfather', 'godmother', 'pate', 'patin', 'taufpate', 'taufpatin', 'gevatter', 'gevatterin'], true)) {
            return 'godparent';
        }

        if (in_array($rela, ['witness', 'trauzeuge', 'trauzeugin', 'zeuge', 'zeugin'], true)) {
            return 'witness';
        }

        return 'other';
    }
}
