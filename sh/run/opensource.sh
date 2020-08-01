#!/bin/bash

#--
# Install opensource packages
#
# @param package (dropzone)
#--
function opensource {
	test -z "$1" && _syntax "opensource [dropzone]"

	_mkdir opensource
  _cd opensource

	case $1 in
		dropzone)
  		_git_checkout "https://gitlab.com/meno/dropzone.git" dropzone
			_cd dropzone
			npm install
			npm test
			echo "start dropzone jekyll server on http://127.0.0.1:400 with"
			echo "cd opensource/dropzone/; grunt build-website; cd website; jekyll serve"
			_cd ..
  		;;
		*)
			_syntax "opensource [dropzone]"
	esac

	_cd ..
}

