#!/usr/bin/env python3
"""Vorher-nachher-Vergleich: was eine App von demselben Stammbaum sieht - mit webtrees 2.2.6 und dem alten Modul
gegen webtrees7 und dieses Modul. Beide Seiten startet tests/docker/vergleich.sh.

  python3 tests/vergleich.py sammeln <ausgabe.json>            alle Leserouten fuer Falkenrath, als Admin und als Gast
  python3 tests/vergleich.py vergleichen <alt.json> <neu.json>  Unterschiede, nach Art zusammengefasst
"""
import json
import os
import re
import sys

import umgebung

FALKENRATH = os.environ.get("FALKENRATH", "/falkenrath/versionen/falkenrath-1.7.ged")

# Was sich immer unterscheidet: Kennungen aus Hashes, Sitzung, Adressen, Versionen
IGNORIEREN = {"csrf", "id", "factId", "lastChange", "url", "thumb", "file", "webtrees", "module", "api", "baseUrl",
              "maxUpload", "rewriteUrls"}


def sammeln(ziel):
    u = umgebung.Umgebung().aufbauen()
    try:
        umgebung.wt("tree", "falkenrath", "--create", "--title=Falkenrath")
        umgebung.wt("tree-import", "falkenrath", FALKENRATH)
        baum = umgebung.sql("SELECT gedcom_id FROM wt_gedcom WHERE gedcom_name = 'falkenrath'")[0][0]
        ids = {t: [x for (x,) in umgebung.sql(f"SELECT {t[0]}_id FROM wt_{t} WHERE {t[0]}_file = ? ORDER BY {t[0]}_id", baum)]
               for t in ("individuals", "families", "sources")}
        antworten = {}
        for rolle in ("admin", None):
            s = u.sitzung(rolle)
            wer = rolle or "gast"

            def holen(aktion, **params):
                a = s.get(aktion, "falkenrath", **params)
                antworten[f"{wer} {aktion} " + " ".join(f"{k}={v}" for k, v in sorted(params.items()))] = a.json if a.json is not None else {"_status": a.status}
                return a.json

            for aktion in ("Sources", "Repositories", "MediaList", "Bookmarks"):
                holen(aktion)
            holen("Anniversaries", days=60)
            seite = 1
            while seite is not None:
                seite = (holen("Individuals", q="", page=seite) or {}).get("nextPage")
            orte = holen("Places", q="") or {}
            namen = sorted({p.get("name") for p in orte.get("places", []) if p.get("name")})
            for name in namen:
                holen("Place", name=name)
            for x in ids["individuals"]:
                holen("Individual", xref=x)
            for x in ids["families"]:
                holen("Family", xref=x)
            for x in ids["sources"]:
                holen("Source", xref=x)
        with open(ziel, "w") as f:
            json.dump(antworten, f, ensure_ascii=False, indent=0, sort_keys=True)
        print(f"{ziel}: {len(antworten)} Antworten")
    finally:
        u.abbauen()


def schluessel(liste):
    """Listen von Fakten, Personen ... nicht nach Position vergleichen: ein Eintrag mehr verschiebt sonst alle."""
    out, zaehler = {}, {}
    for i, e in enumerate(liste):
        if isinstance(e, dict) and ("tag" in e or "xref" in e or "name" in e):
            k = str(e.get("tag") or e.get("xref") or e.get("name"))
            zaehler[k] = zaehler.get(k, 0) + 1
            out[f"{k}#{zaehler[k]}"] = e
        else:
            out[str(i)] = e
    return out


def flach(wert, pfad="", out=None):
    out = {} if out is None else out
    if isinstance(wert, dict):
        for k, v in wert.items():
            if k not in IGNORIEREN:
                flach(v, f"{pfad}.{k}", out)
    elif isinstance(wert, list):
        if not wert:
            out[pfad] = []
        for k, v in schluessel(wert).items():
            flach(v, f"{pfad}[{k}]", out)
    else:
        out[pfad] = wert
    return out


def muster(pfad):
    """$.facts[BIRT#1].date.gedcom -> .facts[].date.gedcom"""
    return re.sub(r"\[[^\]]*\]", "[]", pfad)


def vergleichen(alt_datei, neu_datei):
    alt, neu = json.load(open(alt_datei)), json.load(open(neu_datei))
    gruppen = {}

    def merken(art, aufruf, pfad, a, n):
        g = gruppen.setdefault((art, muster(pfad)), [])
        g.append((aufruf, pfad, a, n))

    for aufruf in sorted(set(alt) | set(neu)):
        if aufruf not in neu or aufruf not in alt:
            merken("nur alt" if aufruf in alt else "nur neu", aufruf, "(Aufruf)", None, None)
            continue
        fa, fn = flach(alt[aufruf]), flach(neu[aufruf])
        for p in sorted(set(fa) | set(fn)):
            if p not in fn:
                merken("fehlt neu", aufruf, p, fa[p], None)
            elif p not in fa:
                merken("neu dazu", aufruf, p, None, fn[p])
            elif fa[p] != fn[p]:
                merken("geaendert", aufruf, p, fa[p], fn[p])

    print(f"{len(alt)} Aufrufe alt, {len(neu)} neu, {len(gruppen)} Arten von Unterschieden\n")
    for (art, m), faelle in sorted(gruppen.items(), key=lambda x: (x[0][0], -len(x[1]))):
        werte = {}
        for _, _, a, n in faelle:
            werte[(json.dumps(a, ensure_ascii=False)[:60], json.dumps(n, ensure_ascii=False)[:60])] = werte.get((json.dumps(a, ensure_ascii=False)[:60], json.dumps(n, ensure_ascii=False)[:60]), 0) + 1
        print(f"[{art}] {m}  ({len(faelle)}x)")
        for (a, n), anzahl in sorted(werte.items(), key=lambda x: -x[1])[:4]:
            print(f"    {anzahl:4}x  {a}  ->  {n}")
        print(f"    z. B. {faelle[0][0]}  {faelle[0][1]}")


if __name__ == "__main__":
    if len(sys.argv) == 3 and sys.argv[1] == "sammeln":
        sammeln(sys.argv[2])
    elif len(sys.argv) == 4 and sys.argv[1] == "vergleichen":
        vergleichen(sys.argv[2], sys.argv[3])
    else:
        print(__doc__)
        sys.exit(1)
