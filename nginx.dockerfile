FROM nginx:stable-alpine

RUN sed -i "s/user  nginx/user root/g" /etc/nginx/nginx.conf

COPY ./nginx/default.conf /etc/nginx/conf.d/

RUN mkdir -p /var/www/html

WORKDIR /var/www/html

COPY ./src /var/www/html