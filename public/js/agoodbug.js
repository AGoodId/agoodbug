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

			this.init();
		}

		init() {
			this.createButton();
			this.createOverlay();
			this.createModal();
			this.bindEvents();
		}

		// Create floating button
		createButton() {
			this.button = document.createElement('button');
			this.button.className = 'agoodbug-button';
			this.button.setAttribute('aria-label', strings.buttonTitle);
			this.button.setAttribute('title', strings.buttonTitle);
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
			this.container.appendChild(this.button);
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
						<div class="agoodbug-modal__form">
							<label for="agoodbug-comment">${strings.commentLabel}</label>
							<textarea id="agoodbug-comment" placeholder="${strings.commentPlaceholder}" rows="4"></textarea>
						</div>
					</div>
					<div class="agoodbug-modal__footer">
						<button type="button" class="agoodbug-modal__cancel">${strings.cancelButton}</button>
						<button type="button" class="agoodbug-modal__submit">${strings.submitButton}</button>
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
			this.commentField = this.modal.querySelector('#agoodbug-comment');
			this.submitBtn = this.modal.querySelector('.agoodbug-modal__submit');
			this.successPanel = this.modal.querySelector('.agoodbug-modal__success');
			this.errorPanel = this.modal.querySelector('.agoodbug-modal__error');
		}

		// Bind events
		bindEvents() {
			// Button click
			this.button.addEventListener('click', () => this.startCapture());

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

		// Capture screenshot
		async captureScreenshot() {
			try {
				// Hide our UI
				this.overlay.classList.remove('is-active');

				// Wait for UI to update
				await new Promise(r => setTimeout(r, 100));

				// Capture with html2canvas
				const canvas = await html2canvas(document.body, {
					useCORS: true,
					allowTaint: true,
					scale: window.devicePixelRatio || 1,
					logging: false,
				});

				// Calculate selection in canvas coordinates
				const { startX, startY, endX, endY } = this.selection;
				const scale = canvas.width / window.innerWidth;
				const x = Math.min(startX, endX) * scale;
				const y = Math.min(startY, endY) * scale;
				const width = Math.abs(endX - startX) * scale;
				const height = Math.abs(endY - startY) * scale;

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
				this.selection = {
					x: Math.min(startX, endX),
					y: Math.min(startY, endY),
					width: Math.abs(endX - startX),
					height: Math.abs(endY - startY)
				};

				// Show modal
				this.cancelCapture();
				this.openModal();

			} catch (error) {
				console.error('Screenshot capture failed:', error);
				this.cancelCapture();
				alert(strings.error);
			}
		}

		// Modal handlers
		openModal() {
			this.previewImg.src = this.screenshot;
			this.commentField.value = '';
			this.modal.classList.add('is-open');
			this.showForm();
			this.commentField.focus();
			document.body.style.overflow = 'hidden';
		}

		closeModal() {
			this.modal.classList.remove('is-open');
			this.screenshot = null;
			document.body.style.overflow = '';
		}

		showForm() {
			this.modal.querySelector('.agoodbug-modal__body').hidden = false;
			this.modal.querySelector('.agoodbug-modal__footer').hidden = false;
			this.successPanel.hidden = true;
			this.errorPanel.hidden = true;
		}

		showSuccess() {
			this.modal.querySelector('.agoodbug-modal__body').hidden = true;
			this.modal.querySelector('.agoodbug-modal__footer').hidden = true;
			this.successPanel.hidden = false;
			this.errorPanel.hidden = true;
		}

		showError() {
			this.modal.querySelector('.agoodbug-modal__body').hidden = true;
			this.modal.querySelector('.agoodbug-modal__footer').hidden = true;
			this.successPanel.hidden = true;
			this.errorPanel.hidden = false;
		}

		// Submit feedback
		async submitFeedback() {
			const comment = this.commentField.value.trim();

			if (!comment) {
				this.commentField.focus();
				this.commentField.classList.add('is-error');
				return;
			}

			this.commentField.classList.remove('is-error');
			this.submitBtn.disabled = true;
			this.submitBtn.textContent = strings.sending;

			const data = {
				screenshot: this.screenshot,
				url: window.location.href,
				comment: comment,
				selection: this.selection,
				viewport: `${window.innerWidth}x${window.innerHeight}`,
				browser: this.getBrowserInfo()
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
	}

	// Initialize when DOM is ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', () => new AGoodBug());
	} else {
		new AGoodBug();
	}

})();
