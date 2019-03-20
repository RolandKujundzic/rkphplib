'use strict';
/* global Dropzone */
/* global rkphplib */


// var {var:dz_name} = new Dropzone('div#{var:dz_name}', {
// Dropzone.options.{var:dz_name} = {

Dropzone.options.{var:dz_name} = {
	url: 'index.php?dir={get:dir}',
	autoProcessQueue: {var:dz_autoProcessQueue},
	uploadMultiple: true,
	paramName: '{var:dz_paramName}',
	parallelUploads: 5,
	maxFiles: {var:dz_maxFiles},
	maxFilesize: 20, // mb
	acceptedFiles: '{var:dz_acceptedFiles}',
	addRemoveLinks: true,
	dictMaxFilesExceeded: "{ptxt:}Sie können nicht mehr als $p1x Dateien hochladen.|#|{{maxFiles}}{:ptxt}",
	dictRemoveFileConfirmation: "{txt:}Möchsten Sie diese Datei entfernen?{:txt}",
	dictRemoveFile: "{txt:}Löschen{:txt}",
	dictCancelUploadConfirmation: "{txt:}Möchten Sie diesen Upload wirklich abbrechen?{:txt}",
	dictUploadCanceled: "{txt:}Upload wurde abgebrochen.{:txt}",
	dictCancelUpload: "{txt:}Upload abbrechen{:txt}",
	dictResponseError: "{ptxt:}Server meldet Fehler $p1x|#|{{statusCode}}{:ptxt}",
	dictInvalidFileType: "{txt:}Dieser Dateityp ist nicht zulässig.{:txt}",
	dictFileTooBig: "{ptxt:}Datei ist zu gross ($p1x MiB). Maximale Dateigröße: $p2x MiB.|#|{{filesize}}|#|{{maxFilesize}}{:ptxt}",
	dictFallbackText: "{txt:}Bitte benutzen Sie den Upload Button.{:txt}",
	dictFallbackMessage: "{txt:}Ihr Browser unterstützt das Hochladen per Drag &amp; Drop nicht.{:txt}",
	dictDefaultMessage: "{txt:}Hier klicken oder Dateien hereinziehen, um hochzuladen{:txt}",

	rk: { currFiles: 0, newFiles: 0 },

	init: function() {
		var dzClosure = this; // Makes sure that 'this' is understood inside the functions below.

		this.options.rkHiddenInput = function(name, value) {
			let el = document.getElementById(name);

			if (el) {
				return;
			}

			let input = document.createElement("input");

			input.setAttribute("type", "hidden");
			input.setAttribute("id", name);
			input.setAttribute("name", name);
			input.setAttribute("value", value);

			dzClosure.element.parentNode.appendChild(input);
		};

		{upload:formData:hidden}

		{if:}{var:dz_autoProcessQueue}|#||#|// manual queue processing
		let page_forms, dzForm = dzClosure.element.parentNode.querySelector("[type=submit]");
	
		if (!dzForm && (page_forms = document.getElementsByTagName('FORM')).length == 1) {
			dzForm = page_forms[0];
		}

		dzForm.addEventListener("submit", function(e) {
			e.preventDefault();
			e.stopPropagation();
			dzClosure.processQueue();

			if (dzClosure.getUploadingFiles().length === 0 && dzClosure.getQueuedFiles().length === 0) {
				e.target.submit();
			}
    }, true);
		{:if}

		// send all the form data along with the files:
		this.on("sendingmultiple", function(data, xhr, formData) {
			// e.g. formData.append("id", document.getElementById("fvin_id").value);
			{upload:formData:append} 
		});

		this.on("removedfile", function(file) {
			{tf:}{var:on_removedfile}{:tf}
			{true:}{var:on_removedfile}{:true}
			{false:}
			let f = this.element.parentNode;
			let ajax_url = this.options.url + '&ajax=' + encodeURIComponent('{var:name}');
			rkphplib.ajax({ url: ajax_url + '&remove_image=' + encodeURIComponent(file.name) });
			{:false}
		});

		this.on("addedfile", function(file) {
			if (file.url) {
				this.options.rk.currFiles++;
			}
			else {
				this.options.rk.newFiles++;
			}

			if (this.options.maxFiles < this.options.rk.currFiles + this.options.rk.newFiles) {
				this.removeFile(file);
			}
		});

		// add existing files
		var existingFiles = {upload:exists}mode=dropzone{:upload};

		for (var i = 0; i < existingFiles.length; i++) {
			this.emit("addedfile", existingFiles[i]);
			this.emit("thumbnail", existingFiles[i], existingFiles[i].tbnUrl);
			this.emit("complete", existingFiles[i]);
		}

		{tf:}{var:dz_fixMarkBug}{:tf}{true:}
		// Bugfix: fix success + error mark visible
		$(".dz-success-mark").css("display", "none");
		$(".dz-error-mark").css("display", "none");

		this.on("success", function(file){   
			$(".dz-success-mark svg").css("background", "green");
			$(".dz-error-mark").css("display", "none");
		});

		this.on("error", function(file) {
			$(".dz-error-mark svg").css("background", "red");
			$(".dz-success-mark").css("display", "none");
		});
		{:true}
	}
};

