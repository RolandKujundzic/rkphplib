#!/bin/bash

#------------------------------------------------------------------------------
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



case $1 in
composer)
	# install composer
	if ! test -f composer.phar; then
		curl -sS https://getcomposer.org/installer | php
	fi
	php composer.phar install
	;;
test)
	# run all tests
	php test/run.php
	;;
docs)
	# create apigen documentation
	test -d docs/api && rm -rf docs/api
	vendor/apigen/apigen/bin/apigen generate -s ./src -d ./docs/api
	;;
mb_check)
	# show where string function needs to change to mb_* version
	_mb_check
	;;
*)
	echo -e "\nSYNTAX: $0 [composer|docs|test|mb_check]\n"
	exit 1
esac

