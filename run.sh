#!/bin/bash
MERGE2RUN="abort apigen_doc cd chmod composer composer_json confirm custom git_checkout license ln log mb_check mkdir phpdocumentor require_dir require_global require_owner require_priv require_program rm sudo syntax syntax_check_php wget  main"


#------------------------------------------------------------------------------
# Abort with error message. Use NO_ABORT=1 for just warning output.
#
# @exit
# @global APP, NO_ABORT
# @param abort message
#------------------------------------------------------------------------------
function _abort {
	if test "$NO_ABORT" = 1; then
		echo "WARNING: $1"
		return
	fi

	echo -e "\nABORT: $1\n\n" 1>&2

	local other_pid=

	if ! test -z "$APP_PID"; then
		# make shure APP_PID dies
		for a in $APP_PID; do
			other_pid=`ps aux | grep -E "^.+\\s+$a\\s+" | awk '{print $2}'`
			test -z "$other_pid" || kill $other_pid 2> /dev/null 1>&2
		done
	fi

	if ! test -z "$APP"; then
		# make shure APP dies
		other_pid=`ps aux | grep "$APP" | awk '{print $2}'`
		test -z "$other_pid" || kill $other_pid 2> /dev/null 1>&2
	fi

	exit 1
}


#------------------------------------------------------------------------------
# Create apigen documentation for php project in docs/apigen.
#
# @param source directory (optional, default = src)
# @param doc directory (optional, default = docs/apigen)
# @require _abort _require_program _require_dir _mkdir _cd _composer_json _confirm _rm
#------------------------------------------------------------------------------
function _apigen_doc {
  local DOC_DIR=./docs/apigen
	local PRJ="docs/.apigen"
	local BIN="$PRJ/vendor/apigen/apigen/bin/apigen"
	local SRC_DIR=./src

	_mkdir "$DOC_DIR"
	_mkdir "$PRJ"
	_require_program composer

	local CURR="$PWD"

	if ! test -f "$PRJ/composer.json"; then
		_cd "$PRJ"
		_composer_json "rklib/rkphplib_doc_apigen"
		composer require "apigen/apigen:dev-master"
		composer require "roave/better-reflection:dev-master#c87d856"
		_cd "$CURR"
	fi

	if ! test -s "$BIN"; then
		_cd "$PRJ"
		composer update
		_cd "$CURR"
	fi

	if ! test -z "$1"; then
		SRC_DIR="$1"
	fi

	if ! test -z "$2"; then
		DOC_DIR="$2"
	fi

	_require_dir "$SRC_DIR"

	if test -d "$DOC_DIR"; then
		_confirm "Remove existing documentation directory [$DOC_DIR] ?" 1
		if test "$CONFIRM" = "y"; then
			_rm "$DOC_DIR"
		fi
	fi

	echo "Create apigen documentation"
	echo "$BIN generate '$SRC_DIR' --destination '$DOC_DIR'"
	$BIN generate "$SRC_DIR" --destination "$DOC_DIR"
}


#------------------------------------------------------------------------------
# Change to directory $1. If parameter is empty and _cd was executed before 
# change to last directory.
#
# @param path
# @param do_not_echo
# @export LAST_DIR
# @require _abort 
#------------------------------------------------------------------------------
function _cd {
	local has_realpath=`which realpath`

	if ! test -z "$has_realpath" && ! test -z "$1"; then
		local curr_dir=`realpath "$PWD"`
		local goto_dir=`realpath "$1"`

		if test "$curr_dir" = "$goto_dir"; then
			return
		fi
	fi

	if test -z "$2"; then
		echo "cd '$1'"
	fi

	if test -z "$1"
	then
		if ! test -z "$LAST_DIR"
		then
			_cd "$LAST_DIR"
			return
		else
			_abort "empty directory path"
		fi
	fi

	if ! test -d "$1"; then
		_abort "no such directory [$1]"
	fi

	LAST_DIR="$PWD"

	cd "$1" || _abort "cd '$1' failed"
}


