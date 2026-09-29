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

# let www-data reach the config. /opt only needs traverse (o+x), not
# read/list: with the legacy `-v ~:/opt` mount /opt *is* the host home
# directory, which must not be made listable for other users.
chmod o+x /opt
chmod 755 "${monitordir}"

# start apache
apache2-foreground
