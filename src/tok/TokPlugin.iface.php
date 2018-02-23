<?php

namespace rkphplib\tok;


if (!defined('SETTINGS_REQ_DIR')) {
	/** @const SETTINGS_REQ_DIR = 'dir' if undefined */
	define('SETTINGS_REQ_DIR', 'dir');
}

if (!defined('HASH_DELIMITER')) {
	/** @const HASH_DELIMITER = '|#|' if undefined */
	define('HASH_DELIMITER', '|#|');
}



/**
 * Tokenizer plugin callback interface.
 * 
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
interface TokPlugin {

/** @const PARSE tokenize plugin body */
const PARSE = 0;

/** @const TEXT don't tokenize plugin body */
const TEXT = 2;

/** @const REDO re-parse plugin result */
const REDO = 4;

/** @const TOKCALL use plugin callback tokCall(name, param, body) instead of tok_name(param, body) */
const TOKCALL = 8;

/** @const REQUIRE_PARAM plugin parameter is required */
const REQUIRE_PARAM = 16;

/** @const NO_PARAM no plugin parameter */
const NO_PARAM = 32;

/** @const REQUIRE_BODY plugin body is required */
const REQUIRE_BODY = 64;

/** @const NO_BODY no plugin body */
const NO_BODY = 128;

/** @const KV_BODY parse body with conf2kv */
const KV_BODY = 256;

/** @const JSON_BODY body is json */
const JSON_BODY = 512;

/** @const PARAM_LIST example {action:p1:p2:...} escape : with \: */
const PARAM_LIST = 1024;

/** @const PARAM_CSLIST example {action:p1,p2,...} escape , with \, */
const PARAM_CSLIST = 2048;

/** @const CSLIST_BODY example {action:}p1, p2, ... {:action} escape , with \, */
const CSLIST_BODY = 4096;

/** @const LIST_BODY example {action:}p1|#|p2|#| ... {:action} escape |#| with \|#| */
const LIST_BODY = 8192;

/** @const XML_BODY body is xml */
const XML_BODY = 16384;

/** @const POSTPROCESS postprocess finished output */
const POSTPROCESS = 32768;

/** @const ONE_PARAM use param or arg if set - error if both are empty */
const ONE_PARAM = 65536;

/** @const IS_STATIC use if plugin can be resolved in pre-process */
const IS_STATIC = 131072;



/**
 * Return Plugin list. Example:
 * 
 * { abc: TokPlugin::PARAM_LIST, xyz: TokPlugin::REQUIRE_BODY | TokPlugin::TEXT | TokPlugin::REDO }
 *  
 * @param Tokenizer $tok
 * @return map<name:int>
 */
public function getPlugins($tok);


}
