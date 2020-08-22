#!/bin/bash

#--
# Build phplib
# @global PATH_PHPLIB
# shellcheck disable=SC2034,SC2153
#--
function build {
	PATH_RKPHPLIB="src/"

	_syntax_check_php "src" "syntax_check_src.php"
	php syntax_check_src.php || _abort "php syntax_check_src.php"
	_rm syntax_check_src.php

	_syntax_check_php "bin" "syntax_check_bin.php"
	php syntax_check_bin.php || _abort "php syntax_check_bin.php"
	_rm syntax_check_bin.php

	if test -n "$PATH_PHPLIB"; then
		"$PATH_PHPLIB/bin/toggle" src log_debug on
		"$PATH_PHPLIB/bin/toggle" src log_debug off
	fi

	bin/plugin_map

	_require_program composer
	composer validate --no-check-all --strict

	for a in $(git status | grep 'src/' | sed -E 's#^.+src/#src/#'); do
		change=$(git diff --color=always "$a" | rks-filter diff | \
			sed -E -e 's#diff .+##' -e 's#index .+##' -e 's#\-\-\- .+##' -e 's#\+\+\+ .+##' | xargs | \
			sed -E -e 's/[^a-z0-9]//gi' -e 's/1m//g')
	
		if test -z "$change"; then
			_ok "$a"
		else
			git diff --color=always "$a" | rks-filter diff
		fi
	done
}