#------------------------------------------------------------------------------
# Change mode of entry $2 to $1. If chmod failed try sudo.
#
# @param file mode (octal)
# @param file path
# @require _abort _sudo
#------------------------------------------------------------------------------
function _chmod {

	if ! test -f "$2" && ! test -d "$2"; then
		_abort "no such file or directory [$2]"
	fi

	if test -z "$1"; then
		_abort "empty privileges parameter"
	fi

	local tmp=`echo "$1" | sed -e 's/[012345678]*//'`
	
	if ! test -z "$tmp"; then
		_abort "invalid octal privileges '$1'"
	fi

	local PRIV=`stat -c "%a" "$2"`

	if test "$1" = "$PRIV" || test "$1" = "0$PRIV"; then
		echo "keep existing mode $1 of $2"
		return
	fi

	_sudo "chmod -R $1 '$2'" 1
}


#------------------------------------------------------------------------------
# Install composer (getcomposer.org). If no parameter is given ask for action
# or execute default action (install composer if missing otherwise update) after
# 10 sec. 
#
# @param [install|update|remove] (empty = default = update or install)
# @require _composer_phar _abort _rm
#------------------------------------------------------------------------------
function _composer {
	local DO="$1"
	local GLOBAL_COMPOSER=`which composer`
	local LOCAL_COMPOSER=

	if test -f "composer.phar"; then
		LOCAL_COMPOSER=composer.phar
	fi

	if test -z "$DO"; then
		echo -e "\nWhat do you want to do?\n"

		if test -z "$GLOBAL_COMPOSER" && test -z "$LOCAL_COMPOSER"; then
			DO=l
			echo "[g] = global composer installation: /usr/local/bin/composer"
			echo "[l] = local composer installation: composer.phar"
		else
			if test -f composer.json; then
				DO=i
				if test -d vendor; then
					DO=u
				fi

				echo "[i] = install packages from composer.json"
				echo "[u] = update packages from composer.json"
				echo "[a] = update vendor/composer/autoload*"
			fi

			if ! test -z "$LOCAL_COMPOSER"; then
				echo "[r] = remove local composer.phar"
			fi
		fi

 		echo -e "[q] = quit\n\n"
		echo -n "Type ENTER or wait 10 sec to select default. Your Choice? [$DO]  "
		read -n1 -t 10 USER_DO
		echo

		if ! test -z "$USER_DO"; then
			DO=$USER_DO
		fi

		if test "$DO" = "q"; then
			return
		fi
	fi

	if test "$DO" = "remove" || test "$DO" = "r"; then
		echo "remove composer"
		_rm "composer.phar vendor composer.lock ~/.composer"
	fi

	if test "$DO" = "g" || test "$DO" = "l"; then
		echo -n "install composer as "
		if test "$DO" = "g"; then
			echo "/usr/local/bin/composer - Enter root password if asked"
			_composer_phar /usr/local/bin/composer
		else
			echo "composer.phar"
			_composer_phar
		fi
	fi

	local COMPOSER=
	if ! test -z "$LOCAL_COMPOSER"; then
		COMPOSER="php composer.phar"
	elif ! test -z "$GLOBAL_COMPOSER"; then
		COMPOSER="composer"
	fi

	if test -f composer.json; then
		if test "$DO" = "install" || test "$DO" = "i"; then
			$COMPOSER install
		elif test "$DO" = "update" || test "$DO" = "u"; then
			$COMPOSER update
		elif test "$DO" = "a"; then
			$COMPOSER dump-autoload -o
		fi
	fi
}


#------------------------------------------------------------------------------
# Create composer.json
#
# @param package name e.g. rklib/test
# @require _abort _rm _confirm _license
#------------------------------------------------------------------------------
function _composer_json {
	if test -z "$1"; then
		_abort "empty project name use e.g. rklib/NAME"
	fi

	if test -f "composer.json"; then
		_confirm "Overwrite existing composer.json"
		if test "$CONFIRM" = "y"; then
			_rm "composer.json"
		else
			return
    fi
	fi

	_license "gpl-3.0"

	local CLASSMAP=
	if test -d "src"; then
		CLASSMAP='"src/"'
	fi

	echo "create composer.json ($1, $LICENSE)"
	1>"composer.json" cat <<EOL
{
	"name": "$1",
	"type": "",
	"description": "",
	"authors": [
		{ "name": "Roland Kujundzic", "email": "roland@kujundzic.de" }
	],
	"minimum-stability" : "dev",
	"prefer-stable" : true,
	"require": {
		"php": ">=7.2.0",
		"ext-mbstring": "*"
	},
	"autoload": {
		"classmap": [$CLASSMAP],
		"files": []
	},
	"license": "GPL-3.0-or-later"
}
EOL
}


