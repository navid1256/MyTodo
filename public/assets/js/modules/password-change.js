import { changePassword } from '../services/account-service.js';
import { translate } from '../utils/i18n.js';

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
            const labelKey = shouldShowPassword
                ? toggleButton.dataset.hideLabelKey
                : toggleButton.dataset.showLabelKey;
            passwordInput.type = shouldShowPassword ? 'text' : 'password';
            toggleButton.setAttribute('aria-pressed', String(shouldShowPassword));
            toggleButton.setAttribute(
                'aria-label',
                translate(
                    labelKey,
                    {},
                    shouldShowPassword ? 'Hide password' : 'Show password'
                )
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
            ? translate('password.validation.confirmation_mismatch')
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
            setFormMessage(translate('password.validation.current_required_client'), 'error');
            currentPassword.focus();
            return;
        }

        if (!passwordIsValid) {
            setFormMessage(translate('password.validation.requirements_unmet'), 'error');
            newPassword.focus();
            return;
        }

        if (!confirmationIsValid) {
            setFormMessage(translate('password.validation.confirm_correctly'), 'error');
            confirmation.focus();
            return;
        }

        submitButton.disabled = true;
        submitButton.textContent = translate('password.changing');
        setFormMessage('', '');

        try {
            const result = await changePassword(
                new FormData(form),
                translate('password.change_failed')
            );

            form.reset();
            updatePasswordRequirements();
            updateConfirmationState();
            setFormMessage(result.message, 'success');
            currentPassword.focus();
        } catch (error) {
            const message = error.message || translate('password.change_failed');

            setFormMessage(message, 'error');

            if (error.code === 'password.validation.current_incorrect') {
                currentPassword.classList.add('is-invalid');
                currentPassword.focus();
            }
        } finally {
            submitButton.disabled = false;
            submitButton.textContent = translate('password.confirm');
        }
    });

    updatePasswordRequirements();
}
