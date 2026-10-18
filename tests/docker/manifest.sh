#!/bin/sh
# docs/API.md und docs/openapi.json aus echten Antworten von webtrees7 erzeugen (tests/manifest.py).
# Aufruf: tests/docker/manifest.sh [Image-Tag]
set -e
cd "$(dirname "$0")"
TAG=${1:-d82c24b269}
export WEBTREES7_IMAGE="webtrees7:$TAG" WEBTREES7_TAG="$TAG"

docker compose down -v >/dev/null 2>&1 || true
trap 'docker compose down -v >/dev/null 2>&1' EXIT
docker compose up -d --build --wait web
docker compose exec -T -u www-data -e MANIFEST_DOCS=/tmp/docs web python3 modules_v4/api4webtrees/tests/manifest.py
docker compose cp web:/tmp/docs/openapi.json ../../docs/openapi.json
docker compose cp web:/tmp/docs/API.md ../../docs/API.md
