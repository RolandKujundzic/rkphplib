#!/bin/bash

APP=$0
APP_DESC=

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
opensource)
	_opensource $2
	;;
*)
	_syntax "[opensource|composer|docs|test|mb_check|ubuntu|docker_osx]"
esac

