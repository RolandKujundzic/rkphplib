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

Dropzone.options.rkDropzone = {
	url: 'index.php?dir={get:dir}',
	autoProcessQueue: false,
	uploadMultiple: true,
	// paramName: "images",
	parallelUploads: 5,
	maxFiles: 16,
	maxFilesize: 20, // mb
	acceptedFiles: 'image/*',
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
		if (!this.options.autoProcessQueue) {
			dzClosure = this; // Makes sure that 'this' is understood inside the functions below.

			// for Dropzone to process the queue (instead of default form behavior):
			document.getElementById("submit-all").addEventListener("click", function(e) {
				// Make sure that the form isn't actually being sent.
				e.preventDefault();
				e.stopPropagation();
				dzClosure.processQueue();
			});
		}

		// send all the form data along with the files:
		this.on("sendingmultiple", function(data, xhr, formData) {
			formData.append("module", document.getElementById('fvin_module').value);
			formData.append("ajax", "upload");
			{fv:appendjs:formData}id,dir{:fv}
		});

		this.on("removedfile", function(file) {
			if (file.path) {
				let id = document.getElementById('fvin_id').value;
				rkphplib.ajax({ url: this.options.url + '&ajax=upload&id=' + id + '&remove_image=' + encodeURIComponent(file.name) });
			}
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
		// var existingFiles = {ignore:}{upload:exists}mode=dropzone|#|images={get:images}|#|thumbnail=120x120^|#|
    //   save_in=data/shop/img/{login:id}/{get:id}{:upload}{:ignore};

		for (var i = 0; i < existingFiles.length; i++) {
			this.emit("addedfile", existingFiles[i]);
			this.emit("thumbnail", existingFiles[i], existingFiles[i].tbnUrl);
			this.emit("complete", existingFiles[i]);
		}
	}
};

