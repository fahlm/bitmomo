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

		function fieldValue(name) {
			var field = form.querySelector('[name="' + name + '"]');
			return field && field.value ? String(field.value).slice(0, 120) : '';
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
				Object.keys(extra).forEach(function (key) {
					payload[key] = extra[key];
				});
			}

			// Provider-neutral browser event. Deliberately excludes email,
			// first name, WhatsApp number, post_id, nonce and record_token.
			if (typeof window.CustomEvent === 'function') {
				window.dispatchEvent(new CustomEvent('bitmomo:analytics', { detail: payload }));
			}

			// If a tag manager/analytics provider already owns dataLayer, feed it.
			// Do not create dataLayer here: Bitmomo stays provider-neutral.
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

		emitTelemetry('whitelist_view');

		form.addEventListener('submit', function (event) {
			event.preventDefault();
			hideError();

			var i18n = config.i18n || {};

			if (!config || !config.ajaxUrl || !config.action || !config.nonce) {
				showError(i18n.generic_error || 'Something went wrong.');
				emitTelemetry('whitelist_error', { error_code: 'configuration' });
				return;
			}

			var emailField = form.querySelector('input[name="email"]');
			var consentField = form.querySelector('input[name="consent"]');

			if (!emailField || !emailField.value || emailField.validity.typeMismatch) {
				showError(i18n.invalid_email || 'Invalid email.');
				emitTelemetry('whitelist_error', { error_code: 'invalid_email' });
				return;
			}
			if (!consentField || !consentField.checked) {
				showError(i18n.consent_required || 'Consent required.');
				emitTelemetry('whitelist_error', { error_code: 'consent_required' });
				return;
			}

			var formData = new FormData(form);
			formData.set('action', config.action);
			formData.set('bm_wl_nonce', config.nonce);
			emitTelemetry('whitelist_submit');

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
						emitTelemetry(data.status === 'duplicate' ? 'whitelist_duplicate' : 'whitelist_created');
						return;
					}
					var errorCode = result.json && result.json.data && result.json.data.error;
					showError((errorCode && i18n[errorCode]) || i18n.generic_error || 'Something went wrong.');
					emitTelemetry('whitelist_error', { error_code: errorCode || 'server_error' });
				})
				.catch(function () {
					if (submitBtn) {
						submitBtn.disabled = false;
					}
					showError(i18n.generic_error || 'Something went wrong.');
					emitTelemetry('whitelist_error', { error_code: 'network_error' });
				});
		});

		if (!waSubmitBtn || !waStep) {
			return;
		}

		waSubmitBtn.addEventListener('click', function () {
			hideWhatsappError();

			var i18n = config.i18n || {};

			if (!config || !config.ajaxUrl || !config.whatsappAction || !config.whatsappNonce) {
				showWhatsappError(i18n.generic_error || 'Something went wrong.');
				return;
			}

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