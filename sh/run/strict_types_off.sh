#!/bin/bash

#--
# Call $PATH_PHPLIB/bin/toggle $1 strict_types off
# @param file
# @global RKBASH_DIR PATH_PHPLIB
#--
function strict_types_off {
  local log has_error php_error
	log="$RKBASH_DIR/$(echo "sto_$1.log" | sed -E 's/\//:/g')"

	_mkdir "$RKBASH_DIR"

  echo -e "remove strict types from $1 (see $log)"
  "$PATH_PHPLIB/bin/toggle" "$1" strict_types off >"$log" 2>&1

  has_error=$(tail -10 "$log" | grep 'ERROR in ')
  php_error=$(tail -2 "$log" | grep 'PHP Parse error')

  if [[ -n "$has_error" || -n "$php_error" ]]; then
    _abort "$has_error"
  fi
}

