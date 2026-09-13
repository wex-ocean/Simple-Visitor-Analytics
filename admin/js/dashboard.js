(function () {
	'use strict';
	var data = window.SVA_DASHBOARD_DATA || {rows: []};
	var lineChart;
	var colors = ['#176b87', '#d96c4f', '#e0a458', '#4e8c6a', '#8b6f9e', '#2f4858'];
	var period = document.getElementById('sva-period');
	var customRange = document.getElementById('sva-custom-range');

	function filteredRows() {
		var rows = data.rows || [];
		if (period.value !== 'custom') return rows;
		var start = document.getElementById('sva-start-date').value;
		var end = document.getElementById('sva-end-date').value;
		return rows.filter(function (row) { return (!start || row.date >= start) && (!end || row.date <= end); });
	}

	function bucketRows(rows) {
		var buckets = {};
		rows.forEach(function (row) {
			var label = row.date;
			if (period.value === 'monthly') label = row.date.slice(0, 7);
			if (period.value === 'weekly') {
				var date = new Date(row.date + 'T00:00:00');
				date.setDate(date.getDate() - date.getDay() + 1);
				label = date.toISOString().slice(0, 10);
			}
			if (!buckets[label]) buckets[label] = {pageviews: 0, uniques: 0};
			buckets[label].pageviews += Number(row.pageviews) || 0;
			buckets[label].uniques += Number(row.uniques) || 0;
		});
		return buckets;
	}

	function updateLineChart() {
		var buckets = bucketRows(filteredRows());
		var labels = Object.keys(buckets).sort();
		var canvas = document.getElementById('sva-line-chart');
		if (!window.Chart || !canvas) return;
		if (lineChart) lineChart.destroy();
		lineChart = new Chart(canvas, {type: 'line', data: {labels: labels, datasets: [
			{label: 'Pageviews', data: labels.map(function (label) { return buckets[label].pageviews; }), borderColor: colors[0], backgroundColor: 'rgba(23,107,135,.12)', fill: true, tension: .25},
			{label: 'Uniques', data: labels.map(function (label) { return buckets[label].uniques; }), borderColor: colors[1], backgroundColor: 'transparent', tension: .25}
		]}, options: {responsive: true, maintainAspectRatio: false, scales: {y: {beginAtZero: true}}}});
	}

	function updateSummary() {
		var rows = filteredRows();
		var pageviews = rows.reduce(function (sum, row) { return sum + Number(row.pageviews || 0); }, 0);
		var uniques = rows.reduce(function (sum, row) { return sum + Number(row.uniques || 0); }, 0);
		document.getElementById('sva-pageviews').textContent = pageviews.toLocaleString();
		document.getElementById('sva-uniques').textContent = uniques.toLocaleString();
		document.getElementById('sva-average').textContent = uniques ? (pageviews / uniques).toFixed(2) : '0';
	}

	function breakdownChart(id, label, values) {
		var canvas = document.getElementById(id);
		if (!window.Chart || !canvas) return;
		var labels = Object.keys(values || {});
		new Chart(canvas, {type: 'doughnut', data: {labels: labels, datasets: [{label: label, data: labels.map(function (key) { return values[key]; }), backgroundColor: colors}]}, options: {responsive: true, maintainAspectRatio: false}});
	}

	period.addEventListener('change', function () { customRange.hidden = period.value !== 'custom'; updateSummary(); updateLineChart(); });
	document.getElementById('sva-start-date').addEventListener('change', function () { updateSummary(); updateLineChart(); });
	document.getElementById('sva-end-date').addEventListener('change', function () { updateSummary(); updateLineChart(); });
	updateSummary();
	updateLineChart();
	breakdownChart('sva-device-chart', 'Devices', data.device);
	breakdownChart('sva-browser-chart', 'Browsers', data.browser);
	breakdownChart('sva-os-chart', 'Operating systems', data.os);
}());
