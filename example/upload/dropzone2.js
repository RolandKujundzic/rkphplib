'use strict';
/* global Dropzone */
/* global rkphplib */


// Dropzone.autoDiscover = false; Dropzone.autoProcessQueue = true
var dropzone_done = false;

function checkDropzone(f) {
  console.log('checkDropzone', f);

  if (dropzone_done) {
    f.submit();
  }
  else {
    document.getElementById('fvin_submit').setAttribute('readonly', 'readonly');
    setTimeout(function(f) { checkDropzone(f); }, 500);
  }

  return false;
}

var {var:dz_name} = new Dropzone('div#{var:dz_name}', { 
	url: 'index.php?dir={get:dir}',
	autoProcessQueue: true,
	uploadMultiple: true,
	paramName: "{var:dz_paramName}",
	parallelUploads: 5,
	maxFiles: {var:dz_maxFiles},
	maxFilesize: 20, // mb
	acceptedFiles: {var:dz_acceptedFiles},
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
			let input = document.createElement("input");

			input.setAttribute("type", "hidden");
			input.setAttribute("id", name);
			input.setAttribute("name", name);
			input.setAttribute("value", value);

			dzClosure.element.parentNode.appendChild(input);
		};

		{upload:formData:hidden}
 
		if (!this.options.autoProcessQueue) {
			// for Dropzone to process the queue (instead of default form behavior):
			dzClosure.element.parentNode.querySelector("[type=submit]").addEventListener("click", function(e) {
				// Make sure that the form isn't actually being sent.
				e.preventDefault();
				e.stopPropagation();
				dzClosure.processQueue();
			});
		}

		// send all the form data along with the files:
		this.on("sendingmultiple", function(data, xhr, formData) {
			// e.g. formData.append("id", document.getElementById("fvin_id").value);
			{upload:formData:append} 
		});

		this.on("removedfile", function(file) {
			let f = this.element.parentNode;
			let ajax_url = this.options.url + '&ajax=' + encodeURIComponent(f.elements['ajax']);
			rkphplib.ajax({ url: ajax_url + '&remove_image=' + encodeURIComponent(file.name) });
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
	}
});

