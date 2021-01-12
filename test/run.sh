#!/bin/bash

test -f run.php || exit 1

php run.php | tee run.log

if grep 'ERROR - FAIL' 'run.log' >/dev/null; then
	exit 1
else
	rm run.log
	rm -rf -- */out
fi

