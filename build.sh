#!/bin/bash

#------------------------------------------------------------------------------
# show where string function needs to change to mb_* version
#
function _mb_check() {
	# do not use ereg*
	MB_FUNCTIONS="parse_str split stripos stristr strlen strpos strrchr strrichr strripos strrpos strstr strtolower strtoupper strwidth substr_count substr"

	for a in $MB_FUNCTIONS
	do
		FOUND=`grep -d skip -r $a'(' src/*.php | grep -v 'mb_'$a'('`

		if ! test -z "$FOUND"
		then
			echo "$FOUND"
		fi
	done
}


#------------------------------------------------------------------------------
function _composer() {

	echo -e "\nOptional Parameter: ./build.sh composer [remove|init]\n\n"

	if test "$1" = "remove"; then
		echo "remove composer"
		rm -rf composer.phar vendor composer.lock ~/.composer
	fi

	if ! test -f composer.phar; then
		echo "install composer"
		curl -sS https://getcomposer.org/installer | php
	fi

	if test "$1" = "init"; then
		php composer.phar require --dev apigen/apigen
		php composer.phar require --dev phpunit/phpunit
		# php composer.phar require --dev phpdocumentor/phpdocumentor
	fi

	php composer.phar install
}


#------------------------------------------------------------------------------
function _docs() {
	# create apigen documentation
	test -d docs/api && rm -rf docs/api
	vendor/apigen/apigen/bin/apigen generate -s ./src -d ./docs/api
}


#------------------------------------------------------------------------------
function _test() {
	# run all tests
	php test/run.php
}


#------------------------------------------------------------------------------
function _abort() {
	echo -e "\nABORT: $1\n\n" 
	exit 1
}


#------------------------------------------------------------------------------
function _ubuntu() {
	test -f /usr/bin/apt-get || _abort "apt-get not found"
	echo "Install php + mysql + nginx"
	sudo apt-get -y update && sudo apt-get -y install php5-cli php5-sqlite php5-curl php5-gd php5-mcrypt php5-xdebug \
		php5-fpm mysql-server mysql-client php5-mysql nginx && sudo php5enmod mcrypt

	if ! test -f /etc/nginx/sites-available/default.original; then
		local SITE=/etc/nginx/sites-available/default
		echo "Overwrite $SITE (save previous version as default.original)"
		cp $SITE /etc/nginx/sites-available/default.original
		echo 'server {' > $SITE
		echo 'listen 80 default_server; root /var/www/html; index index.html index.htm index.php; server_name localhost;' >> $SITE
		echo 'location / { try_files $uri $uri/ =404; }' >> $SITE
		echo 'location ~ \.php$ { fastcgi_pass unix:/var/run/php5-fpm.sock; fastcgi_index index.php; include fastcgi_params; }' >> $SITE
		echo '}' >> $SITE
	fi

	echo "start php5-fpm + nginx + mysql"
	service php5-fpm restart
	service nginx restart
	service mysql restart
}


#------------------------------------------------------------------------------
function _docker_osx {
	echo -e "\nStart docker-machine default\n"
	docker-machine start default

	echo -e "\nSet docker env and restart rkphplib:\n"	
	echo 'eval $(docker-machine env default)'
	echo 'docker stop rkphplib; docker rm rkphplib'
	echo 'docker run -it -v $PWD:/var/www/html/rkphplib -p 80:80 --name rkphplib rolandkujundzic/ubuntu_trusty_dev bash'
	echo
}



case $1 in
composer)
	_composer $2
	;;
test)
	_test
	;;
docs)
	_docs
	;;
mb_check)
	_mb_check
	;;
ubuntu)
	_ubuntu
	;;
docker_osx)
	_docker_osx
	;;
*)
	echo -e "\nSYNTAX: $0 [composer|docs|test|mb_check|ubuntu|docker_osx]\n"
	exit 1
esac

