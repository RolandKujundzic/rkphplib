<?php

namespace rkphplib\tok;

$parent_dir = dirname(__DIR__);
require_once __DIR__.'/TokPlugin.iface.php';
require_once $parent_dir.'/Exception.class.php';
require_once $parent_dir.'/Database.class.php';
require_once $parent_dir.'/JSON.class.php';
require_once $parent_dir.'/File.class.php';
require_once $parent_dir.'/lib/htmlescape.php';
require_once $parent_dir.'/lib/split_str.php';
require_once $parent_dir.'/lib/conf2kv.php';
require_once $parent_dir.'/lib/kv2conf.php';
require_once $parent_dir.'/lib/is_map.php';

use rkphplib\Exception;
use rkphplib\ADatabase;
use rkphplib\Database;
use rkphplib\JSON;
use rkphplib\File;

use function rkphplib\lib\htmlescape;
use function rkphplib\lib\split_str;
use function rkphplib\lib\conf2kv;
use function rkphplib\lib\kv2conf;
use function rkphplib\lib\is_map;



/**
 * Render table data into output template.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class TOutput implements TokPlugin {

// @var array $table
protected $table = null;

// @var array[string]string $env
protected $env = [];

// @var array[string]string $conf
protected $conf = [];

// @var Tokenizer $tok
protected $tok = null;

// @var map $set_search = []
protected $set_search = [];



/**
 * Return {output:set|get|conf|init|loop|json|header|footer|empty}, {sort:} and {search:}.
 */
