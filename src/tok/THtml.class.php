<?php

namespace rkphplib\tok;

require_once __DIR__.'/TokPlugin.iface.php';
require_once __DIR__.'/../XCrypt.class.php';

use rkphplib\Exception;
use rkphplib\XCrypt;


/**
 * HTML plugin.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 */
class THtml implements TokPlugin {


/**
 * Return {html:tag|inner|append|meta|meta_og|tidy|xml|uglify}, {text2html:}, {input:checkbox|radio|select} and {user_agent:} 
 */
public function getPlugins(Tokenizer $tok) : array {
	$plugin = [];
	$plugin['html:tag'] = TokPlugin::REQUIRE_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::POSTPROCESS;
	$plugin['html:inner'] = TokPlugin::REQUIRE_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::POSTPROCESS;
	$plugin['html:append'] = TokPlugin::REQUIRE_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::POSTPROCESS;
	$plugin['html:meta'] = TokPlugin::REQUIRE_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::POSTPROCESS;
	$plugin['html:meta_og'] = TokPlugin::REQUIRE_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::POSTPROCESS;
	$plugin['html:tidy'] = TokPlugin::NO_PARAM | TokPlugin::NO_BODY | TokPlugin::POSTPROCESS;
	$plugin['html:xml'] = TokPlugin::NO_PARAM | TokPlugin::NO_BODY | TokPlugin::POSTPROCESS;
	$plugin['html:uglify'] = TokPlugin::NO_PARAM | TokPlugin::NO_BODY | TokPlugin::POSTPROCESS;
	$plugin['html:nobr'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY;
	$plugin['html'] = 0;

	$plugin['text2html'] = 0;

	$plugin['input:checkbox'] = TokPlugin::KV_BODY;
	$plugin['input:radio'] = TokPlugin::KV_BODY;
	$plugin['input:select'] = TokPlugin::KV_BODY;
	$plugin['input:xcrypt'] = TokPlugin::NO_PARAM | TokPlugin::KV_BODY;
	$plugin['input'] = TokPlugin::REQUIRE_PARAM | TokPlugin::PARAM_LIST | TokPlugin::KV_BODY;

	$plugin['user_agent'] = TokPlugin::REQUIRE_PARAM | TokPlugin::PARAM_CSLIST;

  return $plugin;
}


/**
 * Replace <br/> with ' '.
 */
public static function tok_html_nobr(string $txt) : string {
	$res = str_replace( [ '<br>', '<br/>' ], [ ' ', ' ' ], $txt);
	// \rkphplib\lib\log_debug([ "THtml::tok_html_nobr:56> THtml.tok_html_nobr(<1>) = [<2>]", $txt, $res ]);
	return $res;
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
 */
public function tok_text2html(string $param, string $text) : string {

	if (empty($param)) {
		$text = preg_replace("/\r?\n/", "<br/>", $text);
	}

	return $text;
}


/**
 * Return checkbox input html.
 *
 * @tok  {input:checkbox:agb}class=form-control|#|checked=1{:checkbox} + $_REQUEST[agb]=1
 *   <input type="checkbox" name="agb" value="1" class="form-control" checked/>
 */
public function tok_input_checkbox(string $name, array $p) : string {
	if ($name) {
		$p['name'] = $name;
	}

  if (empty($p['type'])) {
    $p['type'] = 'checkbox';
  }

  if (!isset($p['value'])) {
    $p['value'] = 1;
  }

  $html = $this->getInputHtml($p);

  return $html.'/>';
}


/**
 * Return checkbox input html.
 *
 * @tok  {input:radio:of_age} + $_REQUEST[of_age]=1
 *   <input type="radio" name="of_age" value="1" checked />
 */
public function tok_input_radio(string $name, array $p) : string {
	$p['type'] = 'radio';
	return $this->tok_input_checkbox($name, $p);
}


/**
 * Return select input html.
 *
 * @tok  {input:select:}name=num{:=item}|#|min=0|#|max=99|#|value={:=num}|#|onchange="…"{:input} …
 * <select name="num1001" onchange="…">
 *   <option>0</option><option selected>1</option> … <option>99</option>
 * </select>
 * @eol
 * 
 * @tok {input:select:test}option= @1 ,a,b{:input} …
 * <select name="test"><option></option><option>a</option><option>b</option></select>
 * @eol
 * 
 * @tok {input:select:test}option= @2 m:Male; f:Female|#|empty=…{:input} …
 * <select name="test"><option value="">…</option>
 *   <option value="m">Male</option><option value="f">Female</option></select>
 * @eol
 */
public function tok_input_select(string $name, array $p) : string {
	$p['tag'] = 'select';

	if (!isset($p['size'])) {
		$p['size'] = '1';
	}

	if ($name) {
		$p['name'] = $name;
	}

	$res = $this->getInputHtml($p).'>';

	$value = isset($p['value']) ? $p['value'] : '';
	if ($value && isset($_REQUEST[$p['name']])) {
		$value = $_REQUEST[$p['name']];
	}

	if (isset($p['empty'])) {
		$res .= "\n".'<option value="">'.$p['empty'].'</option>';	
	}

	if (isset($p['min']) && isset($p['max'])) {
		for ($i = $p['min']; $i <= $p['max']; $i++) {
			$selected = ("$i" === $value) ? ' selected' : '';
			$res .= "\n<option$selected>".$i.'</option>'; 
		}
	}
	else if (isset($p['option'])) {
		if (!is_array($p['option'])) {
			throw new Exception('use option= @1|@2', print_r($p, true));
		}

		if (isset($p['option'][0])) {
			foreach ($opt_list as $opt) {
				$selected = ($opt === $value) ? ' selected' : '';
				$res .= "\n<option$selected>".$opt.'</option>'; 
			}
		}
		else {
			foreach ($p['option'] as $val => $label) {
				$selected = ($val === $value) ? ' selected' : '';
				$res .= "\n".'<option value="'.$val.'"'.$selected.'>'.$label.'</option>'; 
			}
		}
	}

	return $res."\n</select>";
}


/**
 * Return tag html
 */
private function getInputHtml(array $p) : string {
	if (!isset($p['tag'])) {
		$p['tag'] = 'input';
	}

	$res = '<'.$p['tag'];

	if ($p['tag'] == 'input') {
		if ($p['type'] == 'checkbox') {
	  	if (!empty($p['name']) && isset($p['value']) && isset($_REQUEST[$p['name']]) && 
					$_REQUEST[$p['name']] == $p['value']) {
    		$p['checked'] = 1;
			}
		}

		if (!isset($p['value']) && !empty($p['name']) && isset($_REQUEST[$p['name']])) {
			$p['value'] = $_REQUEST[$p['name']];
		}
	}
	else if (isset($p['value'])) {
		unset($p['value']);
	}

	$attrib_list = [ 'name', 'type', 'value', 'preset', 'size', 'id', 'onchange', 'class',
		'style', 'title' ];
	foreach ($attrib_list as $attr) {
		if (isset($p[$attr])) {
			$res .= ' '.$attr.'="'.str_replace('"', '\\"', $p[$attr]).'"';
		}
	}

	$single_attr = [ 'checked', 'required', 'disabled' ];
	foreach ($single_attr as $attr) {
		if (!empty($p[$attr])) {
			$res .= ' '.$attr;
		}
	}

	return $res;
}


/**
 * Return input html. First paramter is [ $type, $name ].
 *
 * @tok {input:button}value=Continue{:input} : <input type="button" value="Continue" />
 * @tok {input:text:email} + $_REQUEST[email]='joe@nowhere.com' : <input type="text" name="email" value="joe@nowhere.com" />
 */
public function tok_input(array $type_name, array $p) : string {
	$p['type'] = $type_name[0];

	if (!empty($type_name[1])) {
		$p['name'] = $type_name[1];
	}

	if (!isset($p['value']) && !empty($p['name']) && isset($_REQUEST[$p['name']])) {
		$p['value'] = $_REQUEST[$p['name']];
	}

	return $this->getInputHtml($p).'/>';
}


/**
 * Return hidden input with xcrypt encoded data.
 *
 * @global SETTINGS_XCRYPT_SECRET SETTINGS_XCRYPT_RKEY
 *
 * @code settings.php …
 * define('SETTINGS_XCRYPT_SECRET', 'abc123');
 * define('SETTINGS_XCRYPT_RKEY', 'xcr'); 
 * @EOL
 *
 * @tok  {input:xcrypt}login=joe|#|password=xyz{:input} == …
 *   <input type="hidden" name="xcr" value="Cw0GTUpKGx4PXlVaDx4TUEFAFg0RVU4OUw--" />
 * @EOL
 */
public function tok_input_xcrypt(array $p) : string {
	$xcr = new XCrypt(SETTINGS_XCRYPT_SECRET);
	return '<input type="hidden" name="'.SETTINGS_XCRYPT_RKEY.'" value="'.
		$xcr->encodeArray($p, true).'"/>';
}


/**
 * Postprocess output. Pretty print Html via DOM.
 */
public function tok_html_tidy(string $html) : string {
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
 */
public function tok_html_tag(string $name, string $value, string $html) : string {
	$html = str_replace('{:='.$name.'}', $value, $html);
	return $html;
}


/**
 * Replace meta tag value. Parameter: url, type, title, description, image, ... .
 * 
 * @tok {html:meta_og:url}https://...{:html} -> <meta property="og:url" content="https://..." />
 */
public function tok_html_meta_og(string $property, string $content, string $html) : string {
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
 */
public function tok_html_meta(string $name, string $value, string $html) : string {
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
 * Postprocess output. Append html to first tag (before </tag>).
 *
 * @tok {html:append:head}<!-- insert appendHtml before </head> -->{:html}
 */
public function tok_html_append(string $tag, string $appendHtml, string $html) : string {
	$etag = '</'.$tag.'>';
	$etl = strlen($etag);
	$tag_end = mb_stripos($html, $etag);
	$appendHtml = trim($appendHtml);

	if (strpos($appendHtml, '<'.$tag.'>') === 0 && substr($appendHtml, -1 * $etl) == $etag) {
		$appendHtml = trim(substr($appendHtml, $etl - 1, -1 * $etl));
	}

	if ($tag_end > 0) {
		$res = mb_substr($html, 0, $tag_end)."\n".$appendHtml."\n".mb_substr($html, $tag_end);
	}
  else {
    throw new Exception('failed to find tag end', "search=[</$tag>] tag_end=$tag_end html=[".substr($html, 0, 80).' …]');
  }

	return $res;
}


/**
 * Postprocess output. Replace inner html of <tag>...</tag> 
 * or id="ID">...</tag>] if $tag = tag:id. Return modified html.
 *
 * @tokBefore <title></title><h3 id="headline"></h3>
 * @tok {html:inner:title}New Title{:html}
 * @tok {html:inner:h3:headline}New Headline{:html}
 * @tokAfter <title>New Title</title><h3 id="headline">New Headline</h3>
 * @return string
 */
public function tok_html_inner(string $tag, string $innerHtml, string $html) : string {
	if (strpos($tag, ':') > 0) {
		list ($tag, $id) = explode(':', $tag, 2);
		if (($start = mb_stripos($html, 'id="'.$id.'">')) === false) {
    	throw new Exception('missing id="'.$id.'">');
		}

		$start += mb_strlen($id) + 6;
	}
	else {
		if (($start = mb_stripos($html, '<'.$tag.'>')) === false) {
    	throw new Exception('missing <'.$tag.'>');
		}

		$start += mb_strlen($tag) + 2;
	}

	if (($end = mb_stripos($html, '</'.$tag.'>', $start)) === false) {
    throw new Exception('missing </'.$tag.'>');
	}

	return mb_substr($html, 0, $start).$innerHtml.mb_substr($html, $end);
}


/**
 * Convert html into single line.
 */
public function tok_html_uglify(string $html) : string {
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
 */
public function minify_css(string $str) : string {

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
 */
public function tok_html_xml(string $html) : string {
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

