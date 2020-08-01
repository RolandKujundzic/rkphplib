#!/bin/bash

#--
# Install ubuntu packages
# shellcheck disable=SC2016
#--
function update_ubuntu {
	_require_program apt

	_sudo 'apt -y update'

	_confirm 'Install php packages cli, sqlite, curl, gd, mcrypt, xdebug and mbstring' 1
	if test "$CONFIRM" = 'y'; then
		_apt_install php-cli php-sqlite php-curl php-gd php-mcrypt php-xdebug php-mbstring
		_sudo "php5enmod mcrypt"
	fi

	_confirm 'Install nginx and php-fpm'
	if test "$CONFIRM" = 'y'; then
		_apt_install nginx php-fpm
	fi

	_confirm 'Install mysql-server, mysql-client and php-mysql'
	if test "$CONFIRM" = 'y'; then
		_apt_install mysql-server mysql-client php-mysql
	fi

	local site
	site=/etc/nginx/sites-available/default

	if [[ -f "$site" && ! -f "${site}.orig" ]]; then
		_orig "$site"
		echo 'server {
listen 80 default_server; root /var/www/html; index index.html index.htm index.php; server_name localhost;
location / { try_files $uri $uri/ =404; }
location ~ \.php$ { fastcgi_pass unix:/var/run/php5-fpm.sock; fastcgi_index index.php; include fastcgi_params; }
}' > "$site"
	fi

	echo "start php5-fpm + nginx + mysql"
	_service php5-fpm restart
	_service nginx restart
	_service mysql restart
}

