(function () {
	'use strict';
	var config = window.SVA_TRACKER;
	if (!config || !config.endpoint || !window.fetch) return;
	var payload = {url: window.location.href, title: document.title, referrer: document.referrer};
	fetch(config.endpoint, {
		method: 'POST', credentials: 'same-origin', keepalive: true,
		headers: {'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce},
		body: JSON.stringify(payload)
	}).catch(function () {});
}());