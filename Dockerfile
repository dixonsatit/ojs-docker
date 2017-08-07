FROM php:5.6-apache

LABEL maintainer "Sathit Seethaphon <dixonsatit@gmail.com>" 

ENV OJS_VERSION 3.0.2

WORKDIR /var/www

RUN a2enmod rewrite expires

# install the PHP extensions we need
RUN apt-get -qqy update \
    && apt-get install -qqy libpng12-dev libjpeg-dev libmcrypt-dev libxml2-dev libxslt-dev cron logrotate \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-configure gd --with-png-dir=/usr --with-jpeg-dir=/usr \
    && docker-php-ext-install gd mysqli mysql opcache mcrypt soap xsl zip

# set recommended PHP.ini settings
# see https://secure.php.net/manual/en/opcache.installation.php
RUN { \
		echo 'opcache.memory_consumption=128'; \
		echo 'opcache.interned_strings_buffer=8'; \
		echo 'opcache.max_accelerated_files=4000'; \
		echo 'opcache.revalidate_freq=60'; \
		echo 'opcache.fast_shutdown=1'; \
		echo 'opcache.enable_cli=1'; \
	} > /usr/local/etc/php/conf.d/opcache-recommended.ini \
  && a2enmod rewrite

#upstream tarballs include ./ojs-${OJS_VERSION}/ so this gives us /var/www/ojs
RUN curl -o /var/www/ojs.tar.gz -SL http://pkp.sfu.ca/ojs/download/ojs-${OJS_VERSION}.tar.gz \
	&& tar -xzf /var/www/ojs.tar.gz -C /var/www \
	&& rm /var/www/ojs.tar.gz \
  && mv /var/www/ojs-${OJS_VERSION} /var/www/ojs \

  # creating a directory to save uploaded files.
  && mkdir -p /var/www/files/ \
  && chown -R www-data:www-data /var/www/ \
  && chown -R www-data:www-data /var/www/files \
  && chmod -R 777 /var/www/files/

VOLUME ["/var/www/files"]

# Add crontab running runSheduledTasks.php
COPY ojs-crontab.conf /ojs-crontab.conf
RUN sed -i 's:INSTALL_DIR:'`pwd`':' /ojs-crontab.conf \
    && sed -i 's:FILES_DIR:/var/www/ojs/files:' /ojs-crontab.conf \
    && echo "$(cat /ojs-crontab.conf)" \
    # Use the crontab file
    && crontab /ojs-crontab.conf \
    && touch /var/log/cron.log

COPY 000-default.conf /etc/apache2/sites-enabled/000-default.conf

EXPOSE 80



# Add startup script to the container.
COPY ojs-startup.sh /ojs-startup.sh
# Execute the containers startup script which will start many processes/services
CMD ["/bin/bash", "/ojs-startup.sh"]