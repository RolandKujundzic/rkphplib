<?php

global $sh;

$sh->set('abc', 3);
print 'has(abc)='.intval($sh->has('abc'))."\n";
print 'get(abc)='.$sh->get('abc')."\n";

/**
 * ToDo:
 * ok: http://localhost:15081/DatabaseSessionHandler/run.php?test=1 
 * fail: php run.php
 * 
 * curl call of url fails (cookie is not set)
 * ABORT: /home/rk/workspace/php/rkphplib/test/DatabaseSessionHandler
 * Undefined index: PHPSESSID
 */
// print 'strlen(_COOKIE[PHPSESSID])='.strlen($_COOKIE['PHPSESSID'])."\n";

