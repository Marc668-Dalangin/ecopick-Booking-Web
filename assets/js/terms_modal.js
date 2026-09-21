(function() {
    'use strict';

    function initializeTermsModal() {
        const modalElement = document.getElementById('termsModal');
        const termsContent = document.getElementById('termsContent');
        const mainCheckbox = document.getElementById('main_terms_checkbox');
        const modalCheckOne = document.getElementById('modal_check_1');
        const modalCheckTwo = document.getElementById('modal_check_2');
        const confirmButton = document.getElementById('confirm_terms_btn');

        if (!modalElement || !termsContent || !mainCheckbox || !modalCheckOne || !modalCheckTwo || !confirmButton || !window.bootstrap) {
            return;
        }

        const termsModal = bootstrap.Modal.getOrCreateInstance(modalElement);
        let accepted = false;

        function updateConfirmState() {
            confirmButton.disabled = !(modalCheckOne.checked && modalCheckTwo.checked);
        }

        function resetModalState() {
            accepted = false;
            mainCheckbox.checked = false;
            modalCheckOne.checked = false;
            modalCheckTwo.checked = false;
            modalCheckOne.disabled = true;
            modalCheckTwo.disabled = true;
            confirmButton.disabled = true;
            termsContent.scrollTop = 0;
        }

        function openTermsModal(event) {
            event.preventDefault();
            resetModalState();
            termsModal.show();
        }

        mainCheckbox.addEventListener('click', openTermsModal);
        document.querySelector('[data-bs-target="#termsModal"]')?.addEventListener('click', openTermsModal);
        termsContent.addEventListener('scroll', function() {
            const reachedBottom = termsContent.scrollTop + termsContent.clientHeight >= termsContent.scrollHeight - 20;
            if (reachedBottom) {
                modalCheckOne.disabled = false;
                modalCheckTwo.disabled = false;
            }
        });
        [modalCheckOne, modalCheckTwo].forEach((checkbox) => {
            checkbox.addEventListener('change', updateConfirmState);
        });
        confirmButton.addEventListener('click', function() {
            if (confirmButton.disabled) return;
            accepted = true;
            mainCheckbox.checked = true;
            termsModal.hide();
        });
        modalElement.addEventListener('hidden.bs.modal', function() {
            if (!accepted) {
                resetModalState();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeTermsModal);
    } else {
        initializeTermsModal();
    }
})();