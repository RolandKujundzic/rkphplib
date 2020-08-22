#!/bin/bash
# shellcheck disable=SC2034

#--
# M A I N
#--

_parse_arg "$@"
APP_DESC='Administration script'
_rks_app "$0" "$@"

if test -s ../phplib/bin/toggle; then
	PATH_PHPLIB=$(realpath ../phplib)
elif test -s ../../bin/toggle; then
	PATH_PHPLIB=$(realpath ../..)
fi

case ${ARG[1]} in
	build)
		build;;
	composer)
		_composer "${ARG[2]}";;
	docs)
		docs;;
	docker_osx)
		docker_osx;;
	php)
		do_php "${ARG[2]}";;
	test)
		php test/run.php;;
	ubuntu)
		ubuntu;;
	*)
		_syntax "build|composer|docs|docker_osx|php|test|ubuntu"
esac

