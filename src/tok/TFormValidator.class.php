<?php

namespace rkphplib\tok;


require_once(__DIR__.'/TokPlugin.iface.php');


/**
 * Form validator plugin.
 *
 * {fv:init}
 * template= {:=label}: {:=input} {fv:error_message}|#|
 * template.input= <input type="text" name="{:=name}" value="{:=value}" class="{fv:error:$name}"> {fv:error_message:$name}
 * {:fv}
 *
 * {fv:conf}
 * required= login, password|#|
 * check.login= minLength:2|#|
 * {sql_select:}SELECT count(*) AS num FROM {esc_name:}{login:@table}{:esc_name}
 *   WHERE login={esc:}{login:login}{:esc} AND id!={esc:}{login:id}{:esc}{:sql_select}
 * check.login.2= compare:0:eq:{sql_col:num}:error:{txt:}Login name already exists{:txt}|#|
 * check.password= minLength:4|#|
 * {:fv}
 * 
 * {tf:cmp:yes}{fv:check}{:tf} 
 * 
 * {true:} ... {:true}
 *
 * {false:}
 * <form>
 * {fv:input:login}Login{:fv} 
 *  = Login: <input type="text" name="login" value="{get:login}" class="{fv:error:login}"> {fv:error_message:login}
 * {fv:password:password}label=Password{:fv} 
 *  = Password: <input type="password" name="password" value="{get:password}" class="{fv:error:password}"> {fv:error_message:password}
 * </form>
 * {:false}
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 */
class TFormValidator implements TokPlugin {


}

