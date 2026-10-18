# webtrees7 mit Python fuer tests/test_api.py
ARG WEBTREES7_IMAGE=webtrees7:76ac5d81d1
FROM ${WEBTREES7_IMAGE}
RUN apt-get update \
    && apt-get install -y --no-install-recommends python3 python3-pymysql \
    && rm -rf /var/lib/apt/lists/*
