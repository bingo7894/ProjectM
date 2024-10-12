FROM php:8.0-apache

# อัปเดตแพ็กเกจและติดตั้ง mysqli
RUN apt-get update && \
    apt-get upgrade -y && \
    docker-php-ext-install mysqli && \
    docker-php-ext-enable mysqli && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*

# คัดลอกไฟล์ PHP ไปยัง /var/www/html/
COPY ./html /var/www/html/
