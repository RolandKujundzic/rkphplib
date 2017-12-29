<?php

namespace rkphplib\tok;

require_once(__DIR__.'/TokPlugin.iface.php');

use \rkphplib\Exception;


/**
 * HTML plugin.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 */
class THtml implements TokPlugin {

/**
 *
 */
public function getPlugins($tok) {
  $plugin = [];
  $plugin['innerHtml'] = TokPlugin::POSTPROCESS;
  return $plugin;
}


/**
 * Postprocess output. Replace inner html of tag (= <tag>).
 *
 * @tok {innerHtml:title}Replace <title> with this{:innerHtml}
 *
 * @see DateCalc::formatDateStr()
 * @param string $param
 * @param string $arg
 * @param string $html
 * @return string
 */
public function tok_innerHtml($tag, $arg, $html) {
	$start = mb_stripos($html, '<'.$tag.'>');
	$tag_len = mb_strlen($tag) + 2;
	$end = mb_stripos($html, '</'.$tag.'>', $start + $tag_len);

	if ($start > 0 && $end >= $start + $tag_len) {
		$res = mb_substr($html, 0, $start).'<'.$tag.'>'.$arg.'</'.$tag.'>'.mb_substr($html, $end + $tag_len + 1);
	}

	return $res;
}


}

