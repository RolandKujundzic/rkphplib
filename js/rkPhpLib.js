"use strict";
/* global console */
/* jshint browser: true */
/* jshint globalstrict: true */


/**
 * Javascript functions referenced from rkphplib. Vanilla JS, no dependencies.
 *
 * @copyright Roland Kujundzic 2017
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
function rkPhpLib() {
/* jshint validthis: true */

/** @var this me */
var me = this;

/** @var map env */
var env = [];


/**
 * Hide fselect_list_NAME. Show fselect_input_NAME.
 * 
 * @param Node selbox
 */
this.fselectInput = function (selbox) {

	if (selbox.value != '_') {
		return;
	}

	var name = selbox.getAttribute('name');

	var sbox_in = document.getElementById('fvin_' + name + '_');
	sbox_in.value = '';

	toggleDisplay('fselect_list_' + name);
	toggleDisplay('fselect_input_' + name);
};


/**
 * Close fselect input. Add value as selected (if length > 0).
 * Debounced because multiple calls are possible.
 *
 * @param Node inbox 
 */
this.fselectList = debounce(function (inbox) {
	var name = inbox.getAttribute('name').slice(0, -1);
  var selbox = document.getElementById('fvin_' + name);

	if (inbox.value.length == 0) {
  	selbox.options[0].selected = true;
	}
	else {
	  var newOpt = document.createElement('option');
	  newOpt.text = inbox.value;
	  newOpt.value = inbox.value;

	  try {
  	  selbox.add(newOpt, null);
	  }
  	catch(ex) {
	    selbox.add(newOpt); // IE only
	  }

	  selbox.options[selbox.length - 1].selected = true;
	}

	toggleDisplay('fselect_list_'  + name);
	toggleDisplay('fselect_input_' + name);  
}, 1000, true);


/**
 * Execute ajax call. Options:
 *
 * - method: GET (=default)
 * - url: required
 * - json: true|false (JSON.parse result, default = false)
 * - success: callback success(data) if sucess
 * - error: callback error(status, data) if server error or error(0, '') if connection error - if not set throw exception
 *
 * @param map options
 */
this.ajax = function(options) {
	var request = new XMLHttpRequest();

	if (!options.method) {
		options.method = 'GET';
	}

	if (!options.error) {
		options.error = function(code, data) {
			console.log('ajax query result: ', data);
			throw 'ajax query failed with code ' + code;
		};
	}

	request.open(options.method, options.url, true);

	request.onload = function() {
		if (request.status >= 200 && request.status < 400) {
			var data = request.responseText;

			if (data.indexOf('<html') > -1) {
    		options.error(request.status, request.responseText);
			}
			else {
				if (options.json) {
					data = JSON.parse(data);
				}

				options.success(data);
			}
		}
		else {
    	options.error(request.status, request.responseText);
		}
	};

	request.onerror = function() {
  	options.error(0, '');
	};

	request.send();
};


/**
 * Submit element parent form. Delay submit by 1.2sec. Reset submit if new
 * search parameter is added within delay.
 *
 * @plugin TOutput.tok_search
 * @param Element el
 */
this.searchOutput = function(el) {
	var value = el.value;

	if (env.search_output_timeout) {
		clearTimeout(env.search_output_timeout);
		env.search_output_timeout = null;
	}

	env.search_output_timeout = setTimeout(function() { el.form.submit(); }, 1200);
};


/**
 * Execute search - redirect to el.getAttribute('data-url') + el.value.
 *
 * @param Element el
 */
this.search = function(el) {
	var sval = this.getSearchValue(el);

	if (!sval || sval.length == 0) {
		return;
	}

	var url = el.getAttribute('data-search_url') + '&' + el.getAttribute('name') + '=' + encodeURI(sval);  

	if (sval !== el.value) {
		url += '&' + el.getAttribute('name') + '_label=' + encodeURI(el.value); 
	}

	window.location.href = url;
};


/**
 * Return data-value or value of selected datalist option.
 */
this.getSearchValue = function(el) {
	var i, opt, found, value = el.value, list = document.getElementById(el.getAttribute('list'));

	if (!list) {
		return el.value;
	}

	for (i = 0; !found && i < list.options.length; i++) {
		opt = list.options[i];
		if (opt.value === value) {
			found = opt.getAttribute('data-value') ? opt.getAttribute('data-value') : value;
		}
	}

	return found;
};


/**
 * Update search list.
 *
 * @see example/ajax_live_search
 * @param Element el
 */
