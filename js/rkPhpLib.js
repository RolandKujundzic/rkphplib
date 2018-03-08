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
 * Define callback function (e.g. fselect_NAME_[text|change]).
 *
 * @param string name
 * @param function func
 */
this.setCallback = function (name, func) {
	if (!env.callback) {
		env.callback = [];
	}

	env.callback[name] = func;
};


/**
 * Hide fselect_list_NAME. Show fselect_input_NAME.
 * Call fselect_NAME_text|change(selbox) if defined.
 * 
 * @see setCallback(fselect_NAME_[text|change], ...)
 * @param Node selbox
 */
this.fselectInput = function (selbox) {
	var name = selbox.getAttribute('name');

	if (selbox.value == '_') {
    document.getElementById('fselect_list_' + name).style.display = 'none';
    document.getElementById('fselect_input_' + name).style.display = 'block';

    if (env.callback['fselect_' + name + '_text']) {
			env.callback['fselect_' + name + '_text'](selbox);
  	}
  }
  else if (env.callback['fselect_' + name + '_change']) {
		env.callback['fselect_' + name + '_change'](selbox);
  }
};


/**
 * Close fselect input. Add value as selected.
 *
 * @param Node inbox 
 */
this.fselectList = function (inbox) {
	var name = inbox.getAttribute('name').substr(0, -1);
  var selbox = document.querySelect('fselect_list_' + name + ' > select');

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

  document.getElementById('fselect_list_'  + name).style.display = 'block';
	document.getElementById('fselect_input_' + name).style.display = 'none';  
};


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

});


}


var rkphplib = new rkPhpLib();

