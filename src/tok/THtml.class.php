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
  $plugin['html:append'] = TokPlugin::REQUIRE_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::POSTPROCESS;
	$plugin['html:meta'] = TokPlugin::REQUIRE_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::POSTPROCESS;
	$plugin['html:tidy'] = TokPlugin::NO_PARAM | TokPlugin::NO_BODY | TokPlugin::POSTPROCESS;
	$plugin['html:xml'] = TokPlugin::NO_PARAM | TokPlugin::NO_BODY | TokPlugin::POSTPROCESS;
	$plugin['html:uglify'] = TokPlugin::NO_PARAM | TokPlugin::NO_BODY | TokPlugin::POSTPROCESS;
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
 * Replace meta tag value.
 * 
 * @tok {html:meta:keywords}new,key,words{:html} -> <meta name="keywords" content="new,key,words"
 * 
 * @throws
 * @param string $name
 * @param string $value
 * @param string $html
 * @return string
 */
public function tok_html_meta($name, $value, $html) {
	$search = '<meta name="'.$name.'" content="';
	$start = mb_stripos($html, $search);
	$search_len = mb_strlen($search);
	$end = mb_stripos($html, '"', $start + $search_len);

	if ($start > 0 && $end >= $start + $search_len) {
		$res = mb_substr($html, 0, $start).$search.$value.mb_substr($html, $end);
	}
	else {
		throw new Exception('failed to find meta tag content', "search=[$search] start=$start end=$end");
	}

  return $res;
}


/**
 * Postprocess output. Replace inner html of tag (= <tag>).
 *
 * @tok {html:inner:title}Replace "title" with this{:html}
 *
 * @param string $tag
 * @param string $innerHtml
 * @param string $html
 * @return string
 */
public function tok_html_append($tag, $appendHtml, $html) {
	$tag_end = mb_stripos($html, '</'.$tag.'>');

	if ($tag_end > 0) {
		$res = mb_substr($html, 0, $tag_end)."\n".trim($appendHtml)."\n".mb_substr($html, $tag_end);
	}
  else {
    throw new Exception('failed to find tag end', "search=[</$tag>] tag_end=$tag_end");
  }

	return $res;
}


/**
 * Postprocess output. Replace inner html of tag (= <tag>).
 *
 * @tok {html:inner:title}Replace "title" with this{:html}
 *
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
  else {
    throw new Exception('failed to find tag body', "search=[<$tag>] start=$start end=$end");
  }

	return $res;
}


/**
 * Convert html into single line.
 * 
 * @param string $html
 */
public function tok_html_uglify($html) {
	$res = preg_replace('/\s+(<)|(>)\s+/', '\2\1', $html);
	$start = 1;

	while ($start > 0 && ($start = mb_strpos($res, '<style', $start)) > 0) {
		$start = mb_strpos($res, '>', $start);
		$end = mb_strpos($res, '</style>', $start + 1);

		if ($start > 0 && $end >= $start) {
			$res = mb_substr($res, 0, $start + 1).$this->minify_css(mb_substr($res, $start + 1, $end - $start - 1)).mb_substr($res, $end);
		}
	}

	return $res;
}


/**
 * Minify css.
 *
 * @see https://ideone.com/Q5USEF
 * @param string
 * @return string
 */
public function minify_css($str) {

	// remove comments first (simplifies the other regex)
	$re1 = <<<'EOS'
(?sx)
  # quotes
  (
    "(?:[^"\\]++|\\.)*+"
  | '(?:[^'\\]++|\\.)*+'
  )
|
  # comments
  /\* (?> .*? \*/ )
EOS;
    
	$re2 = <<<'EOS'
(?six)
  # quotes
  (
    "(?:[^"\\]++|\\.)*+"
  | '(?:[^'\\]++|\\.)*+'
  )
|
  # ; before } (and the spaces after it while we're here)
  \s*+ ; \s*+ ( } ) \s*+
|
  # all spaces around meta chars/operators
  \s*+ ( [*$~^|]?+= | [{};,>~+-] | !important\b ) \s*+
|
  # spaces right of ( [ :
  ( [[(:] ) \s++
|
  # spaces left of ) ]
  \s++ ( [])] )
|
  # spaces left (and right) of :
  \s++ ( : ) \s*+
  # but not in selectors: not followed by a {
  (?!
    (?>
      [^{}"']++
    | "(?:[^"\\]++|\\.)*+"
    | '(?:[^'\\]++|\\.)*+' 
    )*+
    {
  )
|
  # spaces at beginning/end of string
  ^ \s++ | \s++ \z
|
  # double spaces to single
  (\s)\s+
EOS;
    
	$str = preg_replace("%$re1%", '$1', $str);
	$res = preg_replace("%$re2%", '$1$2$3$4$5$6$7', $str);

	return $res;
}


/** 
 * Pretty print HTML via SimpleXMLElement. Unless html is strict xml formatting
 * this will not work.
 *
 * @see http://gdatatips.blogspot.de/2008/11/xml-php-pretty-printer.html
 * @param string $html
 * @return string
 */
public function tok_html_xml($html) {
	$xml_obj = new \SimpleXMLElement($html);
	$level = 4;
	$indent = 0; // current indentation level
	$pretty = array();
    
	// get an array containing each XML element
	$xml = explode("\n", preg_replace('/>\s*</', ">\n<", $xml_obj->asXML()));

	// shift off opening XML tag if present
	if (count($xml) && preg_match('/^<\?\s*xml/', $xml[0])) {
		$pretty[] = array_shift($xml);
	}

	foreach ($xml as $el) {
		if (preg_match('/^<([\w])+[^>\/]*>$/U', $el)) {
			// opening tag, increase indent
			$pretty[] = str_repeat(' ', $indent) . $el;
			$indent += $level;
		}
		else {
			if (preg_match('/^<\/.+>$/', $el)) {            
				$indent -= $level;  // closing tag, decrease indent
			}

			if ($indent < 0) {
				$indent += $level;
			}

			$pretty[] = str_repeat(' ', $indent) . $el;
		}
	}

	$xml = implode("\n", $pretty);   

	return htmlentities($xml);
}


}