this.updateSearchList = function(el) {
	var last_search = el.getAttribute('data-search'), curr_search = el.value;

	if (!curr_search || curr_search === last_search) {
		return;
	}

	if (env.update_search_timeout) {
		clearTimeout(env.update_search_timeout);
		env.update_search_timeout = null;
	}

	env.update_search_timeout = setTimeout(function() {
		var list_url = el.getAttribute('data-search_list_url') + '&' + el.getAttribute('name') + '=';

		me.ajax({ url: list_url + encodeURI(curr_search), json: true, success: function (list) {
			var key, options = '', search_list = document.getElementById(el.getAttribute('list'));

			if (navigator.userAgent.indexOf("Safari") > -1) {
				// Safari has no datalist support
				search_list.innerHTML = '<select onChange="rkphplib.search(this)"></select>';
				var sl_id = '#' + el.getAttribute('list');
				search_list = document.querySelector(sl_id + ' > select');
			}

			var n = 0;
			for (key in list) {
				if (typeof list[key] === 'object') {
					options += '<option data-value="' + list[key].value + '">' + list[key].label + "</option>\n";
				}
				else if (key !== n && key.length > 0) {
					options += '<option data-value="' + key + '">' + list[key] + "</option>\n";
				}
				else {
					options += '<option>' + list[key] + "</option>\n";
				}

				n++;
			}

			search_list.innerHTML = options;

			el.setAttribute('data-search', curr_search);
		}});
	}, 600);
};


/**
 * Create search box for output table.
 *
 * @param Element el
 */
this.setOutputSearch = function (el) {
	var tbl_num = parseInt(el.getAttribute('data-output'));
	var tbl = env.output[tbl_num - 1];
	var tr = tbl.children[0].children[1].children;
	var column = el.value;
	var i, label;

	for (i = 0; !label && i < el.options.length; i++) {
		if (el.options[i].selected) {
			label = el.options[i].innerText;
		}
	}

	var input = document.createElement('input');
	var other = tbl.querySelectorAll('input[name=s_' + column + ']');
	if (other.length > 0) {
		input.setAttribute('name', 's_' + column + '.' + other.length);
	}
	else {
		input.setAttribute('name', 's_' + column);
	}

	input.setAttribute('class', 'output_search');
	input.setAttribute('placeholder', label);
	input.setAttribute('value', '');
	input.onchange = function () { el.form.submit(); };
	input.addEventListener('keypress', function (evt) {
		var input = evt.target;
		if (!input.value && (evt.keyCode === 8 || evt.keyCode === 46)) {
			input.parentNode.removeChild(input);
		}
	});

	el.parentNode.appendChild(input);
	el.value = '';
};


/**
 * Replace existing image. Hidden input replace_image must exist.
 *
 * @param element el
 * @param int num
 */
this.replaceImage = function (el, num) {
	var fvin_id = el.parentNode.getAttribute('data-preview');
	var fvin = document.getElementById(fvin_id);

	if (fvin.hasAttribute('multiple')) {
		fvin.removeAttribute('multiple');
		var name = fvin.getAttribute('name');
		fvin.setAttribute('name', name.slice(0, -2));
		removeOverlayIcon(fvin_id);
		env.restoreMultiple = fvin_id;
	}

	fvin.addEventListener('change', function(evt) {
		submitForm(evt.target.form, { 'replace_image': fvin_id.substr(5) + ':' + (num + 1) });
	});
};


/**
 * Remove image from list.
 *
 * @param novel el
 * @param int num
 */
this.removeImage = function (el, num) {
	var fvin_id = el.parentNode.getAttribute('data-preview');
	var f = document.getElementById(fvin_id).form;
	submitForm(f, { 'remove_image': fvin_id.substr(5) + ':' + (num + 1) });
};


/**
 * Toggle delete and replace links.
 */
this.modifyImage = function (el, num) {
	var fvin_id = el.parentNode.getAttribute('data-preview');
	var fvin = document.getElementById(fvin_id);

	restoreMultiple();

	var pel = removeOverlayIcon(fvin_id);

	if (pel != el.parentNode) {
		addOverlayIcon(el, num);
	}
};


/**
 * Execute output action (el.value). If action == add redirect to data-add.
 *
 * @param element el
 * @param string type
 */
