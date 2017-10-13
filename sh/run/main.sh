#!/bin/bash

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
*)
	echo -e "\nSYNTAX: $0 [composer|docs|test|mb_check|ubuntu|docker_osx]\n"
	exit 1
esac

