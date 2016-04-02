#!/bin/bash

#
function _abort {
	_log "ABORT: $1"
	>&2 echo -e "\nABORT: $1\n\n"
	exit 1;
}


#
function _log {
	echo "$1"
}


#
function _git_clone {
	cd source

	_log "Install $1 via github"

	if test -d $1
	then
		_log "Remove old $1 version"
		rm -rf $1
	fi

	_log "git clone https://github.com/$1/$1".git
	git clone "https://github.com/$1/$1".git

	cd ..
}


#
function _update_PHPMailer {
	_log "Update PHPMailer"
	cp source/PHPMailer/*.php PHPMailer/
	cp -r source/PHPMailer/extras PHPMailer/
}


#-------------------------------------------------------------------------
# M A I N
#-------------------------------------------------------------------------

if ! test -d ../other; then
	_abort "Run from directory other"
fi

test -d source || mkdir source


case $1 in
PHPMailer)
	_git_clone PHPMailer
  ;;

*)
  echo -e "\nSYNTAX: $0 [PHPMailer] [[update]]\n\n" 1>&2
  exit 1
esac

if test "$2" = "update"
then
	_update_$1
fi

