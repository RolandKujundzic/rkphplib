#!/bin/bash


#------------------------------------------------------------------------------
function _lib5 {
	if test -z "$PATH_PHPLIB"; then
		_abort "export PATH_PHPLIB"
	fi

	_mkdir lib5
	rsync -a --delete src bin test lib5
	"$PATH_PHPLIB/bin/toggle" lib5 strict_types off
}


#------------------------------------------------------------------------------
function _build {
	PATH_RKPHPLIB="src/"

	_syntax_check_php "src" "syntax_check_src.php"
	php syntax_check_src.php || _abort "php syntax_check_src.php"
	_rm syntax_check_src.php

	_syntax_check_php "bin" "syntax_check_bin.php"
	php syntax_check_bin.php || _abort "php syntax_check_bin.php"
	_rm syntax_check_bin.php

	if ! test -z "$PATH_PHPLIB"; then
		"$PATH_PHPLIB/bin/toggle" log_debug on
		"$PATH_PHPLIB/bin/toggle" log_debug off
		_lib5
	fi

	bin/plugin_map
	composer validate --no-check-all --strict
  git status
}


#------------------------------------------------------------------------------
function _ubuntu {
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


#------------------------------------------------------------------------------
function _opensource {

	if ! test -z "$1"; then
		_mkdir opensource
  	_cd opensource
	fi

	case $1 in
	dropzone)
  	_git_checkout "https://gitlab.com/meno/dropzone.git" dropzone
		cd dropzone
		npm install
		npm test
		echo "start dropzone jekyll server on http://127.0.0.1:400 with"
		echo "cd opensource/dropzone/; grunt build-website; cd website; jekyll serve"
		cd ..
  	;;
	*)
		_syntax "opensource [dropzone]"
	esac

  cd ..
}