this.outputAction = function (el, type) {
	var action = el.value, doItemSbox = document.getElementById('output_action_select').innerHTML;
	var doItemLink = '<a onclick="rkphplib.outputItemAction(this, ' + "'link'" + ')"></a>';
	var tbody = el.parentNode.parentNode.parentNode;

	if (action == 'add') {
		window.location.href = el.getAttribute('data-add');
	}
	else if (action == 'do_on' || action == 'do_off') {
		var i, rows = tbody.children;

  	for (i = 2; i < rows.length - 1; i++) {
			var td = rows[i].children[0];
			var tdc = td.children[0];

			if (action == 'do_on' && tdc.tagName == 'SELECT' || action == 'do_off' && tdc.tagName == 'A') {
				continue;
			}
			else if (action == 'do_on') {
				if (!td.getAttribute('data-id')) {
					td.setAttribute('data-id', tdc.innerText);
				}

				if (!td.getAttribute('data-edit')) {
					td.setAttribute('data-edit', tdc.getAttribute('data-edit'));
				}

				td.innerHTML = doItemSbox;
				td.children[0].options[0].innerText = td.getAttribute('data-id');
			}
			else if (action == 'do_off') {
				td.innerHTML = doItemLink;
				td.children[0].innerText = td.getAttribute('data-id');
			}
    }
	}
};


/**
 * Execute output item action. If type is link show select.
 *
 * @param element el
 * @param string type
 */
this.outputItemAction = function (el, type) {
	var id, url, td = el.parentNode, value = el.value;

	if (!td.getAttribute('data-id')) {
		id = el.getAttribute('data-id') ? el.getAttribute('data-id') : el.innerText;
		td.setAttribute('data-id', id);
		td.setAttribute('data-edit', el.getAttribute('data-edit'));
	}
	else {
		id = td.getAttribute('data-id');
	}

	if (type == 'link') {
		td.innerHTML = document.getElementById('output_action_select').innerHTML;
		td.children[0].options[0].innerText = el.innerText;
	}
	else if ((type == 'select' || type == 'category_click') && (!value || value == '.')) {
		td.innerHTML = '<a onclick="rkphplib.outputItemAction(this, ' + "'link'" + ')">' + id + '</a>';
	}
	else if (type == 'category_click') {
		url = el.getAttribute('data-set');
		var i, cat_id = [];

		for (i = 0; i < el.options.length; i++) {
			if (el.options[i].selected) {
				cat_id.push(el.options[i].value);
			}
		}

		this.ajax({ url: url + '&item_id=' + encodeURI(id) + '&cat_id=' + encodeURI(cat_id.join(',')), json: false, 
			success: function (data) {
				if (data.trim() == 'OK') {
					td.innerHTML = '<a onclick="rkphplib.outputItemAction(this, ' + "'link'" + ')">' + id + '</a>';
				}
			}
		});
	}
	else if (type == 'select') {
		if (value == 'edit') {
			window.location.href = td.getAttribute('data-edit');
		}
		else if (value == 'category') {
			td.innerHTML = document.getElementById('output_action_category').innerHTML;
			var sbox = td.children[0];
			sbox.setAttribute('id', 'cat_item_' + id);
			url = sbox.getAttribute('data-get');

			this.ajax({ url: url + '&id=' + encodeURI(id), json: false, success: function (data) {
				var i, key, sbox = document.getElementById('cat_item_' + id), list = data.trim().split(',');
				for (key in list) {
					for (i = 0; i < sbox.options.length; i++) {
						if (sbox.options[i].value == list[key]) {
							sbox.options[i].selected = 1;
						}
					}
				}
			}});
		}
		else if (value == 'image') {
			console.log('show image');
		}
		else if (value == 'delete') {
			console.log('delete entry');
		}
		else if (value == 'purge') {
			console.log('purge entry');
		}
	}
};


/**
 * Remove overlay icons.
 * 
 * @param string fvin_id
 * @return null|element (parent node)
 */
function removeOverlayIcon(fvin_id) {
	var i, icon, pel;

	if (!env['lastOverlayIcon_' + fvin_id]) {
		return null;
	}

	for (i = 0; i < env['lastOverlayIcon_' + fvin_id].length; i++) {
		icon = env['lastOverlayIcon_' + fvin_id][i];
		pel = icon.parentNode;
		pel.removeChild(icon);
	}

	delete env['lastOverlayIcon_' + fvin_id];

	return pel;
}


/**
 * Submit form f. Add hidden input.
 *
 * @param form f
 * @param hash hidden_input
 */
