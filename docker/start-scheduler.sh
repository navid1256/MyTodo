#!/bin/sh
set -eu

# Keep scheduling independent of the host and each user's task timezone.
export TZ=UTC

# Validate configuration without running jobs, reading secrets, or connecting to MySQL.
if [ "$#" -eq 1 ] && [ "$1" = '--check' ]; then
    exec /usr/local/bin/supercronic -test /etc/mytodo.crontab
fi

if [ "$#" -ne 0 ]; then
    echo 'Usage: mytodo-scheduler [--check]' >&2
    exit 2
fi

# Refuse accidental live delivery when creating or testing a new service.
if [ "${MYTODO_SCHEDULER_ENABLED:-false}" != 'true' ]; then
    echo 'Scheduler disabled. Set MYTODO_SCHEDULER_ENABLED=true only on the scheduler service.' >&2
    exit 2
fi

# Preserve the container environment, log job results, and finish active jobs on SIGTERM.
# Default execution prevents a job from overlapping itself inside this process.
exec /usr/local/bin/supercronic -json /etc/mytodo.crontab
