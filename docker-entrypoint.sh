#!/bin/bash
set -e

# Remove conflicting MPM modules injected by platform / environment
rm -f /etc/apache2/mods-enabled/mpm_event.load
rm -f /etc/apache2/mods-enabled/mpm_event.conf
rm -f /etc/apache2/mods-enabled/mpm_worker.load
rm -f /etc/apache2/mods-enabled/mpm_worker.conf

# Ensure prefork MPM is enabled
a2enmod mpm_prefork > /dev/null 2>&1 || true

# Ensure directories exist and have proper permissions for upload
mkdir -p /var/www/html/assets/images/avatars /var/www/html/logs
chmod -R 777 /var/www/html/assets/images /var/www/html/logs > /dev/null 2>&1 || true

# Bind Apache to $PORT if set (Railway / Cloud)
if [ -n "$PORT" ] && [ "$PORT" != "80" ]; then
    sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
    sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf
fi

exec apache2-foreground
