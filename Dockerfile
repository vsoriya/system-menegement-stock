# Build CSS/JS assets
FROM node:22-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

# PHP + Nginx for Render
FROM richarvey/nginx-php-fpm:3.1.6

COPY . .
COPY --from=frontend /app/public/build ./public/build

ENV WEBROOT=/var/www/html/public
ENV PHP_ERRORS_STDERR=1
ENV RUN_SCRIPTS=1
ENV REAL_IP_HEADER=1
ENV SKIP_COMPOSER=0
ENV COMPOSER_ALLOW_SUPERUSER=1

CMD ["/start.sh"]
