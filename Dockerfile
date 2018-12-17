FROM php:7.2-apache

RUN apt-get update
RUN apt-get install -y htop \
        vim \
        libfreetype6-dev \
		libjpeg62-turbo-dev \
		libpng-dev \
        wget \
        gnupg

# Set new Apache root dir
ENV APACHE_DOCUMENT_ROOT /var/www/html/web

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Enable mod rewrite for clean urls
RUN a2enmod rewrite

# Install additional PHP Libraries
RUN docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/ \
	&& docker-php-ext-install -j$(nproc) gd \
    && docker-php-ext-install opcache \
    && docker-php-ext-install pdo_mysql
#   && apt-get install -y php7.0-mysql \

# Install PHP packes via PECL, xdebug is for dev only
RUN pecl install xdebug-2.6.0 \
	&& docker-php-ext-enable xdebug