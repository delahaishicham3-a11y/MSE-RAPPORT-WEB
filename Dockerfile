# -----------------------------
# 🐳 Dockerfile pour Render.com
# -----------------------------

# Étape 1 : Image PHP + Apache officielle
FROM php:8.2-apache

# Étape 2 : Installer les dépendances système
RUN apt-get update && apt-get install -y \
    git unzip libpq-dev libzip-dev zip \
    && docker-php-ext-install pdo pdo_pgsql

# Étape 3 : Installer Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Étape 4 : Copier le projet dans le conteneur
WORKDIR /var/www/html
COPY . .

# Étape 5 : Installer les dépendances PHP via Composer
RUN composer install --no-interaction --no-dev --optimize-autoloader

# Étape 6 : Configurer Apache
# (optionnel : changer le dossier racine si tu utilises /public)
RUN sed -i 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf

# Étape 7 : Autoriser les fichiers .env
RUN a2enmod rewrite
RUN echo "<Directory /var/www/html>" >> /etc/apache2/apache2.conf
RUN echo "AllowOverride All" >> /etc/apache2/apache2.conf
RUN echo "Require all granted" >> /etc/apache2/apache2.conf
RUN echo "</Directory>" >> /etc/apache2/apache2.conf


# Étape 8 : Exposer le port HTTP
EXPOSE 80

# Étape 9 : Lancer Apache
CMD mkdir -p /tmp/sessions && chmod -R 777 /tmp/sessions && apache2-foreground