#------------------------------------------------------------------------------
# Show "message  Press y or n  " and wait for key press. 
# Set CONFIRM=y if y key was pressed. Otherwise set CONFIRM=n if any other 
# key was pressed or 10 sec expired. Use --q1=y and --q2=n call parameter to confirm
# question 1 and reject question 2. Set CONFIRM_COUNT= before _confirm if necessary.
#
# @param string message
# @param 2^N flag 1=switch y and n (y = default, wait 3 sec) | 2=auto-confirm (y)
# @export CONFIRM CONFIRM_TEXT
#------------------------------------------------------------------------------
function _confirm {
	CONFIRM=

	if test -z "$CONFIRM_COUNT"; then
		CONFIRM_COUNT=1
	else
		CONFIRM_COUNT=$((CONFIRM_COUNT + 1))
	fi

	local FLAG=$(($2 + 0))

	if test $((FLAG & 2)) = 2; then
		if test $((FLAG & 1)) = 1; then
			CONFIRM=n
		else
			CONFIRM=y
		fi

		return
	fi

	while read -d $'\0' 
	do
		local CCKEY="--q$CONFIRM_COUNT"
		if test "$REPLY" = "$CCKEY=y"; then
			echo "found $CCKEY=y, accept: $1" 
			CONFIRM=y
		elif test "$REPLY" = "$CCKEY=n"; then
			echo "found $CCKEY=n, reject: $1" 
			CONFIRM=n
		fi
	done < /proc/$$/cmdline

	if ! test -z "$CONFIRM"; then
		# found -y or -n parameter
		CONFIRM_TEXT="$CONFIRM"
		return
	fi

	local DEFAULT=

	if test $((FLAG & 1)) -ne 1; then
		DEFAULT=n
		echo -n "$1  y [n]  "
		read -n1 -t 10 CONFIRM
		echo
	else
		DEFAULT=y
		echo -n "$1  [y] n  "
		read -n1 -t 3 CONFIRM
		echo
	fi

	if test -z "$CONFIRM"; then
		CONFIRM=$DEFAULT
	fi

	CONFIRM_TEXT="$CONFIRM"

	if test "$CONFIRM" != "y"; then
		CONFIRM=n
  fi
}


#------------------------------------------------------------------------------
function _docs {
	_apigen_doc
	_phpdocumentor
}


#------------------------------------------------------------------------------
function _strict_types_off {
  local LOG=`echo "$1.log" | sed -E 's/\//:/g'`

  echo -e "remove strict types from $1 (see .rkscript/$LOG)"
  "$PATH_PHPLIB/bin/toggle" "$1" strict_types off >".rkscript/$LOG" 2>&1

  local HAS_ERROR=`tail -10 ".rkscript/$LOG" | grep 'ERROR in '`
  local PHP_ERROR=`tail -2 ".rkscript/$LOG" | grep 'PHP Parse error'`

  if ! test -z "$HAS_ERROR" || ! test -z "$PHP_ERROR"; then
    _abort "$HAS_ERROR"
  fi
}


