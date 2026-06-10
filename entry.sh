#!/bin/bash

# set the config directory
monitordir="/opt/nanoNodeMonitor"

# create config dir
mkdir -p "${monitordir}"

# check for config file
if [ ! -f "${monitordir}/config.php" ]; then
        echo "Config File not found, adding default."
        cp "/var/www/html/modules/config.sample.php" "${monitordir}/config.php"
fi

# create config symlink
ln -s $monitordir/config.php /var/www/html/modules/config.php

# migrate the config to the current schema if needed (idempotent; backs up
# the old file next to it on the volume). Never blocks the web server.
php /var/www/html/scripts/migrate-config.php || echo "WARNING: config migration failed, starting with existing config."

# change folder rights so www-data can read
chmod 755 /opt

# start apache
apache2-foreground
