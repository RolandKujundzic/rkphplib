/**
 * Javascript functions referenced from rkphplib.
 *
 * @copyright Roland Kujundzic 2017
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
function rkPhpLibJS() {
'use strict';

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


}


var rkphplib = new rkPhpLibJS();
