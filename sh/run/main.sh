#!/bin/bash

APP=$0
APP_DESC=

export APP_PID="$APP_PID $$"

if test -s ../phplib/bin/toggle; then
	PATH_PHPLIB=`realpath ../phplib`
elif test -s ../../bin/toggle; then
	PATH_PHPLIB=`realpath ../..`
fi

case $1 in
build)
	_build
	;;
php5)
	_php5
	;;
composer)
	_composer $2
	;;
test)
	# run all tests
	php test/run.php
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
docker_osx)
	_docker_osx
	;;
opensource)
	_opensource $2
	;;
*)
	_syntax "[build|opensource|composer|docs|php5|test|mb_check|ubuntu|docker_osx]"
esac

