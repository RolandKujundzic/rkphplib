<?php

namespace rkphplib\tok;


/**
 * Trait collection for Tokenizer plugins.
 * 
 * @code:
 * require_once(PATH_RKPHPLIB.'tok/TokHelper.trait.php');
 *
 * class SomePlugin {
 * use TokHelper;
 * @:
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
trait TokHelper {


/**
 * If map key from required_keys list is missing throw exception.
 * If key in required_keys has suffix "!" use strlen > 0 check.
 *
 * @code $this->checkMap($this->tok->getPluginTxt('plugin:param', 
 *
 * @throws if required key is missing
 * @param string $plugin
 * @param map $map
 * @param vector $required_keys
 */
private function checkMap($plugin_param, $map, $required_keys) {
	foreach ($required_keys as $key) {
		$error = false;

		if (mb_substr($key, -1) == '!') {
			$key = mb_substr($key, 0, -1);
			if (!isset($map[$key]) || strlen($map[$key]) == 0) {
				$error = true;
			}
		}
		else if (!isset($map[$key])) {
			$error = true;
		}

		if ($error) {
			$example = $this->tok->getPluginTxt($plugin_param, "$key=...");
			throw new Exception("missing parameter $key (use $example)");
		}
	}
}


}
