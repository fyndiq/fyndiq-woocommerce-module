#!/usr/bin/env bash

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
apt-get install -y apache2 php5 php5-mysql php5-gd php5-mcrypt php5-curl

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
