<?php

global $th;

if (!isset($th)) {
  require_once(dirname(dirname(__DIR__)).'/src/TestHelper.class.php');
  $th = new rkphplib\TestHelper();
}


$th->load('src/Session.class.php');

$sess = new \rkphplib\Session();
$sess->init([ 'name' => 'test' ]);

$th->compareHash('Session init', $sess->getMetaHash(), [ 'script' => 'run.php' ]);
$th->compare('hasMeta(script)', [ $sess->hasMeta('script') ], [ true ]);
$th->compare('getMeta(docroot)', [ $sess->getMeta('docroot') ], [ '' ]);

$sess->setMeta('host', 'localhost');
$th->compare('set|getMeta(host)', [ $sess->getMeta('host') ], [ 'localhost' ]);

$th->compare('getConf(inactive)', [ $sess->getConf('inactive') ], [ 7200 ]);
$th->compare('getSessionKey()', [ $sess->getSessionKey() ], [ md5('test:docroot') ]);

$sess->set('abc', 3);
$th->compare('has|get()', [ $sess->has('abc'), $sess->get('abc') ], [ true, 3 ]);

$sess->setHash([ 'abc' => 5, 'x' => 'a' ]);
$th->compareHash('getHash()', $sess->getHash(), [ 'abc' => 5, 'x' => 'a' ]);

$js = <<<END
window.setInterval( function() {
	$.ajax({
		cache: false,
		type: "GET",
			url: "test.php"
			
    });
}, 600000);
END;
 
$th->compare('getJSRefresh()', [ $sess->getJSRefresh('test.php') ], [ $js ]);

