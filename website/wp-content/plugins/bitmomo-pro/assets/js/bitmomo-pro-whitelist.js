(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var form = document.getElementById('bm-wl-form');
		if (!form) return;

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
		var submitLabel = submitBtn ? submitBtn.textContent : '';
		var emailField = form.querySelector('input[name="email"]');
		var consentField = form.querySelector('input[name="consent"]');

		var waStep = document.getElementById('bm-wl-whatsapp-step');
		var waNumberInput = document.getElementById('bm-wl-whatsapp-number');
		var waSubmitBtn = document.getElementById('bm-wl-whatsapp-submit');
		var waSubmitLabel = waSubmitBtn ? waSubmitBtn.textContent : '';
		var waErrorEl = document.getElementById('bm-wl-whatsapp-error');
		var waDoneEl = document.getElementById('bm-wl-whatsapp-done');

		function ensureAnnouncementSemantics() {
			if (errorEl) {
				errorEl.setAttribute('role', 'alert');
				errorEl.setAttribute('aria-live', 'assertive');
			}
			if (successPanel) successPanel.setAttribute('aria-live', 'polite');
			if (successTitle) successTitle.setAttribute('tabindex', '-1');
			if (waErrorEl) {
				waErrorEl.setAttribute('role', 'alert');
				waErrorEl.setAttribute('aria-live', 'assertive');
			}
			if (waDoneEl) {
				waDoneEl.setAttribute('role', 'status');
				waDoneEl.setAttribute('tabindex', '-1');
			}
		}

		function fieldValue(name) {
			var field = form.querySelector('[name="' + name + '"]');
			return field && field.value ? String(field.value).slice(0, 120) : '';
		}

		function setFieldValue(name, value) {
			var field = form.querySelector('[name="' + name + '"]');
			if (field) field.value = value || '';
		}

		function safeUrlWithoutQuery(value) {
			if (!value) return '';
			try {
				var url = new URL(value, window.location.origin);
				return url.origin + url.pathname;
			} catch (error) {
				return '';
			}
		}

		function refreshPrivacyBoundAcquisitionContext() {
			setFieldValue('landing_page', window.location.origin + window.location.pathname);
			setFieldValue('referrer', safeUrlWithoutQuery(document.referrer));
		}

		function telemetryContext() {
			return {
				event_version: 1,
				source: fieldValue('source'),
				utm_source: fieldValue('utm_source'),
				utm_medium: fieldValue('utm_medium'),
				utm_campaign: fieldValue('utm_campaign'),
				page_path: window.location.pathname
			};
		}

		function emitTelemetry(eventName, extra) {
			var payload = telemetryContext();
			payload.event = eventName;
			if (extra && typeof extra === 'object') {
				Object.keys(extra).forEach(function (key) { payload[key] = extra[key]; });
			}

			if (typeof window.CustomEvent === 'function') {
				window.dispatchEvent(new CustomEvent('bitmomo:analytics', { detail: payload }));
			}
			if (Array.isArray(window.dataLayer)) {
				window.dataLayer.push(Object.assign({}, payload));
			}
		}

		function ctaPlacement(link) {
			if (link.classList.contains('bm-pro-cta')) return 'homepage_pro_teaser';
			if (link.classList.contains('bm-wl-teaser-cta')) return 'homepage_whitelist_fallback';
			if (link.classList.contains('bm-pro-sales__hero-cta')) return 'pro_hero';
			if (link.closest('.bm-pro-sales__final')) return 'pro_final';
			return 'pro_sales';
		}

		document.addEventListener('click', function (event) {
			var link = event.target && event.target.closest
				? event.target.closest('a.bm-pro-cta, a.bm-wl-teaser-cta, a.bm-pro-sales__cta')
				: null;
			if (!link) return;
			var href = link.getAttribute('href') || '';
			emitTelemetry('pro_cta_click', {
				cta_placement: ctaPlacement(link),
				cta_target: href.indexOf('#bm-pro-whitelist') !== -1 ? 'whitelist' : 'pro_page'
			});
		});

		function detachErrorDescription(field, id) {
			if (!field) return;
			field.removeAttribute('aria-invalid');
			var ids = (field.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean).filter(function (item) { return item !== id; });
			if (ids.length) field.setAttribute('aria-describedby', ids.join(' '));
			else field.removeAttribute('aria-describedby');
		}

		function attachErrorDescription(field, id) {
			if (!field) return;
			field.setAttribute('aria-invalid', 'true');
			var ids = (field.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
			if (ids.indexOf(id) === -1) ids.push(id);
			field.setAttribute('aria-describedby', ids.join(' '));
		}

		function showError(message, field) {
			if (errorEl) {
				errorEl.textContent = message;
				errorEl.hidden = false;
			}
			if (field) {
				attachErrorDescription(field, 'bm-wl-error');
				field.focus();
			}
		}

		function hideError() {
			if (errorEl) {
				errorEl.hidden = true;
				errorEl.textContent = '';
			}
			detachErrorDescription(emailField, 'bm-wl-error');
			detachErrorDescription(consentField, 'bm-wl-error');
		}

		function showWhatsappError(message) {
			if (waErrorEl) {
				waErrorEl.textContent = message;
				waErrorEl.hidden = false;
			}
			if (waNumberInput) {
				attachErrorDescription(waNumberInput, 'bm-wl-whatsapp-error');
				waNumberInput.focus();
			}
		}

		function hideWhatsappError() {
			if (waErrorEl) {
				waErrorEl.hidden = true;
				waErrorEl.textContent = '';
			}
			detachErrorDescription(waNumberInput, 'bm-wl-whatsapp-error');
		}

		function setSubmitBusy(isBusy) {
			if (isBusy) form.setAttribute('aria-busy', 'true');
			else form.removeAttribute('aria-busy');
			if (!submitBtn) return;
			submitBtn.disabled = isBusy;
			submitBtn.textContent = isBusy ? 'MENGIRIM…' : submitLabel;
		}

		function setWhatsappBusy(isBusy) {
			if (waStep) {
				if (isBusy) waStep.setAttribute('aria-busy', 'true');
				else waStep.removeAttribute('aria-busy');
			}
			if (!waSubmitBtn) return;
			waSubmitBtn.disabled = isBusy;
			waSubmitBtn.textContent = isBusy ? 'MENYIMPAN…' : waSubmitLabel;
		}

		function setupWhatsappStep(postId, recordToken, hasWhatsapp) {
			if (!waStep) return;
			if (hasWhatsapp || !postId || !recordToken) {
				waStep.hidden = true;
				return;
			}
			waStep.setAttribute('data-post-id', postId);
			waStep.setAttribute('data-record-token', recordToken);
			waStep.hidden = false;
		}

		function showSuccess(isDuplicate, data) {
			if (!formPanel || !successPanel) return;
			if (successTitle && isDuplicate && config && config.i18n) {
				successTitle.textContent = config.i18n.duplicate_title;
			}
			formPanel.hidden = true;
			successPanel.hidden = false;

			var postId = data && data.post_id;
			var recordToken = data && data.record_token;
			var hasWhatsapp = !!(data && data.has_whatsapp);
			setupWhatsappStep(postId, recordToken, hasWhatsapp);
			if (successTitle) successTitle.focus();
		}

		ensureAnnouncementSemantics();
		refreshPrivacyBoundAcquisitionContext();
		emitTelemetry('whitelist_view');

		form.addEventListener('submit', function (event) {
			event.preventDefault();
			hideError();
			refreshPrivacyBoundAcquisitionContext();

			if (!config || !config.ajaxUrl || !config.action || !config.nonce) {
				showError((config && config.i18n && config.i18n.generic_error) || 'Terjadi kesalahan. Coba lagi.');
				emitTelemetry('whitelist_error', { error_code: 'configuration' });
				return;
			}

			var i18n = config.i18n || {};
			if (!emailField || !emailField.value || !emailField.validity.valid) {
				showError(i18n.invalid_email || 'Invalid email.', emailField);
				emitTelemetry('whitelist_error', { error_code: 'invalid_email' });
				return;
			}
			if (!consentField || !consentField.checked) {
				showError(i18n.consent_required || 'Consent required.', consentField);
				emitTelemetry('whitelist_error', { error_code: 'consent_required' });
				return;
			}

			var formData = new FormData(form);
			formData.set('action', config.action);
			formData.set('bm_wl_nonce', config.nonce);
			emitTelemetry('whitelist_submit');
			setSubmitBusy(true);

			fetch(config.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: formData
			})
				.then(function (response) {
					return response.json().then(function (json) { return { ok: response.ok, json: json }; });
				})
				.then(function (result) {
					if (result.json && result.json.success) {
						var data = result.json.data || {};
						showSuccess(data.status === 'duplicate', data);
						emitTelemetry(data.status === 'duplicate' ? 'whitelist_duplicate' : 'whitelist_created');
						return;
					}
					var errorCode = result.json && result.json.data && result.json.data.error;
					showError((errorCode && i18n[errorCode]) || i18n.generic_error || 'Something went wrong.');
					emitTelemetry('whitelist_error', { error_code: errorCode || 'server_error' });
				})
				.catch(function () {
					showError(i18n.generic_error || 'Something went wrong.');
					emitTelemetry('whitelist_error', { error_code: 'network_error' });
				})
				.finally(function () { setSubmitBusy(false); });
		});

		if (!waSubmitBtn || !waStep) return;

		waSubmitBtn.addEventListener('click', function () {
			hideWhatsappError();

			if (!config || !config.ajaxUrl || !config.whatsappAction || !config.whatsappNonce) {
				showWhatsappError((config && config.i18n && config.i18n.generic_error) || 'Terjadi kesalahan. Coba lagi.');
				return;
			}

			var i18n = config.i18n || {};
			var postId = waStep.getAttribute('data-post-id');
			var recordToken = waStep.getAttribute('data-record-token');
			var rawNumber = waNumberInput ? waNumberInput.value : '';
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
			setWhatsappBusy(true);

			fetch(config.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: formData
			})
				.then(function (response) {
					return response.json().then(function (json) { return { ok: response.ok, json: json }; });
				})
				.then(function (result) {
					if (result.json && result.json.success) {
						waStep.hidden = true;
						if (waDoneEl) {
							waDoneEl.hidden = false;
							waDoneEl.focus();
						}
						return;
					}
					var errorCode = result.json && result.json.data && result.json.data.error;
					showWhatsappError((errorCode && i18n[errorCode]) || i18n.generic_error || 'Something went wrong.');
				})
				.catch(function () { showWhatsappError(i18n.generic_error || 'Something went wrong.'); })
				.finally(function () { setWhatsappBusy(false); });
		});
	});
}());
