(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var form = document.getElementById('bm-wl-form');
		if (!form) {
			return;
		}

		var localizedConfig = window.bitmomoProWhitelist || {};
		var nonceField = form.querySelector('input[name="bm_wl_nonce"]');
		var config = {
			ajaxUrl: localizedConfig.ajaxUrl || form.getAttribute('data-ajax-url'),
			action: localizedConfig.action || form.getAttribute('data-action'),
			nonce: localizedConfig.nonce || form.getAttribute('data-nonce') || (nonceField ? nonceField.value : ''),
			whatsappAction: localizedConfig.whatsappAction || form.getAttribute('data-whatsapp-action'),
			whatsappNonce: localizedConfig.whatsappNonce || form.getAttribute('data-whatsapp-nonce'),
			i18n: localizedConfig.i18n || {
				invalid_email: 'Masukkan alamat email yang valid.',
				consent_required: 'Centang persetujuan untuk melanjutkan.',
				rate_limited: 'Terlalu banyak percobaan. Coba lagi beberapa menit lagi.',
				generic_error: 'Terjadi kesalahan. Coba lagi.',
				duplicate_title: 'Email ini sudah terdaftar di whitelist.',
				invalid_whatsapp: 'Masukkan nomor WhatsApp yang valid.',
				not_found: 'Sesi sudah tidak berlaku. Muat ulang halaman dan coba lagi.'
			}
		};
		var errorEl = document.getElementById('bm-wl-error');
		var formPanel = document.getElementById('bm-wl-form-panel');
		var successPanel = document.getElementById('bm-wl-success-panel');
		var successTitle = document.getElementById('bm-wl-success-title');
		var submitBtn = form.querySelector('.bm-wl__submit');

		// Optional second-step WhatsApp opt-in elements.
		var waStep = document.getElementById('bm-wl-whatsapp-step');
		var waNumberInput = document.getElementById('bm-wl-whatsapp-number');
		var waSubmitBtn = document.getElementById('bm-wl-whatsapp-submit');
		var waErrorEl = document.getElementById('bm-wl-whatsapp-error');
		var waDoneEl = document.getElementById('bm-wl-whatsapp-done');

		function showError(message) {
			if (!errorEl) {
				return;
			}
			errorEl.textContent = message;
			errorEl.hidden = false;
		}

		function hideError() {
			if (!errorEl) {
				return;
			}
			errorEl.hidden = true;
			errorEl.textContent = '';
		}

		function showWhatsappError(message) {
			if (!waErrorEl) {
				return;
			}
			waErrorEl.textContent = message;
			waErrorEl.hidden = false;
		}

		function hideWhatsappError() {
			if (!waErrorEl) {
				return;
			}
			waErrorEl.hidden = true;
			waErrorEl.textContent = '';
		}

		// Reveals the WhatsApp step for a record that doesn't have a number
		// on file yet; a record that already has one (fresh signup that
		// somehow already carries it, or a duplicate resubmission) never
		// gets re-asked — it just stays hidden and the plain success state
		// is shown instead.
		function setupWhatsappStep(postId, recordToken, hasWhatsapp) {
			if (!waStep) {
				return;
			}
			if (hasWhatsapp || !postId || !recordToken) {
				waStep.hidden = true;
				return;
			}
			waStep.setAttribute('data-post-id', postId);
			waStep.setAttribute('data-record-token', recordToken);
			waStep.hidden = false;
		}

		function showSuccess(isDuplicate, data) {
			if (!formPanel || !successPanel) {
				return;
			}
			if (successTitle && isDuplicate && config && config.i18n) {
				successTitle.textContent = config.i18n.duplicate_title;
			}
			formPanel.hidden = true;
			successPanel.hidden = false;

			var postId = data && data.post_id;
			var recordToken = data && data.record_token;
			var hasWhatsapp = !!(data && data.has_whatsapp);
			setupWhatsappStep(postId, recordToken, hasWhatsapp);
		}

		var query = new URLSearchParams(window.location.search);
		var deepPost = query.get('bm_wl_post');
		var deepToken = query.get('bm_wl_token');
		if (deepPost && deepToken) {
			showSuccess(false, { post_id: deepPost, record_token: deepToken, has_whatsapp: false });
			window.history.replaceState({}, document.title, window.location.pathname + window.location.hash);
		}

		form.addEventListener('submit', function (event) {
			event.preventDefault();
			hideError();

			if (!config || !config.ajaxUrl || !config.action || !config.nonce) {
				return;
			}

			var i18n = config.i18n || {};
			var emailField = form.querySelector('input[name="email"]');
			var consentField = form.querySelector('input[name="consent"]');

			if (!emailField || !emailField.value || emailField.validity.typeMismatch) {
				showError(i18n.invalid_email || 'Invalid email.');
				return;
			}
			if (!consentField || !consentField.checked) {
				showError(i18n.consent_required || 'Consent required.');
				return;
			}

			var formData = new FormData(form);
			formData.set('action', config.action);
			formData.set('bm_wl_nonce', config.nonce);

			if (submitBtn) {
				submitBtn.disabled = true;
			}

			fetch(config.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: formData
			})
				.then(function (response) {
					return response.json().then(function (json) {
						return { ok: response.ok, json: json };
					});
				})
				.then(function (result) {
					if (submitBtn) {
						submitBtn.disabled = false;
					}
					if (result.json && result.json.success) {
						var data = result.json.data || {};
						showSuccess(data.status === 'duplicate', data);
						return;
					}
					var errorCode = result.json && result.json.data && result.json.data.error;
					showError((errorCode && i18n[errorCode]) || i18n.generic_error || 'Something went wrong.');
				})
				.catch(function () {
					if (submitBtn) {
						submitBtn.disabled = false;
					}
					showError(i18n.generic_error || 'Something went wrong.');
				});
		});

		if (!waSubmitBtn || !waStep) {
			return;
		}

		waSubmitBtn.addEventListener('click', function () {
			hideWhatsappError();

			if (!config || !config.ajaxUrl || !config.whatsappAction || !config.whatsappNonce) {
				return;
			}

			var i18n = config.i18n || {};
			var postId = waStep.getAttribute('data-post-id');
			var recordToken = waStep.getAttribute('data-record-token');
			var rawNumber = waNumberInput ? waNumberInput.value : '';

			// Light client-side sanity check only -- the server is the
			// single source of truth for normalization/validation.
			var digitsOnly = (rawNumber || '').replace(/[^0-9]/g, '');
			if (!postId || !recordToken) {
				showWhatsappError(i18n.not_found || 'Session expired. Reload the page and try again.');
				return;
			}
			if (digitsOnly.length < 8) {
				showWhatsappError(i18n.invalid_whatsapp || 'Enter a valid WhatsApp number.');
				return;
			}

			var formData = new FormData();
			formData.set('action', config.whatsappAction);
			formData.set('bm_wl_whatsapp_nonce', config.whatsappNonce);
			formData.set('post_id', postId);
			formData.set('record_token', recordToken);
			formData.set('whatsapp_number', rawNumber);

			waSubmitBtn.disabled = true;

			fetch(config.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: formData
			})
				.then(function (response) {
					return response.json().then(function (json) {
						return { ok: response.ok, json: json };
					});
				})
				.then(function (result) {
					waSubmitBtn.disabled = false;
					if (result.json && result.json.success) {
						waStep.hidden = true;
						if (waDoneEl) {
							waDoneEl.hidden = false;
						}
						return;
					}
					var errorCode = result.json && result.json.data && result.json.data.error;
					showWhatsappError((errorCode && i18n[errorCode]) || i18n.generic_error || 'Something went wrong.');
				})
				.catch(function () {
					waSubmitBtn.disabled = false;
					showWhatsappError(i18n.generic_error || 'Something went wrong.');
				});
		});
	});
}());
