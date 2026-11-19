#!/usr/bin/with-contenv bash
# shellcheck shell=bash
# ANTAREJA NMS: folder data persisten (evidence/upload) di volume /data
set -e
mkdir -p /data/antareja/evidence
chown -R librenms:librenms /data/antareja
chmod 750 /data/antareja