function submitForm(f, hidden_input) {
	// add form_action=1
	var key, input, type;

	hidden_input.form_action = 1;

	for (key in hidden_input) {
		if (f.elements[key]) {
			type = f.elements[key].getAttribute('type');

			if (type != 'submit' && type != 'button') {
				f.elements[key].value = hidden_input[key];
				continue;
			}
		}

		input = document.createElement('input');
		input.setAttribute('name', key);
		input.setAttribute('value', hidden_input[key]);
		input.setAttribute('type', 'hidden');
		f.appendChild(input);
	}

	f.submit();
}


/**
 * If env.restoreMultiple (=id) is set, change name to name[] and add multiple attribute.
 */
function restoreMultiple() {
	if (!env.restoreMultiple) {
		return;
	}

	var fvin = document.getElementById(env.restoreMultiple);
	fvin.setAttribute('multiple', 'multiple');
	var name = fvin.getAttribute('name');
	fvin.setAttribute('name', name + '[]');

	delete env.restoreMultiple;
}


/**
 * Create overlay icon (remove|replace).
 *
 * @param element el
 * @param int num
 */
function addOverlayIcon(el, num) {
	var fvin_id = el.parentNode.getAttribute('data-preview');
	var fvin = document.getElementById(fvin_id);
	var action, icon_list = { 'remove': 'left', 'replace': 'right' };

	env['lastOverlayIcon_' + fvin_id] = [];
	
	for (action in icon_list) {
		if (!fvin.hasAttribute('data-' + action)) {
			continue;
		}

		var icon = document.createElement('img');
		icon.src = fvin.getAttribute('data-' + action);	
		icon.setAttribute('style', 'position: absolute; ' + icon_list[action] + ': 5%; bottom: 5%; z-index: 1');
		icon.setAttribute('onclick', 'rkphplib.' + action + 'Image(this, ' + num + ')');

		el.parentNode.appendChild(icon);
		env['lastOverlayIcon_' + fvin_id].push(icon);
	}
}


/**
 * Initialize search input. Attribute name and data-search_list_url required.
 * 
 * @param Element el
 */
function initLiveSearch(el) {
	var name, id, list, datalist;

	if (!(name = el.getAttribute('name'))) {
		throw 'Search input has no name';
	}

	if (!(id = el.getAttribute('id'))) {
		if (document.getElementById(name)) {
			throw 'Search input ' + name + ' has no id';
		}
 
		id = name;
		el.setAttribute('id', id);
	}

	if (!document.getElementById(id)) {
		throw 'Search input ' + name + ' has no id';
	}

	if (!(list = el.getAttribute('list'))) {
		datalist = document.createElement('datalist');
		datalist.setAttribute('id', id + '_list');
		el.setAttribute('list', id + '_list');
		el.parentNode.appendChild(datalist);
	}

	el.setAttribute('data-search', '');
	el.setAttribute('autocomplete', 'off');
	el.setAttribute('oninput', 'rkphplib.updateSearchList(this)');

	if (el.getAttribute('data-search_url')) {
		el.setAttribute('onkeydown', "if (event.keyCode == 13) rkphplib.search(this)");
		el.setAttribute('onblur', 'rkphplib.search(this)');
	}
	else if (el.getAttribute('data-search_script')) {
		var search_script = el.getAttribute('data-search_script'); 
		el.setAttribute('onkeydown', "if (event.keyCode == 13) " + search_script);
		el.setAttribute('onblur', search_script);
	}
	else {
		throw 'Search #' + id + ' has neiter data-search_url nor data-search_script attribute'; 
	}
}


/**
 * Return vector with distinct column values.
 * 
 * @param table tbl
 * @return vector<hash>
 */
function getColumnValues(tbl) {
	var i, j, hide = 0, rows = tbl.children[0].children;

	var column_values = [];

	for (i = 2; i < rows.length - 1; i++) {
		var value, row = rows[i].children;
  
		for (j = 0; j < row.length; j++) {
			value = "x" + row[j].innerHTML;

			if (!column_values[j]) {
				column_values[j] = {};
			}

			if (value === "x") {
				continue;
			}

			if (Object.keys(column_values[j]).length > 15) {
				continue;
			}

			if (column_values[j][value]) {
				column_values[j][value]++;
			}
			else {
				column_values[j][value] = 1;
			}
		}
	}

	return column_values;
}


/**
 * Hide empty columns.
 *
 * @param table tpl
 * @param vector<hash> colval
 */
