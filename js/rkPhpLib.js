"use strict";
/* global $ */


/**
 * Javascript functions referenced from rkphplib. Vanilla JS, no dependencies.
 *
 * @copyright Roland Kujundzic 2017
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
function rkPhpLib() {

/** @var this me */
var me = this;

/** @var map env */
var env = [];



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
			throw 'ajax query failed with code ' + code;
		};
	}

	request.open(options.method, options.url, true);

	request.onload = function() {
		if (request.status >= 200 && request.status < 400) {
			var data = request.responseText;

			if (data.indexOf('html') > -1) {
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
 * Execute search - redirect to el.getAttribute('data-url') + el.value or call
 * el.getAttribute('data-search_script').
 *
 * @param Element el
 */
this.search = function(el) {
	var url, name, search = el.value, old_search = el.getAttribute('data-search');

	if (search.length == 0 || old_search == search) {
		return;
	}
  
	if ((name = el.getAttribute('name')) && (url = el.getAttribute('data-search_url'))) {
		window.location.href = url + '&' + name + '=' + encodeURI(search);
	}
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

			for (key in list) {
				if (key) {
					options += '<option data-id="' + key + '">' + list[key] + '</option>';
				}
			}

			search_list.innerHTML = options;

			el.setAttribute('data-search', curr_search);
		}});
	}, 800);
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
	el.setAttribute('onblur', 'rkphplib.search(this)');
	el.setAttribute('onkeydown', "if (event.keyCode == 13) rkphplib.search(this)");
	el.setAttribute('oninput', 'rkphplib.updateSearchList(this)');
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

