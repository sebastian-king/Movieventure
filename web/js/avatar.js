(function ( root, factory ) {
	if ( typeof define === 'function' && define.amd ) {
		// AMD. Register as an anonymous module.
		define(function () {
			// Also create a global in case some scripts
			// that are loaded still are looking for
			// a global even when an AMD loader is in use.
			return ( root.AvatarUpload = factory() );
		});
	} else {
		// Browser globals
		root.AvatarUpload = factory();
	}
}( this, function () {

	var extend = function(original, extra) {
		return Object.keys(extra).forEach(function(key) {
			original[key] = extra[key];
		});
	};

	var parseJson = function(input) {
		try {
			return JSON.parse(input);
		}
		catch (e) {
			return false;
		}
	};

	var AvatarUpload = function(config) {
		extend(this.config, config);

		if ( ! this.config.el) {
			throw new Error('An element is required to manipulate');
		}

		if ( ! this.config.uploadUrl && ! this.config.pretendUpload) {
			throw new Error('Upload URL not specified');
		}

		this.el = this.config.el;
		this.renderInput();
		this.bindInput();

		this.progressText = this.el.querySelector('span');
		this.imageWrapper = this.el.querySelector('.avatar-upload__image-wrapper');
	};

	AvatarUpload.prototype.config = {
		el: undefined,

		uploadUrl: '',
		uploadMethod: 'post',
		uploadImageKey: 'upload',
		uploadData: {},

		pretendUpload: false,

		onProgress: undefined,
		onSuccess: undefined,
		onError: undefined
	};

	AvatarUpload.prototype.renderInput = function() {
		var imgEl = this.el.querySelector('img'),
			img = imgEl.src;

		var el = document.createElement('div');
		el.className = 'avatar-upload__shell';

		el.innerHTML = [
			'<div class="avatar-upload__wrapper avatar-upload--complete">',
				'<div class="avatar-upload__image-wrapper">',
					'<img src="'+img+'" class="img-lg img-border img-circle" style="position: absolute; left: 5px; top: 5px; margin-left: 0px;" id="avatar_id">',
				'</div>',
				'<img src="'+img+'" class="avatar-upload__faded-image img-lg img-border img-circle" style="position: absolute; left: 5px; top: 5px; margin-left: 0px;">',
				'<div class="avatar-upload__progress-wrapper">',
					'<span>0%</span>',
				'</div>',
				'<input type="file" style="cursor:pointer;" accept="image/jpeg, image/png, image/gif">',
			'</div>',
		].join('');

		imgEl.parentNode.removeChild(imgEl);
		this.el.appendChild(el);

		return this;
	};

	AvatarUpload.prototype.bindInput = function(event) {
		this.el.querySelector('input').addEventListener(
			'change', this.initiateUpload.bind(this), true
		);
	};

	AvatarUpload.prototype.initiateUpload = function(event) {
		var file = event.target.files[0];

		// reset input to allow selecting same file again
		event.target.value = null;

		if ( ! file.type.match(/image.*/)) return;

		// read file & run upload
		var reader = new FileReader;
		reader.onload = this.displayUpload.bind(this);
		reader.readAsDataURL(file);

		this.upload(file);
	};

	AvatarUpload.prototype.displayUpload = function(event) {
		var img = event.target.result;

		this.uiUploadStart(img);
	};

	AvatarUpload.prototype.upload = function(file) {
		var Uploader = WebSocketUploader;

		Uploader(file, this.config, {
			progress: this.uploadProgress.bind(this),
			success: this.uploadSuccess.bind(this),
			error: this.uploadError.bind(this),
		});
	};

	AvatarUpload.prototype.uploadProgress = function(progress) {
		this.uiUploadProgress(progress);
		if (this.config.onProgress) this.config.onProgress(progress, this.el, this);
	};

	AvatarUpload.prototype.uploadSuccess = function(xhr, json) {
		this.uiUploadSuccess(xhr, json);
		if (this.config.onSuccess) this.config.onSuccess(xhr, json);
	};

	AvatarUpload.prototype.uploadError = function(xhr, json) {
		this.uiUploadError(xhr, json);
		if (this.config.onError) this.config.onError(xhr, json);
	};

	AvatarUpload.prototype.uiUploadStart = function(img) {
		var origSrc;
		
		Array.prototype.forEach.call(this.el.querySelectorAll('img'), function(imgEl) {
			origSrc = imgEl.src;
			imgEl.src = img;
		});

		this.origSrc = origSrc;

		this.el.querySelector('.avatar-upload__wrapper').className = 'avatar-upload__wrapper'; // remove complete class
	};

	AvatarUpload.prototype.uiUploadProgress = function(progress) {
		this.progressText.textContent = progress+'%';
		this.imageWrapper.style.width = progress+'%';
	};

	AvatarUpload.prototype.uiUploadSuccess = function(xhr, json) {
		this.progressText.textContent = '0%';
		this.imageWrapper.style.width = null;
		this.el.querySelector('#avatar_id').style.marginLeft = "0px";
		this.el.querySelector('.avatar-upload__wrapper').className = 'avatar-upload__wrapper avatar-upload--complete';
	};

	AvatarUpload.prototype.uiUploadError = function(xhr, json) {
		this.uiUploadSuccess();
		
		$.niftyNoty({
			type: "danger",
			container: 'page',
			html: "An error occured when attempting to upload, please try again later.",
			timer: 5000
		})
		
		var origSrc = this.origSrc;
		Array.prototype.forEach.call(this.el.querySelectorAll('img'), function(imgEl) {
			imgEl.src = origSrc;
		});
	};

	var WebSocketUploader = function(file, config, callbacks) {
		var xhr = new XMLHttpRequest(),
			formData = new FormData();

		xhr.upload.addEventListener('progress', function(transfer) {
			callbacks.progress(parseInt(
				transfer.loaded / transfer.total * 100
			, 10));
		});

		xhr.onreadystatechange = function(e) {
			if (xhr.readyState !== 4) return;

			if (xhr.status === 200) {
				callbacks.success();
			}
			else {
				callbacks.error();
			}
		};

		formData.append(config.uploadImageKey, file);

		for (val in config.uploadData) {
			formData.append(val, config.uploadData[val]);
		}

		xhr.open(config.uploadMethod, config.uploadUrl);
		xhr.send(formData);
	};

	return AvatarUpload;

}));
