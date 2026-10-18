# Plan: api4webtrees für webtrees7 (GEDCOM 7.0)

Stand 18.10.2026. Branch `webtrees7`, abgezweigt von `webtrees-2.3` (9417971, API-Stufe 36).

webtrees7 ist ein Fork von webtrees, der GEDCOM 7.0 speichert. 5.5.1 gibt es dort nur noch beim Einlesen, Umwandeln und Exportieren.

## Ausgangslage

**Das Modul**
- Es hat keinen eigenen GEDCOM-Baustein.
- Geschrieben wird mit handgebauten Zeilen über `createFact`, `updateFact`, `updateRecord` und `createRecord`.
- Gelesen wird mit Regexen (`GedcomText.php`) und mit `Fact::attribute()`.
- Dasselbe Wissen über Tags steht an bis zu zehn Stellen.

**webtrees7**
- Bäume in 5.5.1 sind nur lesbar (`Auth::isEditor`).
- Im Schreibweg sitzt ein Wandler als Sicherheitsnetz (`Gedcom7Storage::pendingChange`). Er protokolliert jede Umwandlung als „GEDCOM 5.5.1 converted to GEDCOM 7.0“.
- Text-Quellen (`2 SOUR text`) und eingebettete Medien kann er nicht umwandeln. Dann bleibt der ganze Datensatz 5.5.1.
- Der Kern prüft beim Schreiben nichts. `Gedcom7Problems` wird nur in den Formularen aufgerufen.

**Folgerung:** Das heutige Modul liefe weitgehend, aber nur über das Sicherheitsnetz. Das ist kein natives 7.0.

**Apps**
- Die meisten Felder sind formatunabhängig: `value`, `label`, `role`, `date.text`/`jd`, `quality`, `notes`, `mime`.
- Kritisch sind vier Dinge:
  - 5.5.1-Daten, z. B. `@#DJULIAN@` in `Kalender.kt` und das Parsen von `date.gedcom`;
  - kleingeschriebene Medienarten (`photo`, `document`);
  - Abfragen `tag == "NOTE"`;
  - Text-Quellen.
- Die Eingabe `{gedcom: …}` nutzen die eigenen Apps nicht.
- `Info` nennt keine GEDCOM-Version. Unbekannte Felder ignorieren die Apps, ein neues Feld ist also gefahrlos.

## Grundsatz

**Das Modul schreibt selbst 7.0.** Der Wandler im Kern darf bei keinem API-Aufruf anspringen. Das ist das Prüfkriterium jedes Schritts.

**Der Vertrag bleibt additiv.**
- Abstrahierte Felder bleiben, wie sie sind.
- Rohfelder tragen 7.0-Werte: `rela`, `date.gedcom`, `media.type`/`format`, `uids`, `gedcomValue`, `facts[].type`.
- Neu ist `Info.gedcom` = `"7.0"`.
- Modulname und Route `_api4webtrees_` bleiben, weil Apps sie fest aufrufen.

## Schritte

### P0 Grundlage und Messung

- Kompatibilitätszweige für webtrees 2.2 entfernen: `Compat.php`, `DeleteRecord`, `FactSortService`, `timestampFactory`.
- `tests/umgebung.py` testet gegen das webtrees7-Image in Docker mit MariaDB statt gegen die ZIP von webtrees 2.2.6 und `php -S`.
- Testbäume:
  - `testbaum.ged` und `gedcoml.ged` als 5.5.1 einlesen und umwandeln lassen. So wird die Umwandlung von `_GODP` und `_ASSO` mitgeprüft.
  - Zusätzlich `maximal70.ged` als echte 7.0-Datei.
- Messen wie in webtrees7 Schritt B0. Die heutige Suite läuft gegen webtrees7, dazu kommen:
  - Einträge „converted“ im Protokoll je Endpunkt;
  - Prüfung der Bäume (CheckTree oder Validator).
  
  Das Ergebnis ist die Arbeitsliste für P1 bis P4.

### Messung P0 (18.10.2026, Image webtrees7:76ac5d81d1)

Aufruf: `tests/docker/test.sh [Tag]`. Das Skript startet ein Wegwerf-webtrees7 mit MariaDB in tmpfs (Compose-Projekt `api4webtrees7-test`, kein Port), lässt `test_api.py` laufen und danach `tests/docker/pruefen7.php`.

