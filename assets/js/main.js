/**
 * EcoPick Phase 1 - Main JavaScript
 */

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    initializeEventListeners();
    initializeValidation();
    initializePasswordToggles();
});

/**
 * Initialize event listeners
 */
function initializeEventListeners() {
    // Close alerts automatically after 5 seconds
    const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alert);
            // Don't auto-close - let user dismiss
        }, 0);
    });

    // Smooth scrolling for navigation links
    document.querySelectorAll('a[href^="#"]').forEach(link => {
        link.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href === '#' || href === '') return;

            const target = document.querySelector(href);
            if (target) {
                e.preventDefault();
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
}

/**
 * Initialize form validation
 */
function initializeValidation() {
    const forms = document.querySelectorAll('form:not(.skip-validation)');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });
}

/**
 * Password toggle behavior is managed in a single shared frontend file:
 * assets/js/password-toggle.js
 */
function initializePasswordToggles() {
    // Intentionally empty. Shared password-toggle.js handles the real toggle state.
}

function setupPasswordToggle(buttonId, inputId) {
    // Intentionally empty. Shared password-toggle.js handles the real toggle state.
}

function updatePasswordIcon(button, isHidden) {
    const icon = button.querySelector('i');
    if (!icon) return;

    icon.classList.remove('bi-eye', 'bi-eye-slash');
    icon.classList.add(isHidden ? 'bi-eye' : 'bi-eye-slash');
}

/**
 * Shared confirmation modal workflow for async actions.
 */
function setupActionConfirmation(options = {}) {
    const modalId = options.modalId || 'confirmActionModal';
    const modalElement = document.getElementById(modalId);
    if (!modalElement) {
        return {
            modal: null,
            open: function() {},
            close: function() {},
            setLoading: function() {}
        };
    }

    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
    const confirmButton = modalElement.querySelector(options.confirmButtonSelector || '[data-confirm-continue]');
    const messageElement = modalElement.querySelector(options.messageSelector || '[data-confirm-message]');
    const cancelButtons = modalElement.querySelectorAll(options.cancelButtonSelector || '[data-confirm-cancel], [data-bs-dismiss="modal"]');
    let pendingHandler = null;

    const getOriginalLabel = function() {
        if (!confirmButton) {
            return 'Continue';
        }

        if (confirmButton.dataset.defaultLabel) {
            return confirmButton.dataset.defaultLabel;
        }

        const label = confirmButton.textContent.trim();
        confirmButton.dataset.defaultLabel = label || 'Continue';
        return confirmButton.dataset.defaultLabel;
    };

    const closeModalSafely = function() {
        if (!modalElement) {
            return;
        }

        const instance = bootstrap.Modal.getOrCreateInstance(modalElement);
        instance.hide();

        document.body.classList.remove('modal-open');
        document.querySelectorAll('.modal-backdrop').forEach(function(backdrop) {
            backdrop.remove();
        });
    };

    const setLoading = function(isLoading) {
        if (!confirmButton) return;

        const defaultLabel = getOriginalLabel();
        confirmButton.dataset.defaultLabel = defaultLabel;
        confirmButton.disabled = isLoading;
        confirmButton.dataset.processing = isLoading ? 'true' : 'false';
        confirmButton.innerHTML = isLoading
            ? '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Processing...'
            : defaultLabel;
    };

    const resetState = function() {
        setLoading(false);
        pendingHandler = null;
    };

    cancelButtons.forEach(function(cancelButton) {
        cancelButton.addEventListener('click', function() {
            if (confirmButton) {
                confirmButton.dataset.processing = 'false';
            }
            closeModalSafely();
            resetState();
        });
    });

    modalElement.addEventListener('hidden.bs.modal', function() {
        document.body.classList.remove('modal-open');
        document.querySelectorAll('.modal-backdrop').forEach(function(backdrop) {
            backdrop.remove();
        });
        resetState();
    });

    if (confirmButton) {
        confirmButton.addEventListener('click', async function(event) {
            event.preventDefault();

            if (confirmButton.dataset.processing === 'true' || !pendingHandler) {
                return;
            }

            setLoading(true);
            try {
                const result = await pendingHandler();
                if (result === false) {
                    setLoading(false);
                    return;
                }

                closeModalSafely();
            } catch (error) {
                console.error('Confirmation action failed:', error);
                setLoading(false);
            } finally {
                if (!modalElement.classList.contains('show')) {
                    resetState();
                }
            }
        });
    }

    return {
        modal: modal,
        open: function(message, handler) {
            if (messageElement) {
                messageElement.textContent = message || 'Confirm this action?';
            }
            pendingHandler = typeof handler === 'function' ? handler : null;
            if (!pendingHandler) {
                setLoading(false);
                return;
            }
            setLoading(false);
            modal.show();
        },
        close: function() {
            closeModalSafely();
            resetState();
        },
        setLoading: setLoading
    };
}

