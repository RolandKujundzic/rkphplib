<?php

namespace rkphplib\tok;

$parent_dir = dirname(__DIR__);
require_once(__DIR__.'/TokPlugin.iface.php');
require_once($parent_dir.'/Exception.class.php');
require_once($parent_dir.'/Database.class.php');
require_once($parent_dir.'/File.class.php');
require_once($parent_dir.'/lib/htmlescape.php');
require_once($parent_dir.'/lib/split_str.php');
require_once($parent_dir.'/lib/conf2kv.php');
require_once($parent_dir.'/lib/is_map.php');

use \rkphplib\Exception;
use \rkphplib\ADatabase;
use \rkphplib\Database;
use \rkphplib\File;



/**
 * Render table data into output template.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class TOutput implements TokPlugin {

/** @var array $table */
protected $table = null;

/** @var array[string]string $env */
protected $env = [];

/** @var array[string]string $conf */
protected $conf = [];

/** @var Tokenizer $tok */
protected $tok = null;

/** @var map $set_search = [] */
protected $set_search = [];



/**
 * Register output plugin {output:set|get|conf|init|loop|header|footer|empty} and {sort:}.
 *
 * @param Tokenizer $tok
 * @return map<string:int>
 */
public function getPlugins($tok) {
	$this->tok = $tok;

	$plugin = [];
	$plugin['output:set']  = TokPlugin::REQUIRE_PARAM;
	$plugin['output:get']  = TokPlugin::REQUIRE_PARAM | TokPlugin::NO_BODY;
	$plugin['output:conf'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY;
	$plugin['output:init'] = TokPlugin::NO_PARAM | TokPlugin::KV_BODY;
	$plugin['output:loop']   = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::REDO | TokPlugin::TEXT;
	$plugin['output:header'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::REDO;
	$plugin['output:footer'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::REDO;
	$plugin['output:empty'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY;
	$plugin['output'] = 0; // no callback for base plugin
	$plugin['sort'] = TokPlugin::REQUIRE_PARAM | TokPlugin::NO_BODY;
	$plugin['search'] = TokPlugin::REQUIRE_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY;

	return $plugin;
}


/**
 * Return search input. Always call rkphplib.searchOutput() (include js/rkPhpLibJS.js). Examples:
 * 
 * @tok {search:status}options= 1={txt:}active{:txt},99={txt:}deleted{:txt},100={txt:}inactive{:txt}{:search} =
 *   <select name="s_status" onchange="rkphplib.searchOutput(this)"><option value="">...</option><option value="1">{txt:}active{:txt}</option>...</select>
 *
 * @tok {search:name}width=5{:search} =
 *   <input type="text" name="s_name" value="{get:s_name}" style="width:$WIDTHch" onkeypress="rkphplib.searchOutput(this)">
 *
 * @tok {search:name}overlay=1|#|sort=1|#|label=NAME|#| ...{:search} = NAME
 *
 * @param string $col
 * @param map $p
 * @return string
 */
public function tok_search($col, $p) {
	$res = '';

	if (empty($p['type'])) {
		if (!empty($p['options'])) {
			$p['type'] = 'select';
		}
		else {
			$p['type'] = 'text';
		}
	}

	$value = isset($_REQUEST['s_'.$col]) ? \rkphplib\lib\htmlescape($_REQUEST['s_'.$col]) : '';

	\rkphplib\lib\log_debug("tok_search($col, ...)> type=[".$p['type']."] value=[$value]");
	if ($p['type'] == 'select') {
		$res = '<select name="s_'.$col.'" onchange="rkphplib.searchOutput(this)">';
		$options = \rkphplib\lib\conf2kv($p['options'], '=', ',');

		if (isset($options['@_1'])) {
			$options[''] = $options['@_1'];
			unset($options['@_1']);
		}

		if ($options == 'auto') {
			if (isset($this->set_search['s_'.$col.'_options'])) {
				$options = \rkphplib\lib\split_str(',', $this->set_search['s_'.$col.'_options']);
				foreach ($options as $opt_value) {
					$opt_value = \rkphplib\lib\htmlescape($opt_value);
					$label = $opt_value ? $this->tok->getPluginTxt('txt:', $opt_value) : $this->tok->getPluginTxt('txt:any');
					$res .= '<option value="'.$opt_value.'">'.$label."</option>\n";
				}
			}
		}
		else {
			foreach ($options as $value => $label) {
				$res .= '<option value="'.\rkphplib\lib\htmlescape($value).'">'.\rkphplib\lib\htmlescape($label)."</option>\n";
			}
		}

		if ($value) {
			$res = str_replace('<option value="'.$value.'">', '<option value="'.$value.'" selected>', $res);
		}
		else {
			$res = str_replace('<option value="">', '<option value="" selected>', $res);
		}

		$res .= '</select>';
	}
	else if ($p['type'] == 'text') {
		$res = '<input type="text" name="s_'.$col.'" value="'.$value.'" placeholder="'.$this->getSearchPlaceholder($col).'" onkeypress="rkphplib.searchOutput(this)"';

		if (!empty($p['width'])) {
			$res .= 'style="width:'.intval($p['width']).'ch"';
		}

		$res .= '/>'; 
	}

	if (!empty($p['overlay']) && !empty($p['label'])) {
		// ToDo: return input layer
		$res = empty($p['sort']) ? $p['label'] : $p['label'].' '.$this->tok->getPluginTxt('sort:'.$col);
	}

	\rkphplib\lib\log_debug("tok_search> return [$res]");
	return $res;
}


/**
 * Return placeholder information for column search.
 * 
 * @param string $col
 * @return string
 */
private function getSearchPlaceholder($col) {
	$res = '';

	if (isset($this->set_search['s_'.$col.'_min']) && isset($this->set_search['s_'.$col.'_max'])) {
		$res = $this->set_search['s_'.$col.'_min'].','.$this->set_search['s_'.$col.'_max'];
	}
	else if (isset($this->set_search['s_'.$col.'_min'])) {
		$res = $this->set_search['s_'.$col.'_min'].',';
	}
	else if (isset($this->set_search['s_'.$col.'_max'])) {
		$res = ','.$this->set_search['s_'.$col.'_max'];
	}

	return $res;
}


/**
 * Return sort icon (conf.sort.desc|asc|no). Sort by conf.sort = [a(scending)|d(escending)]COLNAME
 * or _REQUEST[conf.req.sort].
 *
 * @throws
 * @param string $col
 * @return string
 */
public function tok_sort($col) {
  $sort = empty($_REQUEST[$this->conf['req.sort']]) ? $this->conf['sort'] : $_REQUEST[$this->conf['req.sort']];
	$res = $this->conf['sort.no'];
 
	if (empty($this->conf['sort'])) {
		throw new Exception('missing [output:init]sort=...');
	}

	if ($sort == 'd'.$col) {
		$res = $this->conf['sort.desc'];
  }
	else if ($sort == 'a'.$col) {
		$res = $this->conf['sort.asc'];
  }

	return str_replace('$keep', $this->conf['keep'], $res);
}


/**
 * Set conf.name=value.
 *
 * @param string $name
 * @param string $value
 * @return ''
 */
public function tok_output_set($name, $value) {

	if (count($this->conf) == 0) {
		$this->tok_output_conf([]);
	}

	$this->conf[$name] = $value;
	\rkphplib\lib\log_debug("TOutput::set> [$name]=[$value] conf: ".print_r($this->conf, true));
	return '';
}


/**
 * Get env ($name = key) or conf key ($name = conf.key) value.
 *
 * @throws
 * @param string $name
 * @return string
 */
public function tok_output_get($name) {

	// run init and compute stuff if necessary ...
	$this->isEmpty();

	if (mb_substr($name, 0, 5) == 'conf.') {
		$name = mb_substr($name, 5);

		if (!isset($this->conf[$name])) {
			throw new Exception('No such conf key', $name);
		}
		
		return $this->conf[$name];
	}
	
	if (!isset($this->env[$name])) {
		throw new Exception('No such env key '.$name, print_r($this->env, true));
	}

	return $this->env[$name];
}


/**
 * Show if table is empty.
 *
 * @param string $tpl
 * @return string
 */
public function tok_output_empty($tpl) {
	if (!$this->isEmpty()) {
		return '';
	}

	return $tpl;
}


/**
 * Return true if table is empty.
 *
 * @return bool
 */
private function isEmpty() {

	if (is_null($this->table)) {
		$p = (count($this->conf) > 0) ? [ 'reset' => 0 ] : [];
		$this->tok_output_init($p);
	}

	return $this->env['total'] == 0;
}


/**
 * Show if table is not empty.
 *
 * @param string $tpl
 * @return string
 */
public function tok_output_header($tpl) {
	if ($this->isEmpty()) {
		return '';
	}

	if (!empty($this->env['tags'][0]) && $this->tok->hasReplaceTags($tpl, [ $this->env['tags'][0] ])) {
		$replace = [];

		for ($i = 0; $i < count($this->env['tags']); $i++) {
			$tag = $this->env['tags'][$i];

			if ($this->conf['table.columns'] == 'col_1n') {
				// A=chr(65) ... Z=chr(90) AA ... AZ ... ZA .. ZZ
				$replace[$tag] = ($i < 26) ? chr($i + 65) : chr((intval($i / 26) - 1) + 65).chr(($i % 26) + 65);
			}
			else {
				$replace[$tag] = $tag;
  		}

			$tpl = $this->tok->replaceTags($tpl, $replace);
  	}
	}

	return $tpl;
}


/**
 * Show if table is not empty.
 *
 * @param string $tpl
 * @return string
 */
public function tok_output_footer($tpl) {
	if ($this->isEmpty()) {
		return '';
	}

	if (isset($this->env['scroll'])) {
		$tpl = $this->tok->replaceTags($tpl, $this->env['scroll'], 'scroll.');
		$tpl = $this->tok->replaceTags($tpl, $this->env);
	}

	return $tpl;
}


/**
 * Show if table is not empty. Concat $tpl #env.total. Replace {:=tag} with row value.
 * Default tags are array_keys(row[0]) ({:=0} ... {:=n} if vector or {:=key} if map).
 * If conf.table.columns=col_1n use {:=col_1} ... {:=col_n} as tags. If col.table.columns=first_list
 * use values from row[0] as tags. Otherwise assume conf.table.columns is a comma separted list
 * of tag names. Special tags: 
 * 
 * @tag {:=_rowpos} - 0, 1, 2, ...
 * @tag {:=_rownum} - 1, 2, 3, ...
 * @tag {:=_image1}, {:=_image_num}, {:=_image_preview_js} and {:=_image_preview}
 *   if conf.images= colname is set and value (image1, ...) is not empty
 *
 * @throws
 * @param string $arg
 * @return string
 */
public function tok_output_loop($tpl) {
	if ($this->isEmpty()) {
		return '';
	}

	$start = $this->env['start'];
	$end = $this->env['end'];
	$lang = empty($this->conf['language']) ? '' : $this->conf['language'];
	$output = [];

	if (!empty($this->conf['query']) && $this->env['pagebreak'] > 0) {
		$start = 0;
		$end = $this->env['end'] % $this->env['pagebreak'];
	}

	for ($i = $start; $i <= $end; $i++) {
		$row = $this->table[$i];

		$replace = $this->getImageTags($row);
		$replace['_rowpos'] = $this->env['last'] + $i;
		$replace['_rownum'] = $this->env['last'] + $i + 1;

		if (!$this->env['is_map']) {
			$j = 0;
			foreach ($row as $key => $value) {
				if ($j >= count($this->env['tags'])) {
					throw new Exception('invalid tag', "i=$i j=$j key=$key value=$value tags: ".
						print_r($this->env['tags'], true).' row: '.print_r($row, true)); 
				}

				$tag = $this->env['tags'][$j];
				$replace[$tag] = $value;
				$j++;
			}
		}
		else {
			$tag_num = count($this->env['tags']);
			for ($j = 0; $j < $tag_num; $j++) {
				$tag = $this->env['tags'][$j];
		
				if (mb_strpos($tag, '.') > 0) {
					throw new Exception("todo: replace $tag");
				}
				else if (!isset($row[$tag]) && !array_key_exists($tag, $row)) {
					throw new Exception('invalid tag '.$tag, "row[$i]: ".print_r($row, true));
				}

				$replace[$tag] = $row[$tag];
			}
		}

		array_push($output, $this->tok->replaceTags($tpl, $replace));

		if ($this->env['rowbreak'] > 0 && $i > 0 && (($i + 1) % $this->env['rowbreak']) == 0 && $i != $end) {
			$rowbreak_html = $this->tok->replaceTags($this->conf['rowbreak_html'], [ 'row' =>  ($i + 1) / $this->env['rowbreak'] ]);
			array_push($output, $rowbreak_html);
		}
	}

	if ($this->env['rowbreak'] > 0) {
		$fill_rest = $i % $this->env['rowbreak'];

		for ($j = $fill_rest; $j > 0 && $j < $this->env['rowbreak']; $j++) {
			array_push($output, $this->conf['rowbreak_fill']);
			$i++;
		}
    
		if ($this->env['pagebreak'] > $this->env['rowbreak'] && $i < $this->env['pagebreak'] && 
				!empty($this->conf['pagebreak_fill']) && !empty($this->conf['pagebreak_fill'])) {
			for ($j = $i; $j < $this->env['pagebreak']; $j++) {
				if ($j % $this->env['rowbreak'] == 0) {
					array_push($output, $this->conf['rowbreak_html']);    			
				}

				array_push($output, $this->conf['rowbreak_fill']);    		
			}
		}	
	}

	$res = join('', $output);
	return $res;
}


/**
 * If conf.images is empty or $row[conf.images] is empty return empty map.
 * Otherwise split image list (image1, ...) and set map keys _image1, 
 * _image_num, _image_preview_js and _image_preview.
 *
 * @param map $row
 * @return map
 */
protected function getImageTags($row) {
	if (empty($this->conf['images']) || empty($row[$this->conf['images']])) {
		return [];
	}

	$images = \rkphplib\lib\split_str(',', $row[$this->conf['images']]);
	$id = $row['id'];

	$img_dir = empty($row['_image_dir']) ? '' : $row['_image_dir'].'/';

	$res = [];
	$res['_image1'] = $img_dir.$images[0];
	$res['_image_num'] = count($images);
	$res['_image_preview'] = '<div style="position:relative"><div class="preview_img" id="preview_img_'.$id.'"></div></div>';
	$res['_image_preview_js'] = ' onmouseout="hideOLIP('."'$id'".')" onmouseover="showOLIP('."'$id'".', '."'".
		$img_dir.$images[0]."'".')" ';
	
	return $res;
}


/**
 * Use if you need more configuration blocks beside {output:init}. Parameter are
 * the same as in tok_output_init. Fill this.conf with default value if 
 * this.conf == [] or $p['reset'] = 1. Overwrite with values from $p. 
 *
 * Enable sql sort with conf.sort= id, name, ... and place _SORT in query.
 *
 * @param array[string]string $p
 */
public function tok_output_conf($p) {

	if (count($this->conf) == 0 || !empty($p['reset'])) {
		$tag_min = $this->tok->getTag('min');
		$tag_max = $this->tok->getTag('max');

		$this->conf = [
			'search' => '',
			'sort' => '',
			'sort.desc' => '<a href="{link:$keep}"><img src="img/sort/desc.gif" border="0" alt=""></a>',
      'sort.asc' => '<a href="{link:$keep}"><img src="img/sort/asc.gif" border="0" alt=""></a>',
      'sort.no' => '<a href="{link:$keep}"><img src="img/sort/no.gif" border="0" alt=""></a>',
			'reset' => 1,
			'req.last' => 'last',
			'req.sort' => 'sort',
			'keep' => SETTINGS_REQ_DIR.',sort,last',
			'images' => '',
			'pagebreak' => 0,
			'pagebreak_fill' => 1,
			'rowbreak' => 0,
			'rowbreak_html' => '</tr><tr>',
			'rowbreak_fill' => '<td></td>',
			'query.dsn' => '',
			'table.columns' => 'array_keys',
			'table.type' => '',			
			'table.data' => '',
			'table.url' => '',
			'scroll.link' => '<a href="index.php?'.$this->tok->getTag('keep').'">'.$this->tok->getTag('link').'</a>',
			'scroll.first' => '<img src="img/scroll/first.gif" border="0">',
			'scroll.prev' => '<img src="img/scroll/prev.gif" border="0">',
			'scroll.next' => '<img src="img/scroll/next.gif" border="0">',
			'scroll.last' => '<img src="img/scroll/last.gif" border="0">',
			'scroll.no_first' => '<img src="img/scroll/no_first.gif" border="0">',
			'scroll.no_prev' => '<img src="img/scroll/no_prev.gif" border="0">',
			'scroll.no_next' => '<img src="img/scroll/no_next.gif" border="0">',
			'scroll.no_last' => '<img src="img/scroll/no_last.gif" border="0">',
			'scroll.jump' => $tag_min.' - '.$tag_max,
			'scroll.jump_active' => '<b>'.$tag_min.' - '.$tag_max.'</b>',
			'scroll.jump_delimiter' => '&nbsp;|&nbsp;',
			'scroll.jump_num' => '4'
		];
	}

	foreach ($p as $key => $value) {
		$this->conf[$key] = $value;
	}
}


/**
 * Initialize and retrieve data. Default parameter values are:
 *
 *  reset= 1
 *  search = 
 *  sort =
 *  sort.desc = <a href="{link:$keep}"><img src="img/sort/desc.gif" border="0" alt=""></a>
 *  sort.asc = <a href="{link:$keep}"><img src="img/sort/asc.gif" border="0" alt=""></a>
 *  sort.no = <a href="{link:$keep}"><img src="img/sort/no.gif" border="0" alt=""></a>
 *  req.sort= sort
 *  req.last= last
 *  keep= SETTINGS_REQ_DIR, $req.sort, $req.last (comma separated list)
 *  images= (use {:=_image_num}, {:=_image1}, {:=_image_preview})  
 *  pagebreak= 0
 *  pagebreak_fill= 1
 *  rowbreak= 0
 *  rowbreak_html= </tr><tr>
 *  rowbreak_fill= <td></td>
 *  query= use Database if set - use _[WHERE|AND]_SEARCH and _SORT if conf.search|sort is set
 *  query.dsn= (use SETTINGS_DSN if empty)
 *  query.table= (sql table name)
 *  table.type= (use: "split, |&|, |@|", "split, |&|, |@|, =", csv, unserialize or json)
 *  table.columns= array_keys (or: col_1n / first_list / tag1, ... )
 *  table.url= (e.g. path/to/file = file://path/to/file or http[s]://...) 
 *  table.data= 
 *  scroll.link= <a href="index.php?{:=keep}">{:=link}</a>
 *  scroll.first= <img src="img/scroll/first.gif" border="0">
 *  scroll.prev= <img src="img/scroll/prev.gif" border="0">
 *  scroll.next= <img src="img/scroll/next.gif" border="0">
 *  scroll.last= <img src="img/scroll/last.gif" border="0">
 *  scroll.no_first= <img src="img/scroll/no_first.gif" border="0">
 *  scroll.no_prev= <img src="img/scroll/no_prev.gif" border="0">
 *  scroll.no_next= <img src="img/scroll/no_next.gif" border="0">
 *  scroll.no_last= <img src="img/scroll/no_last.gif" border="0">
 *  scroll.jump= {:=min} - {:=max}
 *  scroll.jump_active= <b>{:=min} - {:=max}</b>
 *  scroll.jump_delimiter= &nbsp;|&nbsp;
 *  scroll.jump_num= 4
 *
 * @see File::loadTable for table.type 
 * @param array[string]string $p
 * @return if search=yes or conf.images
 */
public function tok_output_init($p) {

	if (!isset($p['reset'])) {
		$p['reset'] = 1;
	}

	$this->tok_output_conf($p);

	$this->computePagebreak();

	if (!empty($this->conf['query'])) {
		$this->selectData();
	}
	else {
	  $this->fillTable();
	}

	$this->computeEnv();

	if ($this->env['pagebreak'] > 0) {
		$this->computeScroll();
	}

	return $this->getJavascript();
}


/**
 * Return javascript for search and edit.
 *
 * @param string
 */
protected function getJavascript() {
	
	if (empty($this->conf['search']) && empty($this->conf['edit']) && empty($this->conf['images'])) {
		return '';
	}

	$res = '<script type="text/javascript">';

$search_js = <<<END
var search_output_timeout = null;

//-----------------------------------------------------------------------------
function searchOutput(el) {
  var value = el.value;

  if (search_output_timeout) {
    clearTimeout(search_output_timeout);
    search_output_timeout = null;
  }
  
  search_output_timeout = setTimeout(function() { el.form.submit(); }, 1200);
}
END;

$edit_js = <<<END
// ToDo ...
END;

$images_js = <<<END
//-----------------------------------------------------------------------------
function showOLIP(id, picture) {
	var preview_div = document.getElementById('preview_img_' + id);

  if (!preview_div) {
    return;
  }
  
  if (!document.getElementById('img_' + id) && picture.length > 4) {
    var preview_img = document.createElement('img');
    preview_img.setAttribute('src', picture);
    preview_img.setAttribute('id', 'img_' + id);
    document.getElementById('preview_img_' + id).appendChild(preview_img);
  }

  preview_div.style.visibility = 'visible';   
}

//-----------------------------------------------------------------------------
function hideOLIP(id) {
  var preview_div = document.getElementById('preview_img_' + id);
  if (preview_div) {
    preview_div.style.visibility = 'hidden';    
  }
}
END;

	if (!empty($this->conf['search'])) {
		$res .= $search_js;
	}

	if (!empty($this->conf['edit'])) {
		$res .= $edit_js;
	}

	if (!empty($this->conf['images'])) {
		$res .= $images_js;
	}

	$res .= "</script>";

	if (!empty($this->conf['images'])) {
		$res .= <<<END
<style type="text/css">
div.preview_img {
  position:absolute;
  visibility:hidden;
  z-index:1;
  top: 4px;
  left:24px;
  width: 150px;
  height: 200px;
  text-align:center;
  background: #fff;
  border: 2px solid #cacaca;
}
</style>
END;
	}

	return $res;
}


/**
 * Compute missing env keys (this->table is filled):
 *
 *  is_map= true|false
 *  rowbreak= 0 
 *  visible= #rows visible
 *  end= position of last visible row (= #rows-1 if no pagebreak)
 *  page_num= #pages
 *  tags= 
 *  next_pos= int
 *
 */
private function computeEnv() {

	$this->env['is_map'] = false;
	$this->env['rowbreak'] = intval($this->conf['rowbreak']);
	$this->env['tags'] = [];

	if (count($this->table) == 0) {
		// no output ...
		$this->env['rownum'] = 0;
		$this->env['page_num'] = 0;
		$this->env['visible'] = 0;
		$this->env['start'] = 0;
		$this->env['end'] = 0;
		return;
	}

	if ($this->conf['table.columns'] == 'first_line') {
		$this->env['tags'] = array_shift($this->table);
		$this->env['total']--;
	}

	if ($this->env['pagebreak'] == 0) {
		$this->env['visible'] = $this->env['total'];
		$this->env['end'] = $this->env['total'] - 1;
		$this->env['page_num'] = 1;
	}
	else {
		$this->env['end'] = ($this->env['last'] + $this->env['pagebreak'] < $this->env['total']) ?
			$this->env['last'] + $this->env['pagebreak'] - 1 : $this->env['total'] - 1;

	  $this->env['visible'] = $this->env['end'] - $this->env['start'] + 1;
		$this->env['page_num'] = ceil($this->env['total'] / $this->env['pagebreak']);
	}

	$start = $this->env['start'];
	$this->env['rownum'] = $this->env['end'] - $this->env['start'] + 1;

	if (empty($this->conf['table.columns']) || $this->conf['table.columns'] == 'array_keys') {
		$this->env['tags'] = isset($this->table[0]) ? array_keys($this->table[0]) : array_keys($this->table[$start]);
		$this->env['is_map'] = true;
	}
	else if ($this->conf['table.columns'] == 'col_1n') {
		for ($i = 1; $i <= count($this->table[$start]); $i++) {
			array_push($this->env['tags'], 'col_'.$i);
		}
	}
	else if (count($this->env['tags']) == 0) {
		$this->env['tags'] = \rkphplib\lib\split_str(',', $this->conf['table.columns']);
		$this->env['is_map'] = \rkphplib\lib\is_map($this->table[$start]);
	}

	if (count($this->env['tags']) == 0 || strlen($this->env['tags'][0]) == 0) {
		throw new Exception('invalid table columns', 'table.columns='.$this->conf['table.columns'].' tags: '.
			print_r($this->env['tags'], true));
	}
}


/**
 * Compute pagebreak. Set env keys:
 * 
 *  pagebreak= conf.pagebreak
 *  start= position of first visible row (= 0 if no pagebreak)
 *  last= 0 or start-1 if pagebreak and start > 0
 *  page= 1 or last/pagebreak + 1
 *
 */
private function computePagebreak() {

	$pagebreak = intval($this->conf['pagebreak']);
	$this->env['pagebreak'] = $pagebreak;

	if ($pagebreak == 0) {
		$this->env['start'] = 0;
		$this->env['last'] = 0;
		$this->env['page'] = 1;
		return;
	}

	$last = $pagebreak ? intval($this->getValue('last')) : 0;

	if ($last < 0) {
		throw new Exception('scroll error', "last=$last < 0");
	}

	if ($last % $pagebreak != 0 || $last != intval($last / $pagebreak) * $pagebreak) {
		throw new Exception('scroll error', "last % pagebreak = $last % $pagebreak != 0 or last != intval(last/pagebreak) * pagebreak");
	}

	$this->env['start'] = $last;
	$this->env['last'] = $last;
	$this->env['page'] = ($last / $pagebreak) + 1;
}


/**
 * Compute env.scroll keys:
 *
 *  scroll.first= link
 *  scroll.prev= link
 *  scroll.next= link
 *  scroll.last= link
 *  scroll.jump= html
 */
private function computeScroll() {

	if ($this->env['last'] > 0) {
		$scroll['first'] = $this->_scroll_link('first', 0);
		$scroll['prev'] = $this->_scroll_link('prev', ($this->env['last'] - $this->env['pagebreak']));
	}
	else {
		$scroll['first'] = $this->conf['scroll.no_first'];
		$scroll['prev'] = $this->conf['scroll.no_prev'];
	}

	if ($this->env['last'] + $this->env['pagebreak'] < $this->env['total']) {
		$this->env['next_pos'] = $this->env['last'] + $this->env['pagebreak'];
		$scroll['next'] = $this->_scroll_link('next', $this->env['next_pos']);
		$scroll['last'] = $this->_scroll_link('last', ($this->env['page_num'] - 1) * $this->env['pagebreak']);
	}
	else {
		$this->env['next_pos'] = 0;
		$scroll['next'] = $this->conf['scroll.no_next'];
		$scroll['last'] = $this->conf['scroll.no_last'];
	}

	if (!empty($this->conf['scroll.jump']) && $this->conf['scroll.jump_num'] > 0) {
		$scroll['jump'] = $this->getScrollJumpHtml();
	}

	$this->env['scroll'] = $scroll;
}


/**
 * Return scroll jump html.
 * 
 * @return string
 */
private function getScrollJumpHtml() {

	$pbreak = $this->conf['pagebreak'];
	$jn = $this->conf['scroll.jump_num'];
	$j2 = intval($jn / 2);
	$cpage = $this->env['page'];
	$lpage = $this->env['page_num'];

	$jfpage = min($cpage - $j2, $lpage - $jn + 1);
	$jfpage = max(1, $jfpage); 
	$jlpage = min($lpage, $jfpage + $jn - 1);

	$res = '';

	for ($i = $jfpage; $i <= $jlpage; $i++) {
		if ($i != $cpage) {
			$jump = $this->_scroll_link('jump', (($i - 1) * $pbreak));
		}
		else {
			$jump = $this->conf['scroll.jump_active'];
		}

		$jump = $this->tok->replaceTags($jump, [ 'page' => $i ]);
		$jump = $this->tok->replaceTags($jump, [ 'min' => ($i - 1) * $pbreak + 1 ]);

		if ($i * $pbreak <= $this->env['total']) {
			$jump = $this->tok->replaceTags($jump, [ 'max' => $i * $pbreak ]);
		}
		else {
			$jump = $this->tok->replaceTags($jump, [ 'max' => $this->env['total'] ]);
		}

		if ($i > $jfpage) {
			$res .= $this->conf['scroll.jump_delimiter'];
		}

		$res .= $jump;
	}

	return $res;
}


/**
 * Return scroll link. Replace {:=link}, {:=keep[_crypt]} and {:=last} in conf[scroll.$key].
 * 
 * @param string $key
 * @param int $last
 * @return string
 */
private function _scroll_link($key, $last) {

	$kv = [ $this->conf['req.last'] => rawurlencode($last) ];
	$keep_param = \rkphplib\lib\split_str(',', $this->conf['keep']);

	foreach ($keep_param as $name) {
		if (isset($_REQUEST[$name])) {
			$value = $this->getValue($name);
			$kv[$name] = $value;
		}
	}

	$res = $this->tok->replaceTags($this->conf['scroll.link'], [
		'link' => $this->conf['scroll.'.$key],
		'keep' => http_build_query($kv), 
		'keep_crypt' => TBase::encodeHash($kv),
		'last' => $last
	]);

	return $res;
}


/**
 * Get request parameter value. Request key ist $name or this.conf[req.$name] (if defined).
 * 
 * @param string $name
 * @return string
 * 
 */
protected function getValue($name) {
	$key = empty($this->conf['req.'.$name]) ? $name : $this->conf['req.'.$name];
	return isset($_REQUEST[$key]) ? $_REQUEST[$key] : '';
}


/**
 * Return sql search expression. Define search via conf.search= COLUMN:METHOD, .... 
 * Search methods: =|EQ, %$%|LIKE, %$|LLIKE, $%|RLIKE, [a,b], [] (with value = a,b), 
 * ]], [[, ][, ?|OPTION, <|LT, >|GT, <=|LE, >=|GE. Place _[WHERE¦AND]_SEARCH in query.
 *
 * @example conf.search= id:=, age:EQ, firstname:LIKE, lastname:%$%, ...
 *
 * @throws
 * @return array [ _WHERE_SEARCH, _AND_SEARCH ]
 */ 
protected function getSqlSearch() {
	$compare = [ 'EQ' => '=', 'LT' => '<', 'GT' => '>', 'LE' => '<=', 'GE' => '>=' ];
	$like = [ 'LIKE' => '%$%', 'LLIKE' => '$%', 'RLIKE' => '%$' ];

	$this->set_search = [];
	$select_search = [];
	$expr = [];

	$search_cols = \rkphplib\lib\split_str(',', $this->conf['search']);

	foreach ($search_cols as $col_method) {
		list ($col, $method) = explode(':', $col_method, 2);
		$value = isset($_REQUEST['s_'.$col]) ? $_REQUEST['s_'.$col] : '';
		$found = false;

		foreach ($compare as $cx => $op) {
			if ($cx == $method || $op == $method) {
				if ($value) {
					array_push($expr, $col.' '.$op." '".preg_replace('/[^0-9\-\+\.]/', '', $value)."'");
				}

				$found = true;
			}
		}

		if (!$found) {
			foreach ($like as $cx => $op) {
				if ($cx == $method || $op == $method) {
					if ($value) {
						$value = ADatabase::escape(str_replace('$', $value, $op));
						array_push($expr, $col." LIKE '$value'");
					}

					$found = true;
				}
			}
		}

		if ($found) {
			// do nothing ...
		}
		else if (preg_match('/^([\[\]]{1})([0-9\-\.\?])*\,?([0-9\-\.\?])*([\[\]]{1})$/', $method, $match)) {
			$op1 = $match[1];
			$op2 = $match[4];

			if ($match[2]) {
				$this->set_search['s_'.$col.'_min'] = $match[2];
			}
			else {
				array_push($select_search, "MIN($col) AS s_".$col."_min");
			}

			if ($match[3]) {
				$this->set_search['s_'.$col.'_max'] = $match[3];
			}
			else {
				array_push($select_search, "MAX($col) AS s_".$col."_max");
			}

			if ($value) {
				array_push($expr, $this->getRangeExpression($col, $value, $method));
			}
		}
		else if ($method == 'OPTION' || $method == '?') {
			if ($value) {
				array_push($expr, $col." = '".ADatabase::escape($value)."'");
			}

			array_push($select_search, "GROUP_CONCAT(DISTINCT($col)) AS s_".$col."_options");
		}
		else {
			throw new Exception("search method [$method] not found ($col=$value)");
		}
	}

	if (count($expr) == 0) {
		return [ '', '' ];
	}

	if (count($select_search) > 0) {
		$this->set_search = array_merge($this->selectSearch($select_search), $this->set_search);
	}

	$sql_and = join(' AND ', $expr);

	return [ ' WHERE '.$sql_and, ' AND '.$sql_and ];
}


/**
 * Return range limits and options for search evaluation. 
 *
 * @throws
 * @param vector $cols 
 * @return map
 */
private function selectSearch($cols) {
	if (empty($this->conf['query.table'])) {
		throw new Exception('missing [output:]query.table=...');
	}

	$query = "SELECT ".join(', ', $cols)." FROM ".ADatabase::escape_name($this->conf['query.table']);
	$db = Database::getInstance($this->conf['query.dsn'], [ 'search_info' => $query ]);
	// \rkphplib\lib\log_debug("TOutput::selectSearch> ".$db->getQuery('search_info'));
	return $db->selectOne($db->getQuery('search_info'));
}


/**
 * Return range expression. Example:
 * 
 * age, "18,24", [] = (18 <= age AND age <= 24)
 *
 * @throws
 * @return string
 */
private function getRangeExpression($col, $value, $range) {
	$value = preg_replace('/[^0-9\-\+\.\,]/', '', $value);

	if (mb_strpos($value, ',') === false) {
		// range [value,value] same as =value 
		return $col."='$value'";
	}

	list ($a, $b) = explode(',', $value, 2);

	if (mb_strlen($a) == 0) {
		throw new Exception("invalid number a=[$a] in a,b");
	}

	if (mb_strlen($b) == 0) {
		throw new Exception("invalid number b=[$b] in a,b");
	}

	$bracket_op = [ '[' => '<=', ']' => '>=' ];
	$bracket_1 = mb_substr($range, 0, 1);
	$bracket_2 = mb_substr($range, -1);
	$op1 = $bracket_op[$bracket_1];
	$op2 = $bracket_op[$bracket_2];

	$expr = "('$a' $op1 $col AND '$b' $op2 $col)";
	return $expr;
}


/**
 * Return "ORDER BY ...". Use conf.sort or _REQUEST[conf.req.sort] = sort.
 * Sort value is [a|d]colname.
 *
 * @throws
 * @return string ''|ORDER BY ...
 */
protected function getSqlSort() {
  $sort = empty($_REQUEST[$this->conf['req.sort']]) ? $this->conf['sort'] : $_REQUEST[$this->conf['req.sort']];

	if (empty($sort)) {
		return '';
	}

	$direction = mb_substr($sort, 0, 1);
	$column = ADatabase::escape_name(mb_substr($sort, 1));
	$res = 'ORDER BY '.$column;
		
	if ($direction == 'a') {
		// ASC = ASCENDING = DEFAULT
	}
	else if ($direction == 'd') {
		$res .= ' DESC';
	}
	else {
		throw new Exception("invalid sort value [$sort]");
	}

	return $res;
}


/**
 * Load data with select query. Extract only data we are displaying.
 *
 */
protected function selectData() {
	$query = $this->conf['query'];

	if (!empty($this->conf['search'])) {
		$query = str_replace([ '_WHERE_SEARCH', '_AND_SEARCH' ], $this->getSqlSearch(), $query);
	}

	if (!empty($this->conf['sort'])) {
		$query = str_replace('_SORT', $this->getSqlSort(), $query);
	}

	$this->conf['query'] = $query;
	$db = Database::getInstance($this->conf['query.dsn'], [ 'output' => $this->conf['query'] ]);
	// \rkphplib\lib\log_debug("TOutput::selectData> ".$db->getQuery('output', $_REQUEST));
	$db->execute($db->getQuery('output', $_REQUEST), true);

	$this->env['total'] = $db->getRowNumber();
	// \rkphplib\lib\log_debug("TOutput::selectData> found ".$this->env['total'].' entries');
	$this->table = [];

	if ($this->env['start'] >= $this->env['total']) {
		// out of range ... show nothing ...
		$this->env['total'] = 0;
		$db->freeResult();
		return;
	}

	$db->setFirstRow($this->env['start']);
	$n = ($this->env['pagebreak'] > 0) ? 0 : -100000;

	// \rkphplib\lib\log_debug("TOutput::selectData> show max. $n rows");
	while (($row = $db->getNextRow()) && $n < $this->env['pagebreak']) {
		array_push($this->table, $row);
		$n++;
	}

	$db->freeResult();
	// \rkphplib\lib\log_debug('TOutput::selectData> show '.count($this->table).' rows');
}


/**
 * Load table data from table.data or retrieve from table.url = file|http[s]://.
 * Set env.total.
 *
 */
protected function fillTable() {

	if (!empty($this->conf['table.url'])) {
		$uri = strpos($this->conf['table.url'], '://') ? $this->conf['table.url'] : 'file://'.$this->conf['table.url'];
	}
	else if (!empty($this->conf['table.data'])) {
		$uri = 'string://';
	}
	else {
		throw new Exception('empty table.data and table.url');
	}

	if (empty($this->conf['table.type'])) {
		throw new Exception('empty table.type');
	}

	$table_type = \rkphplib\lib\split_str(',', $this->conf['table.type']);
	$uri = array_shift($table_type).':'.$uri;

	$this->table = File::loadTable($uri, $table_type);

	$this->env['total'] = count($this->table);
}


}

