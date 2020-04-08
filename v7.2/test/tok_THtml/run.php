<?php

global $th;

if (!isset($th)) {
  require_once(dirname(dirname(__DIR__)).'/src/TestHelper.class.php');
  $th = new rkphplib\TestHelper();
}

$th->load('src/tok/THtml.class.php');
$th->load('src/File.class.php');

use \rkphplib\tok\THtml;
use \rkphplib\File;

$t_html = new THtml();
$html = File::load('in.html');
$ok_1 = File::load('ok_01.html');
$ok_2 = File::load('ok_02.html');
$ok_3 = File::load('ok_03.html');

$th->compare('[html:inner:title]', [ $t_html->tok_html_inner('title', 'NEW TITLE', $html) ], [ $ok_1 ]);
$th->compare('[html:meta:keywords]', [ $t_html->tok_html_meta('keywords', 'NEW KEYWORDS', $html) ], [ $ok_2 ]);
$th->compare('[html:meta:description]', [ $t_html->tok_html_meta('description', 'NEW DESCRIPTION', $html) ], [ $ok_3 ]);

