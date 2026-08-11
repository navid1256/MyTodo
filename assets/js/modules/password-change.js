import { changePassword } from '../services/account-service.js';

const PASSWORD_CHECK_DELAY = 100;

function getPasswordChecks(password) {
    return {
        length: Array.from(password).length >= 8,
        number: /\p{N}/u.test(password),
        special: /[^\p{L}\p{N}\s]/u.test(password)
    };
}

export function initPasswordChange() {
    const form = document.getElementById('changePasswordForm');

    if (!form) {
        return;
    }

    const currentPassword = document.getElementById('currentPassword');
    const newPassword = document.getElementById('newPassword');
    const confirmation = document.getElementById('confirmNewPassword');
    const confirmationMessage = document.getElementById('passwordConfirmationMessage');
    const formMessage = document.getElementById('changePasswordMessage');
    const submitButton = document.getElementById('confirmPasswordButton');
    const requirementItems = Array.from(document.querySelectorAll('[data-password-requirement]'));
    const passwordToggles = Array.from(form.querySelectorAll('.changePasswordToggle'));
    let checkTimer = null;

    passwordToggles.forEach(function (toggleButton) {
        toggleButton.addEventListener('click', function () {
            const passwordInput = document.getElementById(toggleButton.dataset.passwordTarget);

            if (!passwordInput) {
                return;
            }

            const shouldShowPassword = passwordInput.type === 'password';
            passwordInput.type = shouldShowPassword ? 'text' : 'password';
            toggleButton.setAttribute('aria-pressed', String(shouldShowPassword));
            toggleButton.setAttribute(
                'aria-label',
                shouldShowPassword ? 'Hide password' : 'Show password'
            );
        });
    });

    function setFormMessage(message, type) {
        formMessage.textContent = message;
        formMessage.classList.toggle('is-error', type === 'error');
        formMessage.classList.toggle('is-success', type === 'success');
    }

    function updatePasswordRequirements() {
        const checks = getPasswordChecks(newPassword.value);

        requirementItems.forEach(function (item) {
            const requirement = item.dataset.passwordRequirement;
            const isMet = Boolean(checks[requirement]);
            const checkbox = item.querySelector('input[type="checkbox"]');

            item.classList.toggle('is-met', isMet);

            if (checkbox) {
                checkbox.checked = isMet;
            }
        });

        return Object.values(checks).every(Boolean);
    }

    function updateConfirmationState() {
        const hasConfirmation = confirmation.value !== '';
        const passwordsMatch = hasConfirmation && confirmation.value === newPassword.value;

        confirmation.classList.toggle('is-invalid', hasConfirmation && !passwordsMatch);
        confirmationMessage.textContent = hasConfirmation && !passwordsMatch
            ? 'New password confirmation does not match.'
            : '';

        return passwordsMatch;
    }

    function schedulePasswordCheck() {
        if (checkTimer !== null) {
            window.clearTimeout(checkTimer);
        }

        checkTimer = window.setTimeout(function () {
            checkTimer = null;
            updatePasswordRequirements();
            updateConfirmationState();
        }, PASSWORD_CHECK_DELAY);
    }

    newPassword.addEventListener('input', function () {
        setFormMessage('', '');
        schedulePasswordCheck();
    });

    newPassword.addEventListener('blur', function () {
        if (checkTimer !== null) {
            window.clearTimeout(checkTimer);
            checkTimer = null;
        }

        updatePasswordRequirements();
        updateConfirmationState();
    });

    confirmation.addEventListener('input', function () {
        setFormMessage('', '');
        updateConfirmationState();
    });

    currentPassword.addEventListener('input', function () {
        currentPassword.classList.remove('is-invalid');
        setFormMessage('', '');
    });

    form.addEventListener('submit', async function (event) {
        event.preventDefault();

        if (checkTimer !== null) {
            window.clearTimeout(checkTimer);
            checkTimer = null;
        }

        const passwordIsValid = updatePasswordRequirements();
        const confirmationIsValid = updateConfirmationState();
        currentPassword.classList.toggle('is-invalid', currentPassword.value === '');

        if (currentPassword.value === '') {
            setFormMessage('Enter your current password.', 'error');
            currentPassword.focus();
            return;
        }

        if (!passwordIsValid) {
            setFormMessage('Your new password must meet all password requirements.', 'error');
            newPassword.focus();
            return;
        }

        if (!confirmationIsValid) {
            setFormMessage('Confirm your new password correctly.', 'error');
            confirmation.focus();
            return;
        }

        submitButton.disabled = true;
        submitButton.textContent = 'Changing...';
        setFormMessage('', '');

        try {
            const result = await changePassword(new FormData(form));

            form.reset();
            updatePasswordRequirements();
            updateConfirmationState();
            setFormMessage(result.message, 'success');
            currentPassword.focus();
        } catch (error) {
            const message = error.message || 'The password could not be changed.';

            setFormMessage(message, 'error');

            if (message === 'Current password is incorrect.') {
                currentPassword.classList.add('is-invalid');
                currentPassword.focus();
            }
        } finally {
            submitButton.disabled = false;
            submitButton.textContent = 'Confirm';
        }
    });

    updatePasswordRequirements();
}