public function getPlugins(Tokenizer $tok) : array {
	$this->tok = $tok;

	$plugin = [];
	$plugin['output:set']  = TokPlugin::REQUIRE_PARAM;
	$plugin['output:get']  = TokPlugin::REQUIRE_PARAM | TokPlugin::NO_BODY;
	$plugin['output:conf'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY;
	$plugin['output:init'] = TokPlugin::NO_PARAM | TokPlugin::KV_BODY;
	$plugin['output:loop']   = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::REDO | TokPlugin::TEXT;
	$plugin['output:json']   = TokPlugin::NO_PARAM | TokPlugin::NO_BODY;
	$plugin['output:header'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::REDO;
	$plugin['output:footer'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::REDO;
	$plugin['output:empty'] = TokPlugin::REQUIRE_BODY | TokPlugin::REDO | TokPlugin::TEXT;
	$plugin['output'] = 0; // no callback for base plugin
	$plugin['sort'] = TokPlugin::REQUIRE_PARAM | TokPlugin::NO_BODY;
	$plugin['search'] = TokPlugin::REQUIRE_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY;

	return $plugin;
}


/**
 * Return search input. Always call rkphplib.searchOutput() (include js/rkPhpLibJS.js). Examples:
 * 
 * @tok {search:status}options= 1={txt:}active{:txt},99={txt:}deleted{:txt},100={txt:}inactive{:txt}{:search} …
 * <select name="s_status" onchange="rkphplib.searchOutput(this)">
 *   <option value="">...</option>
 *   <option value="1">{txt:}active{:txt}</option>
 *   ...
 * </select>
 * @eol
 *
 * @tok {search:name}width=5{:search} …
 * <input type="text" name="s_name" value="{get:s_name}" style="width:$WIDTHch" onkeypress="rkphplib.searchOutput(this)">
 * @eol
 *
 * @tok {search:name}overlay=1|#|sort=1|#|label=NAME|#| ...{:search} = NAME
 */
public function tok_search(string $col, array $p) : string {
	$res = '';

	if (empty($p['type'])) {
		if (!empty($p['options'])) {
			$p['type'] = 'select';
		}
		else {
			$p['type'] = 'text';
		}
	}

	$s_value = isset($_REQUEST['s_'.$col]) ? htmlescape($_REQUEST['s_'.$col]) : '';

	// \rkphplib\lib\log_debug("TOutput.tok_search:110> col=[$col] type=[".$p['type']."] s_value=[$s_value]");
	if ($p['type'] == 'select') {
		$res = '<select name="s_'.$col.'" onchange="rkphplib.searchOutput(this)">';
		$options = \rkphplib\lib\conf2kv($p['options'], '=', ',');

		if (isset($options['@_1'])) {
			$options[''] = $options['@_1'];
			unset($options['@_1']);
		}

		if ($options == 'auto') {
			if (isset($this->set_search['s_'.$col.'_options'])) {
				$options = split_str(',', $this->set_search['s_'.$col.'_options']);
				foreach ($options as $opt_value) {
					$selected = ($opt_value == $s_value) ? ' selected' : '';
					$opt_value = htmlescape($opt_value);
					$label = $opt_value ? $this->tok->getPluginTxt('txt:', $opt_value) : $this->tok->getPluginTxt('txt:any');
					$res .= '<option value="'.$opt_value.'"'.$selected.'>'.$label."</option>\n";
				}
			}
		}
		else {
			foreach ($options as $value => $label) {
				$selected = ($value == $s_value) ? ' selected' : '';
				$res .= '<option value="'.htmlescape($value).'"'.$selected.'>'.htmlescape($label)."</option>\n";
			}
		}

		$res .= '</select>';
	}
	else if ($p['type'] == 'text') {
		$res = '<input type="text" name="s_'.$col.'" value="'.htmlescape($s_value).'" placeholder="'.
			$this->getSearchPlaceholder($col).'" onkeypress="rkphplib.searchOutput(this)"';

		if (!empty($p['width'])) {
			$res .= ' style="width:'.intval($p['width']).'ch"';
		}

		$res .= '/>'; 
	}

	if (!empty($p['overlay']) && !empty($p['label'])) {
		// ToDo: return input layer
		$res = empty($p['sort']) ? $p['label'] : $p['label'].' '.$this->tok->getPluginTxt('sort:'.$col);
	}

	// \rkphplib\lib\log_debug("TOutput.tok_search:156> return [$res]");
	return $res;
}


/**
 * Return placeholder information for column search.
 */
private function getSearchPlaceholder(string $col) : string {
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
 */
public function tok_sort(string $col) : string {
 
	if (!isset($this->conf['req.sort']) || !isset($this->conf['sort'])) {
		throw new Exception('missing [output:init]sort=...|#|req.sort=...');
	}

	$sort = isset($_REQUEST[$this->conf['req.sort']]) ? $_REQUEST[$this->conf['req.sort']] : $this->conf['sort'];
	$res = $this->conf['sort.no'];
	$new_sort = $this->conf['req.sort'].'=a'.$col;
 
	if ($sort == 'd'.$col) {
		$res = $this->conf['sort.desc'];
		$new_sort = $this->conf['req.sort'].'=';
  }
	else if ($sort == 'a'.$col) {
		$res = $this->conf['sort.asc'];
		$new_sort = $this->conf['req.sort'].'=d'.$col;
  }

	$get_dir = $this->tok->getPluginTxt('get:dir');
	$reset_last = $this->conf['req.last'].'=';
	$link = $this->tok->getPluginTxt('link:', '@='.$get_dir.HASH_DELIMITER.$new_sort.HASH_DELIMITER.$reset_last);

	return str_replace('$link', $link, $res);
}


/**
 * Set conf.name=value.
 */
public function tok_output_set(string $name, string $value) : void {
	if (count($this->conf) == 0) {
		$this->tok_output_conf([]);
	}

	// \rkphplib\lib\log_debug("TOutput.tok_output_set:220> set conf[$name]=[$value]");
	$this->conf[$name] = $value;
}


/**
 * Get env ($name = key) or conf key ($name = conf.key) value.
 *
 * @tok {output:get:conf.query} = SELECT ...
 * @tok {output:get:start|end|pagebreak|rownum|page_num|total|visible|rownum|tags.*|scroll.*} = ...
 * @tok {output:get:conf.*} = conf.query= ... |#| ...
 * @tok {output:get:*} = rownum= ... |#| ...
 * @tok {output:get:row.N.colname} = this.table[n][colname]
 */
public function tok_output_get(string $name) : string {
	// run init and compute stuff if necessary ...
	$this->isEmpty();

	if (mb_substr($name, 0, 5) == 'conf.') {
		$name = mb_substr($name, 5);

		if ($name == '*') {
			return kv2conf($this->conf);
		}

		if (!isset($this->conf[$name])) {
			throw new Exception('No such conf key', $name);
		}

		return $this->conf[$name];
	}

	$res = '';

	if ($name == '*') {
		$res = kv2conf($this->env);
	}
	else if (isset($this->env[$name])) {
		$res = $this->env[$name];
	}
	else if (preg_match('/^row\.([0-9]+)\.(.+)$/', $name, $match)) {
		$n = intval($match[1]);

		if (!isset($this->table[$n])) {
			throw new Exception("Row $n not found (use 0 ... ".(count($this->table) - 1)." as rownum");
		}

		$row = $this->table[$n];
		$col = $match[2];
		if (!isset($row[$col])) {
			throw new Exception("Column $col not found in row", 'colnames: '.join(', ', array_keys($row)));
		}

		$res = $row[$col];
	}
	else if ($name == 'colnum') {
		$res = count($this->conf['column_label']);
	}
	else if (method_exists($this, 'get_'.$name)) {
		$func = 'get_'.$name;
		$res = $this->$func();
	}
	else {
		throw new Exception('No such env key '.$name, print_r($this->env, true));
	}

	return $res;
}


/**
 * Dynamic call in tok_output_get(). Return html option list of searchable columns.
 */
private function get_search_col_options() : string {
	$search_keys = [];

	foreach ($this->conf as $key => $value)	{
		if ($key == 'search' || strpos($key, 'search.') === 0) {
			$list = array_keys(\rkphplib\lib\conf2kv($value, ':', ','));
			$search_keys = array_merge($search_keys, $list);
		}
	}

	return '<option value="">{txt:}search in ...{:txt}</option>'."\n<option>".join("</option>\n<option>", $search_keys)."</option>\n";
}


/**
 * Show if table is (not) empty.
 * @tok {output:empty}no output{:output}
 * @tok {output:empty:no}found output{:output}
 * @tok {output:empty:yes}no output{:output}
 */
public function tok_output_empty(string $if, string $tpl) : string {
	$res = '';

	if (($if == 'yes' || $if == '') && $this->isEmpty()) {
		$res = $tpl;
	}
	else if ($if == 'no' && !$this->isEmpty()) {
		$res = $tpl;
	}

	return $tpl;
}


/**
 * Return true if table is empty.
 */
private function isEmpty() : bool {

	if (is_null($this->table)) {
		$p = (count($this->conf) > 0) ? [ 'reset' => 0 ] : [];
		$this->tok_output_init($p);
	}

	return $this->env['total'] == 0;
}


/**
 * Process conf.column_label. Split into hash, add columns if necessary and save to conf-Table if 
 * necessary.
 */
protected function checkColumnLabel() : void {
	$column_label = \rkphplib\lib\conf2kv($this->conf['column_label'], ':', ',');
	$table_cols = $this->conf['table_desc'];

	foreach ($column_label as $column => $label) {
		if (!isset($table_cols[$column])) {
			unset($column_label[$column]);
		}
		else {
			unset($table_cols[$column]);
		}
	}

	foreach ($table_cols as $column => $cinfo) {
		if (!in_array($column, [ 'owner' ])) {
			$column_label[$column] = $this->tok->getPluginTxt('txt:col_'.$column, $column);
		}
	}

	$this->conf['column_label'] = $column_label;

	// ToDo: ... $this->tok->callPlugin('conf', 'tok_conf_get');
}


/**
 * Return header_label tag replacement.
 */
protected function getHeaderLabel() : string {
	$label_suffix = empty($this->conf['label_suffix']) ? [] : \rkphplib\lib\conf2kv($this->conf['label_suffix'], ':', ',');
	$header_label = [];

	foreach ($this->conf['column_label'] as $column => $label) {
		$suffix = empty($label_suffix[$column]) ? '' : ' data-suffix="'.htmlescape($label_suffix[$column]).'"';
		$sort = empty($this->conf['table_desc'][$column]['key']) ? '' : ' {sort:'.$column.'}';
		$suffix .= ' data-column="'.$column.'"';

		if (empty($this->conf['shorten.label'])) {
			$entry = str_replace([ '$column', '$label', '$sort', '$suffix' ], [ $column, $label, $sort, $suffix ], 
				$this->conf['template.header_label']);
		}
		else {
			$entry = str_replace([ '$column', '$label', '$sort', '$suffix', '$shorten' ], 
				[ $column, $label, $sort, $suffix, intval($this->conf['shorten.label']) ], 
				$this->conf['template.header_label_shorten']);
		}

		array_push($header_label, $entry);
	}

	return join("\n", $header_label); 
}


/**
 * Show if table is not empty.
 *
 * @tok …
 * {output:init}
 * column_label= id:ID, name:NAME|#|
 * template.header_label= <td nowrap align="center"$suffix>$txt_label$sort</td>|#|
 * {:output}
 * @eol
 *
 * @tok {output:header}{:=header_label}{:output} = <table>
 */
public function tok_output_header(string $tpl) : string {
	if ($this->isEmpty()) {
		return '';
	}

	if (!empty($this->conf['column_label'])) {
		$tpl = $this->tok->getPluginTxt('redo:', $this->tok->replaceTags($tpl, [ 'header_label' => $this->getHeaderLabel() ]));
	}

	// \rkphplib\lib\log_debug("TOutput.tok_output_header:412> replace tpl: $tpl");
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

	// \rkphplib\lib\log_debug("TOutput.tok_output_header:431> exit tpl: $tpl");
	return $tpl;
}


/**
 * Show if table is not empty.
 */
public function tok_output_footer(string $tpl) : string {
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
 * Return table json.
 */
public function tok_output_json() : string {
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

	$res = JSON::encode(array_slice($this->table, $start, $end - $start + 1));

	// \rkphplib\lib\log_debug("TOutput.tok_output_json:473> return $res");
	return $res;
}


/**
 * Return $tpl with {:=loop_column} replaced. If conf.action="id,TEMPLATE" is defined
 * use {tpl:TEMPLATE}{:=id}{:tpl} instead of {:=id}.
 */
protected function getOutputLoopTemplate(string $tpl) : string {
	$loop_column = [];

	$language = $this->tok->callPlugin('language:get', 'tok_language_get');
	$action = [];

	if (!empty($this->conf['action'])) {
		$action = split_str(',', $this->conf['action'], 2);
	}

	foreach ($this->conf['column_label'] as $column => $label) {
		$cinfo = $this->conf['table_desc'][$column];
		$is_number = $cinfo['type'] == 'double' || strpos($cinfo['type'], 'int(') !== false;
		$align = $is_number ? ' align="right"' : '';
		$column_tag = $this->tok->getTag($column);

		if (count($action) == 2 && $column == $action[0]) {
			$column_tag = $this->tok->getPluginTxt('tpl:'.$action[1], $column_tag);
		}
		else if ($column == 'status') {
			$column_tag = '<img src="img/status/'.$column_tag.'.gif" title="'.$this->tok->getPluginTxt('txt:', $column_tag).'">';
		}
		else if (in_array($cinfo['type'], [ 'date', 'datetime', 'timestamp' ])) {
			$column_tag = $this->tok->getPluginTxt('date:sql,'.$language, $column_tag);
		}
		else if (!empty($this->conf['shorten.cell']) && (strpos($cinfo['type'], 'varchar(') === 0 || $cinfo['type'] == 'text')) {
			$column_tag = $this->tok->getPluginTxt('shorten:'.intval($this->conf['shorten.cell']), $column_tag);
		}

		$entry = str_replace([ '$column_tag', '$align' ], [ $column_tag, $align ], $this->conf['template.loop_column']);
		array_push($loop_column, $entry);
	}
	
	$tpl = $this->tok->replaceTags($tpl, [ 'loop_column' => join("\n", $loop_column) ]); 
	// \rkphplib\lib\log_debug("TOutput.getOutputLoopTemplate:516> return [$tpl]");
	return $tpl;
}


/**
 * Show if table is not empty. Concat $tpl #env.total. Replace {:=tag} with row value.
 * Default tags are array_keys(row[0]) ({:=0} ... {:=n} if vector or {:=key} if map).
 * If conf.table.columns=col_1n use {:=col_1} ... {:=col_n} as tags. If col.table.columns=first_list
 * use values from row[0] as tags. Otherwise assume conf.table.columns is a comma separted list
 * of tag names. Special tags: 
 * 
 * @tag {:=_rowpos} = 0, 1, 2, ...
 * @tag {:=_rownum} = 1, 2, 3, ...
 * @tag {:=_hash} = key1=value1|#|...
 * @tag {:=_image1}, {:=_image_num}, {:=_image_preview_js} and {:=_image_preview}
 *   if conf.images= colname is set and value (image1, ...) is not empty
 */
public function tok_output_loop(string $tpl) : string {
	if ($this->isEmpty()) {
		return '';
	}

	if (!empty($this->conf['column_label'])) {
		$tpl = $this->getOutputLoopTemplate($tpl);
	}

	$start = $this->env['start'];
	$end = $this->env['end'];
	$lang = empty($this->conf['language']) ? '' : $this->conf['language'];
	$output = [];

	if (!empty($this->conf['query']) && $this->env['pagebreak'] > 0) {
		$start = 0;
		$end = $this->env['end'] % $this->env['pagebreak'];
	}

	// \rkphplib\lib\log_debug("TOutput.tok_output_loop:553> start=$start end=$end lang=$lang tpl:\n$tpl");
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

		// \rkphplib\lib\log_debug("TOutput.tok_output_loop:590> replace: ".print_r($replace, true)); 
		array_push($output, $this->tok->replaceTags($tpl, $replace));

		if ($this->env['rowbreak'] > 0 && $i > 0 && (($i + 1) % $this->env['rowbreak']) == 0 && $i != $end) {
			$rowbreak_html = $this->tok->replaceTags($this->conf['rowbreak_html'], [ 'row' =>  ($i + 1) / $this->env['rowbreak'] ]);
			// \rkphplib\lib\log_debug("TOutput.tok_output_loop:595> rowbreak:\n$rowbreak_html"); 
			array_push($output, $rowbreak_html);
		}
	}

	if ($this->env['rowbreak'] > 0) {
		$fill_rest = $i % $this->env['rowbreak'];

		for ($j = $fill_rest; $j > 0 && $j < $this->env['rowbreak']; $j++) {
			// \rkphplib\lib\log_debug("TOutput.tok_output_loop:604> rowbreak_fill:\n{$this->conf['rowbreak_fill']}");
			array_push($output, $this->conf['rowbreak_fill']);
			$i++;
		}
    
		if ($this->env['pagebreak'] > $this->env['rowbreak'] && $i < $this->env['pagebreak'] && 
				!empty($this->conf['pagebreak_fill']) && !empty($this->conf['pagebreak_fill'])) {
			for ($j = $i; $j < $this->env['pagebreak']; $j++) {
				if ($j % $this->env['rowbreak'] == 0) {
					// \rkphplib\lib\log_debug("TOutput.tok_output_loop:613> rowbreak:\n{$this->conf['rowbreak_html']}");
					array_push($output, $this->conf['rowbreak_html']);    			
				}

				// \rkphplib\lib\log_debug("TOutput.tok_output_loop:617> rowbreak_fill:\n{$this->conf['rowbreak_fill']}"); 
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
 */
protected function getImageTags(array $row) : array {
	if (empty($this->conf['images']) || empty($row[$this->conf['images']])) {
		return [];
	}

	$img_dir = empty($row['_image_dir']) ? '' : $row['_image_dir'].'/';
	$images = split_str(',', $row[$this->conf['images']]);
	$id = $row['id'];

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
 */
public function tok_output_conf(array $p) : void {

	if (count($this->conf) == 0 || !empty($p['reset'])) {
		$tag_min = $this->tok->getTag('min');
		$tag_max = $this->tok->getTag('max');
		$tag_last = $this->tok->getTag('last');
		$get_dir = $this->tok->getPluginTxt('get:dir');
		$link = $this->tok->getPluginTxt('link:', '@='.$get_dir);
		$link_last = $this->tok->getPluginTxt('link:', '@='.$get_dir.HASH_DELIMITER.'last='.$tag_last);
		$txt_label = $this->tok->getPluginTxt('txt:col_$column', '$label');

		$this->conf = [
			'search' => '',
			'sort' => '',
			'sort.desc' => '<a href="$link"><img src="img/sort/desc.gif" border="0" alt=""></a>',
      'sort.asc' => '<a href="$link"><img src="img/sort/asc.gif" border="0" alt=""></a>',
      'sort.no' => '<a href="$link"><img src="img/sort/no.gif" border="0" alt=""></a>',
			'reset' => 1,
			'req.search' => '',
			'req.last' => 'last',
			'req.sort' => 'sort',
			'keep' => SETTINGS_REQ_DIR.',sort,last',
			'images' => '',
			'skip' => 0,
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
			'template.header_label_shorten' => '<td nowrap align="center"$suffix>'.$this->tok->getPluginTxt('shorten:$shorten', $txt_label).'$sort</td>',
			'template.header_label' => '<td nowrap align="center"$suffix>'.$txt_label.'$sort</td>',
			'template.loop_column' => '<td valign="top"$align>$column_tag</td>',
			'shorten.label' => 10,
			'shorten.cell' => 60,
			'scroll.link' => '<a href="'.$link_last.'">'.$this->tok->getTag('link').'</a>',
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
	// \rkphplib\lib\log_debug("TOutput.tok_output_conf:718> this.conf: ".print_r($this->conf, true));
}


/**
 * Initialize and retrieve data. Default parameter values are:
 *
 *  reset= 1
 *  search = 
 *  sort =
 *  sort.desc = <a href="$link"><img src="img/sort/desc.gif" border="0" alt=""></a>
 *  sort.asc = <a href="$link"><img src="img/sort/asc.gif" border="0" alt=""></a>
 *  sort.no = <a href="$link"><img src="img/sort/no.gif" border="0" alt=""></a>
 *  req.sort= sort
 *  req.last= last
 *  keep= SETTINGS_REQ_DIR, $req.sort, $req.last (comma separted list - if search add s_*)
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
 *  template.header_label_shorten= {escape:tok}<td nowrap align="center"$suffix>{shorten:$shorten}{txt:col_$column}$label{:txt}{:shorten}$sort</td>{:escape}
 *  template.header_label= {escape:tok}<td nowrap align="center"$suffix>{txt:col_$column}$label{:txt}$sort</td>{:escape}
 *  template.loop_column= <td valign="top"$align>$column_tag</td>
 *  shorten.label=10
 *  shorten.cell=60
 *  scroll.link= {escape:tok}<a href="{link:}@={get:dir}|#|last={:=last}{:link}">{:=link}</a>{:escape}
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
 */
public function tok_output_init(array $p) : void {

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

	$this->exportLinkKeep();
	$this->computeEnv();

	if ($this->env['pagebreak'] > 0) {
		$this->computeScroll();
	}
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
private function computeEnv() : void {
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
		$this->env['tags'] = split_str(',', $this->conf['table.columns']);
		$this->env['is_map'] = is_map($this->table[$start]);
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
private function computePagebreak() : void {
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
private function computeScroll() : void {
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
 */
private function getScrollJumpHtml() : string {
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
 * Return scroll link. Replace {:=link} and {:=last} in conf[scroll.$key].
 */
private function _scroll_link(string $key, int $last) : string {
	$tpl = $this->conf['scroll.link'];
	$link = $this->conf['scroll.'.$key];

	$res = $this->tok->replaceTags($tpl, [ 'link' => $link, 'last' => $last ]);
	// \rkphplib\lib\log_debug("TOutput._scroll_link:988> key=[$key], last=[$last] tpl=[$tpl] link=[$link] last=[$last] res=[$res]");
	return $res;
}


/**
 * Export link keep map {v:=#link_keep}dir={get:dir}|#|last={get:last}|#|...
 */
private function exportLinkKeep() : void {
	$keep_param = split_str(',', $this->conf['keep']);
	$kv = [];

	$last = $this->conf['req.last'];
	if (!in_array($last, $keep_param)) {
		array_push($keep_param, $last);
	}

	if (!empty($this->conf['search'])) {
		$tmp = array_keys(\rkphplib\lib\conf2kv($this->conf['search'], ':', ','));
		foreach ($tmp as $key) {
			array_push($keep_param, 's_'.$key);
		}
	}

	// \rkphplib\lib\log_debug("TOutput.exportLinkKeep:1012> keep_param: ".join('|', $keep_param));
	foreach ($keep_param as $name) {
		if (isset($_REQUEST[$name])) {
			$kv[$name] = $this->getValue($name);
		}
	}

	$this->tok->setVar('link_keep', $kv);
}


/**
 * Get request parameter value. Request key ist $name or this.conf[req.$name] (if defined).
 */
protected function getValue(string $name) : string {
	$key = empty($this->conf['req.'.$name]) ? $name : $this->conf['req.'.$name];
	return isset($_REQUEST[$key]) ? $_REQUEST[$key] : '';
}


/**
 * Return sql search expression ([where, and]).
 *
 * @see getSqlSearch
 */
protected function getSearch() : array {
	$options = [];

	if (!empty($this->conf['req.search']) && isset($_REQUEST[$this->conf['req.search']])) {
		$s_key = $this->conf['req.search'];
		$s_val = $_REQUEST[$this->conf['req.search']];
		$s_list = empty($this->conf['search.'.$s_key]) ? $this->conf['search'] : $this->conf['search.'.$s_key];

		$options['join_or'] = 1;
		$options['value'] = $s_val;
		$options['search_cols'] = split_str(',', $s_list);
		list ($where, $and) = $this->getSqlSearch($options);

		if (!empty($this->conf['search.'.$s_key]) && !empty($this->conf['search'])) {
			$options = [ 'no_value' => 1 ];
			$options['search_cols'] = split_str(',', $this->conf['search']);
			list ($ignore, $and2) = $this->getSqlSearch($options);

			$where .= $and2;
			$and .= $and2;
		}
	}
	else if (!empty($this->conf['search'])) {
		$options['search_cols'] = split_str(',', $this->conf['search']);
		list ($where, $and) = $this->getSqlSearch($options);
	}

	// \rkphplib\lib\log_debug("TOutput.getSearch:1064> return where=[$where]\nand=[$and]");
	return [ $where, $and ];
}


/**
 * Return sql search expression ([where, and]). Define search via conf.search= COLUMN:METHOD, .... 
 * Search methods: =|EQ, %$%|LIKE, %$|LLIKE, $%|RLIKE, [a,b], [] (with value = a,b), 
 * ]], [[, ][, ?|OPTION, <|LT, >|GT, <=|LE, >=|GE. Place _[WHERE¦AND]_SEARCH in query.
 *
 * @example conf.search= id:=, age:EQ, firstname:LIKE, lastname:%$%, ...
 *
 * Search value is either _REQUEST[s_NAME] of if not set and req.search=X: $_REQUEST[X].
 */ 
protected function getSqlSearch(array $options = []) : array {
  $env = [ 'col' => '', 'cname' => '', 'method' => '', 'value' => '', 'set_search' => [], 'select_search' => [], 'expr' => [] ];

	if (isset($options['search_cols']) && is_array($options['search_cols'])) {
		$search_cols = $options['search_cols'];
	}
	else {
		$search_cols = split_str(',', $this->conf['search']);
	}

	foreach ($search_cols as $col_method) {
		list ($env['col'], $env['method']) = explode(':', $col_method, 2);

		if (($pos = strpos($env['col'], '.')) > 0) {
			$env['cname'] = $env['col'];
			$env['col'] = substr($env['col'], $pos + 1);
		}
		else {
			$env['cname'] = $env['col'];
		}

		$env['value'] = isset($_REQUEST['s_'.$env['col']]) ? $_REQUEST['s_'.$env['col']] : '';
		if (!empty($options['value'])) {
			$env['value'] = $options['value'];
		}
		else if (empty($options['no_value']) && !$env['value'] && !empty($this->conf['req.search']) && 
							isset($_REQUEST[$this->conf['req.search']])) {
			$env['value'] = $_REQUEST[$this->conf['req.search']];
		}

		$found = false;

		if (strlen($env['value']) > 0) {
			$found = $this->searchColumnValue($env);
		}

		if (!$found && preg_match('/^([\[\]]{1})([0-9\-\.\?])*\,?([0-9\-\.\?])*([\[\]]{1})$/', $env['method'], $match)) {
			$found = $this->searchRange($env, $match);
		}
		else if ($env['method'] == 'OPTION' || $env['method'] == '?') {
			if ($env['value']) {
				array_push($env['expr'], $env['cname']." = '".ADatabase::escape($env['value'])."'");
			}

			array_push($env['select_search'], "GROUP_CONCAT(DISTINCT(".$env['cname'].")) AS s_".$env['col']."_options");
		}
	}

	if (count($env['expr']) == 0) {
		return [ '', '' ];
	}

	if (count($env['select_search']) > 0) {
		$env['set_search'] = array_merge($this->selectSearch($env['select_search']), $env['set_search']);
	}

	if (!empty($options['join_or']) || !empty($this->conf['search_or'])) {
		$sql_and = '(('.join(') OR (', $env['expr']).'))';
	}
	else {
		$sql_and = join(' AND ', $env['expr']);
	}

	return [ ' WHERE '.$sql_and, ' AND '.$sql_and ];
}


/**
 * Update $env.
 */
private function searchRange(array &$env, array $match) : bool {
	$col = $env['col'];
	$value = '';

	if (!$env['value'] && isset($_REQUEST['s_'.$col.'_min']) && isset($_REQUEST['s_'.$col.'_max']) &&
			(!empty($_REQUEST['s_'.$col.'_min']) || !empty($_REQUEST['s_'.$col.'_max']))) {
		$value = $_REQUEST['s_'.$col.'_min'].','.$_REQUEST['s_'.$col.'_max'];
	}

	if (empty($value)) {
		return false;
	}

	if ($match[2]) {
		$env['set_search']['s_'.$col.'_min'] = $match[2];
	}
	else {
		array_push($env['select_search'], "MIN($col) AS s_".$col."_min");
	}

	if ($match[3]) {
		$env['set_search']['s_'.$col.'_max'] = $match[3];
	}
	else {
		array_push($env['select_search'], "MAX($col) AS s_".$col."_max");
	}

	array_push($env['expr'], $this->getRangeExpression($col, $value, $env['method']));
	return true;
}


/**
 * Update env.expr if necessary. Return true if found.
 */
private function searchColumnValue(array &$env) : bool {
	$compare = [ 'EQ' => '=', 'LT' => '<', 'GT' => '>', 'LE' => '<=', 'GE' => '>=' ];
	$like = [ 'LIKE' => '%$%', 'LLIKE' => '$%', 'RLIKE' => '%$' ];
	$func = [ 'int' => "=FLOOR('\$')", 'in' => " IN ('\$,')", 'or' => "" ];

	foreach ($compare as $cx => $op) {
		if ($cx == $env['method'] || $op == $env['method']) {
			$num_val = preg_replace('/[^0-9\-\+\.]/', '', $env['value']);

			if ($op != '=' || $num_val == $env['value']) {
				array_push($env['expr'], $env['cname']." $op '".$num_val."'");
			}
			else {
				array_push($env['expr'], $env['cname']." $op '".ADatabase::escape($env['value'])."'");
			}

			return true;
		}
	}

	foreach ($like as $cx => $op) {
		if ($cx == $env['method'] || $op == $env['method']) {
			$env['value'] = preg_replace('/ +/', '%', $env['value']);
			$env['value'] = ADatabase::escape(str_replace('$', $env['value'], $op));
			array_push($env['expr'], $env['cname']." LIKE '".$env['value']."'");

			return true;
		}
	}

	$expr_before = count($env['expr']);

	foreach ($func as $name => $func_call) {
		if ($name == $env['method']) {
			if (strpos($func_call, '$,') !== false) {
				$list = preg_split('/\s*,\s*/', trim($env['value']));
				
				for ($i = 0; $i < count($list); $i++) {
					$list[$i] = ADatabase::escape($list[$i]);
				}

				$env['value'] = str_replace('$,', join("','", $list), $func_call);
				array_push($env['expr'], $env['cname'].$env['value']);
			}
			else if (strpos($func_call, '$') !== false) {
				$env['value'] = str_replace('$', ADatabase::escape($env['value']), $func_call);
				array_push($env['expr'], $env['cname'].$env['value']);
			}
			else if ($name == 'or') {
				$list = preg_split('/\s*,\s*/', trim($env['value']));
				
				for ($i = 0; $i < count($list); $i++) {
					$list[$i] = $env['cname']."='".ADatabase::escape($list[$i])."'";
				}

				array_push($env['expr'], '('.join(' OR ', $list).')');
			}
		}
	}

	// \rkphplib\lib\log_debug("TOutput.searchColumnValue:1243> return - expr_before=[$expr_befrore] env: ".print_r($env, true));
	return $expr_before < count($env['expr']);
}


/**
 * Return select (one) from query.table.
 */
private function selectSearch(array $cols) : array {
	if (empty($this->conf['query.table'])) {
		throw new Exception('missing [output:]query.table=...');
	}

	$query = "SELECT ".join(', ', $cols)." FROM ".ADatabase::escape_name($this->conf['query.table']);
	$db = Database::getInstance($this->conf['query.dsn'], [ 'search_info' => $query ]);
	// \rkphplib\lib\log_debug("TOutput.selectSearch:1258> query.search_info: ".$db->getQuery('search_info'));
	return $db->selectOne($db->getQuery('search_info'));
}


/**
 * Return range expression. You can use col_[min|max] suffix too.
 * Example:
 * 
 * age, "18,24", [] = (18 <= age AND age <= 24)
 * s_age_min=18, s_age_max=24
 */
private function getRangeExpression(string $col, string $value, string $range) : string {
	$value = preg_replace('/[^0-9\-\+\.\,]/', '', $value);

	if (mb_strpos($value, ',') === false) {
		// range [value,value] same as =value 
		return $col."='$value'";
	}

	$bracket_op = [ '[' => '<=', ']' => '>=' ];
	$bracket_1 = mb_substr($range, 0, 1);
	$bracket_2 = mb_substr($range, -1);
	$op1 = $bracket_op[$bracket_1];
	$op2 = $bracket_op[$bracket_2];

	list ($a, $b) = explode(',', $value, 2);
	$len_a = mb_strlen($a);
	$len_b = mb_strlen($b);

	if ($len_a == 0 && $len_b == 0) {
		throw new Exception("invalid range $col=[$a,$b]");
	}
	else if ($len_a == 0) {
		$expr = "('$b' $op2 $col)";
	}
	else if ($len_b == 0) {
		$expr = "('$a' $op1 $col)";
	}
	else {
		$expr = "('$a' $op1 $col AND '$b' $op2 $col)";
	}

	return $expr;
}


/**
 * Return "ORDER BY ...". Use conf.sort or _REQUEST[conf.req.sort] = sort.
 * Sort value is [a|d]colname.
 */
protected function getSqlSort() : string {
  $sort = isset($_REQUEST[$this->conf['req.sort']]) ? $_REQUEST[$this->conf['req.sort']] : $this->conf['sort'];

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
 */
protected function selectData() : void {
	$query = $this->conf['query'];

	if (!empty($this->conf['search'])) {
		$query = str_replace([ '_WHERE_SEARCH', '_AND_SEARCH' ], $this->getSearch(), $query);
	}

	$query = str_replace('_SORT', $this->getSqlSort(), $query);

	$this->conf['query'] = $query;
	$db = Database::getInstance($this->conf['query.dsn'], [ 'output' => $this->conf['query'] ]);
	// \rkphplib\lib\log_debug("TOutput.selectData:1348> query.output: ".$db->getQuery('output', $_REQUEST));
	$db->execute($db->getQuery('output', $_REQUEST), true);

	$this->env['total'] = $db->getRowNumber();
	// \rkphplib\lib\log_debug("TOutput.selectData:1352> found ".$this->env['total'].' entries');
	$this->table = [];

	if ($this->env['start'] >= $this->env['total']) {
		// out of range ... show nothing ...
		$this->env['total'] = 0;
		$db->freeResult();
		return;
	}

	$db->setFirstRow($this->env['start']);
	$n = ($this->env['pagebreak'] > 0) ? 0 : -100000;
	$skip = intval($this->conf['skip']);
	$this->env['total'] -= $skip;

	// \rkphplib\lib\log_debug("TOutput.selectData:1367> show max. $n rows");
	while (($row = $db->getNextRow()) && $n < $this->env['pagebreak']) {
		if ($skip > 0) {
			$skip--;
			continue;
		}

		array_push($this->table, $row);
		$n++;
	}

	if (!empty($this->conf['column_label'])) {
		$this->conf['table_desc'] = $db->getTableDesc($this->conf['query.table']);
		$this->checkColumnLabel();
	}

	// \rkphplib\lib\log_debug('TOutput.selectData:1383> show '.count($this->table).' rows');
	$db->freeResult();
}


/**
 * Load table data from table.data or retrieve from table.url = file|http[s]://.
 * Set env.total.
 */
public function fillTable(?array $table_data = null) : void {
	if (!is_null($table_data)) {
		$this->table = $table_data;
		$this->env['total'] = count($this->table);		
		\rkphplib\lib\log_debug("TOutput.fillTable:1405> env.total=".$this->env['total']);
		return;
	}

	if (!empty($this->conf['table.url'])) {
		$uri = strpos($this->conf['table.url'], '://') ? $this->conf['table.url'] : 'file://'.$this->conf['table.url'];
	}
	else if (!empty($this->conf['table.data'])) {
		$uri = 'string://';
	}
	else if (!is_null($this->table) && isset($this->env['total'])) {
		// use existing ...
		return;
	}
	else {
		throw new Exception('empty table.data and table.url');
	}

	if (empty($this->conf['table.type'])) {
		throw new Exception('empty table.type');
	}

	$table_type = split_str(',', $this->conf['table.type']);
	$uri = array_shift($table_type).':'.$uri;

	$this->table = File::loadTable($uri, $table_type);
	$this->env['total'] = count($this->table);
}


}