#------------------------------------------------------------------------------
function _php5 {
  local BRANCH=`git branch | grep '* ' | sed 's/* //'`
  local PROJECT=`realpath .`

  if ! test -s "$PROJECT/.git/config"; then
    _abort "project is not git repository"
  fi

  local IS_RKPHPLIB=`cat .git/config | grep '/rkphplib.git'`

  if test -z "$IS_RKPHPLIB"; then
    _abort "change into rkphplib directory"
  fi

  if test "$BRANCH" != "php5"; then
    git checkout -b php5
  else
    git pull
  fi

	if test -z "$PATH_PHPLIB"; then
		if test -s "../phplib/bin/toggle"; then
			PATH_PHPLIB=`realpath ../phplib`
		else
			_abort "export PATH_PHPLIB"
		fi
	fi

	_strict_types_off src
	_strict_types_off test

	for a in bin/*; do
		_strict_types_off $a
	done

	git stash
	git checkout -b php5
	git pull
}


#------------------------------------------------------------------------------
function _build {
	PATH_RKPHPLIB="src/"

	_syntax_check_php "src" "syntax_check_src.php"
	php syntax_check_src.php || _abort "php syntax_check_src.php"
	_rm syntax_check_src.php

	_syntax_check_php "bin" "syntax_check_bin.php"
	php syntax_check_bin.php || _abort "php syntax_check_bin.php"
	_rm syntax_check_bin.php

	if ! test -z "$PATH_PHPLIB"; then
		"$PATH_PHPLIB/bin/toggle" src log_debug on
		"$PATH_PHPLIB/bin/toggle" src log_debug off
	fi

	bin/plugin_map

	_require_program composer
	composer validate --no-check-all --strict

  git status
}


#------------------------------------------------------------------------------
function _ubuntu {
	test -f /usr/bin/apt-get || _abort "apt-get not found"
	echo "Install php + mysql + nginx"
	sudo apt-get -y update && sudo apt-get -y install php5-cli php5-sqlite php5-curl php5-gd php5-mcrypt php5-xdebug \
		php5-fpm mysql-server mysql-client php5-mysql nginx && sudo php5enmod mcrypt

	if ! test -f /etc/nginx/sites-available/default.original; then
		local SITE=/etc/nginx/sites-available/default
		echo "Overwrite $SITE (save previous version as default.original)"
		cp $SITE /etc/nginx/sites-available/default.original
		echo 'server {' > $SITE
		echo 'listen 80 default_server; root /var/www/html; index index.html index.htm index.php; server_name localhost;' >> $SITE
		echo 'location / { try_files $uri $uri/ =404; }' >> $SITE
		echo 'location ~ \.php$ { fastcgi_pass unix:/var/run/php5-fpm.sock; fastcgi_index index.php; include fastcgi_params; }' >> $SITE
		echo '}' >> $SITE
	fi

	echo "start php5-fpm + nginx + mysql"
	service php5-fpm restart
	service nginx restart
	service mysql restart
}


#------------------------------------------------------------------------------
function _docker_osx {
	echo -e "\nStart docker-machine default\n"
	docker-machine start default

	echo -e "\nSet docker env and restart rkphplib:\n"	
	echo 'eval $(docker-machine env default)'
	echo 'docker stop rkphplib; docker rm rkphplib'
	echo 'docker run -it -v $PWD:/var/www/html/rkphplib -p 80:80 --name rkphplib rolandkujundzic/ubuntu_trusty_dev bash'
	echo
}


#------------------------------------------------------------------------------
function _opensource {

	if ! test -z "$1"; then
		_mkdir opensource
  	_cd opensource
	fi

	case $1 in
	dropzone)
  	_git_checkout "https://gitlab.com/meno/dropzone.git" dropzone
		cd dropzone
		npm install
		npm test
		echo "start dropzone jekyll server on http://127.0.0.1:400 with"
		echo "cd opensource/dropzone/; grunt build-website; cd website; jekyll serve"
		cd ..
  	;;
	*)
		_syntax "opensource [dropzone]"
	esac

  cd ..
}


#------------------------------------------------------------------------------
# Update/Create git project. Use subdir (js/, php/, ...) for other git projects.
# For git parameter (e.g. [-b master --single-branch]) use global variable GIT_PARAMETER.
#
# Example: git_checkout rk@git.tld:/path/to/repo test
# - if test/ exists: cd test; git pull; cd ..
# - if ../../test: ln -s ../../test; call again (goto 1st case)
# - else: git clone rk@git.tld:/path/to/repo test
#
# @param git url
# @param local directory
# @param after_checkout (e.g. "./run.sh build")
# @global CONFIRM_CHECKOUT (if =1 use positive confirm if does not exist) GIT_PARAMETER
# @require _abort _confirm _cd _ln
#------------------------------------------------------------------------------
function _git_checkout {
	local CURR="$PWD"

	if test -d "$2"; then
		_confirm "Update $2 (git pull)?" 1
	elif ! test -z "$CONFIRM_CHECKOUT"; then
		_confirm "Checkout $1 to $2 (git clone)?" 1
	fi

	if test "$CONFIRM" = "n"; then
		echo "Skip $1"
		return
	fi

	if test -d "$2"; then
		_cd "$2"
		echo "git pull $2"
		git pull
		test -s .gitmodules && git submodule update --init --recursive --remote
		test -s .gitmodules && git submodule foreach "(git checkout master; git pull)"
		_cd "$CURR"
	elif test -d "../../$2" && ! test -L "../../$2"; then
		_ln "../../$2" "$2"
		_git_checkout "$1" "$2"
	else
		echo -e "git clone $GIT_PARAMETER '$1' '$2'\nEnter password if necessary"
		git clone $GIT_PARAMETER "$1" "$2"

		if ! test -d "$2/.git"; then
			_abort "git clone failed - no $2/.git directory"
		fi

		if test -s "$2/.gitmodules"; then
			_cd "$2"
			test -s .gitmodules && git submodule update --init --recursive --remote
			test -s .gitmodules && git submodule foreach "(git checkout master; git pull)"
			_cd ..
		fi

		if ! test -z "$3"; then
			_cd "$2"
			echo "run [$3] in $2"
			$3
			_cd ..
		fi
	fi

	GIT_PARAMETER=
}


#------------------------------------------------------------------------------
# Create LICENCSE file for "gpl-3.0" (keep existing).
#
# @see https://help.github.com/en/articles/licensing-a-repository 
# @param license name (default "gpl-3.0")
# @export LICENSE
# @require _abort _wget _confirm
#------------------------------------------------------------------------------
function _license {
	if ! test -z "$1" && test "$1" != "gpl-3.0"; then
		_abort "unknown license [$1] use [gpl-3.0]"
	fi

	LICENSE=$1
	if test -z "$LICENSE"; then
		LICENSE="gpl-3.0"
	fi

	local LFILE="./LICENSE"

	if test -s "$LFILE"; then
		local IS_GPL3=`head -n 2 "$LFILE" | tr '\n' ' ' | sed -E 's/\s+/ /g' | grep 'GNU GENERAL PUBLIC LICENSE Version 3'`

		if ! test -z "$IS_GPL3"; then
			echo "keep existing gpl-3.0 LICENSE ($LFILE)"
			return
		fi

		_confirm "overwrite existing $LFILE file with $LICENSE"
		if test "$CONFIRM" != "y"; then
			echo "keep existing $LFILE file"
			return
		fi
	fi

	_wget "http://www.gnu.org/licenses/gpl-3.0.txt" "$LFILE"
}

#------------------------------------------------------------------------------
# Link $2 to $1.
#
# @param source path
# @param link path
# @require _abort _rm _mkdir _require_program _cd
#------------------------------------------------------------------------------
function _ln {
	_require_program realpath

	local target=`realpath "$1"`

	if test "$PWD" = "$target"; then
		_abort "ln -s '$taget' '$2' # in $PWD"
	fi

	if test -L "$2"; then
		local old_target=`realpath "$2"`

		if test "$target" = "$old_target"; then
			echo "Link $2 to $target already exists"
			return
		fi

		_rm "$2"
	fi

	local link_dir=`dirname "$2"`
	link_dir=`realpath "$link_dir"`
	local target_dir=`dirname "$target"`

	if test "$target_dir" = "$link_dir"; then
		local cwd="$PWD"
		_cd "$target_dir"
		local tname=`basename "$1"`
		local lname=`basename "$2"`
		echo "ln -s '$tname' '$lname' # in $PWD"
		ln -s "$tname" "$lname" || _abort "ln -s '$tname' '$lname' # in $PWD"
		_cd "$cwd"
	else
		_mkdir "$link_dir"
		echo "Link $2 to $target"
		ln -s "$target" "$2"
	fi

	if ! test -L "$2"; then
		_abort "ln -s '$target' '$2'"
	fi
}


declare -Ai LOG_COUNT  # define hash (associative array) of integer
declare -A LOG_FILE  # define hash
declare -A LOG_CMD  # define hash
LOG_NO_ECHO=

#------------------------------------------------------------------------------
# Pring log message. If second parameter is set assume command logging.
# Set LOG_NO_ECHO=1 to disable echo output.
#
# @param message
# @param name (if set use .rkscript/$name/$NAME_COUNT.nfo)
# @export LOG_NO_ECHO LOG_COUNT[$2] LOG_FILE[$2] LOG_CMD[$2]
#------------------------------------------------------------------------------
function _log {
	test -z "$LOG_NO_ECHO" && echo -n "$1"
	
	if test -z "$2"; then
		test -z "$LOG_NO_ECHO" && echo
		return
	fi

	# assume $1 is shell command
	LOG_COUNT[$2]=$((LOG_COUNT[$2] + 1))
	LOG_FILE[$2]=".rkscript/$2/${LOG_COUNT[$2]}.nfo"
	LOG_CMD[$2]=">> '${LOG_FILE[$2]}' 2>&1"

	test -d ".rkscript/$2" || ( mkdir -p ".rkscript/$2" && chmod 777 ".rkscript/$2" )

	local NOW=`date +'%d.%m.%Y %H:%M:%S'`
	echo -e "# _$2: $NOW\n# $PWD\n# $1 ${LOG_CMD[$2]}\n" > "${LOG_FILE[$2]}"

	test -z "$LOG_NO_ECHO" && echo " ${LOG_CMD[$2]}"
}


#------------------------------------------------------------------------------
# Show where php string function needs to change to mb_* version.
#------------------------------------------------------------------------------
function _mb_check {

	echo -e "\nSearch all *.php files in src/ - output filename if string function\nmight need to be replaced with mb_* version.\n"
	echo -e "Type any key to continue or wait 5 sec.\n"

	read -n1 -t 5 ignore_keypress

	# do not use ereg*
	MB_FUNCTIONS="parse_str split stripos stristr strlen strpos strrchr strrichr strripos strrpos strstr strtolower strtoupper strwidth substr_count substr"

	local a=; for a in $MB_FUNCTIONS
	do
		FOUND=`grep -d skip -r --include=*.php $a'(' src | grep -v 'mb_'$a'('`

		if ! test -z "$FOUND"
		then
			echo "$FOUND"
		fi
	done
}


#------------------------------------------------------------------------------
# Create directory (including parent directories) if directory does not exists.
#
# @param path
# @param flag (optional, 2^0=abort if directory already exists, 2^1=chmod 777 directory)
# @global SUDO
# @require _abort
#------------------------------------------------------------------------------
function _mkdir {

	if test -z "$1"; then	
		_abort "Empty directory path"
	fi

	local FLAG=$(($2 + 0))

	if ! test -d "$1"; then
		echo "mkdir -p $1"
		$SUDO mkdir -p $1 || _abort "mkdir -p '$1'"
	else
		test $((FLAG & 1)) = 1 && _abort "directory $1 already exists"
		echo "directory $1 already exists"
	fi

	test $((FLAG & 2)) = 2 && _chmod 777 "$1"
}


#------------------------------------------------------------------------------
# Create phpdocumentor documentation for php project in docs/phpdocumentor.
#
# @param source directory (optional, default = src)
# @param doc directory (optional, default = docs/phpdocumentor)
# @require _abort _require_program _require_dir _mkdir _cd _composer_json _confirm _rm
#------------------------------------------------------------------------------
function _phpdocumentor {
  local DOC_DIR=./docs/phpdocumentor
	local PRJ="docs/.phpdocumentor"
	local BIN="$PRJ/vendor/phpdocumentor/phpdocumentor/bin/phpdoc"
	local SRC_DIR=./src

	_mkdir "$DOC_DIR"
	_mkdir "$PRJ"
	_require_program composer

	local CURR="$PWD"

	if ! test -f "$PRJ/composer.json"; then
		_cd "$PRJ"
		_composer_json "rklib/rkphplib_doc_phpdocumentor"
		composer require "phpdocumentor/phpdocumentor:dev-master"
		_cd "$CURR"
	fi

	if ! test -s "$BIN"; then
		_cd "$PRJ"
		composer update
		_cd "$CURR"
	fi

	if ! test -z "$1"; then
		SRC_DIR="$1"
	fi

	if ! test -z "$2"; then
		DOC_DIR="$2"
	fi

	_require_dir "$SRC_DIR"

	if test -d "$DOC_DIR"; then
		_confirm "Remove existing documentation directory [$DOC_DIR] ?" 1
		if test "$CONFIRM" = "y"; then
			_rm "$DOC_DIR"
		fi
	fi

	echo "Create phpdocumentor documentation"
	echo "$BIN run -d '$SRC_DIR' -t '$DOC_DIR'"
	$BIN run -d "$SRC_DIR" -t "$DOC_DIR"
}

#------------------------------------------------------------------------------
# Abort if directory does not exists or owner or privileges don't match.
#
# @param path
# @param owner[:group] (optional)
# @param privileges (optional, e.g. 600)
# @require _abort _require_priv _require_owner
#------------------------------------------------------------------------------
function _require_dir {
	test -d "$1" || _abort "no such directory '$1'"

	if ! test -z "$2"; then
		_require_owner "$1" "$2"
	fi

	if ! test -z "$3"; then
		_require_priv "$1" "$3"
	fi
}


#------------------------------------------------------------------------------
# Abort if global variable is empty. With bash version >= 4.4 check works even
# for arrays.
#
# @param variable name (e.g. "GLOBAL" or "GLOB1 GLOB2 ...")
# @require _abort
#------------------------------------------------------------------------------
function _require_global {
	local BASH_VERSION=`bash --version | grep -E '.+bash.+Version [0-9\.]+' | sed -E 's/^.+Version ([0-9]+)\.([0-9]+)\..+$/\1.\2/'`

	local a=; for a in $1; do
		if (( $(echo "$BASH_VERSION >= 4.4" | bc -l) )); then
			typeset -n ARR=$a

			if test -z "$ARR" && test -z "${ARR[@]:1:1}"; then
				echo "no such global variable $a"
			fi
		elif test -z "${!a}"; then
			echo "no such global variable $a"
		fi
	done
}

#------------------------------------------------------------------------------
# Abort if file or directory owner:group don't match.
#
# @param path
# @param owner[:group]
# @require _abort
#------------------------------------------------------------------------------
function _require_owner {
	if ! test -f "$1" && ! test -d "$1"; then
		_abort "no such file or directory '$1'"
	fi

	local arr=( ${2//:/ } )
	local owner=`stat -c '%U' "$1"`
	local group=`stat -c '%G' "$1"`

	if ! test -z "${arr[0]}" && ! test "${arr[0]}" = "$owner"; then
		_abort "invalid owner - chown ${arr[0]} '$1'"
	fi

	if ! test -z "${arr[1]}" && ! test "${arr[1]}" = "$group"; then
		_abort "invalid group - chgrp ${arr[1]} '$1'"
	fi
}


#------------------------------------------------------------------------------
# Abort if file or directory privileges don't match.
#
# @param path
# @param privileges (e.g. 600)
# @require _abort
#------------------------------------------------------------------------------
function _require_priv {
	if test -z "$2"; then
		_abort "empty privileges"
	fi

	local priv=`stat -c '%a' "$1" || _abort "no such filesystem entry '$1'"`

	if ! test "$2" = "$priv"; then
		_abort "invalid privileges - chmod $1 '$2'"
	fi
}


#------------------------------------------------------------------------------
# Print md5sum of file.
#
# @param program
# @param abort if not found (1=abort, empty=continue)
# @export HAS_PROGRAM (abs path to program or zero)
# @require _abort
#------------------------------------------------------------------------------
function _require_program {
	local TYPE=`type -t "$1"`

	if test "$TYPE" = "function"; then
		return
	fi

	command -v "$1" > /dev/null 2>&1 || ( test -z "$2" || _abort "No such program [$1]" )
}


#------------------------------------------------------------------------------
# Remove files/directories.
#
# @param path_list
# @param int (optional - abort if set and path is invalid)
# @require _abort
#------------------------------------------------------------------------------
function _rm {

	if test -z "$1"; then
		_abort "Empty remove path list"
	fi

	local a=; for a in $1
	do
		if ! test -f $a && ! test -d $a
		then
			if ! test -z "$2"; then
				_abort "No such file or directory $a"
			fi
		else
			echo "remove $a"
			rm -rf $a
		fi
	done
}


#------------------------------------------------------------------------------
# Switch to sudo mode. Switch back after command is executed.
# 
# @param command
# @param optional flag (1=try sudo if normal command failed)
# @require _abort _log
#------------------------------------------------------------------------------
function _sudo {
	local CURR_SUDO=$SUDO

	# ToDo: unescape $1 to avoid eval. Example: use [$EXEC] instead of [eval "$EXEC"]
	# and [_sudo "cp 'a' 'b'"] will execute [cp "'a'" "'b'"].
	local EXEC="$1"

	# change $2 into number
	local FLAG=$(($2 + 0))

	if test $((FLAG & 1)) = 1 && test -z "$CURR_SUDO"; then
		_log "$EXEC" sudo
		eval "$EXEC ${LOG_CMD[sudo]}" || \
			( echo "try sudo $EXEC"; eval "sudo $EXEC ${LOG_CMD[sudo]}" || _abort "sudo $EXEC" )
	else
		SUDO=sudo
		_log "sudo $EXEC" sudo
		eval "sudo $EXEC ${LOG_CMD[sudo]}" || _abort "sudo $EXEC"
		SUDO=$CURR_SUDO
	fi
}


#------------------------------------------------------------------------------
# Abort with SYNTAX: message.
# Usually APP=$0
#
# @global APP, APP_DESC, $APP_PREFIX
# @param message
#------------------------------------------------------------------------------
function _syntax {
	if ! test -z "$APP_PREFIX"; then
		echo -e "\nSYNTAX: $APP_PREFIX $APP $1\n" 1>&2
	else
		echo -e "\nSYNTAX: $APP $1\n" 1>&2
	fi

	if ! test -z "$APP_DESC"; then
		echo -e "$APP_DESC\n\n" 1>&2
	else
		echo 1>&2
	fi

	exit 1
}


#------------------------------------------------------------------------------
# Create php file with includes from source directory.
#
# @param source directory
# @param output file
# @global PATH_RKPHPLIB
# @require _require_global
#------------------------------------------------------------------------------
function _syntax_check_php {
	local PHP_FILES=`find "$1" -type f -name '*.php'`
	local PHP_BIN=`grep -R -E '^#\!/usr/bin/php' "bin" | grep -v 'php -c skip_syntax_check' | sed -E 's/\:\#\!.+//'`

	_require_global PATH_RKPHPLIB

	echo -e "<?php\n\ndefine('APP_HELP', 'quiet');\ndefine('PATH_RKPHPLIB', '$PATH_RKPHPLIB');\n" > "$2"
	echo -e "function _syntax_test(\$php_file) {\n  print \"\$php_file ... \";\n  include_once \$php_file;" >> "$2"
	echo -n '  print "ok\n";' >> "$2"
	echo -e "\n}\n" >> "$2"

	for a in $PHP_FILES $PHP_BIN
	do
		local SKIP=`head -1 "$a" | grep 'php -c skip_syntax_check'`
	
		if test -z "$SKIP"; then
			echo "_syntax_test('$a');" >> "$2"
		fi
	done
}


#------------------------------------------------------------------------------
# Download URL with wget. 
#
# @param url
# @param save as default = autodect, use "-" for stdout
# @require _abort _require_program _confirm
#------------------------------------------------------------------------------
function _wget {
	if test -z "$1"; then
		_abort "empty url"
	fi

	_require_program wget

	if ! test -z "$2" && test "$2" != "-" && test -f "$2"; then
		_confirm "Overwrite $2" 1
		if test "$CONFIRM" != "y"; then
			echo "keep $2 - skip wget '$1'"
			return
		fi
	fi

	if test -z "$2"; then
		echo "download $1"
		wget -q "$1"
	elif test "$2" = "-"; then
		wget -q -O "$2" "$1"
	else
		echo "download $1 as $2"
		wget -q -O "$2" "$1"
	fi

  if test "$2" != "-"; then
		if test -z "$2"; then
			local SAVE_AS=`basename "$1"`
			local NEW_FILES=`find . -amin 1 -type f`

			if ! test -s "$SAVE_AS" && test -z "$NEW_FILES"; then
				_abort "Download from $1 failed"
			fi
		elif ! test -s "$2"; then
			_abort "Download of $2 from $1 failed"
		fi
  fi
}


APP=$0
APP_DESC=

export APP_PID="$APP_PID $$"

if test -s ../phplib/bin/toggle; then
	PATH_PHPLIB=`realpath ../phplib`
elif test -s ../../bin/toggle; then
	PATH_PHPLIB=`realpath ../..`
fi

case $1 in
build)
	_build
	;;
php5)
	_php5
	;;
composer)
	_composer $2
	;;
test)
	# run all tests
	php test/run.php
	;;
docs)
	_docs
	;;
mb_check)
	_mb_check
	;;
ubuntu)
	_ubuntu
	;;
docker_osx)
	_docker_osx
	;;
opensource)
	_opensource $2
	;;
*)
	_syntax "[build|opensource|composer|docs|php5|test|mb_check|ubuntu|docker_osx]"
esac

