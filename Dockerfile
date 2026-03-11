FROM adminer:latest

USER root

RUN mkdir -p /var/lib/adminer /var/www/html/plugins-enabled \
    && chown -R adminer:adminer /var/lib/adminer /var/www/html/plugins-enabled

COPY plugins-enabled/ /var/www/html/plugins-enabled/

USER adminer
