/**
 * AGoodBug - Frontend Widget
 * Visual feedback and bug reporting with screenshot capture
 */

(function() {
	'use strict';

	const config = window.agoodbugConfig || {};
	const strings = config.strings || {};

	class AGoodBug {
		constructor() {
			this.container = document.getElementById('agoodbug-widget');
			if (!this.container) return;

			this.isCapturing = false;
			this.selection = null;
			this.screenshot = null;
			this.feedbackMode = null; // 'general' or 'screenshot'

			this.init();
		}

		init() {
			this.createButton();
			this.createChoicePanel();
			this.createOverlay();
			this.createModal();
			this.bindEvents();
		}

		// Create floating button
		createButton() {
			const style = config.buttonStyle || 'button';
			const label = config.tabLabel || 'Tyck till';

			this.button = document.createElement('button');
			this.button.setAttribute('aria-label', strings.buttonTitle);
			this.button.setAttribute('title', strings.buttonTitle);

			if (style === 'tab-bottom') {
				this.button.className = 'agoodbug-button agoodbug-button--tab-bottom';
				this.button.textContent = label;
			} else if (style === 'tab-side') {
				this.button.className = 'agoodbug-button agoodbug-button--tab-side';
				this.button.textContent = label;
			} else {
				this.button.className = 'agoodbug-button';
				this.button.innerHTML = `
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<path d="M8 2l1.88 1.88"/>
						<path d="M14.12 3.88L16 2"/>
						<path d="M9 7.13v-1a3.003 3.003 0 116 0v1"/>
						<path d="M12 20c-3.3 0-6-2.7-6-6v-3a4 4 0 014-4h4a4 4 0 014 4v3c0 3.3-2.7 6-6 6"/>
						<path d="M12 20v-9"/>
						<path d="M6.53 9C4.6 8.8 3 7.1 3 5"/>
						<path d="M6 13H2"/>
						<path d="M3 21c0-2.1 1.7-3.9 3.8-4"/>
						<path d="M20.97 5c0 2.1-1.6 3.8-3.5 4"/>
						<path d="M22 13h-4"/>
						<path d="M17.2 17c2.1.1 3.8 1.9 3.8 4"/>
					</svg>
				`;
			}

			this.container.appendChild(this.button);
		}

		// Create choice panel (General feedback vs Screenshot)
		createChoicePanel() {
			this.choicePanel = document.createElement('div');
			this.choicePanel.className = 'agoodbug-choice';
			this.choicePanel.innerHTML = `
				<div class="agoodbug-choice__backdrop"></div>
				<div class="agoodbug-choice__content">
					<div class="agoodbug-choice__header">
						<h3>${strings.choiceTitle || 'How would you like to report?'}</h3>
						<button type="button" class="agoodbug-choice__close" aria-label="${strings.closeButton}">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
								<path d="M18 6L6 18M6 6l12 12"/>
							</svg>
						</button>
					</div>
					<div class="agoodbug-choice__options">
						<button type="button" class="agoodbug-choice__option" data-mode="general">
							<div class="agoodbug-choice__icon">
								<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
								</svg>
							</div>
							<div class="agoodbug-choice__label">${strings.generalFeedback || 'General feedback'}</div>
							<div class="agoodbug-choice__desc">${strings.generalFeedbackDesc || 'Write a comment without screenshot'}</div>
						</button>
						<button type="button" class="agoodbug-choice__option" data-mode="screenshot">
							<div class="agoodbug-choice__icon">
								<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
									<circle cx="8.5" cy="8.5" r="1.5"/>
									<polyline points="21 15 16 10 5 21"/>
								</svg>
							</div>
							<div class="agoodbug-choice__label">${strings.screenshotFeedback || 'Take a screenshot'}</div>
							<div class="agoodbug-choice__desc">${strings.screenshotFeedbackDesc || 'Mark an area on the page'}</div>
						</button>
					</div>
				</div>
			`;
			this.container.appendChild(this.choicePanel);
		}

		// Show choice panel
		showChoicePanel() {
			this.button.classList.add('is-hidden');
			this.choicePanel.classList.add('is-open');
		}

		// Hide choice panel
		hideChoicePanel() {
			this.choicePanel.classList.remove('is-open');
			this.button.classList.remove('is-hidden');
		}

		// Handle feedback mode selection
		selectFeedbackMode(mode) {
			this.feedbackMode = mode;
			this.hideChoicePanel();

			if (mode === 'general') {
				// Open modal directly without screenshot
				this.screenshot = null;
				this.selection = null;
				this.openModal();
			} else if (mode === 'screenshot') {
				// Start screenshot capture flow
				this.startCapture();
			}
		}

		// Create selection overlay
		createOverlay() {
			this.overlay = document.createElement('div');
			this.overlay.className = 'agoodbug-overlay';
			this.overlay.innerHTML = `
				<div class="agoodbug-overlay__hint">${strings.overlayHint}</div>
				<canvas class="agoodbug-overlay__canvas"></canvas>
				<div class="agoodbug-overlay__selection"></div>
			`;
			this.container.appendChild(this.overlay);

			this.canvas = this.overlay.querySelector('.agoodbug-overlay__canvas');
			this.selectionBox = this.overlay.querySelector('.agoodbug-overlay__selection');
			this.hint = this.overlay.querySelector('.agoodbug-overlay__hint');
		}

		// Create modal
		createModal() {
			const showEmailField = config.showEmailField;

			this.modal = document.createElement('div');
			this.modal.className = 'agoodbug-modal';
			this.modal.innerHTML = `
				<div class="agoodbug-modal__backdrop"></div>
				<div class="agoodbug-modal__content">
					<div class="agoodbug-modal__header">
						<h2>${strings.modalTitle}</h2>
						<button type="button" class="agoodbug-modal__close" aria-label="${strings.closeButton}">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
								<path d="M18 6L6 18M6 6l12 12"/>
							</svg>
						</button>
					</div>
					<div class="agoodbug-modal__body">
						<div class="agoodbug-modal__preview">
							<img src="" alt="Screenshot preview" />
						</div>
						<div class="agoodbug-modal__sidebar">
							<div class="agoodbug-modal__screenshot-notice" hidden>
								<p>${strings.screenshotFailed || 'Skärmbild kunde inte tas — beskriv problemet nedan.'}</p>
							</div>
							<div class="agoodbug-modal__form">
								<div class="agoodbug-modal__field">
									<label for="agoodbug-comment">${strings.commentLabel}</label>
									<textarea id="agoodbug-comment" placeholder="${strings.commentPlaceholder}" rows="3"></textarea>
								</div>
								${showEmailField ? `
								<div class="agoodbug-modal__field">
									<label for="agoodbug-email">${strings.emailLabel}</label>
									<input type="email" id="agoodbug-email" placeholder="${strings.emailPlaceholder}" />
								</div>
								` : ''}
							</div>
							<div class="agoodbug-modal__actions">
								<button type="button" class="agoodbug-modal__cancel">${strings.cancelButton}</button>
								<button type="button" class="agoodbug-modal__submit">${strings.submitButton}</button>
							</div>
						</div>
					</div>
					<div class="agoodbug-modal__success" hidden>
						<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
							<path d="M22 11.08V12a10 10 0 11-5.93-9.14"/>
							<polyline points="22 4 12 14.01 9 11.01"/>
						</svg>
						<p>${strings.success}</p>
						<button type="button" class="agoodbug-modal__close-success">${strings.closeButton}</button>
					</div>
					<div class="agoodbug-modal__error" hidden>
						<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
							<circle cx="12" cy="12" r="10"/>
							<line x1="15" y1="9" x2="9" y2="15"/>
							<line x1="9" y1="9" x2="15" y2="15"/>
						</svg>
						<p>${strings.error}</p>
						<button type="button" class="agoodbug-modal__retry">${strings.retryButton}</button>
					</div>
				</div>
			`;
			this.container.appendChild(this.modal);

			this.previewImg = this.modal.querySelector('.agoodbug-modal__preview img');
			this.emailField = this.modal.querySelector('#agoodbug-email');
			this.commentField = this.modal.querySelector('#agoodbug-comment');
			this.submitBtn = this.modal.querySelector('.agoodbug-modal__submit');
			this.successPanel = this.modal.querySelector('.agoodbug-modal__success');
			this.errorPanel = this.modal.querySelector('.agoodbug-modal__error');

			// Pre-fill email: logged-in user email > localStorage > empty
			if (this.emailField) {
				if (config.userEmail) {
					this.emailField.value = config.userEmail;
				} else {
					const savedEmail = localStorage.getItem('agoodbug_email');
					if (savedEmail) {
						this.emailField.value = savedEmail;
					}
				}
			}
		}

		// Bind events
		bindEvents() {
			// Button click - show choice panel
			this.button.addEventListener('click', () => this.showChoicePanel());

			// Choice panel events
			this.choicePanel.querySelector('.agoodbug-choice__backdrop').addEventListener('click', () => this.hideChoicePanel());
			this.choicePanel.querySelector('.agoodbug-choice__close').addEventListener('click', () => this.hideChoicePanel());
			this.choicePanel.querySelectorAll('.agoodbug-choice__option').forEach(option => {
				option.addEventListener('click', () => {
					const mode = option.dataset.mode;
					this.selectFeedbackMode(mode);
				});
			});

			// Overlay events
			this.overlay.addEventListener('mousedown', (e) => this.onMouseDown(e));
			this.overlay.addEventListener('mousemove', (e) => this.onMouseMove(e));
			this.overlay.addEventListener('mouseup', (e) => this.onMouseUp(e));
			this.overlay.addEventListener('touchstart', (e) => this.onTouchStart(e));
			this.overlay.addEventListener('touchmove', (e) => this.onTouchMove(e));
			this.overlay.addEventListener('touchend', (e) => this.onTouchEnd(e));

			// Escape key
			document.addEventListener('keydown', (e) => {
				if (e.key === 'Escape') {
					if (this.choicePanel.classList.contains('is-open')) this.hideChoicePanel();
					if (this.isCapturing) this.cancelCapture();
					if (this.modal.classList.contains('is-open')) this.closeModal();
				}
			});

			// Modal events
			this.modal.querySelector('.agoodbug-modal__backdrop').addEventListener('click', () => this.closeModal());
			this.modal.querySelector('.agoodbug-modal__close').addEventListener('click', () => this.closeModal());
			this.modal.querySelector('.agoodbug-modal__cancel').addEventListener('click', () => this.closeModal());
			this.submitBtn.addEventListener('click', () => this.submitFeedback());
			this.modal.querySelector('.agoodbug-modal__close-success').addEventListener('click', () => this.closeModal());
			this.modal.querySelector('.agoodbug-modal__retry').addEventListener('click', () => this.showForm());
		}

		// Start capture mode
		startCapture() {
			this.isCapturing = true;
			this.selection = null;
			this.button.classList.add('is-hidden');
			this.overlay.classList.add('is-active');
			document.body.style.cursor = 'crosshair';
			document.body.style.overflow = 'hidden';
		}

		// Cancel capture
		cancelCapture() {
			this.isCapturing = false;
			this.selection = null;
			this.button.classList.remove('is-hidden');
			this.overlay.classList.remove('is-active');
			this.selectionBox.style.display = 'none';
			document.body.style.cursor = '';
			document.body.style.overflow = '';
		}

		// Mouse/touch handlers
		onMouseDown(e) {
			if (!this.isCapturing) return;
			this.startSelection(e.clientX, e.clientY);
		}

		onMouseMove(e) {
			if (!this.selection || !this.selection.isDrawing) return;
			this.updateSelection(e.clientX, e.clientY);
		}

		onMouseUp(e) {
			if (!this.selection || !this.selection.isDrawing) return;
			this.endSelection();
		}

		onTouchStart(e) {
			if (!this.isCapturing) return;
			const touch = e.touches[0];
			this.startSelection(touch.clientX, touch.clientY);
		}

		onTouchMove(e) {
			if (!this.selection || !this.selection.isDrawing) return;
			e.preventDefault();
			const touch = e.touches[0];
			this.updateSelection(touch.clientX, touch.clientY);
		}

		onTouchEnd(e) {
			if (!this.selection || !this.selection.isDrawing) return;
			this.endSelection();
		}

		// Selection helpers
		startSelection(x, y) {
			this.selection = {
				startX: x,
				startY: y,
				endX: x,
				endY: y,
				isDrawing: true
			};
			this.hint.style.display = 'none';
			this.selectionBox.style.display = 'block';
			this.updateSelectionBox();
		}

		updateSelection(x, y) {
			this.selection.endX = x;
			this.selection.endY = y;
			this.updateSelectionBox();
		}

		updateSelectionBox() {
			const { startX, startY, endX, endY } = this.selection;
			const left = Math.min(startX, endX);
			const top = Math.min(startY, endY);
			const width = Math.abs(endX - startX);
			const height = Math.abs(endY - startY);

			this.selectionBox.style.left = left + 'px';
			this.selectionBox.style.top = top + 'px';
			this.selectionBox.style.width = width + 'px';
			this.selectionBox.style.height = height + 'px';
		}

		async endSelection() {
			this.selection.isDrawing = false;

			const { startX, startY, endX, endY } = this.selection;
			const width = Math.abs(endX - startX);
			const height = Math.abs(endY - startY);

			// Minimum selection size
			if (width < 20 || height < 20) {
				this.cancelCapture();
				return;
			}

			// Capture screenshot
			await this.captureScreenshot();
		}

		// Proxy a single image URL, returns data URL or null
		async proxyImageUrl(url) {
			if (!url || url.startsWith('data:') || url.startsWith('blob:')) return null;

			try {
				const imgUrl = new URL(url);
				if (imgUrl.origin === window.location.origin) return null;

				const proxyResponse = await fetch(
					config.proxyUrl + '?url=' + encodeURIComponent(url) + '&responseType=text',
					{ credentials: 'same-origin' }
				);

				if (!proxyResponse.ok) {
					console.warn('AGoodBug proxy failed for', url, proxyResponse.status);
					return null;
				}

				const base64 = await proxyResponse.text();
				// Detect MIME type from URL extension
				const ext = url.split('?')[0].split('.').pop().toLowerCase();
				const mimeTypes = { jpg: 'image/jpeg', jpeg: 'image/jpeg', png: 'image/png', gif: 'image/gif', webp: 'image/webp', svg: 'image/svg+xml' };
				const mime = mimeTypes[ext] || 'image/jpeg';
				return 'data:' + mime + ';base64,' + base64;
			} catch (e) {
				console.warn('AGoodBug proxy error for', url, e);
				return null;
			}
		}

		// Replace cross-origin images with data URLs via proxy
		async proxyCrossOriginImages() {
			if (!config.proxyUrl) return [];

			const restored = [];

			// Handle <img> elements (use currentSrc for srcset support)
			const imgPromises = Array.from(document.querySelectorAll('img')).map(async (img) => {
				const actualSrc = img.currentSrc || img.src;
				const dataUrl = await this.proxyImageUrl(actualSrc);
				if (dataUrl) {
					const originalSrc = img.src;
					const originalSrcset = img.srcset;
					img.srcset = '';
					img.src = dataUrl;
					restored.push({ el: img, originalSrc, originalSrcset });
				}
			});

			// Handle CSS background-image
			const bgElements = document.querySelectorAll('*');
			const bgPromises = Array.from(bgElements).map(async (el) => {
				const bg = getComputedStyle(el).backgroundImage;
				if (!bg || bg === 'none') return;

				const match = bg.match(/url\(["']?(https?:\/\/[^"')]+)["']?\)/);
				if (!match) return;

				const dataUrl = await this.proxyImageUrl(match[1]);
				if (dataUrl) {
					const originalBg = el.style.backgroundImage;
					el.style.backgroundImage = 'url(' + dataUrl + ')';
					restored.push({ el, originalBg, isBg: true });
				}
			});

			await Promise.all([...imgPromises, ...bgPromises]);
			console.log('AGoodBug: proxied', restored.length, 'cross-origin images');
			return restored;
		}

		// Defeat GSAP/ScrollTrigger initial-hidden states. Animation libraries set
		// opacity:0 + visibility:hidden + clip-path on elements as initial states
		// for scroll-driven reveals. Override all three. Genuinely hidden UI
		// (cookie banners, modals, our own widget) uses display:none or
		// aria-hidden/hidden — those stay hidden via the explicit excludes below.
		applyVisibilityOverride() {
			const style = document.createElement('style');
			style.id = 'agoodbug-capture-overrides';
			style.textContent = `
				*, *::before, *::after {
					opacity: 1 !important;
					visibility: visible !important;
					clip-path: none !important;
					-webkit-clip-path: none !important;
				}
				.agoodbug-overlay,
				.agoodbug-modal,
				.agoodbug-button,
				.agoodbug-tab,
				[aria-hidden="true"],
				[hidden] { display: none !important; }
			`;
			document.head.appendChild(style);

			// Best-effort: fast-forward GSAP/ScrollTrigger animations to their end
			// state in the live DOM before capture, so the cloned iframe inherits
			// completed final states (transform: translateY(0), height: auto, etc.)
			// rather than initial hidden states.
			try {
				if ( window.ScrollTrigger && typeof window.ScrollTrigger.getAll === 'function' ) {
					window.ScrollTrigger.getAll().forEach(t => {
						if ( t.animation && typeof t.animation.progress === 'function' ) {
							t.animation.progress(1);
						}
					});
				}
			} catch (e) {}

			return () => style.remove();
		}

		// Patch color() CSS functions directly in the live document before html2canvas runs.
		// html2canvas throws on color(display-p3 …) / color(srgb …) etc. because its own
		// CSS parser doesn't support them. We convert each one to rgb() via a 1×1 canvas
		// (the browser converts any color space to sRGB when drawing to canvas).
		// Returns an array of restore functions; call each after html2canvas finishes.
		async applyColorPatch() {
			const tiny = document.createElement('canvas');
			tiny.width = tiny.height = 1;
			const ctx = tiny.getContext('2d');

			const toRgb = (colorStr) => {
				try {
					ctx.clearRect(0, 0, 1, 1);
					ctx.fillStyle = colorStr;
					ctx.fillRect(0, 0, 1, 1);
					const [r, g, b, a] = ctx.getImageData(0, 0, 1, 1).data;
					if (a === 0) return 'transparent';
					return a === 255 ? `rgb(${r},${g},${b})` : `rgba(${r},${g},${b},${(a / 255).toFixed(3)})`;
				} catch (e) {
					return 'transparent';
				}
			};

			const patch = (text) => text.replace(/(?<![a-zA-Z0-9_-])color\(([^)]*)\)/g, (m) => toRgb(m));
			const restoreFns = [];

			// Patch inline <style> elements
			document.querySelectorAll('style').forEach(el => {
				const orig = el.textContent;
				const patched = patch(orig);
				if (patched !== orig) {
					el.textContent = patched;
					restoreFns.push(() => { el.textContent = orig; });
				}
			});

			// Fetch external stylesheets, patch, inject as <style>, disable original <link>
			const links = Array.from(document.querySelectorAll('link[rel="stylesheet"]'));
			await Promise.all(links.map(async (link) => {
				const href = link.href;
				if (!href) return;
				try {
					const res = await fetch(href);
					if (!res.ok) return;
					const text = await res.text();
					const patched = patch(text);
					if (patched === text) return;
					const style = document.createElement('style');
					style.textContent = patched;
					link.insertAdjacentElement('afterend', style);
					link.disabled = true;
					restoreFns.push(() => { style.remove(); link.disabled = false; });
				} catch (e) {}
			}));

			// Belt-and-suspenders: html2canvas also reads window.getComputedStyle() per
			// element. In Chrome 111+ this returns color(display-p3 …) natively. Apply
			// !important inline styles with rgb() equivalents for any element that still
			// has color() in its computed values after the stylesheet patches above.
			const colorProps = [
				'color', 'background-color',
				'border-top-color', 'border-right-color', 'border-bottom-color', 'border-left-color',
				'outline-color', 'text-decoration-color',
			];
			document.querySelectorAll('*').forEach(el => {
				const computed = window.getComputedStyle(el);
				const overrides = {};
				colorProps.forEach(prop => {
					const val = computed.getPropertyValue(prop);
					if (val && val.includes('color(')) overrides[prop] = toRgb(val);
				});
				if (!Object.keys(overrides).length) return;
				const prev = {};
				colorProps.forEach(prop => { prev[prop] = el.style.getPropertyValue(prop); });
				Object.entries(overrides).forEach(([p, v]) => el.style.setProperty(p, v, 'important'));
				restoreFns.push(() => {
					Object.entries(prev).forEach(([p, v]) => v ? el.style.setProperty(p, v) : el.style.removeProperty(p));
				});
			});

			return restoreFns;
		}

		// Restore original image sources
		restoreImages(restored) {
			for (const item of restored) {
				if (item.isBg) {
					item.el.style.backgroundImage = item.originalBg;
				} else {
					item.el.srcset = item.originalSrcset || '';
					item.el.src = item.originalSrc;
				}
			}
		}

		// Returns true if canvas is monochromatic — html2canvas silently failed
		isCanvasBlank(canvas) {
			const ctx = canvas.getContext('2d');
			const { data } = ctx.getImageData(0, 0, canvas.width, canvas.height);
			const total = data.length / 4;
			const step = Math.max(1, Math.floor(total / 200)) * 4;
			const r0 = data[0], g0 = data[1], b0 = data[2];
			let uniform = 0, sampled = 0;
			for (let i = 0; i < data.length; i += step) {
				if (Math.abs(data[i] - r0) < 8 && Math.abs(data[i + 1] - g0) < 8 && Math.abs(data[i + 2] - b0) < 8) {
					uniform++;
				}
				sampled++;
			}
			return uniform / sampled > 0.9;
		}

		// Show "we need permission" soft-start prompt before triggering the
		// browser's getDisplayMedia dialog. Resolves to true if user clicks
		// Fortsätt, false if Avbryt or backdrop click.
		showPermissionPrompt() {
			return new Promise((resolve) => {
				const title  = strings.permissionTitle  || 'Tillåtelse krävs';
				const body   = strings.permissionBody   || 'Vi behöver din tillåtelse att fånga skärmen. Klicka "Dela" i nästa dialog.';
				const cancel = strings.cancelButton     || 'Avbryt';
				const cont   = strings.continueButton   || 'Fortsätt';
				const prompt = document.createElement('div');
				prompt.className = 'agoodbug-permission';
				prompt.innerHTML = `
					<div class="agoodbug-permission__backdrop"></div>
					<div class="agoodbug-permission__box">
						<h3></h3>
						<p></p>
						<div class="agoodbug-permission__actions">
							<button type="button" class="agoodbug-permission__cancel"></button>
							<button type="button" class="agoodbug-permission__confirm"></button>
						</div>
					</div>
				`;
				prompt.querySelector('h3').textContent = title;
				prompt.querySelector('p').textContent = body;
				prompt.querySelector('.agoodbug-permission__cancel').textContent = cancel;
				prompt.querySelector('.agoodbug-permission__confirm').textContent = cont;
				document.body.appendChild(prompt);
				const close = (val) => { prompt.remove(); resolve(val); };
				prompt.querySelector('.agoodbug-permission__cancel').onclick = () => close(false);
				prompt.querySelector('.agoodbug-permission__backdrop').onclick = () => close(false);
				prompt.querySelector('.agoodbug-permission__confirm').onclick = () => close(true);
			});
		}

		// Capture via the browser's Screen Capture API. Pixel-perfect — captures
		// exactly what the user sees, regardless of CSS quirks, animations or page
		// length. Trade-off: the browser shows its own permission dialog.
		// We rely on transient activation from the selection mouseup so no extra
		// "Continue" click is needed.
		async captureViaScreenShare() {
			// Hide our overlay so it isn't captured in the frame
			this.overlay.classList.remove('is-active');
			await new Promise(r => requestAnimationFrame(r));

			let stream;
			try {
				stream = await navigator.mediaDevices.getDisplayMedia({
					video: true,
					audio: false,
					// These options must be top-level on the options object (not in video).
					// On Chrome 109+ preferCurrentTab triggers a simple "share this tab?"
					// confirmation instead of the full picker. Other browsers ignore unknowns.
					preferCurrentTab: true,
					selfBrowserSurface: 'include',
					surfaceSwitching: 'exclude',
					monitorTypeSurfaces: 'exclude',
				});
			} catch (e) {
				console.warn('AGoodBug: getDisplayMedia denied/failed:', e);
				this.cancelCapture();
				this.screenshot = null;
				this.screenshotFailed = true;
				this.openModal();
				return;
			}

			// Pull one frame from the stream
			const video = document.createElement('video');
			video.muted = true;
			video.srcObject = stream;
			await new Promise(r => video.onloadedmetadata = r);
			await video.play();
			await new Promise(r => requestAnimationFrame(r));

			const fullCanvas = document.createElement('canvas');
			fullCanvas.width  = video.videoWidth;
			fullCanvas.height = video.videoHeight;
			fullCanvas.getContext('2d').drawImage(video, 0, 0);

			// Stop the stream — frees the indicator and the user's permission
			stream.getTracks().forEach(t => t.stop());
			video.srcObject = null;

			// The captured frame matches the viewport (browser tab surface).
			// Selection coords are clientX/Y → map directly with viewport scale.
			const { startX, startY, endX, endY } = this.selection;
			const scaleX = fullCanvas.width  / window.innerWidth;
			const scaleY = fullCanvas.height / window.innerHeight;

			const x = Math.min(startX, endX) * scaleX;
			const y = Math.min(startY, endY) * scaleY;
			const w = Math.abs(endX - startX) * scaleX;
			const h = Math.abs(endY - startY) * scaleY;

			const padding = 50 * scaleX;
			const cropped = document.createElement('canvas');
			cropped.width  = w + padding * 2;
			cropped.height = h + padding * 2;
			const ctx = cropped.getContext('2d');
			ctx.fillStyle = 'rgba(0, 0, 0, 0.3)';
			ctx.fillRect(0, 0, cropped.width, cropped.height);
			ctx.drawImage(
				fullCanvas,
				x - padding, y - padding, w + padding * 2, h + padding * 2,
				0, 0, cropped.width, cropped.height
			);
			ctx.strokeStyle = '#ff6b35';
			ctx.lineWidth   = 4 * scaleX;
			ctx.strokeRect(padding, padding, w, h);

			this.screenshot = cropped.toDataURL('image/png');
			const selectionData = {
				x: Math.min(startX, endX),
				y: Math.min(startY, endY),
				width:  Math.abs(endX - startX),
				height: Math.abs(endY - startY),
			};
			this.cancelCapture();
			this.selection = selectionData;
			this.openModal();
		}

		// Capture screenshot
		async captureScreenshot() {
			// Prefer Screen Capture API when available — pixel-perfect and works
			// regardless of CSS/animation quirks. Falls back to html2canvas below
			// if the browser doesn't expose getDisplayMedia (older Safari, etc.).
			if (navigator.mediaDevices && typeof navigator.mediaDevices.getDisplayMedia === 'function') {
				return this.captureViaScreenShare();
			}
			return this.captureViaHtml2Canvas();
		}

		async captureViaHtml2Canvas() {
			let restored = [];
			let cssRestoreFns = [];
			try {
				// Hide our UI
				this.overlay.classList.remove('is-active');

				// Wait for UI to update
				await new Promise(r => setTimeout(r, 100));

				// Replace cross-origin images with proxied data URLs
				restored = await this.proxyCrossOriginImages();

				// Patch color() functions in live document before html2canvas reads CSS
				cssRestoreFns = await this.applyColorPatch();

				// Force visibility — kills GSAP/ScrollTrigger-driven hidden states
				// that html2canvas's clone iframe would otherwise capture as blank.
				cssRestoreFns.push( this.applyVisibilityOverride() );

				// Compute a safe scale: long pages produce huge canvases that hit
				// browser limits (Safari ~16 megapixels, others vary) and only render
				// partially — captures past a certain depth come back blank/garbled.
				// Cap area at 12MP and pick the largest scale that fits.
				const bodyRectPre = document.body.getBoundingClientRect();
				const maxArea  = 12 * 1024 * 1024; // 12 megapixels — safe everywhere
				const dpr      = window.devicePixelRatio || 1;
				const scaleFit = Math.sqrt( maxArea / (bodyRectPre.width * bodyRectPre.height) );
				const captureScale = Math.min( dpr, scaleFit );

				const canvas = await html2canvas(document.body, {
					useCORS: true,
					allowTaint: false,
					scale: captureScale,
					logging: false,
				});

				// Restore CSS patches and image sources
				cssRestoreFns.forEach(fn => fn());
				this.restoreImages(restored);

				// Detect blank canvas — html2canvas silently fails on unsupported CSS
				// (e.g. oklch(), color-mix()). Sample pixels; if >90% are uniform, bail out.
				if (this.isCanvasBlank(canvas)) {
					this.cancelCapture();
					this.screenshot = null;
					this.screenshotFailed = true;
					this.openModal();
					return;
				}

				// Selection uses clientX/clientY (viewport-relative); html2canvas
				// captures the full body. Use bodyRect to translate viewport coords
				// to canvas coords (works for both native scroll and transform scroll).
				const { startX, startY, endX, endY } = this.selection;
				const bodyRect = document.body.getBoundingClientRect();
				const scale    = canvas.width / bodyRect.width;
				const x        = (Math.min(startX, endX) - bodyRect.left) * scale;
				const y        = (Math.min(startY, endY) - bodyRect.top)  * scale;
				const width    = Math.abs(endX - startX) * scale;
				const height   = Math.abs(endY - startY) * scale;

				// Debug log — paste this from the console when reporting issues
				console.log('[AGoodBug debug]', {
					canvas: { w: canvas.width, h: canvas.height },
					bodyRect: { left: bodyRect.left, top: bodyRect.top, w: bodyRect.width, h: bodyRect.height },
					window: { innerW: window.innerWidth, innerH: window.innerHeight, scrollX: window.scrollX, scrollY: window.scrollY, dpr: window.devicePixelRatio },
					selection: { startX, startY, endX, endY },
					crop: { x, y, width, height, scale },
				});

				// Debug mode: skip cropping and use the entire raw html2canvas output
				// as the screenshot. Add ?agoodbug-debug-full to the URL to enable.
				// This lets us verify whether html2canvas itself is rendering correctly.
				if ( window.location.search.indexOf('agoodbug-debug-full') !== -1 ) {
					this.screenshot = canvas.toDataURL('image/png');
					this.cancelCapture();
					this.openModal();
					return;
				}

				// Create cropped canvas with highlight
				const croppedCanvas = document.createElement('canvas');
				const padding = 50 * scale;
				croppedCanvas.width = width + padding * 2;
				croppedCanvas.height = height + padding * 2;

				const ctx = croppedCanvas.getContext('2d');

				// Draw surrounding area (dimmed)
				ctx.fillStyle = 'rgba(0, 0, 0, 0.3)';
				ctx.fillRect(0, 0, croppedCanvas.width, croppedCanvas.height);

				// Draw the selected area
				ctx.drawImage(
					canvas,
					x - padding, y - padding,
					width + padding * 2, height + padding * 2,
					0, 0,
					croppedCanvas.width, croppedCanvas.height
				);

				// Draw highlight border
				ctx.strokeStyle = '#ff6b35';
				ctx.lineWidth = 4 * scale;
				ctx.strokeRect(padding, padding, width, height);

				this.screenshot = croppedCanvas.toDataURL('image/png');
				const selectionData = {
					x: Math.min(startX, endX),
					y: Math.min(startY, endY),
					width: Math.abs(endX - startX),
					height: Math.abs(endY - startY)
				};

				// Show modal (cancelCapture resets this.selection, so restore after)
				this.cancelCapture();
				this.selection = selectionData;
				this.openModal();

			} catch (error) {
				console.error('Screenshot capture failed:', error);
				cssRestoreFns.forEach(fn => fn());
				this.restoreImages(restored);
				this.cancelCapture();
				this.screenshot = null;
				this.screenshotFailed = true;
				this.openModal();
			}
		}

		// Modal handlers
		openModal() {
			const previewContainer = this.modal.querySelector('.agoodbug-modal__preview');
			const screenshotNotice = this.modal.querySelector('.agoodbug-modal__screenshot-notice');

			if (this.screenshot) {
				this.previewImg.src = this.screenshot;
				previewContainer.hidden = false;
				this.modal.classList.remove('is-general-mode');
			} else {
				previewContainer.hidden = true;
				this.modal.classList.add('is-general-mode');
			}

			if (screenshotNotice) {
				screenshotNotice.hidden = !this.screenshotFailed;
			}

			this.commentField.value = '';
			this.modal.classList.add('is-open');
			this.showForm();
			this.commentField.focus();
			document.body.style.overflow = 'hidden';
		}

		closeModal() {
			this.modal.classList.remove('is-open');
			this.screenshot = null;
			this.screenshotFailed = false;
			document.body.style.overflow = '';
		}

		showForm() {
			this.modal.querySelector('.agoodbug-modal__body').hidden = false;
			this.modal.querySelector('.agoodbug-modal__actions').hidden = false;
			this.successPanel.hidden = true;
			this.errorPanel.hidden = true;
		}

		showSuccess() {
			this.modal.querySelector('.agoodbug-modal__body').hidden = true;
			this.modal.querySelector('.agoodbug-modal__actions').hidden = true;
			this.successPanel.hidden = false;
			this.errorPanel.hidden = true;
		}

		showError() {
			this.modal.querySelector('.agoodbug-modal__body').hidden = true;
			this.modal.querySelector('.agoodbug-modal__actions').hidden = true;
			this.successPanel.hidden = true;
			this.errorPanel.hidden = false;
		}

		// Submit feedback
		async submitFeedback() {
			const comment = this.commentField.value.trim();
			const email = this.emailField ? this.emailField.value.trim() : '';

			// Validate email if field is shown
			if (this.emailField && !email) {
				this.emailField.focus();
				this.emailField.classList.add('is-error');
				return;
			}

			if (!comment) {
				this.commentField.focus();
				this.commentField.classList.add('is-error');
				return;
			}

			if (this.emailField) {
				this.emailField.classList.remove('is-error');
			}
			this.commentField.classList.remove('is-error');
			this.submitBtn.disabled = true;
			this.submitBtn.textContent = strings.sending;

			// Save email to localStorage for next time
			if (email) {
				localStorage.setItem('agoodbug_email', email);
			}

			const deviceInfo = this.getDeviceInfo();
			const data = {
				feedback_type: this.feedbackMode || 'screenshot',
				screenshot: this.screenshot || null,
				url: window.location.href,
				comment: comment,
				email: email,
				selection: this.selection ? JSON.stringify(this.selection) : null,
				viewport: `${window.innerWidth}x${window.innerHeight}`,
				browser: this.getBrowserInfo(),
				// Extended device info
				device_type: deviceInfo.deviceType,
				screen_resolution: deviceInfo.screenResolution,
				pixel_ratio: deviceInfo.pixelRatio,
				color_depth: deviceInfo.colorDepth,
				touch_enabled: deviceInfo.touchEnabled,
				color_scheme: deviceInfo.colorScheme,
				language: deviceInfo.language,
				timezone: deviceInfo.timezone,
				referrer: deviceInfo.referrer,
				cookies_enabled: deviceInfo.cookiesEnabled
			};

			try {
				const response = await fetch(config.apiUrl, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': config.nonce
					},
					body: JSON.stringify(data)
				});

				const result = await response.json();

				if (response.ok && result.success) {
					this.showSuccess();
				} else {
					throw new Error(result.message || 'Unknown error');
				}

			} catch (error) {
				console.error('Submit failed:', error);
				this.showError();
			} finally {
				this.submitBtn.disabled = false;
				this.submitBtn.textContent = strings.submitButton;
			}
		}

		// Get browser info
		getBrowserInfo() {
			const ua = navigator.userAgent;
			let browser = 'Unknown';

			if (ua.includes('Firefox/')) {
				browser = 'Firefox ' + ua.split('Firefox/')[1].split(' ')[0];
			} else if (ua.includes('Chrome/') && !ua.includes('Edg/')) {
				browser = 'Chrome ' + ua.split('Chrome/')[1].split(' ')[0];
			} else if (ua.includes('Safari/') && !ua.includes('Chrome/')) {
				browser = 'Safari ' + ua.split('Version/')[1]?.split(' ')[0] || '';
			} else if (ua.includes('Edg/')) {
				browser = 'Edge ' + ua.split('Edg/')[1].split(' ')[0];
			}

			let os = 'Unknown';
			if (ua.includes('Windows')) os = 'Windows';
			else if (ua.includes('Mac')) os = 'macOS';
			else if (ua.includes('Linux')) os = 'Linux';
			else if (ua.includes('iPhone') || ua.includes('iPad')) os = 'iOS';
			else if (ua.includes('Android')) os = 'Android';

			return `${browser} / ${os}`;
		}

		// Get device type (mobile, tablet, desktop)
		getDeviceType() {
			const ua = navigator.userAgent;

			// Check for mobile devices
			if (/iPhone|iPod|Android.*Mobile|webOS|BlackBerry|IEMobile|Opera Mini/i.test(ua)) {
				return 'mobile';
			}

			// Check for tablets
			if (/iPad|Android(?!.*Mobile)|Tablet/i.test(ua)) {
				return 'tablet';
			}

			return 'desktop';
		}

		// Get extended device information
		getDeviceInfo() {
			return {
				// Device type
				deviceType: this.getDeviceType(),

				// Screen info
				screenResolution: `${window.screen.width}x${window.screen.height}`,
				pixelRatio: window.devicePixelRatio || 1,
				colorDepth: window.screen.colorDepth,

				// Touch capability
				touchEnabled: ('ontouchstart' in window) || (navigator.maxTouchPoints > 0),
				maxTouchPoints: navigator.maxTouchPoints || 0,

				// User preferences
				colorScheme: window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light',
				reducedMotion: window.matchMedia('(prefers-reduced-motion: reduce)').matches,

				// Locale info
				language: navigator.language || navigator.userLanguage || 'unknown',
				languages: navigator.languages ? navigator.languages.join(', ') : navigator.language,
				timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || 'unknown',

				// Navigation
				referrer: document.referrer || 'direct',

				// Browser capabilities
				cookiesEnabled: navigator.cookieEnabled,
				doNotTrack: navigator.doNotTrack === '1' || navigator.doNotTrack === 'yes'
			};
		}
	}

	// Initialize when DOM is ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', () => new AGoodBug());
	} else {
		new AGoodBug();
	}

})();
