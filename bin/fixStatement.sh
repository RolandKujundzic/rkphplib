#!/bin/bash
#
# require[_once] and include[_once] are statements so [require|include][_once] '...' is preferred over require_once('...')
#

. /usr/local/lib/rkscript.sh


#------------------------------------------------------------------------------
function _fix_require {
	local has_require=`cat $1 | grep -E '^\s*(require|include|require_once|include_once)\('`
	local n=0

	if ! test -z "$has_require"; then
		cat $1 | sed -E 's/^(\s*)(require_once|require|include|include_once)\((.+)\)(.+)$/\1\2 \3\4/g' > $1.fix
		local has_changed=`diff -u $a $a.fix`

		if ! test -z "$has_changed"; then
			_mv $1.fix $1
		fi
	fi
}



#------------------------------------------------------------------------------
# M A I N
#------------------------------------------------------------------------------

for a in src/*.php src/*/*.php
do
	_fix_require $a
done 