Ergebnis: 61 Tests, davon 43 grün und 18 rot (14 FAIL, 4 ERROR). Der Kern hat 5-mal umgewandelt. Datensätze, die nicht 7.0 sind: 0.

**Umwandlungen durch den Kern**, also Stellen, an denen das Modul noch 5.5.1 schreibt:
- `NOTE` mit `CONC`: Das Modul hat einen Fortsetzungstext so geschrieben (I4).
- `_ASSO` + `RELA witness` und `_WITN` an MARR (F1).
- `_ASSO` + `RELA godparent`/`Godmother` und `_GODP` an CHR (X8), dreimal.
  Ein Datensatz trägt danach `3 _ROLE GODP`: Das Modul hat eine zweite Rolle an ein schon umgewandeltes `ASSO` gehängt. Ob dabei etwas verloren ging, ist noch zu prüfen.

**Rote Tests nach Ursache**

| Ursache | Tests | Schritt |
|---|---|---|
| Paten und Zeugen lesen (`ROLE`, `@VOID@`, Gegenrichtung) | 9 | P1 |
| Paten und Zeugen schreiben (Erwartung 5.5.1-Text) | 2 | P2 |
| Text-Quellen sind beim Einlesen `_S7_n` geworden | 2 | P1, Entscheidung 3 |
| Medien: `FORM image/png` statt `png` | 1 | P1 |
| `UID` statt `_UID` | 1 | P1 |
| Rundlauf GEDCOM-L (Anzahl Zeilen) | 1 | P4 |
| Zusammenführen: neuer Fakt `CREA` | 1 | P4 |
| Folgefehler im Ortsteil (Umbenennen) und im Zugangstest | 2 | P3, prüfen |

Die heutigen Testdaten sind 5.5.1. Sie werden beim Einlesen umgewandelt.

**Nachtrag P0:**
- Die Kompatibilitätszweige für 2.2 sind entfernt (`Compat.php`).
- webtrees7 ist nicht das 2.3, das der Branch `webtrees-2.3` annahm. Es kennt `I18N::compare()` und `activeLanguages()`, aber nicht `comparator()` und `activeLocales()`. Das Modul nutzt jetzt direkt, was webtrees7 hat.
- `maximal70.ged` wird beim Test geladen: gedcom.io, Commit a7b136b, mit Prüfsumme. Daraus wird der private Baum `maximal70`.
- Der neue Test `Gedcom7.test_alles_lesen` ruft jede Leseroute für alle Datensätze dieses Baums auf. Er ist grün.
- Gegen `webtrees7:ed039c653d` (C5 + C7) ergibt sich dasselbe Bild wie gegen 76ac5d81d1: 44 von 62 Tests grün, dieselben 18 rot, 5 Umwandlungen, 0 Datensätze außerhalb von 7.0.
- Ab jetzt wird gegen `ed039c653d` gemessen.

### P1 Lesen

- `SNOTE`: betrifft `noteXref`, `notesFromBlock` und Notizen an Quellen und `_LOC`.
- `ASSO` + `ROLE` (+ `PHRASE`):
  - `associateRole` bildet `GODP` auf `godparent` und `WITN` auf `witness` ab.
  - `associatedIn` muss mit.
  - Bezugspersonen `@VOID@` kommen als `free`.
- `UID` neben `_UID`; `EXID` mit GOV-Typ statt `_GOV`.
- Medien: `FORM` ist der Medientyp, dazu `MEDI` (+ `PHRASE`).
- `@@` entschlüsseln; `@VOID@` bei `HUSB`, `WIFE` und `SOUR` berücksichtigen.
- `date.gedcom` ist der 7.0-Rohwert, neu ist `date.phrase`.

### Ergebnis P1 (18.10.2026, gegen ed039c653d)

57 von 62 Tests grün. Rot sind nur noch Schreiben (P2: Paten, Trauzeugen und ein Folgefehler bei den Quellen) und P4 (Rundlauf GEDCOM-L, `CREA` beim Zusammenführen).

**Umgesetzt**
- `src/Associates.php` liest Bezugspersonen an einer Stelle:
  - `ASSO` + `ROLE` (+ `PHRASE`), dazu `@VOID@`;
  - als Rückfall das alte `_ASSO` + `RELA`.
