<?php

require_once '../settings.php';

use \rkphplib\tok\TokPlugin;
use \rkphplib\tok\Tokenizer;


/**
 *
 */
class TOutput implements TokPlugin {

private $mode;

public function __construct($mode = 'A') {
	$this->mode = $mode;
}

public function getPlugins(Tokenizer $tok) : array {
	$plugin = [];

	$plugin['output_header'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY;
	$plugin['output_loop']   = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::REDO | TokPlugin::TEXT;
	$plugin['output_footer'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::TEXT;
	$plugin['output:header2'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY;
	$plugin['output'] = 0;

	return $plugin;
}

public function tok_output_header($txt) {	return 'h['.$txt.']'; }
public function tok_output_header2($txt) {	return 'h2['.$txt.']'; }
public function tok_output_loop($txt) { return 'l['.str_replace('{x:}', 'X', $txt).']'; }
public function tok_output_footer($txt) {	return 'f['.$txt.']'; }

}


/*
 * M A I N
 */

global $th;

$t_output = new TOutput();
$th->useTokPlugin([ $t_output ]);
$th->run(1, 8);

