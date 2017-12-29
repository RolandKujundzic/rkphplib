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
  $plugin['html:inner'] = TokPlugin::REQUIRE_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::POSTPROCESS;
	$plugin['html:tidy'] = TokPlugin::NO_PARAM | TokPlugin::NO_BODY | TokPlugin::POSTPROCESS;
	$plugin['html'] = 0;
  return $plugin;
}


/**
 * Postprocess output. Pretty print Html via DOM.
 *
 * @param string $html
 * @return string
 */
public function tok_html_tidy($html) {
	$dom = new \DOMDocument();

	$dom->preserveWhiteSpace = false;
	$dom->formatOutput = true;
	$dom->loadHTML($html);

	return $dom->saveHTML();
}


/**
 * Postprocess output. Replace inner html of tag (= <tag>).
 *
 * @tok {innerHtml:title}Replace "title" with this{:innerHtml}
 *
 * @see DateCalc::formatDateStr()
 * @param string $tag
 * @param string $innerHtml
 * @param string $html
 * @return string
 */
public function tok_html_inner($tag, $innerHtml, $html) {
	$start = mb_stripos($html, '<'.$tag.'>');
	$tag_len = mb_strlen($tag) + 2;
	$end = mb_stripos($html, '</'.$tag.'>', $start + $tag_len);

	if ($start > 0 && $end >= $start + $tag_len) {
		$res = mb_substr($html, 0, $start).'<'.$tag.'>'.$innerHtml.'</'.$tag.'>'.mb_substr($html, $end + $tag_len + 1);
	}

	return $res;
}


}

