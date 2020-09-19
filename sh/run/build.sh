#!/bin/bash

#--
# Build rkphplib
# @global PATH_PHPLIB
# shellcheck disable=SC2034,SC2153
#--
function build {
	PATH_RKPHPLIB="src/"

	_composer

	_require_dir "$PATH_PHPLIB"
	"$PATH_PHPLIB/bin/toggle" src log_debug on
	"$PATH_PHPLIB/bin/toggle" src log_debug off

	_syntax_check_php 'src' 'syntax_check_src.php' 1
	_syntax_check_php 'bin' 'syntax_check_bin.php' 1

	bin/plugin_map

	_git_status
}

