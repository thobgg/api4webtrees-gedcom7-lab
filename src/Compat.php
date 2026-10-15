<?php

declare(strict_types=1);

namespace Api4Webtrees;

use BackedEnum;
use Closure;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;

use function array_keys;
use function array_map;
use function class_exists;
use function enum_exists;
use function method_exists;

/**
 * Unterschiede zwischen webtrees 2.2 und 2.3 an einer Stelle.
 *
 * webtrees 2.3 ersetzt Zahlen und Zeichenketten durch Enums: Auth::PRIV_* durch AccessLevel, das Geschlecht durch Sex.
 * Die Zahlenwerte der Zugriffsstufen sind gleich geblieben. Die Klassennamen stehen als Zeichenketten da, weil es die
 * Enums in 2.2 nicht gibt.
 */
final class Compat
{
    private const string ACCESS_LEVEL = 'Fisharebest\\Webtrees\\Enums\\AccessLevel';
    private const string SEX          = 'Fisharebest\\Webtrees\\Enums\\Sex';

    // Zugriffsstufen, wie Auth::PRIV_PRIVATE usw. in webtrees 2.2
    public const int PUBLIC  = 2;
    public const int MEMBER  = 1;
    public const int MANAGER = 0;
    public const int HIDDEN  = -1;

    /**
     * Eine Zugriffsstufe fuer canShow(), privatizeGedcom() usw.: in 2.2 die Zahl, in 2.3 das Enum.
     */
    public static function access(int $level): mixed
    {
        if (enum_exists(self::ACCESS_LEVEL)) {
            return (self::ACCESS_LEVEL)::from($level);
        }

        return $level;
    }

    /**
     * Eine Zugriffsstufe als Zahl, z. B. von Auth::accessLevel(). Enums lassen sich nicht mit < und > vergleichen.
     */
    public static function level(mixed $level): int
    {
        return $level instanceof BackedEnum ? (int) $level->value : (int) $level;
    }

    /**
     * Das Geschlecht als M, F, X oder U.
     */
    public static function sex(Individual $individual): string
    {
        $sex = $individual->sex();

        return $sex instanceof BackedEnum ? (string) $sex->value : (string) $sex;
    }

    /**
     * Ein Geschlecht als Argument, z. B. fuer RelationIsDescriptor::values(): in 2.2 die Zeichenkette, in 2.3 das Enum.
     */
    public static function sexArgument(string $sex): mixed
    {
        if (enum_exists(self::SEX)) {
            return (self::SEX)::tryFrom($sex) ?? (self::SEX)::from('U');
        }

        return $sex;
    }

    /**
     * Vergleich von Zeichenketten in der Sprache der Sitzung, fuer usort().
     *
     * @return Closure(string,string):int
     */
    public static function comparator(): Closure
    {
        if (method_exists(I18N::class, 'comparator')) {
            return I18N::comparator();
        }

        return static fn (string $x, string $y): int => I18N::compare($x, $y);
    }

    /**
     * Die Klasse einer Seite fuer route(): webtrees 2.3 hat die Request-Handler nach Http\Controllers verschoben und
     * manche umbenannt, z. B. RequestHandlers\ModulesAllPage -> Controllers\ModulesAll.
     *
     * @param class-string ...$classes Die Namen in 2.2, dann in 2.3
     *
     * @return class-string
     */
    public static function page(string ...$classes): string
    {
        foreach ($classes as $class) {
            if (class_exists($class)) {
                return $class;
            }
        }

        return $classes[0];
    }

    /**
     * Die Sprachen, die in webtrees aktiv sind, z. B. ["de", "en-US"].
     *
     * @return list<string>
     */
    public static function activeLanguageTags(): array
    {
        if (method_exists(I18N::class, 'activeLocales')) {
            return array_map(static fn ($locale): string => $locale->languageTag(), I18N::activeLocales());
        }

        return array_map('strval', array_keys(I18N::activeLanguages()));
    }
}
