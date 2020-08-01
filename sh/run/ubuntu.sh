#!/bin/bash

#--
# Install php, nginx and mariadb (apt packages)
#--
function ubuntu {
	_confirm 'Install php packages' 1
	test "$CONFIRM" = 'y' && _install_php

	_confirm 'Install mariadb-server, mariadb-client and php-mysql' 1
	test "$CONFIRM" = 'y' && _install_mariadb

	_confirm 'Install nginx and php-fpm' 1
	test "$CONFIRM" = 'y' && _nginx_php_fpm
}

