#!/usr/bin/env bash

set -Eeuo pipefail

: "${DEPLOY_PATH:?DEPLOY_PATH must be set.}"
: "${RELEASE_SHA:?RELEASE_SHA must be set.}"

RELEASE_ARCHIVE="${RELEASE_ARCHIVE:-/tmp/print-for-me-release.tar.gz}"
KEEP_RELEASES="${KEEP_RELEASES:-5}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"

release_name="$(date +%Y%m%d%H%M%S)"
release_dir="${DEPLOY_PATH}/releases/${release_name}"
shared_dir="${DEPLOY_PATH}/shared"
current_dir="${DEPLOY_PATH}/current"
release_activated=false

cleanup_failed_release() {
    if [[ "${release_activated}" == "false" && -d "${release_dir}" ]]; then
        rm -rf "${release_dir}"
    fi
}

trap cleanup_failed_release ERR

mkdir -p "${DEPLOY_PATH}/releases"
mkdir -p "${shared_dir}/storage/app/private"
mkdir -p "${shared_dir}/storage/app/private/prints"
mkdir -p "${shared_dir}/storage/app/public"
mkdir -p "${shared_dir}/storage/logs"

if [[ ! -f "${shared_dir}/.env" ]]; then
    echo "Missing shared environment file at ${shared_dir}/.env" >&2
    exit 1
fi

mkdir -p "${release_dir}"
tar -xzf "${RELEASE_ARCHIVE}" -C "${release_dir}"

rm -f "${release_dir}/public/hot"

if [[ ! -f "${release_dir}/public/build/manifest.json" ]]; then
    echo "Missing Vite manifest at ${release_dir}/public/build/manifest.json" >&2
    exit 1
fi

if [[ ! -f "${release_dir}/.release-sha" ]]; then
    echo "Missing release SHA marker at ${release_dir}/.release-sha" >&2
    exit 1
fi

if [[ "$(<"${release_dir}/.release-sha")" != "${RELEASE_SHA}" ]]; then
    echo "Release SHA mismatch for ${release_dir}" >&2
    exit 1
fi

if [[ ! -f "${release_dir}/.release-manifest" ]]; then
    echo "Missing release manifest at ${release_dir}/.release-manifest" >&2
    exit 1
fi

(
    cd "${release_dir}"
    sha256sum -c .release-manifest
)

if [[ ! -f "${release_dir}/resources/views/vendor/mail/html/themes/print-for-me.css" ]]; then
    echo "Missing custom mail theme at ${release_dir}/resources/views/vendor/mail/html/themes/print-for-me.css" >&2
    exit 1
fi

mkdir -p "${release_dir}/storage/app"
mkdir -p "${release_dir}/storage/framework/cache"
mkdir -p "${release_dir}/storage/framework/cache/data"
mkdir -p "${release_dir}/storage/framework/sessions"
mkdir -p "${release_dir}/storage/framework/views"
rm -rf "${release_dir}/storage/app/private"
rm -rf "${release_dir}/storage/app/public"
rm -rf "${release_dir}/storage/logs"
ln -sfn "${shared_dir}/storage/app/private" "${release_dir}/storage/app/private"
ln -sfn "${shared_dir}/storage/app/public" "${release_dir}/storage/app/public"
ln -sfn "${shared_dir}/storage/logs" "${release_dir}/storage/logs"
ln -sfn "${shared_dir}/.env" "${release_dir}/.env"

mkdir -p "${release_dir}/bootstrap/cache"
rm -f "${release_dir}/bootstrap/cache/"*.php

shared_writable_paths=(
    "${shared_dir}/storage"
    "${shared_dir}/storage/app"
    "${shared_dir}/storage/app/private"
    "${shared_dir}/storage/app/public"
    "${shared_dir}/storage/logs"
)

release_writable_paths=(
    "${release_dir}/storage"
    "${release_dir}/storage/framework"
    "${release_dir}/storage/framework/cache"
    "${release_dir}/storage/framework/cache/data"
    "${release_dir}/storage/framework/sessions"
    "${release_dir}/storage/framework/views"
    "${release_dir}/bootstrap/cache"
)

if ! chgrp www-data "${shared_writable_paths[@]}"; then
    echo "Warning: unable to normalize shared storage group ownership; continuing." >&2
fi
if ! chmod ug+rwx "${shared_writable_paths[@]}"; then
    echo "Warning: unable to normalize shared storage permissions; continuing." >&2
fi
if ! chmod g+s "${shared_writable_paths[@]}"; then
    echo "Warning: unable to normalize shared storage setgid bits; continuing." >&2
fi

chgrp www-data "${release_writable_paths[@]}"
chmod ug+rwx "${release_writable_paths[@]}"
chmod g+s "${release_writable_paths[@]}"
chmod -R a+rX "${release_dir}/public/build"

cd "${release_dir}"

"${COMPOSER_BIN}" install --no-dev --prefer-dist --no-interaction --optimize-autoloader
"${PHP_BIN}" artisan migrate --force
"${PHP_BIN}" artisan optimize:clear
"${PHP_BIN}" artisan optimize
"${PHP_BIN}" artisan storage:link || true

ln -sfn "${release_dir}" "${current_dir}"
release_activated=true

cd "${current_dir}"
"${PHP_BIN}" artisan reload || "${PHP_BIN}" artisan queue:restart
"${PHP_BIN}" artisan schedule:interrupt || true

releases=()

while IFS= read -r old_release; do
    releases+=("${old_release}")
done < <(find "${DEPLOY_PATH}/releases" -mindepth 1 -maxdepth 1 -type d | sort)

if (( ${#releases[@]} > KEEP_RELEASES )); then
    for old_release in "${releases[@]:0:${#releases[@]}-KEEP_RELEASES}"; do
        rm -rf "${old_release}"
    done
fi

rm -f "${RELEASE_ARCHIVE}"

trap - ERR

echo "Deployment completed: ${release_name}"
