#!/bin/bash
MERGE2RUN="abort apigen_doc composer confirm mb_check rm abort custom main"


#------------------------------------------------------------------------------
# Abort with error message.
#
# @param abort message
#------------------------------------------------------------------------------
function _abort {
	echo -e "\nABORT: $1\n\n" 1>&2
	exit 1
}


#------------------------------------------------------------------------------
# Create apigen documentation for php project.
#
# @param source directory (optional, default = src)
# @param doc directory (optional, default = docs/api)
# @require _composer _abort _confirm _rm
#------------------------------------------------------------------------------
function _apigen_doc {

	if ! test -d vender/apigen/apigen; then
		_composer init
	fi

	local SRC_DIR=./src
	local DOC_DIR=./docs/api

	if ! test -z "$1"; then
		SRC_DIR="$1"
	fi

	if ! test -z "$2"; then
		DOC_DIR="$2"
	fi

	if ! test -d "$SRC_DIR"; then
		_abort "no such directory [$SRC_DIR]"
	fi

	if test -d "$DOC_DIR"; then
		_confirm "Remove existing documentation directory [$DOC_DIR] ?"
		if test "$CONFIRM" = "y"; then
			_rm "$DOC_DIR"
		fi
	fi

	vendor/apigen/apigen/bin/apigen generate -s "$SRC_DIR" -d "$DOC_DIR"
}

#------------------------------------------------------------------------------
# Install composer (getcomposer.org). If no parameter is given ask for action
# or execute default action (install composer if missing otherwise update) after
# 10 sec. 
#
# @param [install|update|remove] (empty = default = update or install)
# @require _abort _rm
#------------------------------------------------------------------------------
function _composer {
	local DO="$1"
	local GLOBAL_COMPOSER=`which composer`
	local LOCAL_COMPOSER=

	if test -f "composer.phar"; then
		LOCAL_COMPOSER=composer.phar
	fi

	if test -z "$DO"; then
		echo -e "\nWhat do you want to do?\n"

		if test -z "$GLOBAL_COMPOSER" && test -z "$LOCAL_COMPOSER"; then
			DO=l
			echo "[g] = global composer installation: /usr/local/bin/composer"
			echo "[l] = local composer installation: composer.phar"
		else
			if test -f composer.json; then
				DO=i
				if test -d vendor; then
					DO=u
				fi

				echo "[i] = install packages from composer.json"
				echo "[u] = update packages from composer.json"
			fi

			if ! test -z "$LOCAL_COMPOSER"; then
				echo "[r] = remove local composer.phar"
			fi
		fi

 		echo -e "[q] = quit\n\n"
		echo -n "Type ENTER or wait 10 sec to select default. Your Choice? [$DO]  "
		read -n1 -t 10 USER_DO
		echo

		if ! test -z "$USER_DO"; then
			DO=$USER_DO
		fi

		if test "$DO" = "q"; then
			return
		fi
	fi

	if test "$DO" = "remove" || test "$DO" = "r"; then
		echo "remove composer"
		_rm "composer.phar vendor composer.lock ~/.composer"
	fi

	if test "$DO" = "g" || test "$DO" = "l"; then
		php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
		php -r "if (hash_file('SHA384', 'composer-setup.php') === '544e09ee996cdf60ece3804abc52599c22b1f40f4323403c44d44fdfdd586475ca9813a858088ffbc1f233e9b180f061') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"

		test -f composer-setup.php || _abort "composer-setup.php missing"

		echo -n "install composer as "
		if test "$DO" = "g"; then
			echo "/usr/local/bin/composer - Enter root password if asked"
			sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer
		else
			echo "composer.phar"
			php composer-setup.php
		fi

		php -r "unlink('composer-setup.php');"

		# curl -sS https://getcomposer.org/installer | php
	fi

	local COMPOSER=
	if ! test -z "$LOCAL_COMPOSER"; then
		COMPOSER="php composer.phar"
	elif ! test -z "$GLOBAL_COMPOSER"; then
		COMPOSER="composer"
	fi

	if test -f composer.json; then
		if test "$DO" = "install" || test "$DO" = "i"; then
			$COMPOSER install
		elif test "$DO" = "update" || test "$DO" = "u"; then
			$COMPOSER update
		fi
	fi
}


#------------------------------------------------------------------------------
# Show "message  Press y or n  " and wait for key press. 
# Set CONFIRM=y if y key was pressed. Otherwise set CONFIRM=n if any other 
# key was pressed or 10 sec expired.
#
# @param string message
# @export CONFIRM
#------------------------------------------------------------------------------
function _confirm {
	CONFIRM=n

	echo -n "$1  y [n]  "
	read -n1 -t 10 CONFIRM
	echo

	if test "$CONFIRM" != "y"; then
		CONFIRM=n
  fi
}


#------------------------------------------------------------------------------
# Show where php string function needs to change to mb_* version.
#------------------------------------------------------------------------------
function _mb_check {

	echo -e "\nSearch all *.php files in src/ - output filename if string function\nmight need to be replaced with mb_* version.\n"
	echo -e "Type any key to continue or wait 5 sec.\n"

	read -n1 -t 5 ignore_keypress

	# do not use ereg*
	MB_FUNCTIONS="parse_str split stripos stristr strlen strpos strrchr strrichr strripos strrpos strstr strtolower strtoupper strwidth substr_count substr"

	local a=; for a in $MB_FUNCTIONS
	do
		FOUND=`grep -d skip -r --include=*.php $a'(' src | grep -v 'mb_'$a'('`

		if ! test -z "$FOUND"
		then
			echo "$FOUND"
		fi
	done
}


#------------------------------------------------------------------------------
# Remove files/directories.
#
# @param path_list
# @param int (optional - abort if set and path is invalid)
# @require _abort
#------------------------------------------------------------------------------
function _rm {

	if test -z "$1"; then
		_abort "Empty remove path list"
	fi

	local a=; for a in $1
	do
		if ! test -f $a && ! test -d $a
		then
			if ! test -z "$2"; then
				_abort "No such file or directory $a"
			fi
		else
			echo "remove $a"
			rm -rf $a
		fi
	done
}


#------------------------------------------------------------------------------
# Abort with error message.
#
# @param abort message
#------------------------------------------------------------------------------
function _abort {
	echo -e "\nABORT: $1\n\n" 1>&2
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
	# run all tests
	php test/run.php
	;;
docs)
	_apigen_doc
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

