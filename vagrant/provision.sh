#!/usr/bin/env bash

WC_PATH=/var/www/html/woocommerce
DOMAIN=woocommerce.local
ADMIN_USER=admin
ADMIN_EMAIL=admin@example.com
ADMIN_PASS=password123123

apt-get update
apt-get install -y build-essential vim-nox
apt-get install -y unzip

## Setup locales
locale-gen en_GB.UTF-8
dpkg-reconfigure locales

## Install MySQL and PHP
echo "mysql-server-5.5 mysql-server/root_password password 123" | sudo debconf-set-selections
echo "mysql-server-5.5 mysql-server/root_password_again password 123" | sudo debconf-set-selections
apt-get install -y mysql-server
apt-get install -y apache2 php5 php5-mysql php5-gd php5-mcrypt php5-curl php5-xdebug

## COMPOSER
if [ ! -e '/usr/local/bin/composer' ]; then
    curl -sS https://getcomposer.org/installer | php
    mv composer.phar /usr/local/bin/composer
fi

composer self-update

if [ ! -e '/usr/bin/wp' ]; then
    composer create-project wp-cli/wp-cli /usr/share/wp-cli --no-dev
    sudo ln -s /usr/share/wp-cli/bin/wp /usr/bin/wp
fi

## PHP error_log
if [ ! -f '/etc/php5/apache2/conf.d/30-error_log.ini' ]; then
    echo 'error_log=/tmp/php_error.log' > /etc/php5/apache2/conf.d/30-error_log.ini
fi

if [ ! -f "$WC_PATH/index.php" ]; then
    mkdir -p $WC_PATH

    chown -R vagrant:www-data $WC_PATH
    chmod -R 775 $WC_PATH

    ## Create database
    mysql -uroot -p123 -e 'create database woocommerce'

    ## Setup virtual host
    ln -s /vagrant/assets/001-woocommerce.conf /etc/apache2/sites-enabled/001-woocommerce.conf
    service apache2 restart

    ## Install WordPress
    sudo -u vagrant -i -- wp core download --path=$WC_PATH
    sudo -u vagrant -i -- wp core config --dbname=woocommerce --dbuser=root --dbpass=123 --path=$WC_PATH
    sudo -u vagrant -i -- wp core install --url=$DOMAIN --title="Fyndiq Test Store" \
    --admin_user=$ADMIN_USER --admin_password=$ADMIN_PASS --admin_email=$ADMIN_EMAIL --path=$WC_PATH

    ## Install WooCommerce
    sudo -u vagrant -i -- wp plugin install --path=$WC_PATH woocommerce --activate

    ## Install woocommerce-fyndiq
    ln -s /opt/fyndiq-woocommerce-module/src $WC_PATH/wp-content/plugins/woocommerce-fyndiq
    sudo -u vagrant -i -- wp plugin activate --path=$WC_PATH woocommerce-fyndiq

    ## Directly install plug-ins (no FTP)
    echo "define('FS_METHOD', 'direct');" >> $WC_PATH/wp-config.php

    chown -R vagrant:www-data $WC_PATH
    chmod -R 775 $WC_PATH

    ## Add hosts to file
    echo "192.168.44.44  fyndiq.local" >> /etc/hosts
    echo "127.0.0.1  woocommerce.local" >> /etc/hosts
fi
