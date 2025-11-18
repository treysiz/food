# 使用官方 Apache + PHP
FROM php:8.2-apache

# 开启 Apache rewrite
RUN a2enmod rewrite

# 复制 public 的文件到 Apache Web Root
COPY public/ /var/www/html/

# 权限
RUN chown -R www-data:www-data /var/www/html
