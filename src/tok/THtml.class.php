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
 * Return {html:tag|inner|append|meta|meta_og|tidy|xml|uglify}, {text2html:}, {input:checkbox|radio} and {user_agent:} 
 */
public function getPlugins(object $tok) : array {
  $plugin = [];
  $plugin['html:tag'] = TokPlugin::REQUIRE_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::POSTPROCESS;
  $plugin['html:inner'] = TokPlugin::REQUIRE_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::POSTPROCESS;
  $plugin['html:append'] = TokPlugin::REQUIRE_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::POSTPROCESS;
	$plugin['html:meta'] = TokPlugin::REQUIRE_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::POSTPROCESS;
	$plugin['html:meta_og'] = TokPlugin::REQUIRE_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::POSTPROCESS;
	$plugin['html:tidy'] = TokPlugin::NO_PARAM | TokPlugin::NO_BODY | TokPlugin::POSTPROCESS;
	$plugin['html:xml'] = TokPlugin::NO_PARAM | TokPlugin::NO_BODY | TokPlugin::POSTPROCESS;
	$plugin['html:uglify'] = TokPlugin::NO_PARAM | TokPlugin::NO_BODY | TokPlugin::POSTPROCESS;
	$plugin['html'] = 0;

	$plugin['text2html'] = 0;

	$plugin['input:checkbox'] = TokPlugin::REQUIRE_PARAM | TokPlugin::KV_BODY;
	$plugin['input:radio'] = TokPlugin::REQUIRE_PARAM | TokPlugin::KV_BODY;
	$plugin['input'] = TokPlugin::REQUIRE_PARAM | TokPlugin::PARAM_LIST | TokPlugin::KV_BODY;

	$plugin['user_agent'] = TokPlugin::REQUIRE_PARAM | TokPlugin::PARAM_CSLIST;

  return $plugin;
}


/**
 * Return 1 if visitors browser matches entry from $user_agent_list.
 * 
 * @tok {user_agent:Android} -> 1 if true
 * @tok {user_agent:iPad|iPhone|iPod} = {user_agent:iOS} -> 1 if true
 * @tok {user_agent:Handheld} = {user_agent:iPad|iPhone|iPod|Android} -> 1 if true
 */
public static function tok_user_agent(array $user_agent_list) : string {
  $ua = $_SERVER['HTTP_USER_AGENT'];
  $res = '';

	if (count($user_agent_list) == 1) {
		$ua_name = $user_agent_list[0];

		if ($ua_name == 'iOS') {
			$user_agent_list = [ 'iPad', 'iPhone', 'iPod' ];
		}
		else if ($ua_name == 'Handheld') {
			$user_agent_list = [ 'iPad', 'iPhone', 'iPod', 'Android' ];
		}
	}
	
	for ($i = 0; empty($res) && $i < count($user_agent_list); $i++) {
		if (strstr($ua, $user_agent_list[$i])) {
      $res = '1';
    }
  }

  return $res;
}


/**
 * Render text with html tags. Replace "\r?\n" with <br>.
 *
 * @tok {text2html:}a\nb\nc{:text2html} -> a<br/>b<br/>c
 *
 * @param string $param
 * @param string $text
 * @return string
 */
public function tok_text2html($param, $text) {

	if (empty($param)) {
		$text = preg_replace("/\r?\n/", "<br/>", $text);
	}

	return $text;
}


/**
 * Return checkbox input html.
 *
 * @tok  {input:checkbox:agb}class=form-control{:checkbox} + $_REQUEST[agb]=1
 *   <input type="checkbox" name="agb" value="1" class="form-control" checked/>
 * 
 * @param string $name
 * @param hash $attrib
 * @return string
 */
public function tok_input_checkbox($name, $attrib) {
  $html = '<input name="'.$name.'"';

  if (empty($attrib['type'])) {
    $attrib['type'] = 'checkbox';
  }

  if (!isset($attrib['value'])) {
    $attrib['value'] = 1;
  }

  foreach ($attrib as $key => $value) {
    $html .= ' '.$key.'="'.str_replace('"', '\"', $value).'"';
  }

  if (!empty($_REQUEST[$name]) && $_REQUEST[$name] == $attrib['value']) {
    $html .= ' checked';
  }

  return $html.'/>';
}


/**
 * Return checkbox input html.
 *
 * @tok  {input:radio:of_age} + $_REQUEST[of_age]=1
 *   <input type="radio" name="of_age" value="1" checked />
 * 
 * @param string $name
 * @param hash $attrib
 * @return string
 */
public function tok_input_radio($name, $attrib) {
	$attrib['type'] = 'radio';
	return $this->tok_input_checkbox($name, $attrib);
}


/**
 * Return inut html.
 *
 * @tok {input:button}value=Continue{:input} : <input type="button" value="Continue" />
 * @tok {input:text:email} + $_REQUEST[email]='joe@nowhere.com' : <input type="text" name="email" value="joe@nowhere.com" />
 * 
 * @param vector $type_name (0 = type, 1 = name)
 * @param hash $attrib
 * @return string
 */
public function tok_input($type_name, $attrib) {
	$html = '<input type="'.$type_name[0].'"';

	if (!empty($type_name[1])) {
		$name = $type_name[1];
		$html .= ' name="'.$name.'"';

		if (!isset($attrib['value']) && isset($_REQUEST[$name])) {
			$attrib['value'] = $_REQUEST[$name];
		}
	}

  foreach ($attrib as $key => $value) {
    $html .= ' '.$key.'="'.str_replace('"', '\"', $value).'"';
  }

  return $html.'/>';
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
 * Replace name tags with value.
 *
 * @tok {html:tag:test}hallo{:html} -> A{:=test}A = AhalloA
 * 
 * @param string $name
 * @param string $value
 * @param string $html
 */
public function tok_html_tag($name, $value, $html) {
	$html = str_replace('{:='.$name.'}', $value, $html);
	return $html;
}


/**
 * Replace meta tag value. Parameter: url, type, title, description, image, ... .
 * 
 * @tok {html:meta_og:url}https://...{:html} -> <meta property="og:url" content="https://..." />
 * 
 * @throws
 * @param string $property
 * @param string $content
 * @param string $html
 * @return string
 */
public function tok_html_meta_og($property, $content, $html) {
	$search = '<meta property="og:'.$property.'" content="';
	$start = mb_stripos($html, $search);
	$search_len = mb_strlen($search);
	$end = mb_stripos($html, '"', $start + $search_len);

	if ($start > 0 && $end >= $start + $search_len) {
		$res = mb_substr($html, 0, $start).$search.$content.mb_substr($html, $end);
	}
	else {
		throw new Exception('failed to find meta tag property og:'.$property.' content', "search=[$search] start=$start end=$end");
	}

  return $res;
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
 * @tok {html:append:head}<!-- insert appendHtml before </head> -->{:html}
 *
 * @param string $tag
 * @param string $appendHtml
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