function hideEmptyColumns(tbl, colval) {
	var i, j, hide = 0, rows = tbl.children[0].children;

	for (i = 0; i < rows.length; i++) {
		var row = rows[i].children;

		if (i == 1 || i == rows.length - 1) {
			rows[i].setAttribute('colspan', rows[0].length - hide);
			continue;
		} 

		for (j = 0; j < row.length; j++) {
			if (Object.keys(colval[j]).length === 0) {
				if (i == 0) {
					hide++;
				}

				row[j].style.display = 'none';
			}
		}
	}
}


/**
 * Return child tag position (or -1 if not found).
 *
 * @param element el
 * @param string name
 * @return int
 */
function getChildTag(el, name) {
	var i, found = -1, uname = name.toUpperCase();

	for (i = 0; found === -1 && i < el.children.length; i++) {
		if (el.children[i].tagName === uname) {
			found = i;
		}
	}

	return found;
}


/**
 * Unshorten label if possible. Return column name label name hash.
 *
 * @param table tbl
 * @param vector<hash> colval
 * @return hash 
 */	
function unshortenTableColumnLabel(tbl, colval) {
	var i, j, hide = 0, rows = tbl.children[0].children;
	var row = rows[0].children, column_label = { };

	for (j = 0; j < row.length; j++) {
		if (Object.keys(colval[j]).length === 0) {
			continue;
		}

		var column = row[j].getAttribute('data-column');
		var label, has_span = getChildTag(row[j], 'span');
	
		if (row[j].firstChild.nodeType === 3) {
			column_label[column] = row[j].firstChild.data;
		}

		if (has_span === -1) {
			continue;
		}

		var span = row[j].children[has_span];
		if (!span.getAttribute('data-short')) {
			continue;
		}

		if ((label = span.getAttribute('title'))) {
			column_label[column] = label;

			if (tbl.scrollWidth <= tbl.clientWidth) {
				span.innerHTML = label;
				span.removeAttribute('title');		
			}
		}
	}

	return column_label;
}


/**
 * Add label to search box add disabled if column is invisible.
 * 
 * @param select sbox
 * @param hash column_label
 */
function fixSearchColumn(sbox, column_label) {
	sbox.setAttribute('data-output', env.output.length);

	for (var i = 0; i < sbox.options.length; i++) {
		var value = sbox.options[i].value;

		if (!value) {
			continue;
		}

		if (!column_label[value]) {
			sbox.options[i].setAttribute('disabled', 'disabled');
		}
		else {
			sbox.options[i].innerText = column_label[value];
			sbox.options[i].value = value;
		}
	}
}


/**
 * If #output_add_action exists, add all children with data-action=value and data-label=label to 
 * #output_action_select.
 */
function updateOutputAction() {
	var add_action = document.getElementById('output_add_action');
	var select_action = document.getElementById('output_action_select');

	if (!add_action || !select_action) {
		return;
	}

	for (var i = 0; i < add_action.children.length; i++) {
		var html, id, value, label, el = add_action.children[i];

		if (el.tagName == 'DIV' && (value = el.getAttribute('data-action')) && (label = el.getAttribute('data-label'))) {
			if ((id = el.getAttribute('data-id'))) {
				var div = document.createElement('div');
				div.setAttribute('id', id);
				div.innerHTML = el.innerHTML;
				document.getElementById('output_action_wrapper').appendChild(div);
			}

			var action_select = document.getElementById('output_action_select').children[0];
			for (var j = action_select.options.length; j > 2; j--) {
				action_select.options[j] = new Option(action_select.options[j-1].text, action_select.options[j-1].value);
			}

			action_select.options[2] = new Option(label, value, false, true);
		}
	}
}


/**
 * Prepare output table. Hide empty columns. Prolong shortened lables if possible.
 * 
 * @param table tbl
 */
function prepareOutputTable(tbl) {
	tbl.style.visibility = 'hidden';
	updateOutputAction();
	var column_values = getColumnValues(tbl);
	hideEmptyColumns(tbl, column_values);
	var sbox, column_label = unshortenTableColumnLabel(tbl, column_values);

	env.output.push(tbl);

	if ((sbox = tbl.querySelector('select.search_column'))) {
		fixSearchColumn(sbox, column_label);
	}

	tbl.style.visibility = 'visible';
}


/**
 * Create span with upload preview.
 *
 * @param event evt
 */
