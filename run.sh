#!/usr/bin/env bash
#
# Copyright (c) 2016 - 2020 Roland Kujundzic <roland@kujundzic.de>
#
# shellcheck disable=SC1091,SC1001,SC2006,SC2009,SC2012,SC2016,SC2024,SC2028,SC2034,SC2046,SC2048,SC2068,SC2086,SC2119,SC2120,SC2153,SC2183,SC2206
#


test -z "$RKBASH_DIR" && RKBASH_DIR="$HOME/.rkbash/$$"

if declare -A __hash=([key]=value) 2>/dev/null; then
	test "${__hash[key]}" = 'value' || { echo -e "\nERROR: declare -A\n"; exit 1; }
	unset __hash
else
	echo -e "\nERROR: declare -A\n"
	exit 1  
fi  

if test "${@: -1}" = 'help' 2>/dev/null; then
	for a in ps tr xargs head grep awk find sed sudo cd chown chmod mkdir rm ls; do
		command -v $a >/dev/null || { echo -e "\nERROR: missing $a\n"; exit 1; }
	done
fi

function _abort {
	local msg line rf brf nf
	rf="\033[0;31m"
	brf="\033[1;31m"
	nf="\033[0m"

	msg="$1"
	if test -n "$2"; then
		msg="$2"
		line="[$1]"
	fi

	if test "$NO_ABORT" = 1; then
		ABORT=1
		echo "${rf}WARNING${line}: ${msg}${nf}"
		return 1
	fi

	msg="${rf}${msg}${nf}"

	local frame trace
	if type -t caller >/dev/null 2>/dev/null; then
		frame=0
		trace=$(while caller $frame; do ((frame++)); done)
		msg="$msg\n\n$trace"
	fi

	if [[ -n "$LOG_LAST" && -s "$LOG_LAST" ]]; then
		msg="$msg\n\n$(tail -n+5 "$LOG_LAST")"
	fi

	echo -e "\n${brf}ABORT${line}:${nf} $msg\n" 1>&2

	local other_pid=

	if test -n "$APP_PID"; then
		for a in $APP_PID; do
			other_pid=$(ps aux | grep -E "^.+\\s+$a\\s+" | awk '{print $2}')
			test -z "$other_pid" || kill "$other_pid" 2>/dev/null 1>&2
		done
	fi

	if test -n "$APP"; then
		other_pid=$(ps aux | grep "$APP" | awk '{print $2}')
		test -z "$other_pid" || kill "$other_pid" 2>/dev/null 1>&2
	fi

	exit 1
}


