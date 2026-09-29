# check=skip=CopyIgnoredFile
# (directive above must stay on line 1: COPY . necessarily references
# paths excluded by .dockerignore, which is intended; skip that lint)

# apache with php base image
FROM php:8.5-apache

# OCI labels; the source label links the image to the GitHub repository
# (this is what attaches the GHCR package to GigaionLLC/nanoNodeMonitor)
LABEL org.opencontainers.image.source="https://github.com/GigaionLLC/nanoNodeMonitor" \
      org.opencontainers.image.description="Server-side PHP monitor for Nano and Banano nodes" \
      org.opencontainers.image.licenses="GPL-3.0-only"

# silence AH00558 startup notice
RUN echo "ServerName localhost" > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername

# do not advertise Apache/PHP versions or show PHP errors to visitors
# (errors still go to the container log)
RUN printf 'ServerTokens Prod\nServerSignature Off\n' > /etc/apache2/conf-available/zz-hardening.conf \
    && a2enconf zz-hardening \
    && printf 'expose_php = Off\ndisplay_errors = Off\nlog_errors = On\n' > /usr/local/etc/php/conf.d/zz-hardening.ini

# copy all contents to public html
COPY . /var/www/html

# cleanup as we don't have a seperate public folder
RUN rm /var/www/html/Dockerfile /var/www/html/entry.sh

# entry shell
COPY entry.sh /entry.sh

# make it executable
RUN chmod +x /entry.sh

# go for it!
CMD ["/bin/bash", "/entry.sh"]
