# -----------------------------
# 🐳 Dockerfile pour Render.com
# -----------------------------

# Étape 1 : Image PHP + Apache officielle
FROM php:8.2-apache

# Étape 2 : Installer les dépendances système et extensions PHP nécessaires
RUN apt-get update && apt-get install -y \
    git unzip libpq-dev libzip-dev zip \
    && docker-php-ext-install pdo pdo_pgsql zip

# Étape 3 : Installer Composer (depuis l'image composer officielle)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Étape 4 : Copier le projet dans le conteneur
WORKDIR /var/www/html
COPY . .

# Étape 5 : Installer les dépendances PHP du projet
RUN composer install --no-interaction --no-dev --optimize-autoloader

# Étape 6 : Configurer Apache
RUN sed -i 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf

# Activer mod_rewrite pour les routes propres
RUN a2enmod rewrite
RUN echo "<Directory /var/www/html/public>" >> /etc/apache2/apache2.conf \
    && echo "    AllowOverride All" >> /etc/apache2/apache2.conf \
    && echo "    Require all granted" >> /etc/apache2/apache2.conf \
    && echo "</Directory>" >> /etc/apache2/apache2.conf

# Étape 7 : Créer les dossiers et donner les bonnes permissions
RUN mkdir -p /var/www/html/uploads/reports /var/www/html/temp && \
    chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html/uploads /var/www/html/temp

# Étape 8 : Exposer le port HTTP
EXPOSE 80

# Étape 9 : Lancer Apache
CMD mkdir -p /tmp/sessions && \
    chown www-data:www-data /tmp/sessions && \
    chmod 750 /tmp/sessions && \
    apache2-foreground
