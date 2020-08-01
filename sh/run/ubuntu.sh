#!/bin/bash

#--
# Install php, nginx and mariadb (apt packages)
# shellcheck disable=SC2016,SC2012
#--
function ubuntu {
	_confirm 'Install php packages' 1
	test "$CONFIRM" = 'y' && _install_php

	_confirm 'Install mariadb-server, mariadb-client and php-mysql'
	test "$CONFIRM" = 'y' && _install_mariadb

	_nginx_php_fpm
}

