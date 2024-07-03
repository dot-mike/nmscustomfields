ARG LIBRENMS_VERSION=23.8.2
FROM mcr.microsoft.com/devcontainers/php:1-8.1-bullseye

COPY php.ini /usr/local/etc/php/conf.d/99-php.ini

RUN apt-get update && export DEBIAN_FRONTEND=noninteractive \
    && apt-get install -y mariadb-client \
    acl curl fping git graphviz imagemagick mtr-tiny nmap rrdtool \
    snmp snmpd whois unzip iputils-ping \
    python3-pymysql python3-dotenv python3-redis python3-setuptools python3-systemd python3-pip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN apt update \
    && apt install -y zlib1g-dev g++ git libicu-dev zip libzip-dev libpq-dev \
    && docker-php-ext-configure zip \
    && docker-php-ext-install zip

RUN apt update \
    && apt install -y libsnmp-dev \
    && docker-php-ext-configure snmp --with-snmp \
    && docker-php-ext-install snmp

RUN apt-get update && apt-get install -y \
    libfreetype-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd

RUN docker-php-ext-install mysqli pdo pdo_mysql sockets

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR "/workspaces/${localWorkspaceFolderBasename}"
