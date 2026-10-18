#!/bin/sh
# tests/test_api.py gegen webtrees7 in Docker.  Aufruf: tests/docker/test.sh [Image-Tag] [Testname ...]
# Beispiel: tests/docker/test.sh 76ac5d81d1 Paten
set -e
cd "$(dirname "$0")"
TAG=${1:-76ac5d81d1}
[ $# -gt 0 ] && shift
export WEBTREES7_IMAGE="webtrees7:$TAG" WEBTREES7_TAG="$TAG"

docker compose down -v >/dev/null 2>&1 || true
trap 'docker compose down -v >/dev/null 2>&1' EXIT
docker compose up -d --build --wait web
docker compose exec -T -u www-data web python3 modules_v4/api4webtrees/tests/test_api.py "$@" || STATUS=$?
docker compose exec -T -u www-data web php modules_v4/api4webtrees/tests/docker/pruefen7.php || STATUS=$?
exit ${STATUS:-0}
