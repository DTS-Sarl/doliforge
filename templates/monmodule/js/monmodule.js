/* monmodule.js — JavaScript du module Mon Module
 * Namespace unique : MonModule
 * Jamais de console.log en production
 * Pas de CDN — toutes les libs dans js/vendor/
 */

var MonModule = MonModule || {};

MonModule.debug = false;

MonModule.log = function (msg) {
	if (MonModule.debug) {
		console.log('[MonModule]', msg);
	}
};

MonModule.init = function () {
	MonModule.log('init');
	MonModule.bindEvents();
};

MonModule.bindEvents = function () {
	// Exemple : confirmation avant suppression
	$(document).on('click', '.monmodule-btn-delete', function (e) {
		if (!confirm(monmodule_lang.confirmDelete)) {
			e.preventDefault();
		}
	});
};

/**
 * Appel AJAX générique vers les handlers du module
 * @param {string} action
 * @param {object} data
 * @param {function} callback(err, data)
 */
MonModule.ajax = function (action, data, callback) {
	$.ajax({
		url:  monmodule_ajax_url,
		type: 'POST',
		data: Object.assign({ action: action, token: monmodule_token }, data),
		dataType: 'json',
		success: function (response) {
			if (response && response.success) {
				callback(null, response.data);
			} else {
				callback((response && response.error) ? response.error : 'Erreur inconnue');
			}
		},
		error: function (xhr) {
			callback('Erreur HTTP ' + xhr.status);
		}
	});
};

// Initialisation au chargement
$(document).ready(function () {
	MonModule.init();
});
