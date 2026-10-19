#!/bin/sh
# Run only as root inside a disposable, network-isolated Docker test container.
# This fixture deliberately restores conflicting MPM links in the container only.
set -eu

if [ "$(id -u)" -ne 0 ] || [ ! -f /.dockerenv ]; then
    echo 'Run this regression test as root inside a disposable Docker container.' >&2
    exit 1
fi

for mpm in event worker; do
    for extension in load conf; do
        ln -sf "../mods-available/mpm_${mpm}.${extension}" \
            "/etc/apache2/mods-enabled/mpm_${mpm}.${extension}"
    done
done

# Restored base-image links must not change the build-time module selection.
apache2ctl -t
modules="$(apache2ctl -M)"
printf '%s\n' "$modules" | grep -q 'mpm_prefork_module'
printf '%s\n' "$modules" | grep -q 'php_module'
if [ "$(printf '%s\n' "$modules" | grep -c 'mpm_.*_module')" -ne 1 ]; then
    echo 'Apache must load only prefork, even with extra MPM symlinks present.' >&2
    exit 1
fi

echo 'PASS: restored event/worker load and configuration links cannot activate another MPM.'
