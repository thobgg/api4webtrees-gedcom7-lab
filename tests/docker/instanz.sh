#!/bin/sh
# Testinstanz fuer die Apps: tests/docker/instanz.sh start|stop|neu [Image-Tag]
#   start  starten; beim ersten Mal einrichten (admin/admin, Baeume falkenrath, gedcoml, maximal70)
#   stop   anhalten, Daten bleiben
#   neu    alles loeschen und neu einrichten
set -e
cd "$(dirname "$0")"
TAG=${2:-ed039c653d}
export WEBTREES7_IMAGE="webtrees7:$TAG" WEBTREES7_TAG="$TAG"
ADRESSE=${ADRESSE:-http://$(hostname -I | awk '{print $1}'):8717}
DC="docker compose -f instanz.yaml"

case "$1" in
    stop) $DC stop; exit ;;
    neu) $DC down -v ;;
    start) ;;
    *) echo "Aufruf: $0 start|stop|neu [Image-Tag]"; exit 1 ;;
esac

$DC up -d --build --wait

if ! $DC exec -T web test -f data/config.ini.php; then
    $DC exec -T -u www-data web sh -s <<SETUP
set -e
php index.php config-ini --dbtype=mysql --dbhost=db --dbuser=webtrees --dbpass=webtrees --dbname=webtrees --base-url=$ADRESSE >/dev/null
php docker/seed-database.php >/dev/null
php index.php user admin --create --real-name=Administrator --email=admin@example.invalid --password=admin --quiet
php index.php user-setting admin canadmin 1
php index.php user-setting admin auto_accept 1
php -r 'copy("https://raw.githubusercontent.com/FamilySearch/GEDCOM.io/a7b136b777add6fe5796bcc49f61dfa1631c0ec2/testfiles/gedcom70/maximal70.ged", "/tmp/maximal70.ged");'
for BAUM in "falkenrath /falkenrath/versionen/falkenrath-1.7.ged" "gedcoml modules_v4/api4webtrees/tests/gedcoml.ged" "maximal70 /tmp/maximal70.ged"; do
    set -- \$BAUM
    php index.php tree "\$1" --create --title="\$1" --quiet
    php index.php tree-import "\$1" "\$2" >/dev/null
    echo "eingelesen: \$1"
done
mkdir -p data/media
cp /falkenrath/media/* data/media/
SETUP
fi

echo "Testinstanz: $ADRESSE/index.php?route=%2F  (admin/admin)"
