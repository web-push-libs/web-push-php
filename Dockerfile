FROM alpine:3.13.3

RUN apk add curl openssl php7 php7-curl php7-openssl php7-gmp php7-mbstring php7-json php7-phar

RUN curl -sS https://getcomposer.org/installer | php7 -- --install-dir=/usr/local/bin --filename=composer

RUN mkdir /app; cd /app; composer create-project minishlink/web-push-php-example

RUN cd /app/web-push-php-example; openssl ecparam -genkey -name prime256v1 -out keys/private_key.pem && \
   openssl ec -in private_key.pem -pubout -outform DER|tail -c 65|base64|tr -d '=' |tr '/+' '_-' >> keys/public_key.txt && \
   openssl ec -in private_key.pem -outform DER|tail -c +8|head -c 32|base64|tr -d '=' |tr '/+' '_-' >> keys/private_key.txt

EXPOSE 8000

CMD [ "php", "-S", "0.0.0.0:8000", "/app/web-push-php-example/router.php" ]