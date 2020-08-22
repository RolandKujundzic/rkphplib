#!/bin/bash

#--
# Execute mb_check|v5|v7|server
# @param action
# shellcheck disable=SC2044,SC2034
#--
function do_php {
	local a

	APP_DESC="mb_check: show missing mb_*
v5: update php 5.6 version in v5.6
server: start buildin php server
syntax: check php syntax in src/"

	test -z "$1" && _syntax 'php mb_check|v5|server|syntax'

	case $1 in
		mb_check)
			_mb_check;;
		v5)
			build_php5;;
		server)
			_php_server;;
		syntax)
			for a in $(find src -name '*.php'); do
				php -l "$a" >/dev/null
			done
			;;
	esac
}

