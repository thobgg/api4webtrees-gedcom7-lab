<?php

/**
 * Nach tests/test_api.py: Was hat das Modul als GEDCOM 5.5.1 geschrieben?
 * - Eintraege "GEDCOM 5.5.1 converted to GEDCOM 7.0" (der Wandler im Kern ist eingesprungen)
 * - Datensaetze und Aenderungen, die nicht 7.0 sind (der Wandler konnte sie nicht umwandeln)
 */

declare(strict_types=1);

use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Gedcom7\Gedcom7Storage;
use Fisharebest\Webtrees\Webtrees;

require __DIR__ . '/../../../../vendor/autoload.php';

Webtrees::new()->bootstrap();

$config = parse_ini_file(Webtrees::CONFIG_FILE);

DB::connect(
    driver: $config['dbtype'],
    host: $config['dbhost'],
    port: $config['dbport'],
    database: $config['dbname'],
    username: $config['dbuser'],
    password: $config['dbpass'],
    prefix: $config['tblpfx'],
    key: $config['dbkey'] ?? '',
    certificate: $config['dbcert'] ?? '',
    ca: $config['dbca'] ?? '',
    verify_certificate: (bool) ($config['dbverify'] ?? false),
);

$fehler = 0;

$umwandlungen = DB::table('log')
    ->where('log_message', 'LIKE', 'GEDCOM 5.5.1 converted to GEDCOM 7.0:%')
    ->orderBy('log_id')
    ->pluck('log_message');

echo "\n== Vom Kern umgewandelt: " . count($umwandlungen) . "\n";
foreach ($umwandlungen as $meldung) {
    echo '-- ' . str_replace("\n", "\n   ", $meldung) . "\n";
}
$fehler += count($umwandlungen);

$tabellen = [
    'individuals' => ['i_id', 'i_gedcom'],
    'families'    => ['f_id', 'f_gedcom'],
    'sources'     => ['s_id', 's_gedcom'],
    'media'       => ['m_id', 'm_gedcom'],
    'other'       => ['o_id', 'o_gedcom'],
];

$nicht7 = [];
foreach ($tabellen as $tabelle => [$id, $spalte]) {
    foreach (DB::table($tabelle)->select([$id, $spalte])->get() as $zeile) {
        if ($zeile->$spalte !== '' && !str_starts_with($zeile->$spalte, '0 HEAD') && !str_starts_with($zeile->$spalte, '0 TRLR') && !Gedcom7Storage::isGedcom7($zeile->$spalte)) {
            $nicht7[] = $tabelle . ' ' . $zeile->$id . "\n   " . str_replace("\n", "\n   ", $zeile->$spalte);
        }
    }
}
// Auch angenommene Aenderungen: ein Test kann einen Datensatz schreiben und danach wieder loeschen
foreach (DB::table('change')->select(['change_id', 'status', 'xref', 'new_gedcom'])->orderBy('change_id')->get() as $zeile) {
    if ($zeile->new_gedcom !== '' && !Gedcom7Storage::isGedcom7($zeile->new_gedcom)) {
        $nicht7[] = 'change ' . $zeile->change_id . ' (' . $zeile->status . ') ' . $zeile->xref . "\n   " . str_replace("\n", "\n   ", $zeile->new_gedcom);
    }
}

echo "\n== Nicht GEDCOM 7.0: " . count($nicht7) . "\n";
foreach ($nicht7 as $text) {
    echo '-- ' . $text . "\n";
}
$fehler += count($nicht7);

exit($fehler === 0 ? 0 : 1);
