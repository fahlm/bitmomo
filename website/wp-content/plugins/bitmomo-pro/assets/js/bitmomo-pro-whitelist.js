(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var form = document.getElementById('bm-wl-form');
		if (!form) {
			return;
		}

		var config = window.bitmomoProWhitelist;
		var errorEl = document.getElementById('bm-wl-error');
		var formPanel = document.getElementById('bm-wl-form-panel');
		var successPanel = document.getElementById('bm-wl-success-panel');
		var successTitle = document.getElementById('bm-wl-success-title');
		var submitBtn = form.querySelector('.bm-wl__submit');

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

		function showSuccess(isDuplicate) {
			if (!formPanel || !successPanel) {
				return;
			}
			if (successTitle && isDuplicate && config && config.i18n) {
				successTitle.textContent = config.i18n.duplicate_title;
			}
			formPanel.hidden = true;
			successPanel.hidden = false;
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
						var status = result.json.data && result.json.data.status;
						showSuccess(status === 'duplicate');
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
	});
}());
