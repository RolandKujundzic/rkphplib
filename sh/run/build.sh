#!/bin/bash

#--
# Build rkphplib
# @global PATH_PHPLIB
# shellcheck disable=SC2034,SC2153
#--
function build {
	PATH_RKPHPLIB="src/"

	_composer

	_mkdir "$RKBASH_DIR" >/dev/null
	_require_dir "$PATH_PHPLIB"

	if ! "$PATH_PHPLIB/bin/toggle" src log_debug on  >"$RKBASH_DIR/log_debug_on.log"; then
		cat "$RKBASH_DIR/log_debug_on.log"
		_abort "bin/toggle src log_debug on"
	fi

	echo -e "bin/toggle src log_debug off\nsee: $RKBASH_DIR/log_debug_off.log"
	"$PATH_PHPLIB/bin/toggle" src log_debug off >"$RKBASH_DIR/log_debug_off.log"

	bin/plugin_map

	_syntax_check_php 'src' 'syntax_check_src.php' 1
	_syntax_check_php 'bin' 'syntax_check_bin.php' 1
	_syntax_check_php 'test'

	_git_status
}