- `rela` liefert weiter webtrees-Werte (`godparent`, `godmother`, `friend`), über `Gedcom7Associates::relationship()`.
- `ASSO @VOID@` mit Phrase erscheint in `freeAssociates`, wie früher `_GODP`/`_WITN`.
- `noteXref` und Notizen lesen auch `SNOTE`.
- `uids` liest `UID` und `_UID`. `UID` steht nicht mehr als Fakt in der Liste.
- `media[].type` ist `MEDI`, klein geschrieben wie bisher (`photo`, `card`); bei `OTHER` die Phrase.
- `extras` meldet die GOV-Kennung (`PLAC:EXID` mit GOV-Typ) weiter als `PLAC:_GOV`.

**Testerwartungen an 7.0 angepasst** (Rohwerte, die webtrees7 beim Einlesen vereinheitlicht)
- `rela` „Godparent“/„Godfather“ werden zu `godparent`/`godfather`;
- `format` `png` wird zu `image/png`;
- `lang` „German“ wird zu `de`;
- Text-Quelle `""` wird zum Datensatz `_S7_1`.

**Ohne Änderung grün:** Orte und `_LOC` (P3) laufen schon gegen webtrees7. Bleibt in P3 nur noch die GOV-Kennung beim Schreiben.

**Beim Schreiben zu klären (P2):** Ein `ASSO` bekam `3 _ROLE GODP`. Das Modul hatte an ein schon umgewandeltes `ASSO` eine zweite Rolle gehängt. Nach dem Protokoll ging dabei keine Person verloren. Die Ursache verschwindet, wenn das Modul selbst 7.0 schreibt.

### P2 Schreiben

- Zuerst die Bau- und Leseregeln je Thema an eine Stelle ziehen: Paten, Quellenverweis, Notiz, Datum, Medium.
- `GedcomText::line()` und `multiline()` maskieren `@` als `@@`.
- Datum:
  - Eingaben werden weiter als 5.5.1 angenommen und mit `Gedcom7Payload::date()` nach 7.0 (+ `PHRASE`) gewandelt.
  - Phrasen nicht mehr in Großbuchstaben setzen.
  - Wer `DATE` entfernt, darf `TIME` und `PHRASE` nicht mit entfernen.
- Paten und Zeugen:
  - `2 ASSO @I@` + `3 ROLE GODP|WITN|OTHER` (+ `PHRASE`).
  - Personen ohne Datensatz werden `ASSO @VOID@` + `PHRASE`.
  - `convertLevel1` entfällt.
- Text-Quellen: siehe Entscheidung 3.
- Medien:
  - `FORM <mime>` + `MEDI`, kein leeres `FORM` mehr.
  - `MEDIA_TYPES` folgt der 7.0-Liste.
  - Alte Werte (`document`, `certificate` …) werden weiter angenommen und als `OTHER` + `PHRASE` geschrieben.
- `EVEN` und `FACT` ohne `TYPE` werden abgelehnt.
- Roh-Eingabe `{gedcom: …}`: siehe Entscheidung 4.
- `ADDABLE_TAGS` bekommt `SNOTE` und gegebenenfalls `NO`.

### Ergebnis P2 (18.10.2026, gegen ed039c653d)

61 von 63 Tests grün. Rot sind nur noch die beiden P4-Punkte.

**Schreibt jetzt 7.0**
- Paten und Zeugen: `ASSO` + `ROLE` (+ `PHRASE`), ohne Datensatz `ASSO @VOID@` + `PHRASE`. Die Regeln stehen in `Associates`.
- Medienart: `MEDI` (+ `PHRASE`). Die Regeln stehen in `Medium`. Die API nimmt weiter `photo`, `document` usw. an.
- Text-Quellen (Entscheidung 3 = a): `SOUR @VOID@`. Der Text steht in der ersten Zeile von `PAGE`, die Seite in den Zeilen darunter. Gelesen wird das wieder als `title` und `page`.
- Daten: `GedcomText::dateLines()` wandelt 5.5.1-Eingaben mit `Gedcom7Payload::date()`, also `@#DJULIAN@` zu `JULIAN` und `INT … (…)` zu `PHRASE`.
- Neu ist `date.phrase` (Stufe 37).
- `EVEN` und `FACT` ohne `TYPE` werden abgelehnt (`type-required`).

**Prüfung:** `pruefen7.php` prüft jetzt auch alle angenommenen Änderungen, nicht nur den Endstand. Ein Test kann etwas schreiben und wieder löschen, früher fiel so die Text-Quelle durch.

