#!/usr/bin/env sh
set -eu

: "${WP_DEPLOY_USER:?Set WP_DEPLOY_USER to the SSH account}"
: "${WP_DEPLOY_HOST:?Set WP_DEPLOY_HOST to the WordPress host}"
: "${WP_DEPLOY_PATH:?Set WP_DEPLOY_PATH to the remote wp-content/plugins directory}"

WP_DEPLOY_PORT="${WP_DEPLOY_PORT:-22}"

rsync -avz \
  -e "ssh -p ${WP_DEPLOY_PORT}" \
  cinemashowtimes-json \
  "${WP_DEPLOY_USER}@${WP_DEPLOY_HOST}:${WP_DEPLOY_PATH%/}/"
