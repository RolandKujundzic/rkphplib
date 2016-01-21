#!/bin/bash

case $1 in
composer)
	if ! test -f composer.phar; then
		curl -sS https://getcomposer.org/installer | php
	fi
	php composer.phar install
	;;
docs)
	test -d docs/api && rm -rf docs/api
	vendor/apigen/apigen/bin/apigen generate -s ./src -d ./docs/api
  ;;
*)
	echo -e "\nSYNTAX: $0 [composer|docs]\n"
	exit 1
esac

