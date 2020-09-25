#!/bin/bash

test -f run.php || exit 1

if php run.php | tee run.log; then
	RESULT=PASS
else
	RESULT=FAIL
fi

if test "$RESULT" = 'PASS'; then
	rm run.log
	rm -rf -- */out
fi

