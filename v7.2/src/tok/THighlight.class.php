<?php

namespace rkphplib\tok;

require_once __DIR__.'/TokPlugin.iface.php';
require_once dirname(__DIR__).'/FSEntry.class.php';
require_once dirname(__DIR__).'/File.class.php';

use rkphplib\Exception;
use rkphplib\FSEntry;
use rkphplib\File;


/**
 * Show syntax highlighted (*.php|html) code.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @copyright 2020 Roland Kujundzic
 */
class THighlight implements TokPlugin {

// @var Tokenizer $tok
protected $tok = null;


/**
 *
 */
public function getPlugins(Tokenizer $tok) : array {
	$this->tok = $tok;

	$plugin = [];
	$plugin['source:php'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::TEXT;
	$plugin['source:html'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::TEXT;

	return $plugin;
}


/**
 * Return escaped html source. If $html is file://[relative path to html file] (suffix .htm[l])
 * load html file.
 */
public function tok_source_html(string $html) : string {
	if (mb_substr($html, 0, 7) == 'file://') { 
		$html_file = FSEntry::checkPath(mb_substr($txt, 7), '', [ '.html', '.htm' ]);
		$html = File::load($html);
	}

	$res = lib_htmlescape($html);
	$res = preg_replace([ "/\r?\n/", "\t", ' ' ], [ "<br>\r\n", '&nbsp;&nbsp;', '&nbsp;' ], $res);

	return $this->tok->escape($res);
}


/**
 * Return highlighted $txt. If $txt is file://[relative path to php file] (suffix .phps)
 * return highlighted file.
 */
public function tok_source_php(string $txt) : string {
	if (mb_substr($txt, 0, 7) == 'file://') { 
		$php_file = FSEntry::checkPath(mb_substr($txt, 7));
		$res = highlight_file($php_file, true, [ '.phps' ]);
	}
	else {
		$res = highlight_string($txt, true);
	}

	return $this->tok->escape($res);
}


}
