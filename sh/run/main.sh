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
	php5)
		build_php5;;
	composer)
		_composer "${ARG[2]}";;
	test)
		php test/run.php;;
	docs)
		docs;;
	mb_check)
		_mb_check;;
	ubuntu)
		ubuntu;;
	docker_osx)
		docker_osx;;
	*)
		_syntax "build|composer|docs|docker_osx|mb_check|php5|test|ubuntu"
esac

