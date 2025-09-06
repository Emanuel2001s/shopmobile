# Dockerfile otimizado para ShopMobile
# Corrige problemas identificados nos logs do Dokploy

FROM php:8.1-apache

# Instalar extensões PHP necessárias
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo pdo_mysql mysqli zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Habilitar mod_rewrite e outros módulos Apache necessários
RUN a2enmod rewrite expires deflate headers

# Copiar configuração Apache personalizada
COPY apache-config.conf /etc/apache2/conf-available/shopmobile.conf
RUN a2enconf shopmobile

# Definir ServerName globalmente para evitar warnings
RUN echo "ServerName shopmobile-app" >> /etc/apache2/apache2.conf

# Configurar diretório de trabalho
WORKDIR /var/www/html

# Copiar arquivos da aplicação
COPY . /var/www/html/

# Criar diretórios necessários e definir permissões
RUN mkdir -p /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/uploads

# Criar diretórios de log
RUN mkdir -p /var/log/apache2 \
    && touch /var/log/apache2/shopmobile_error.log \
    && touch /var/log/apache2/shopmobile_access.log \
    && touch /var/log/apache2/php_errors.log \
    && chown www-data:www-data /var/log/apache2/*.log

# Configurações PHP personalizadas
RUN echo "display_errors = Off" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "log_errors = On" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "error_log = /var/log/apache2/php_errors.log" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "upload_max_filesize = 10M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "post_max_size = 10M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/custom.ini

# Remover arquivos desnecessários
RUN rm -f /var/www/html/Dockerfile \
    && rm -f /var/www/html/docker-compose.yml \
    && rm -f /var/www/html/.dockerignore

# Expor porta 80
EXPOSE 80

# Comando para iniciar Apache
CMD ["apache2-foreground"]