(function () {
    if (window.EcoPickPasswordToggleInitialized) {
        return;
    }

    window.EcoPickPasswordToggleInitialized = true;

    function syncToggleButtonState(button, isVisible) {
        const icon = button.querySelector('i');
        if (icon) {
            icon.classList.toggle('bi-eye', !isVisible);
            icon.classList.toggle('bi-eye-slash', isVisible);
        }

        button.setAttribute('aria-label', isVisible ? 'Hide password' : 'Show password');
        button.setAttribute('aria-pressed', String(isVisible));
    }

    function bindPasswordToggle(button) {
        if (!button || button.dataset.ecopickBound === 'true') {
            return;
        }

        const targetId = button.getAttribute('data-target');
        const input = targetId ? document.getElementById(targetId) : null;

        if (!input) {
            return;
        }

        button.dataset.ecopickBound = 'true';
        button.type = 'button';
        syncToggleButtonState(button, input.type === 'text');

        button.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();

            const currentlyVisible = input.type === 'text';
            input.type = currentlyVisible ? 'password' : 'text';
            input.setAttribute('autocomplete', currentlyVisible ? 'current-password' : 'new-password');
            syncToggleButtonState(button, !currentlyVisible);

            input.focus();
            const end = input.value.length;
            if (typeof input.setSelectionRange === 'function') {
                input.setSelectionRange(end, end);
            }
        });
    }

    function initializePasswordToggles() {
        document.querySelectorAll('.password-toggle').forEach(bindPasswordToggle);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializePasswordToggles);
        return;
    }

    initializePasswordToggles();
})();
