/**
 * AGoodBug Admin Scripts
 *
 * Handles dynamic project loading from AGoodMember API.
 */
(function () {
	'use strict';

	const select = document.getElementById('agoodbug-project-select');
	const hiddenInput = document.getElementById('agoodbug-project-id');
	const statusEl = document.getElementById('agoodbug-project-status');
	const apiKeyInput = document.querySelector(
		'input[name="agoodbug_settings[agoodmember_token]"]'
	);

	if (!select || !hiddenInput || !apiKeyInput) {
		return;
	}

	/**
	 * Fetch projects from AGoodMember via AJAX proxy
	 */
	function fetchProjects() {
		const apiKey = apiKeyInput.value.trim();

		if (!apiKey) {
			setStatus('Ange en API-nyckel f\u00f6rst.', 'info');
			resetSelect();
			return;
		}

		setStatus('H\u00e4mtar projekt\u2026', 'loading');
		select.disabled = true;

		const formData = new FormData();
		formData.append('action', 'agoodbug_fetch_projects');
		formData.append('nonce', agoodbugAdmin.nonce);
		formData.append('api_key', apiKey);

		fetch(agoodbugAdmin.ajaxUrl, {
			method: 'POST',
			body: formData,
		})
			.then(function (response) {
				return response.json();
			})
			.then(function (data) {
				if (!data.success) {
					setStatus(data.data || 'Kunde inte h\u00e4mta projekt.', 'error');
					resetSelect();
					return;
				}

				populateSelect(data.data);
				setStatus('', '');
				select.disabled = false;
			})
			.catch(function () {
				setStatus('Nätverksfel. Försök igen.', 'error');
				resetSelect();
			});
	}

	/**
	 * Populate the select with projects
	 */
	function populateSelect(projects) {
		const currentValue = hiddenInput.value;

		// Clear existing options except the first
		select.innerHTML = '';

		const emptyOption = document.createElement('option');
		emptyOption.value = '';
		emptyOption.textContent = '— Inget projekt (anv\u00e4nder "Diverse") —';
		select.appendChild(emptyOption);

		// Group by organization
		const groups = {};
		projects.forEach(function (project) {
			const orgName = project.organizationName || 'Okänd';
			if (!groups[orgName]) {
				groups[orgName] = [];
			}
			groups[orgName].push(project);
		});

		const orgNames = Object.keys(groups).sort();

		if (orgNames.length === 1) {
			// Single org: flat list
			groups[orgNames[0]].forEach(function (project) {
				const option = document.createElement('option');
				option.value = project.id;
				option.textContent = project.name;
				if (project.id === currentValue) {
					option.selected = true;
				}
				select.appendChild(option);
			});
		} else {
			// Multiple orgs: use optgroups
			orgNames.forEach(function (orgName) {
				const optgroup = document.createElement('optgroup');
				optgroup.label = orgName;

				groups[orgName].forEach(function (project) {
					const option = document.createElement('option');
					option.value = project.id;
					option.textContent = project.name;
					if (project.id === currentValue) {
						option.selected = true;
					}
					optgroup.appendChild(option);
				});

				select.appendChild(optgroup);
			});
		}
	}

	/**
	 * Reset select to empty state
	 */
	function resetSelect() {
		select.innerHTML = '';
		const emptyOption = document.createElement('option');
		emptyOption.value = '';
		emptyOption.textContent = '— Ange API-nyckel f\u00f6rst —';
		select.appendChild(emptyOption);
		select.disabled = true;
	}

	/**
	 * Set status message
	 */
	function setStatus(message, type) {
		if (!statusEl) return;

		statusEl.textContent = message;
		statusEl.className = 'agoodbug-project-status';

		if (type) {
			statusEl.classList.add('agoodbug-project-status--' + type);
		}
	}

	// Sync select to hidden input
	select.addEventListener('change', function () {
		hiddenInput.value = select.value;
	});

	// Load projects on page load if API key exists
	if (apiKeyInput.value.trim()) {
		fetchProjects();
	} else {
		resetSelect();
	}

	// Reload projects when API key changes
	let debounceTimer;
	apiKeyInput.addEventListener('input', function () {
		clearTimeout(debounceTimer);
		debounceTimer = setTimeout(fetchProjects, 800);
	});

	// Also reload on blur (in case paste doesn't trigger input)
	apiKeyInput.addEventListener('blur', function () {
		clearTimeout(debounceTimer);
		fetchProjects();
	});
})();
