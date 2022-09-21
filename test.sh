docker build -t cache-service-tester .
docker run -v "${PWD}:/var/www/html" cache-service-tester /usr/local/bin/composer test