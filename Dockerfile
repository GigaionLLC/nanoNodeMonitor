# apache with php base image
FROM php:8.5-apache

# OCI labels; the source label links the image to the GitHub repository
# (this is what attaches the GHCR package to GigaionLLC/nanoNodeMonitor)
LABEL org.opencontainers.image.source="https://github.com/GigaionLLC/nanoNodeMonitor" \
      org.opencontainers.image.description="Server-side PHP monitor for Nano and Banano nodes" \
      org.opencontainers.image.licenses="GPL-3.0-only"

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