function showUploadPreview(evt) {
	var i, f, input_id, old_preview, files = evt.target.files;

	// remove old preview
	input_id = evt.target.getAttribute('id');
	if ((old_preview = document.querySelectorAll('span[data-preview="' + input_id + '"]'))) {
		for (i = 0; i < old_preview.length; i++) {
			old_preview[i].parentNode.removeChild(old_preview[i]);
		}
	}

	for (i = 0; i < files.length; i++) {
		f = files[i];

		if (!f.type.match('image.*')) {
			continue;
		}
	
		var reader = new FileReader();

		// Closure to capture the file information.
		reader.onload = (function(theFile) {
			return function(e) {
				// Render thumbnail.
				var span = document.createElement('span');
				span.innerHTML = [
					'<img style="height: 75px; border: 1px solid #000; margin: 5px" src="', e.target.result, '" title="', 
					theFile.name, '"/>' ].join('');

				span.setAttribute('data-preview', input_id);
				evt.target.parentNode.insertBefore(span, null);
			};
		})(f);

		// Read in the image file as a data URL.
		reader.readAsDataURL(f);
	}
}


/**
 * Create thumbnail.
 * 
 * @param element target
 * @param string src
 * @param int img_num (0 = single image)
 */
function showPreviewImage(target, src, img_num) {
	var wrapper = document.createElement('div');
	wrapper.setAttribute('style', 'position:relative; border: 1px solid black; display: inline-block; margin: 5px;');
	wrapper.setAttribute('data-preview', target.getAttribute('id'));

	wrapper.innerHTML = [
		'<img style="height: 120px; width:auto" onclick="rkphplib.modifyImage(this, ' + img_num + 
		')" src="', src + '?rx=' + Math.floor(Math.random() * 1000000), '" title="', src, '"/>' ].join('');

	target.parentNode.insertBefore(wrapper, null);
}


/**
 * If element (#id) style.display == 'none' set to previous value (data-display).
 * 
 * @param string id
 */
function toggleDisplay(id) {
	var el = document.getElementById(id);

	if (!el) {
		throw 'no such element id ' + id; 
	}

	var display = '';
	if (el.hasAttribute('data-display')) {
		display = el.getAttribute('data-display');
	}
	else if (el.style.display && el.style.display != 'none') {
		el.setAttribute('data-display', el.style.display);
	}
	
	if (el.style.display == 'none') {
		el.style.display = display;
	}
	else {
		el.style.display = 'none';
	}
}


/**
 * Delay function call for wait milliseconds. Execute only once after wait.
 *  
 *
 * @param function func
 * @param int wait (in milliseconds, e.g. 250)
 * @param boolean immediate
 */
function debounce(func, wait, immediate) {
	var timeout;

	return function() {
		var context = this, args = arguments;

		var later = function() {
			timeout = null;
			if (!immediate) {
				func.apply(context, args);
			}
		};

		var callNow = immediate && !timeout;
		clearTimeout(timeout);
		timeout = setTimeout(later, wait);
		if (callNow) {
			func.apply(context, args);
		}
	};
};



/*
 * Constructor
 */

document.addEventListener('DOMContentLoaded', function () { 
	var i, list;

	if ((list = document.querySelectorAll('input[data-search_list_url]'))) {
		for (i = 0; i < list.length; i++) {
			initLiveSearch(list[i]);
		}
	}

	if ((list = document.querySelectorAll('form[data-key13="prevent"] input[type="text"], form[data-key13="prevent"] textarea'))) {
		for (i = 0; i < list.length; i++) {
			list[i].addEventListener('keydown', function (e) {
				if (e.keyCode == '13') {
					// stop event - disable form submit via enter - send change event instead
					e.preventDefault();
					var evt2 = new CustomEvent('change');
					e.target.dispatchEvent(evt2);
				}
			});
		}
	}

	if ((list = document.querySelectorAll('table.output'))) {
		env.output = [];
		for (i = 0; i < list.length; i++) {
			prepareOutputTable(list[i]);
		}
	}

	if ((list = document.querySelectorAll('input[type="file"]'))) {
		for (i = 0; i < list.length; i++) {
			list[i].addEventListener('change', showUploadPreview, false);
			if (list[i].getAttribute('data-value')) {
				var j, images = list[i].getAttribute('data-value').split(',');
				var dir = list[i].getAttribute('data-dir') ? list[i].getAttribute('data-dir') + '/' : '';
				for (j = 0; j < images.length; j++) {
					showPreviewImage(list[i], dir + images[j], j);
				}
			}
		}
	}
});


}


var rkphplib = new rkPhpLib();