/**
 * Show toast notification
 */
function showToast(message, type = 'info', duration = 5000) {
    const toastContainer = document.getElementById('toast-container');
    if (!toastContainer) return;

    const toast = document.createElement('div');
    toast.className = `alert alert-${type} alert-dismissible fade show`;
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;

    toastContainer.appendChild(toast);

    if (duration > 0) {
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(toast);
            bsAlert.close();
        }, duration);
    }
}

/**
 * Sanitize user input (basic)
 */
function sanitizeInput(input) {
    const div = document.createElement('div');
    div.textContent = input;
    return div.innerHTML;
}

/**
 * Validate email format
 */
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

/**
 * Validate password strength
 */
function validatePasswordStrength(password) {
    const strength = {
        score: 0,
        feedback: []
    };

    if (password.length >= 8) {
        strength.score += 1;
    } else {
        strength.feedback.push('At least 8 characters');
    }

    if (/[a-z]/.test(password)) {
        strength.score += 1;
    } else {
        strength.feedback.push('Lowercase letters');
    }

    if (/[A-Z]/.test(password)) {
        strength.score += 1;
    } else {
        strength.feedback.push('Uppercase letters');
    }

    if (/[0-9]/.test(password)) {
        strength.score += 1;
    } else {
        strength.feedback.push('Numbers');
    }

    if (/[!@#$%^&*]/.test(password)) {
        strength.score += 1;
    } else {
        strength.feedback.push('Special characters (!@#$%^&*)');
    }

    return strength;
}

/**
 * Format phone number
 */
function formatPhoneNumber(phone) {
    const cleaned = phone.replace(/\D/g, '');

    if (cleaned.length === 10) {
        return cleaned.replace(/(\d{3})(\d{3})(\d{4})/, '($1) $2-$3');
    } else if (cleaned.length === 11) {
        return cleaned.replace(/(\d{2})(\d{3})(\d{3})(\d{3})/, '+$1 ($2) $3-$4');
    }

    return phone;
}

/**
 * Copy text to clipboard
 */
function copyToClipboard(text, feedbackElement = null) {
    navigator.clipboard.writeText(text).then(() => {
        if (feedbackElement) {
            feedbackElement.textContent = 'Copied!';
            setTimeout(() => {
                feedbackElement.textContent = 'Copy';
            }, 2000);
        }
    }).catch(err => {
        console.error('Failed to copy:', err);
    });
}

/**
 * Debounce function for search/filter operations
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Throttle function for scroll/resize operations
 */
function throttle(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            timeout = null;
            func(...args);
        };
        if (!timeout) {
            timeout = setTimeout(later, wait);
        }
    };
}

/**
 * Check if element is in viewport
 */
function isElementInViewport(el) {
    const rect = el.getBoundingClientRect();
    return (
        rect.top >= 0 &&
        rect.left >= 0 &&
        rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
        rect.right <= (window.innerWidth || document.documentElement.clientWidth)
    );
}

/**
 * Fade in animation for elements
 */
function fadeIn(element, duration = 300) {
    element.style.opacity = '0';
    element.style.display = 'block';

    setTimeout(() => {
        element.style.transition = `opacity ${duration}ms ease-in`;
        element.style.opacity = '1';
    }, 0);
}

/**
 * Fade out animation for elements
 */
function fadeOut(element, duration = 300) {
    element.style.transition = `opacity ${duration}ms ease-out`;
    element.style.opacity = '0';

    setTimeout(() => {
        element.style.display = 'none';
    }, duration);
}

/**
 * Format currency
 */
function formatCurrency(amount, currency = 'PHP') {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: currency
    }).format(amount);
}

/**
 * Parse URL parameters
 */
function getUrlParameter(name) {
    name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
    const regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
    const results = regex.exec(location.search);
    return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
}

/**
 * Add loading state to button
 */
function setButtonLoading(button, isLoading = true) {
    if (isLoading) {
        button.disabled = true;
        button.setAttribute('data-original-html', button.innerHTML);
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Loading...';
    } else {
        button.disabled = false;
        button.innerHTML = button.getAttribute('data-original-html');
    }
}

/**
 * Log message in development mode
 */
function devLog(message, data = null) {
    if (typeof APP_ENV !== 'undefined' && APP_ENV === 'development') {
        console.log(`[EcoPick] ${message}`, data || '');
    }
}

// Export functions for use in other scripts
if (typeof window !== 'undefined') {
    window.ecopick = {
        setupPasswordToggle,
        showToast,
        sanitizeInput,
        isValidEmail,
        validatePasswordStrength,
        formatPhoneNumber,
        copyToClipboard,
        debounce,
        throttle,
        isElementInViewport,
        fadeIn,
        fadeOut,
        formatCurrency,
        getUrlParameter,
        setButtonLoading,
        setupActionConfirmation,
        devLog
    };
}