**Umwandlungen durch den Kern:** nur noch eine. Der Test „Conctest“ schickt über die Roh-Eingabe `{gedcom}` absichtlich `CONC`, das hängt an Entscheidung 4.

**`@@` braucht keine Maskierung:** webtrees7 speichert ohne Escapes (`Gedcom7Record::create` entfernt sie). Das Modul schreibt also richtig, wenn es `@` unmaskiert lässt. `looksLikePointer()` verhindert weiter Werte, die wie `@X1@` aussehen.

**Offen in P2**
- Entscheidung 4: die Roh-Eingabe `{gedcom}`.
- `ADDABLE_TAGS` um `SNOTE`/`NO` ergänzen, zusammen mit P5 (Tags).

### P3 Orte und `_LOC`

Dieser Schritt ist nach der Codemenge der größte.
- Die SQL-Abfragen mit `LIKE` auf `*_gedcom` gegen 7.0-Text prüfen: `\n2 PLAC`, `3 _LOC @` und `change.new_gedcom`.
- `_GOV` wird `PLAC:EXID` + `TYPE https://gov.genealogy.net/`.
- `_LOC` und seine GEDCOM-L-Felder bleiben Erweiterungen.

### P4 Übrige GEDCOM-L-Strukturen und Zusammenführen

- `_TODO`, `_PRIM`, `_STAT` und `_RUFNAME` bleiben Erweiterungen. Für sie werden nur Tests ergänzt.
- Zusammenführen:
  - `_UID` wird `UID`.
  - Das Rückgängigmachen (`GedcomImportService::updateRecord()`) gegen `Gedcom7Storage::edit` prüfen.

### P5 Vertrag und Doku

- API-Stufe 37 mit `Info.gedcom`, auch je Baum in `trees[]`.
- `Tags` liefert 7.0-Tags.
- `API.md` und `openapi.json` mit `tests/manifest.py` neu erzeugen, CHANGELOG ergänzen.

### P6 Apps (app4webtrees)

Die Apps schalten über `Info.gedcom` um.
- Lesen:
  - Kalender `JULIAN` und `PHRASE` (`Kalender.kt`, `DateForm.kt`, `Timeline.kt`, `Buch.kt`);
  - Medienarten (`OrteFenster.kt`);
  - `SNOTE` (`Dialogs.kt`).
- Schreiben: Solange P2 die 5.5.1-Eingaben wandelt, müssen die Apps beim Schreiben nichts ändern.

### P7 Auslieferung

- Das webtrees7-Image bringt das Modul mit.
- Releases getrennt von 1.x.

**Aufwand grob**
- Groß: Paten (P1/P2) und P3.
- Mittel: Daten, Notizen, Quellen, Medien.
- Klein: alles Übrige.

## Testdateien

- **gedcom.io**: FamilySearch/GEDCOM.io, Ordner `testfiles/gedcom70`. Darin `maximal70.ged` und 20 weitere Dateien. Die Lizenz ist ungeklärt, deshalb liegen sie nur lokal.
- **gedcom7code/test-files**: laut Fremddoku unter Unlicense. Vor einer Übernahme ins Repo prüfen.
- **g7validation.json**: aus FamilySearch/GEDCOM-registries, als Grundlage für den Validator.

## Entscheidungen

1. **Ort:** Branch `webtrees7` in diesem Repo. *Entschieden.*
2. **Vertrag:** additiv wie oben, oder eine eigene Schnittstelle v2? Empfehlung: additiv.
3. **Text-Quellen** (`source` als Freitext): Empfehlung: `SOUR @VOID@` + `PAGE`. Das ist 7.0-konform und legt keine Datensätze an. Alternativen sind ein automatisch angelegter `SOUR`-Datensatz oder Ablehnen.
4. **Roh-`gedcom`:** Empfehlung: nur 7.0 annehmen und sonst einen Fehler mit der Meldung aus `Gedcom7Problems` liefern.
5. **`_STAT` NOT/NEVER MARRIED:** als Erweiterung lassen oder zusätzlich `NO MARR` schreiben?
6. **Zeitpunkt:** P0 jetzt gegen `gedcom7-c`. P1 bis P4 erst gegen einen festen Stand von webtrees7, also nach C7 und dem Merge nach `gedcom7-native`.
