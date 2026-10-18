#!/bin/sh
# Vorher-nachher-Vergleich (tests/vergleich.py): Falkenrath mit webtrees 2.2.6 + altem Modul gegen webtrees7 + dieses Modul.
# Aufruf: tests/docker/vergleich.sh <Ordner des alten Moduls> [Image-Tag] - Ergebnis in tests/.vergleich/
set -e
cd "$(dirname "$0")"
ALT=$(realpath "$1")
TAG=${2:-ed039c653d}
FALKENRATH=$(realpath ../../../../falkenrath)
AUS=$(realpath ..)/.vergleich
mkdir -p "$AUS"

# Alt: webtrees 2.2.6 mit SQLite unter php -S, im Container
docker build -q -t api4webtrees7-alt -f Dockerfile.alt . >/dev/null
docker run --rm -u "$(id -u):$(id -g)" -e HOME=/tmp -e MODUL_DIR=/alt -e UMGEBUNG_ARBEIT=/tmp/alt -e PYTHONDONTWRITEBYTECODE=1 \
    -v "$ALT":/alt:ro -v "$(realpath ..)":/tests:ro -v "$FALKENRATH":/falkenrath:ro -v "$AUS":/aus \
    api4webtrees7-alt python3 /tests/vergleich.py sammeln /aus/alt.json

# Neu: webtrees7 mit MariaDB, wie tests/docker/test.sh
export WEBTREES7_IMAGE="webtrees7:$TAG" WEBTREES7_TAG="$TAG"
docker compose down -v >/dev/null 2>&1 || true
trap 'docker compose down -v >/dev/null 2>&1' EXIT
docker compose up -d --build --wait web
docker compose exec -T -u www-data -e FALKENRATH=/falkenrath/versionen/falkenrath-1.7.ged web sh -c \
    "python3 modules_v4/api4webtrees/tests/vergleich.py sammeln /tmp/neu.json"
docker compose cp web:/tmp/neu.json "$AUS/neu.json"

docker run --rm -u "$(id -u):$(id -g)" -e PYTHONDONTWRITEBYTECODE=1 -v "$(realpath ..)":/tests:ro -v "$AUS":/aus \
    api4webtrees7-alt python3 /tests/vergleich.py vergleichen /aus/alt.json /aus/neu.json > "$AUS/unterschiede.txt"
head -3 "$AUS/unterschiede.txt"
echo "Alles: $AUS/unterschiede.txt"
