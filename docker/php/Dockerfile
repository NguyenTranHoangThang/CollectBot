FROM php:7.4-fpm

RUN apt update;
RUN apt install -y libicu-dev;
RUN docker-php-ext-install pdo_mysql intl;
RUN apt-get update && apt-get install -y \
    		libfreetype6-dev \
    		libjpeg62-turbo-dev \
    		libpng-dev \
    	&& docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/ \
    	&& docker-php-ext-install -j$(nproc) gd;
RUN apt-get update && apt-get install -y \
    zlib1g-dev \
    libzip-dev ;
RUN docker-php-ext-install zip;
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

