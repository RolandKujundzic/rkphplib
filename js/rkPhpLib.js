"use strict";
/* global $ */


/**
 * Javascript functions referenced from rkphplib. Vanilla JS, no dependencies.
 *
 * @copyright Roland Kujundzic 2017
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
function rkPhpLibJS() {

/** @var this me */
var me = this;

/** @var map env */
var env = [];



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
		window.location.href = url + '&' + name = encodeURI(search);
	}
};


/**
 * Use input el and datalist el.getAttribute('list'). Append search parameter to 
 * el.getAttribute(data-query). Create new datalist from ajax result { "key": "value", .... }.
 * Example:
 * 
 * <input type="text" id="search" name="search" autocomplete="off" onblur="rkphplib.search(this)" 
 *   onkeydown="if (event.keyCode == 13) rkphplib.search(this)"
 *   list="search_list" data-url="index.php?dir=shop/search&search=" 
 *   data-query="index.php?dir=ajax/search&search=" 
 *   data-last="" placeholder="search" />
 * <datalist id="search_list"></datalist>
 *
 * @see example/ajax_live_search 
 */
function updateSearchList() {
	var last_search = $('#search').attr('data-last'), curr_search = $('#search').val();

	if (!curr_search || curr_search === last_search) {
		return;
	}

	if (env.update_search_timeout) {
		clearTimeout(env.update_search_timeout);
		env.update_search_timeout = null;
	}

	env.update_search_timeout = setTimeout(function() {
		var query = $('#search').attr('data-query');

		if (!query) {
			query = 'index.php?dir=ajax/search';
		}

		$.get(query + '&search=' + encodeURI(curr_search), function (data) {
			var key, list = JSON.parse(data), search_list = $('#search_list');

			if (navigator.userAgent.indexOf("Safari") > -1) {
				// Safari has no datalist support
				search_list.html('<select onChange="search(this)"></select>');
				search_list = $('#search_list > select');
			}
			else {
				search_list.html('');
	    }

			for (key in list) {
				if (key) {
					$('<option data-id="' + key + '">' + list[key] + '</option>').appendTo(search_list);
				}
			}

			$('#search').attr('data-last', curr_search);
		});
	}, 800);
}


function initLiveSearch(el) {
	var name, id, list, datalist;

	if (!(name = el.getAttribute('name'))) {
		throw 'Search input has no name';
	}

	if (!(id = el.getAttribute('id'))) {
		if (document.getElementById(name)) {
			throw 'Search input has no id';
		}
 
		el.setAttribute('id', name);
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

	if ((list = document.querySelectorAll('input[data-search_query=""]'))) {
console.log('list: ', list);
		for (i = 0; i < list.length; i++) {
			initLiveSearch(list[i]);
		}
	}

});


}


var rkphplib = new rkPhpLibJS();

