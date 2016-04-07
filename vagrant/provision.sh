#!/usr/bin/env bash

WC_PATH=/var/www/html/woocommerce
DOMAIN=woocommerce.local
ADMIN_USER=admin
ADMIN_EMAIL=admin@example.com
ADMIN_PASS=password123123

##We're not doing any installs interactively
export DEBIAN_FRONTEND=noninteractive

apt-get update
apt-get install -y git
apt-get install -y curl
apt-get install -y build-essential vim-nox
apt-get install -y unzip
apt-get install -y subversion

## Setup locales
export LANGUAGE=en_GB.UTF-8
export LANG=en_GB.UTF-8
export LC_ALL=en_GB.UTF-8
locale-gen en_GB.UTF-8
dpkg-reconfigure locales

## Install MySQL and PHP
echo "mysql-server-5.5 mysql-server/root_password password 123" | sudo debconf-set-selections
echo "mysql-server-5.5 mysql-server/root_password_again password 123" | sudo debconf-set-selections
apt-get install -y mysql-server
apt-get install -y apache2 php5 php5-mysql php5-gd php5-mcrypt php5-curl php5-xdebug

echo 'ServerName localhost' >> /etc/apache2/apache2.conf

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
    echo 'xdebug.remote_enable=on
    xdebug.remote_connect_back=on
    xdebug.idekey="PHPSTORM"
    xdebug.extended_info=1' >> /etc/php5/mods-available/xdebug.ini
    ln -s /vagrant/assets/001-woocommerce.conf /etc/apache2/sites-enabled/001-woocommerce.conf
    service apache2 restart

    ## Install WordPress, enabling debug and setting direct plugin access
    sudo -u vagrant -i -- wp core download --path=$WC_PATH
    sudo -u vagrant -i -- wp core config --dbname=woocommerce --dbuser=root --dbpass=123 --path=$WC_PATH \
    --extra-php <<PHP
    define( 'WP_DEBUG', true );
    define( 'WP_DEBUG_LOG', true );
    define('FS_METHOD', 'direct');
PHP

    sudo -u vagrant -i -- wp core install --url=$DOMAIN --title="Fyndiq Test Store" \
    --admin_user=$ADMIN_USER --admin_password=$ADMIN_PASS --admin_email=$ADMIN_EMAIL --path=$WC_PATH

    ## Install WooCommerce
    sudo -u vagrant -i -- wp plugin install --path=$WC_PATH woocommerce --activate

    ## Install woocommerce-fyndiq
    ln -s /opt/fyndiq-woocommerce-module/src $WC_PATH/wp-content/plugins/woocommerce-fyndiq
    sudo -u vagrant -i -- wp plugin activate --path=$WC_PATH woocommerce-fyndiq

    chown -R vagrant:www-data $WC_PATH
    chmod -R 775 $WC_PATH

    ## Automatically set the currency and country in WooCommerce
     mysql -uroot -p123 --database=woocommerce -e "UPDATE wp_options SET option_value = 'SE' WHERE option_name = 'woocommerce_default_country'"
     mysql -uroot -p123 --database=woocommerce -e "UPDATE wp_options SET option_value = 'SEK' WHERE option_name = 'woocommerce_currency'"

    ## Add hosts to file
    echo "192.168.44.45  fyndiq.local" >> /etc/hosts
    echo "127.0.0.1  woocommerce.local" >> /etc/hosts

    ## Installing the tests
    sudo mkdir /opt/wptests
    sudo chown -R vagrant /opt/wptests
    /opt/fyndiq-woocommerce-module/bin/install-wp-tests.sh wp-test root 123 localhost 4.4.2
    svn co --quiet https://develop.svn.wordpress.org/tags/4.4.2/tests/phpunit/includes/ /opt/wptests/wordpress-tests-lib/includes
fi
