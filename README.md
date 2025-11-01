# 🧠 MSE - Application de rapports d'intervention

Application web PHP permettant :
- La création de rapports d’intervention
- La génération automatique de PDF
- L’envoi par e-mail aux clients

## 🚀 Déploiement
- Hébergement : [Render.com](https://render.com)
- Langage : PHP 8.2 (Apache)
- Base de données : PostgreSQL
- PDF : TCPDF
- Envoi d’e-mails : PHPMailer

## ⚙️ Installation locale
```bash
composer install
php -S localhost:8080 -t public