function _add_abort_linenum {
	local lines changes tmp_file fix_line
	type -t caller >/dev/null 2>/dev/null && return

	_mkdir "$RKBASH_DIR/add_abort_linenum"
	tmp_file="$RKBASH_DIR/add_abort_linenum/"$(basename "$1")
	test -f "$tmp_file" && _abort "$tmp_file already exists"

	echo -n "add line number to _abort in $1"
	changes=0

	readarray -t lines < "$1"
	for ((i = 0; i < ${#lines[@]}; i++)); do
		fix_line=$(echo "${lines[$i]}" | grep -E -e '(;| \|\|| &&) _abort ["'"']" -e '^\s*_abort ["'"']" | grep -vE -e '^\s*#' -e '^\s*function ')
		if test -z "$fix_line"; then
			echo "${lines[$i]}" >> "$tmp_file"
		else
			changes=$((changes+1))
			echo "${lines[$i]}" | sed -E 's/^(.*)_abort (.+)$/\1_abort '$((i+1))' \2/g' >> "$tmp_file"
		fi
	done

	echo " ($changes)"
	_cp "$tmp_file" "$1" >/dev/null
}


function _apigen_doc {
	local doc_dir prj bin src_dir
  doc_dir=./docs/apigen
	prj="docs/.apigen"
	bin="$prj/vendor/apigen/apigen/bin/apigen"
	src_dir=./src

	_mkdir "$doc_dir"
	_mkdir "$prj"
	_require_program composer

	if ! test -f "$prj/composer.json"; then
		_cd "$prj"
		_composer_json "rklib/rkphplib_doc_apigen"
		composer require "apigen/apigen:dev-master"
		composer require "roave/better-reflection:dev-master#c87d856"
		_cd "$CURR"
	fi

	if ! test -s "$bin"; then
		_cd "$prj"
		composer update
		_cd "$CURR"
	fi

	test -n "$1" && src_dir="$1"
	test -n "$2" && doc_dir="$2"

	_require_dir "$src_dir"

	if test -d "$doc_dir"; then
		_confirm "Remove existing documentation directory [$doc_dir] ?" 1
		if test "$CONFIRM" = "y"; then
			_rm "$doc_dir"
		fi
	fi

	echo "Create apigen documentation"
	echo "$bin generate '$src_dir' --destination '$doc_dir'"
	$bin generate "$src_dir" --destination "$doc_dir"
}


function _apt_install {
	local curr_lne
	curr_lne=$LOG_NO_ECHO
	LOG_NO_ECHO=1

	_require_program apt
	_run_as_root 1
	_rkbash_dir

	for a in $*; do
		if test -d "$RKBASH_DIR/apt/$a"; then
			_msg "already installed, skip: apt -y install $a"
		else
			sudo apt -y install "$a" || _abort "apt -y install $a"
			_log "apt -y install $a" "apt/$a"
		fi
	done

	_rkbash_dir reset
	LOG_NO_ECHO=$curr_lne
}


function _apt_update {
	_require_program apt
	local lu now

	_rkbash_dir apt
	lu="$RKBASH_DIR/last_update"
	now=$(date +%s)

	if [[ -f "$lu" && $(cat "$lu") -gt $((now - 3600 * 24 * 7)) ]]; then
		:
	else
		echo "$now" > "$lu" 

		_run_as_root 1
		echo -n "apt -y update &>$RKBASH_DIR/update.log ... "
		sudo apt -y update &>"$RKBASH_DIR/update.log" || _abort 'sudo apt -y update'
		echo "done"

		if test "$1" = 1; then
			echo -n "apt -y upgrade &>$RKBASH_DIR/upgrade.log  ... "
 			sudo apt -y upgrade &>"$RKBASH_DIR/upgrade.log" || _abort 'sudo apt -y upgrade'
			echo "done"
		fi
	fi

	_rkbash_dir reset
}


function _cd {
	local has_realpath curr_dir goto_dir
	has_realpath=$(command -v realpath)

	if [[ -n "$has_realpath" && -n "$1" ]]; then
		curr_dir=$(realpath "$PWD")
		goto_dir=$(realpath "$1")

		if test "$curr_dir" = "$goto_dir"; then
			return
		fi
	fi

	if test -z "$2"; then
		echo "cd '$1'"
	fi

	if test -z "$1"; then
		if test -n "$LAST_DIR"; then
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


function _chmod {
	local tmp cmd i priv
	test -z "$1" && _abort "empty privileges parameter"
	test -z "$2" && _abort "empty path"

	tmp=$(echo "$1" | sed -E 's/[012345678]*//')
	test -z "$tmp" || _abort "invalid octal privileges '$1'"

	cmd="chmod -R"
	if test -n "$CHMOD"; then
		cmd="$CHMOD"
		CHMOD=
	fi

	if test -z "$2"; then
		for ((i = 0; i < ${#FOUND[@]}; i++)); do
			priv=

			if test -f "${FOUND[$i]}" || test -d "${FOUND[$i]}"; then
				priv=`stat -c "%a" "${FOUND[$i]}"`
			fi

			if test "$1" != "$priv" && test "$1" != "0$priv"; then
				_sudo "$cmd $1 '${FOUND[$i]}'" 1
			fi
		done
	elif test -f "$2"; then
		priv=`stat -c "%a" "$2"`

		if [[ "$1" != "$priv" && "$1" != "0$priv" ]]; then
			_sudo "$cmd $1 '$2'" 1
		fi
	elif test -d "$2"; then
		_sudo "$cmd $1 '$2'" 1
	fi
}


function _composer {
	local action global_comp local_comp user_action cmd
	global_comp=$(command -v composer)
	action="$1"

	test -f "composer.phar" && local_comp=composer.phar

	if test -z "$action"; then
		echo -e "\nWhat do you want to do?\n"

		if [[ -z "$global_comp" && -z "$local_comp" ]]; then
			action=l
			echo "[g] = global composer installation: /usr/local/bin/composer"
			echo "[l] = local composer installation: composer.phar"
		else
			if test -f composer.json; then
				action=i
				test -d vendor && action=u

				echo "[i] = install packages from composer.json"
				echo "[u] = update packages from composer.json"
				echo "[a] = update vendor/composer/autoload*"
			fi

			if test -n "$local_comp"; then
				echo "[r] = remove local composer.phar"
			fi
		fi

 		echo -e "[q] = quit\n\n"
		echo -n "Type ENTER or wait 10 sec to select default. Your Choice? [$action]  "
		read -n1 -r -t 10 user_action
		echo

		test -z "$user_action" || action=$user_action
		test "$action" = "q" && return
	fi

	if test "$action" = "remove" || test "$action" = "r"; then
		echo "remove composer"
		_rm "composer.phar vendor composer.lock ~/.composer"
	fi

	if test "$action" = "g" || test "$action" = "l"; then
		echo -n "install composer as "
		if test "$action" = "g"; then
			echo "/usr/local/bin/composer - Enter root password if asked"
			_composer_phar /usr/local/bin/composer
		else
			echo "composer.phar"
			_composer_phar
		fi
	fi

	if test -n "$local_comp"; then
		cmd="php composer.phar"
	elif test -n "$global_comp"; then
		cmd="composer"
	fi

	if test -f composer.json; then
		if test "$action" = "install" || test "$action" = "i"; then
			$cmd install
		elif test "$action" = "update" || test "$action" = "u"; then
			$cmd update
		elif test "$action" = "a"; then
			$cmd dump-autoload -o
		fi
	fi
}


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


function _composer_phar {
	local expected_sig actual_sig install_as sudo result
	expected_sig="$(_wget "https://composer.github.io/installer.sig" -)"
	php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
	actual_sig="$(php -r "echo hash_file('sha384', 'composer-setup.php');")"

  if test "$expected_sig" != "$actual_sig"; then
    _rm composer-setup.php
    _abort 'Invalid installer signature'
  fi

	install_as="$1"
	sudo='sudo'

	if test -z "$install_as"; then
		install_as="./composer.phar"
		sudo=
	fi

  $sudo php composer-setup.php --quiet --install-dir=$(dirname "$install_as") --filename=$(basename "$install_as")
	result=$?

	if ! test "$result" = "0" || ! test -s "$install_as"; then
		_abort "composer installation failed"
	fi

	_rm composer-setup.php
}


function _confirm {
	local msg
	msg="\033[0;35m$1\033[0m"

	CONFIRM=

	if test -n "$AUTOCONFIRM"; then
		CONFIRM="${AUTOCONFIRM:0:1}"
		echo -e "$msg <$CONFIRM>"
		AUTOCONFIRM="${AUTOCONFIRM:1}"
		return
	fi

	if test -z "$CONFIRM_COUNT"; then
		CONFIRM_COUNT=1
	else
		CONFIRM_COUNT=$((CONFIRM_COUNT + 1))
	fi

	local flag cckey default

	flag=$(($2 + 0))

	if test $((flag & 2)) = 2; then
		if test $((flag & 1)) = 1; then
			CONFIRM=n
		else
			CONFIRM=y
		fi

		return
	fi

	while read -r -d $'\0' 
	do
		cckey="--q$CONFIRM_COUNT"
		if test "$REPLY" = "$cckey=y"; then
			echo "found $cckey=y, accept: $1" 
			CONFIRM=y
		elif test "$REPLY" = "$cckey=n"; then
			echo "found $cckey=n, reject: $1" 
			CONFIRM=n
		fi
	done < /proc/$$/cmdline

	if test -n "$CONFIRM"; then
		CONFIRM_TEXT="$CONFIRM"
		return
	fi

	if test $((flag & 1)) -ne 1; then
		default=n
		echo -n -e "$msg  y [n]  "
		read -r -n1 -t 10 CONFIRM
		echo
	else
		default=y
		echo -n -e "$msg  \033[0;35m[y]\033[0m n  "
		read -r -n1 -t 3 CONFIRM
		echo
	fi

	if test -z "$CONFIRM"; then
		CONFIRM="$default"
	fi

	CONFIRM_TEXT="$CONFIRM"

	if test "$CONFIRM" != "y"; then
		CONFIRM=n
  fi
}


function _cp {
	local curr_lno target_dir md1 md2 pdir
	curr_lno="$LOG_NO_ECHO"
	LOG_NO_ECHO=1

	CP_FIRST=
	CP_KEEP=

	test -z "$2" && _abort "empty target"

	target_dir=$(dirname "$2")
	test -d "$target_dir" || _abort "no such directory [$target_dir]"

	if test "$3" != 'md5'; then
		:
	elif ! test -f "$2"; then
		CP_FIRST=1
	elif test -f "$1"; then
		md1=$(_md5 "$1")
		md2=$(_md5 "$2")

		if test "$md1" = "$md2"; then
			_msg "_cp: keep $2 (same as $1)"
			CP_KEEP=1
		else
			_msg "Copy file $1 to $2 (update)"
			_sudo "cp '$1' '$2'" 1
		fi

		return
	fi

	if test -f "$1"; then
		_msg "Copy file $1 to $2"
		_sudo "cp '$1' '$2'" 1
	elif test -d "$1"; then
		if test -d "$2"; then
			pdir="$2"
			_confirm "Remove existing target directory '$2'?"
			if test "$CONFIRM" = "y"; then
				_rm "$pdir"
				_msg "Copy directory $1 to $2"
				_sudo "cp -r '$1' '$2'" 1
			else
				_msg "Copy directory $1 to $2 (use rsync)" 
				_rsync "$1/" "$2"
			fi
		else
			_msg "Copy directory $1 to $2"
			_sudo "cp -r '$1' '$2'" 1
		fi
	else
		_abort "No such file or directory [$1]"
	fi

	LOG_NO_ECHO="$curr_lno"
}


function _find {
	FOUND=()
	local a

	_require_program find
	_require_dir "$1"

	while read -r a; do
		FOUND+=("$a")
	done < <(eval "find '$1' $2" || _abort "find '$1' $2")
}


function _install_mariadb {
	_apt_update
	_apt_install 'mariadb-server mariadb-client php-mysql'
}
	

function _install_nginx {
	_apt_update
	_apt_install 'nginx php-fpm'
}
	

function _install_php {
	_apt_update	
  _apt_install 'php-cli php-curl php-mbstring php-gd php-xml php-tcpdf php-json'
  _apt_install 'php-dev php-imap php-intl php-xdebug php-pear php-zip php-pclzip'
}


function _install_sqlite3 {
	_apt_update
	_apt_install 'sqlite3 php-sqlite3'
}
	

function _license {
	if [[ -n "$1" && "$1" != 'gpl-3.0' ]]; then
		_abort "unknown license [$1] use [gpl-3.0]"
	fi

	LICENSE=$1
	if test -z "$LICENSE"; then
		LICENSE="gpl-3.0"
	fi

	local lfile is_gpl3
	lfile="./LICENSE"

	if test -s "$lfile"; then
		is_gpl3=$(head -n 2 "$lfile" | tr '\n' ' ' | sed -E 's/\s+/ /g' | grep 'GNU GENERAL PUBLIC LICENSE Version 3')
		if test -n "$is_gpl3"; then
			echo "keep existing gpl-3.0 LICENSE ($lfile)"
			return
		fi

		_confirm "overwrite existing $lfile file with $LICENSE"
		if test "$CONFIRM" != "y"; then
			echo "keep existing $lfile file"
			return
		fi
	fi

	_wget "http://www.gnu.org/licenses/gpl-3.0.txt" "$lfile"
}

declare -Ai LOG_COUNT  # define hash (associative array) of integer
declare -A LOG_FILE  # define hash
declare -A LOG_CMD  # define hash
LOG_NO_ECHO=

function _log {
	test -z "$LOG_NO_ECHO" && echo -n "$1"
	
	if test -z "$2"; then
		test -z "$LOG_NO_ECHO" && echo
		return
	fi

	LOG_COUNT[$2]=$((LOG_COUNT[$2] + 1))
	LOG_FILE[$2]="$RKBASH_DIR/$2/${LOG_COUNT[$2]}.nfo"
	LOG_CMD[$2]=">>'${LOG_FILE[$2]}' 2>&1"
	LOG_LAST=

	if ! test -d "$RKBASH_DIR/$2"; then
		mkdir -p "$RKBASH_DIR/$2"
		if test -n "$SUDO_USER"; then
			chown -R $SUDO_USER.$SUDO_USER "$RKBASH_DIR" || _abort "chown -R $SUDO_USER.$SUDO_USER '$RKBASH_DIR'"
		elif test "$UID" = "0"; then
			chmod -R 777 "$RKBASH_DIR" || _abort "chmod -R 777 '$RKBASH_DIR'"
		fi
	fi

	local now
	now=$(date +'%d.%m.%Y %H:%M:%S')
	echo -e "# _$2: $now\n# $PWD\n# $1 ${LOG_CMD[$2]}\n" > "${LOG_FILE[$2]}"

	if test -n "$SUDO_USER"; then
		chown $SUDO_USER.$SUDO_USER "${LOG_FILE[$2]}" || _abort "chown $SUDO_USER.$SUDO_USER '${LOG_FILE[$2]}'"
	elif test "$UID" = "0"; then
		chmod 666 "${LOG_FILE[$2]}" || _abort "chmod 666 '${LOG_FILE[$2]}'"
	fi

	test -z "$LOG_NO_ECHO" && echo " ${LOG_CMD[$2]}"
	test -s "${LOG_FILE[$2]}" && LOG_LAST="${LOG_FILE[$2]}"
}


function _mb_check {
	_require_dir src
	local a mb_func

	echo -e "\nSearch all *.php files in src/ - output filename if string function\nmight need to be replaced with mb_* version.\n"
	echo -e "Type any key to continue or wait 5 sec.\n"
	read -r -n1 -t 5 ignore_keypress

	mb_func="parse_str split stripos stristr strlen strpos strrchr strrichr 
		strripos strrpos strstr strtolower strtoupper strwidth substr_count substr"

	for a in $mb_func; do
		grep -d skip -r --include=*.php "$a(" src | grep -v "mb_$a("
	done
}


function _md5 {
	_require_program md5sum
	
	if test -z "$1"; then
		_abort "Empty parameter"
	elif test -f "$1"; then
		md5sum "$1" | awk '{print $1}'
	elif test "$2" = "1"; then
		echo -n "$1" | md5sum | awk '{print $1}'
	else
		_abort "No such file [$1]"
	fi
}


function _merge_sh {
	local a my_app mb_app sh_dir rkbash_inc tmp_app md5_new md5_old inc_sh scheck
	my_app="${1:-$APP}"
	sh_dir="${my_app}_"

	if test -n "$2"; then
		my_app="$2"
		sh_dir="$1"
	else
		_require_file "$my_app"
		mb_app=$(basename "$my_app")
		test -d "$sh_dir" || { test -d "$mb_app" && sh_dir="$mb_app"; }
	fi

	test "${ARG[static]}" = "1" && rkbash_inc=$(_merge_static "$sh_dir")

	_require_dir "$sh_dir"

	tmp_app="$sh_dir"'_'
	test -s "$my_app" && md5_old=$(_md5 "$my_app")
	echo -n "merge $sh_dir into $my_app ... "

	inc_sh=$(find "$sh_dir" -name '*.inc.sh' 2>/dev/null | sort)
	scheck=$(grep -E '^# shellcheck disable=' $inc_sh | sed -E 's/.+ disable=(.+)$/\1/g' | tr ',' ' ' | xargs -n1 | sort -u | xargs | tr ' ' ',')
	test -z "$scheck" || RKS_HEADER_SCHECK="shellcheck disable=SC1091,$scheck"

	if test -z "$rkbash_inc"; then
		_rks_header "$tmp_app" 1
	else
		_rks_header "$tmp_app"
		echo "$rkbash_inc" >> "$tmp_app"
	fi

	for a in $inc_sh; do
		tail -n+2 "$a" | grep -E -v '^# shellcheck disable=' >> "$tmp_app"
	done

	_add_abort_linenum "$tmp_app"

	md5_new=$(_md5 "$tmp_app")
	if test "$md5_old" = "$md5_new"; then
		echo "no change"
		_rm "$tmp_app" >/dev/null
	else
		echo "update"
		_mv "$tmp_app" "$my_app"
		_chmod 755 "$my_app"
	fi

	test -z "$2" && exit 0
}


function _merge_static {
	local a rks_inc inc_sh
	inc_sh=$(find "$1" -name '*.inc.sh' 2>/dev/null | sort)

	for a in $inc_sh; do
		_rkbash_inc "$a"
		rks_inc="$rks_inc $RKBASH_INC"
	done

	for a in $(_sort $rks_inc); do
		tail -n +2 "$RKBASH_SRC/${a:1}.sh" | grep -E -v '^\s*#'
	done
}


function _mkdir {
	local flag
	flag=$(($2 + 0))

	test -z "$1" && _abort "Empty directory path"

	if test -d "$1"; then
		test $((flag & 1)) = 1 && _abort "directory $1 already exists"
		test $((flag & 4)) = 4 && _msg "directory $1 already exists"
	else
		echo "mkdir -p $1"
		$SUDO mkdir -p "$1" || _abort "mkdir -p '$1'"
	fi

	test $((flag & 2)) = 2 && _chmod 777 "$1"
}


function _msg {
	if test -z "$2"; then
		echo "$1"
	else
		echo "$2" "$1"
	fi
}


function _mv {

	if test -z "$1"; then
		_abort "Empty source path"
	fi

	if test -z "$2"; then
		_abort "Empty target path"
	fi

	local pdir
	pdir=$(dirname "$2")
	if ! test -d "$pdir"; then
		_abort "No such directory [$pdir]"
	fi

	local AFTER_LAST_SLASH=${1##*/}

	if test "$AFTER_LAST_SLASH" = "*"
	then
		echo "mv $1 $2"
		mv "$1" "$2" || _abort "mv $1 $2 failed"
	else
		echo "mv '$1' '$2'"
		mv "$1" "$2" || _abort "mv '$1' '$2' failed"
	fi
}


function _nginx_php_fpm {
	local site php_fpm
	site=/etc/nginx/sites-available/default

	_install_nginx

	if [[ -f "$site" && ! -f "${site}.orig" ]]; then
		_orig "$site"
		echo "changing $site"
		echo 'server {
listen 80 default_server; root /var/www/html; index index.html index.htm index.php; server_name localhost;
location / { try_files $uri $uri/ =404; }
location ~ \.php$ { fastcgi_pass unix:/var/run/php5-fpm.sock; fastcgi_index index.php; include fastcgi_params; }
}' > "$site"
	fi

	php_fpm=php$(ls /etc/php/*/fpm/php.ini | sed -E 's#/etc/php/(.+)/fpm/php.ini#\1#')-fpm
	_service "$php_fpm" restart
	_service nginx restart
}


function _ok {
	echo -e "\033[0;32m$1\033[0m" 1>&2
}


function _orig {
	if ! test -f "$1" && ! test -d "$1"; then
		test -z "$2" && _abort "missing $1"
		return 1
	fi

	local RET=0

	if test -f "$1.orig"; then
		_msg "$1.orig already exists"
		RET=1
	else
		_msg "create backup $1.orig"
		_cp "$1" "$1.orig"
	fi

	return $RET
}


if [ "$(uname)" = "Darwin" ]; then

shopt -s expand_aliases 

test -z "$(command -v md5sum)" && _abort "install brew (https://brew.sh/)"

test -f "/usr/local/bin/bash" || _abort "brew install bash"

[[ "$BASH_VERSION" =~ 5. ]] || _abort 'change shebang to: #!/usr/bin/env bash'  

test -z "$(command -v realpath)" && _abort "brew install coreutils"

test "$(echo -e "a_c\naa_b" | sort | xargs)" != "aa_b a_c" && \
	_abort "UTF-8 sort is broken - fix /usr/share/locale/${LC_ALL}/LC_COLLATE"


function stat {
	if test "$1" = "-c"; then
		if test "$2" = "%Y"; then
			/usr/bin/stat -f %m "$3"
			return
		elif test "$2" = "%U"; then
			ls -la "$3" | awk '{print $3}'
		elif test "$2" = "%G"; then
			ls -la "$3" | awk '{print $3}'
		elif test "$2" = "%a"; then
			/usr/bin/stat -f %A "$3"
		else
			_abort "ToDo: stat $*"
		fi
	else
		_abort "ToDo: stat $*"
	fi
}

fi


declare -A ARG
declare ARGV

function _parse_arg {
	ARGV=()

	local i n key val
	n=0
	for (( i = 0; i <= $#; i++ )); do
		ARGV[$i]="${!i}"
		val="${!i}"
		key=

		if [[ $val == "--"*"="* ]]; then
			key="${val/=*/}"
			key="${key/--/}"
			val="${val#*=}"
		elif [[ $val == "--"* ]]; then
			key="${val/--/}"
			val=1
		elif [[ $val =~ ^[a-zA-Z0-9_\.\-]+= ]]; then
			key="${val/=*/}"
			val="${val#*=}"
		fi

		if test -z "$key"; then
			ARG[$n]="$val"
			n=$(( n + 1 ))
		elif test -z "${ARG[$key]}"; then
			ARG[$key]="$val"
		else
			ARG[$key]="${ARG[$key]} $val"
		fi
	done

	ARG[#]=$n
}


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

	test -n "$1" && SRC_DIR="$1"
	test -n "$2" && DOC_DIR="$2"

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

function _require_dir {
	test -d "$1" || _abort "no such directory '$1'"
	test -z "$2" || _require_owner "$1" "$2"
	test -z "$3" || _require_priv "$1" "$3"
}


function _require_file {
	test -f "$1" || _abort "no such file '$1'"
	test -z "$2" || _require_owner "$1" "$2"
	test -z "$3" || _require_priv "$1" "$3"
}


function _require_global {
	local a has_hash bash_version
	bash_version=$(bash --version | grep -iE '.+bash.+version [0-9\.]+' | sed -E 's/^.+version ([0-9]+)\.([0-9]+)\..+$/\1.\2/i')

	for a in "$@"; do
		has_hash="HAS_HASH_$a"

		if (( $(echo "$bash_version >= 4.4" | bc -l) )); then
			typeset -n ARR=$a

			if test -z "$ARR" && test -z "${ARR[@]:1:1}"; then
				_abort "no such global variable $a"
			fi
		elif test -z "${a}" && test -z "${has_hash}"; then
			_abort "no such global variable $a - add HAS_HASH_$a if necessary"
		fi
	done
}


function _require_owner {
	if ! test -f "$1" && ! test -d "$1"; then
		_abort "no such file or directory '$1'"
	fi

	local arr owner group
	arr=( ${2//:/ } )
	owner=$(stat -c '%U' "$1" 2>/dev/null)
	test -z "$owner" && _abort "stat -c '%U' '$1'"
	group=$(stat -c '%G' "$1" 2>/dev/null)
	test -z "$group" && _abort "stat -c '%G' '$1'"

	if [[ -n "${arr[0]}" && "${arr[0]}" != "$owner" ]]; then
		_abort "invalid owner - chown ${arr[0]} '$1'"
	fi

	if [[ -n "${arr[1]}" && "${arr[1]}" != "$group" ]]; then
		_abort "invalid group - chgrp ${arr[1]} '$1'"
	fi
}


function _require_priv {
	test -z "$2" && _abort "empty privileges"
	local priv
	priv=$(stat -c '%a' "$1" 2>/dev/null)
	test -z "$priv" && _abort "stat -c '%a' '$1'"
	test "$2" = "$priv" || _abort "invalid privileges [$priv] - chmod -R $2 '$1'"
}


function _require_program {
	local ptype
	ptype=$(type -t "$1")

	test "$ptype" = "function" && return
	command -v "$1" >/dev/null 2>&1 && return
	command -v "./$1" >/dev/null 2>&1 && return

	if test "${2:0:4}" = "apt:"; then
		_apt_install "${2:4}"
	elif test -z "$2"; then
		echo "No such program [$1]"
		exit 1
	else
		return 1
	fi
}


function _rkbash_dir {
	if [[ "$RKBASH_DIR" = "$HOME/.rkbash" && "$1" = 'reset' ]]; then
		RKBASH_DIR="$HOME/.rkbash/$$"
		return
	fi

	if [[ "$RKBASH_DIR" != "$HOME/.rkbash/$$" ]]; then
		:
	elif test -z "$1"; then
		RKBASH_DIR="$HOME/.rkbash"
	elif [[ "$1" != 'reset' ]]; then
		RKBASH_DIR="$HOME/.rkbash/$1"
		_mkdir "$RKBASH_DIR"
	fi
}
	

function _rkbash_inc {
	local _HAS_SCRIPT
	declare -A _HAS_SCRIPT

	if test -z "$RKBASH_SRC"; then
		if test -s "src/abort.sh"; then
			RKBASH_SRC='src'
		else
			_abort 'set RKBASH_SRC'
		fi
	elif ! test -s "$RKBASH_SRC/abort.sh"; then
		_abort "invalid RKBASH_SRC='$RKBASH_SRC'"
	fi

	test -s "$1" || _abort "no such file '$1'"
	_rrs_scan "$1"

	RKBASH_INC=$(_sort ${!_HAS_SCRIPT[@]})
	RKBASH_INC_NUM="${#_HAS_SCRIPT[@]}"
}


function _rrs_scan {
	local a func_list
	test -f "$1" || _abort "no such file '$1'"
	func_list=$(grep -E -o -e '(_[a-z0-9\_]+)' "$1" | xargs -n1 | sort -u | xargs)

	for a in $func_list; do
		if [[ -z "${_HAS_SCRIPT[$a]}" && -s "$RKBASH_SRC/${a:1}.sh" ]]; then
			_HAS_SCRIPT[$a]=1
			_rrs_scan "$RKBASH_SRC/${a:1}.sh"
		fi
	done
}


function _rks_app {
	local me p1 p2 p3
	me="$1"
	shift
	p1="$1"
	p2="$2"
	p3="$3"

	test -z "$me" && _abort "call _rks_app '$0' $*"
	test -z "${ARG[1]}" || p1="${ARG[1]}"
	test -z "${ARG[2]}" || p2="${ARG[2]}"
	test -z "${ARG[3]}" || p3="${ARG[3]}"

	if test -z "$APP"; then
		APP="$me"
		APP_DIR=$( cd "$( dirname "$APP" )" >/dev/null 2>&1 && pwd )
		CURR="$PWD"
		if test -z "$APP_PID"; then
			 export APP_PID="$$"
		elif test "$APP_PID" != "$$"; then
			 export APP_PID="$APP_PID $$"
		fi
	fi

	test -z "$APP_DESC" && _abort "APP_DESC is empty"
	test -z "${#SYNTAX_CMD[@]}" && _abort "SYNTAX_CMD is empty"
	test -z "${#SYNTAX_HELP[@]}" && _abort "SYNTAX_HELP is empty"

	[[ "$p1" =	'self_update' ]] && _merge_sh

	[[ "$p1" = 'help' ]] && _syntax "*" "cmd:* help:*"
	test -z "$p1" && return

	test -z "${SYNTAX_HELP[$p1]}" || APP_DESC="${SYNTAX_HELP[$p1]}"
	test -z "${SYNTAX_HELP[$p1.$p2]}" || APP_DESC="${SYNTAX_HELP[$p1.$p2]}"

	[[ -n "${SYNTAX_CMD[$p1]}" && "$p2" = 'help' ]] && _syntax "$p1" "help:"
	[[ -n "${SYNTAX_CMD[$p1.$p2]}" && "$p3" = 'help' ]] && _syntax "$p1.$p2" "help:"
}


function _rks_header {
	local flag header copyright
	copyright=$(date +"%Y")
	flag=$(($2 + 0))

	[ -z "${RKS_HEADER+x}" ] || flag=$((RKS_HEADER + 0))

	if test -f ".gitignore"; then
		copyright=$(git log --diff-filter=A -- .gitignore | grep 'Date:' | sed -E 's/.+ ([0-9]+) \+[0-9]+/\1/')" - $copyright"
	fi

	test $((flag & 1)) = 1 && \
		header='source /usr/local/lib/rkbash.lib.sh || { echo -e "\nERROR: source /usr/local/lib/rkbash.lib.sh\n"; exit 1; }'

	printf '\x23!/usr/bin/env bash\n\x23\n\x23 Copyright (c) %s Roland Kujundzic <roland@kujundzic.de>\n\x23\n\x23 %s\n\x23\n\n' \
		"$copyright" "$RKS_HEADER_SCHECK" > "$1"
	test -z "$header" || echo "$header" >> "$1"
}


function _rm {
	test -z "$1" && _abort "Empty remove path"

	if ! test -f "$1" && ! test -d "$1"; then
		test -z "$2" || _abort "No such file or directory '$1'"
	else
		_msg "remove '$1'"
		rm -rf "$1" || _abort "rm -rf '$1'"
	fi
}


function _rsync {
	local target="$2"
	test -z "$target" && target="."

	test -z "$1" && _abort "Empty rsync source"
	test -d "$target" || _abort "No such directory [$target]"

	local rsync="rsync -av $3 -e ssh '$1' '$2'"
	local error
	_log "$rsync" rsync
	eval "$rsync ${LOG_CMD[rsync]}" || error=1

	if test "$error" = "1"; then
		test -z "$(tail -4 "${LOG_FILE[rsync]}" | grep 'speedup is ')" && _abort "$rsync"
		test -z "$(tail -1 "${LOG_FILE[rsync]}" | grep "rsync error:")" || \
			_msg "[WARNING]: FIX rsync errors in ${LOG_FILE[rsync]}"
	fi
}


function _run_as_root {
	test "$UID" = "0" && return

	if test -z "$1"; then
		_abort "Please change into root and try again"
	else
		echo "sudo true - you might need to type in your password"
		sudo true 2>/dev/null || _abort "sudo true failed - Please change into root and try again"
	fi
}


function _service {
	test -z "$1" && _abort "empty service name"
	test -z "$2" && _abort "empty action"

	local is_active
	is_active=$(systemctl is-active "$1")

	if [[ "$is_active" != 'active' && ! "$2" =~ start && ! "$2" =~ able ]]; then
		_abort "$is_active service $1"
	fi

	if test "$2" = 'status'; then
		_ok "$1 is active"
		return
	fi

	_msg "systemctl $2 $1"
	_sudo "systemctl $2 $1"
}


function _sort {
	echo "$@" | xargs -n1 | sort -u | xargs
}


function _sudo {
	local curr_sudo exec flag
	curr_sudo="$SUDO"

	exec="$1"

	flag=$(($2 + 0))

	if test "$USER" = "root"; then
		_log "$exec" sudo
		eval "$exec ${LOG_CMD[sudo]}" || _abort "$exec"
	elif test $((flag & 1)) = 1 && test -z "$curr_sudo"; then
		_log "$exec" sudo
		eval "$exec ${LOG_CMD[sudo]}" || \
			( echo "try sudo $exec"; eval "sudo $exec ${LOG_CMD[sudo]}" || _abort "sudo $exec" )
	else
		SUDO=sudo
		_log "sudo $exec" sudo
		eval "sudo $exec ${LOG_CMD[sudo]}" || _abort "sudo $exec"
		SUDO="$curr_sudo"
	fi

	LOG_LAST=
}


declare -A SYNTAX_CMD
declare -A SYNTAX_HELP

function _syntax {
	local a msg old_msg desc base syntax
	msg=$(_syntax_cmd "$1") 
	syntax="\n\033[1;31mSYNTAX:\033[0m"

	for a in $2; do
		old_msg="$msg"

		if test "${a:0:4}" = "cmd:"; then
			test "$a" = "cmd:" && a="cmd:$1"
			msg="$msg$(_syntax_cmd_other "$a")"
		elif test "${a:0:5}" = "help:"; then
			test "$a" = "help:" && a="help:$1"
			msg="$msg$(_syntax_help "${a:5}")"
		fi

		test "$old_msg" != "$msg" && msg="$msg\n"
	done

	test "${msg: -3:1}" = '|' && msg="${msg:0:-3}\n"

	base=$(basename "$APP")
	if test -n "$APP_PREFIX"; then
		echo -e "$syntax $(_warn_msg "$APP_PREFIX $base $msg")" 1>&2
	else
		echo -e "$syntax $(_warn_msg "$base $msg")" 1>&2
	fi

	for a in APP_DESC APP_DESC_2 APP_DESC_3 APP_DESC_4; do
		test -z "${!a}" || desc="$desc${!a}\n\n"
	done
	echo -e "$desc" 1>&2

	exit 1
}


function _syntax_cmd {
	local a rx msg keys prefix
	keys=$(_sort "${!SYNTAX_CMD[@]}")
	msg="$1\n" 

	if test -n "${SYNTAX_CMD[$1]}"; then
		msg="${SYNTAX_CMD[$1]}\n"
	elif test "${1: -1}" = "*" && test "${#SYNTAX_CMD[@]}" -gt 0; then
		if test "$1" = "*"; then
			rx='^[a-zA-Z0-9_]+$'
		else
			prefix="${1:0:-1}"
			rx="^${1:0:-2}"'\.[a-zA-Z0-9_\.]+$'
		fi

		msg=
		for a in $keys; do
			grep -E "$rx" >/dev/null <<< "$a" && msg="$msg|${a/$prefix/}"
		done
		msg="${msg:1}\n"
	elif [[ "$1" = *'.'* && -n "${SYNTAX_CMD[${1%%.*}]}" ]]; then
		msg="${SYNTAX_CMD[${1%%.*}]}\n"
	fi

	echo "$msg"
}


function _syntax_cmd_other {
	local a rx msg keys base
	keys=$(_sort "${!SYNTAX_CMD[@]}")
	rx="$1"

	test "${rx:4}" = "*" && rx='^[a-zA-Z0-9_]+$' || rx="^${rx:4:-2}"'\.[a-zA-Z0-9_]+$'

	base=$(basename "$APP")
	for a in $keys; do
		grep -E "$rx" >/dev/null <<< "$a" && msg="$msg\n$base ${SYNTAX_CMD[$a]}"
	done

	echo "$msg"
}


function _syntax_help {
	local a rx msg keys prefix
	keys=$(_sort "${!SYNTAX_HELP[@]}")

	if test "$1" = '*'; then
		rx='^[a-zA-Z0-9_]+$'
	elif test "${1: -1}" = '*'; then
		rx="^${rx: -2}"'\.[a-zA-Z0-9_\.]+$'
	fi

	for a in $keys; do
		if test "$a" = "$1"; then
			msg="$msg\n${SYNTAX_HELP[$a]}"
		elif test -n "$rx" && grep -E "$rx" >/dev/null <<< "$a"; then
			prefix=$(sed -E 's/^[a-zA-Z0-9_]+\.//' <<< "$a")
			msg="$msg\n$prefix: ${SYNTAX_HELP[$a]}\n"
		fi
	done

	[[ -n "$msg" && "$msg" != "\n$APP_DESC" ]] && echo -e "$msg"
}


function _syntax_check_php {
	local a php_files php_bin
	php_files=$(find "$1" -type f -name '*.php')
	php_bin=$(grep -R -E '^#\!/usr/bin/php' "bin" | grep -v 'php -c skip_syntax_check' | sed -E 's/\:\#\!.+//')

	_require_global PATH_RKPHPLIB

	{
		echo -e "<?php\n\ndefine('APP_HELP', 'quiet');\ndefine('PATH_RKPHPLIB', '$PATH_RKPHPLIB');\n"
		echo -e "function _syntax_test(\$php_file) {\n  print \"\$php_file ... \";\n  include_once \$php_file;"
		echo -n '  print "ok\n";'
		echo -e "\n}\n"
	} >"$2"

	for a in $php_files $php_bin
	do
		if test -z "$(head -1 "$a" | grep 'php -c skip_syntax_check')"; then
			echo "_syntax_test('$a');" >> "$2"
		fi
	done
}


function _version {
	local flag version
	flag=$(($2 + 0))

	if [[ "$1" =~ ^v?[0-9\.]+$ ]]; then
		version="$1"
	elif command -v "$1" &>/dev/null; then
		version=$({ $1 --version || _abort "$1 --version"; } | head -1 | grep -E -o 'v?[0-9]+\.[0-9\.]+')
	fi

	version="${version/v/}"

	[[ "$version" =~ ^[0-9\.]+$ ]] || _abort "version detection failed ($1)"

	if [[ $((flag & 1)) = 1 ]]; then
		if [[ "$version" =~ ^[0-9]{1,2}\.[0-9]{1,2}$ ]]; then
			printf "%d%02d" $(echo "$version" | tr '.' ' ')
		elif [[ "$version" =~ ^[0-9]{1,2}\.[0-9]{1,2}\.[0-9]{1,2}$ ]]; then
			printf "%d%02d%02d" $(echo "$version" | tr '.' ' ')
		else
			_abort "failed to convert $version to number"
		fi
	elif [[ $((flag & 2)) ]]; then
		echo -n "${version%%.*}"
	elif [[ $((flag & 4)) ]]; then
		echo -n "${version%.*}"
	else
		echo -n "$version"
	fi
}


function _warn_msg {
	local line first
	while IFS= read -r line; do
		if test "$first" = '1'; then
			echo "$line"
		else
			echo '\033[0;31m'"$line"'\033[0m'
			first=1
		fi
	done <<< "${1//\\n/$'\n'}"
}


function _wget {
	local save_as

	test -z "$1" && _abort "empty url"
	_require_program wget

	save_as=${2:-$(basename "$1")}
	if test -s "$save_as"; then
		_confirm "Overwrite $save_as" 1
		if test "$CONFIRM" != "y"; then
			echo "keep $save_as - skip wget '$1'"
			return
		fi
	fi

	if test -z "$2"; then
		echo "download $1"
		wget -q "$1" || _abort "wget -q '$1'"
	elif test "$2" = "-"; then
		wget -q -O "$2" "$1" || _abort "wget -q -O '$2' '$1'"
		return
	else
		_mkdir "$(dirname "$2")"
		echo "download $1 to $2"
		wget -q -O "$2" "$1" || _abort "wget -q -O '$2' '$1'"
	fi

	local new_files
	if test -z "$2"; then
		if ! test -s "$save_as"; then
			new_files=$(find . -amin 1 -type f)
			test -z "$new_files" && _abort "Download $1 failed"
		fi
	elif ! test -s "$2"; then
		_abort "Download $2 to $1 failed"
	fi
}


#--
# Build php5 version
#--
function build_php5 {
	_abort "ToDo ..."
	_strict_types_off src
	_strict_types_off test
	_strict_types_off bin
	_log_debug_off src
	_log_debug_off test
	_log_debug_off bin
}


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

  git status
}


#--
# Run docker-machine start default#
# shellcheck disable=SC2016
#--
function docker_osx {
	echo -e "\nStart docker-machine default\n"
	docker-machine start default

	echo -e "\nSet docker env and restart rkphplib:\n"	
	echo 'eval $(docker-machine env default)'
	echo 'docker stop rkphplib; docker rm rkphplib'
	echo 'docker run -it -v $PWD:/var/www/html/rkphplib -p 80:80 --name rkphplib rolandkujundzic/ubuntu_trusty_dev bash'
	echo
}


#--
# Create documentation
#--
function docs {
	_abort "ToDo ..."
	_apigen_doc
	_phpdocumentor
}


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


#--
# Install php, nginx and mariadb (apt packages)
#--
function ubuntu {
	_confirm 'Install php packages' 1
	test "$CONFIRM" = 'y' && _install_php

	_confirm 'Install mariadb-server, mariadb-client and php-mysql' 1
	test "$CONFIRM" = 'y' && _install_mariadb

	_confirm 'Install sqlite3 and php-sqlite3' 1
	test "$CONFIRM" = 'y' && _install_sqlite3

	_confirm 'Install nginx and php-fpm' 1
	test "$CONFIRM" = 'y' && _nginx_php_fpm
}

# shellcheck disable=SC2034

#--
# M A I N
#--

_parse_arg "$@"
APP_DESC='Administration script'
_rks_app "$0" "$@"

if test -s ../phplib/bin/toggle; then
	PATH_PHPLIB=$(realpath ../phplib)
elif test -s ../../bin/toggle; then
	PATH_PHPLIB=$(realpath ../..)
fi

case ${ARG[1]} in
	build)
		build;;
	php5)
		build_php5;;
	composer)
		_composer "${ARG[2]}";;
	test)
		php test/run.php;;
	docs)
		docs;;
	mb_check)
		_mb_check;;
	ubuntu)
		ubuntu;;
	docker_osx)
		docker_osx;;
	*)
		_syntax "build|composer|docs|docker_osx|mb_check|php5|test|ubuntu"
esac

