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
function _ubuntu() {
	echo "Install php5-cli php5-sqlite"
	sudo -s apt-get install php5-cli php5-sqlite
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
*)
	echo -e "\nSYNTAX: $0 [composer|docs|test|mb_check|ubuntu]\n"
	exit 1
esac

