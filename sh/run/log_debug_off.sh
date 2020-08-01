#!/bin/bash

#--
# Call $PATH_PHPLIB/bin/toggle $1 log_debug on
#
# @param file
# @global RKBASH_DIR PATH_PHPLIB
#--
function log_debug_off {
  local log has_error php_error
	log="$RKBASH_DIR/$(echo "ldo_$1.log" | sed -E 's/\//:/g')"

	_mkdir "$RKBASH_DIR"

  echo -e "update log debug line numbers in $1 (see $log)"
  "$PATH_PHPLIB/bin/toggle" "$1" log_debug on >"$log" 2>&1
  "$PATH_PHPLIB/bin/toggle" "$1" log_debug off >>"$log" 2>&1

  has_error=$(tail -10 "$log" | grep 'ERROR in ')
  php_error=$(tail -2 "$log" | grep 'PHP Parse error')

  if [[ -n "$has_error" || -n "$php_error" ]]; then
    _abort "$has_error"
  fi
}

