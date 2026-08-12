<?php

/** @var string $csrfToken */
?>

<section class="changePasswordPage" aria-labelledby="changePasswordTitle">
    <svg class="passwordIconDefinitions" aria-hidden="true" focusable="false">
        <symbol id="change-password-eye-closed" viewBox="0 0 24 24">
            <path d="M3.5 9.25c1.75 3.35 4.58 5.05 8.5 5.05s6.75-1.7 8.5-5.05" />
            <path d="M5.5 12.05 4.1 13.5M8.55 13.75l-.65 1.9M12 14.3v2M15.45 13.75l.65 1.9M18.5 12.05l1.4 1.45" />
        </symbol>
        <symbol id="change-password-eye-open" viewBox="0 0 24 24">
            <path d="M2.75 12s3.35-5 9.25-5 9.25 5 9.25 5-3.35 5-9.25 5-9.25-5-9.25-5Z" />
            <circle cx="12" cy="12" r="2.35" />
        </symbol>
    </svg>

    <a class="backToProfileLink" href="?view=profile">
        <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
        <span>Back to My Profile</span>
    </a>

    <header class="changePasswordHeader">
        <h1 id="changePasswordTitle">Change Password</h1>
        <p>Enter your current password and choose a secure new password.</p>
    </header>

    <form class="changePasswordForm" id="changePasswordForm" method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

        <div class="changePasswordField">
            <label for="currentPassword">Current Password :</label>
            <div class="changePasswordInput">
                <input
                    id="currentPassword"
                    name="current_password"
                    type="password"
                    required
                    autocomplete="current-password"
                    placeholder="Enter your current password">
                <button class="changePasswordToggle" type="button" data-password-target="currentPassword" aria-label="Show current password" aria-pressed="false">
                    <svg class="changePasswordIcon changePasswordIconClosed" aria-hidden="true">
                        <use href="#change-password-eye-closed"></use>
                    </svg>
                    <svg class="changePasswordIcon changePasswordIconOpen" aria-hidden="true">
                        <use href="#change-password-eye-open"></use>
                    </svg>
                </button>
            </div>
        </div>

        <div class="changePasswordField">
            <label for="newPassword">New Password :</label>
            <div class="changePasswordInput">
                <input
                    id="newPassword"
                    name="new_password"
                    type="password"
                    required
                    minlength="8"
                    maxlength="72"
                    autocomplete="new-password"
                    aria-describedby="passwordRequirements"
                    placeholder="Enter your new password">
                <button class="changePasswordToggle" type="button" data-password-target="newPassword" aria-label="Show new password" aria-pressed="false">
                    <svg class="changePasswordIcon changePasswordIconClosed" aria-hidden="true">
                        <use href="#change-password-eye-closed"></use>
                    </svg>
                    <svg class="changePasswordIcon changePasswordIconOpen" aria-hidden="true">
                        <use href="#change-password-eye-open"></use>
                    </svg>
                </button>
            </div>
        </div>

        <div class="changePasswordField">
            <label for="confirmNewPassword">Confirm New Password :</label>
            <div class="changePasswordInput">
                <input
                    id="confirmNewPassword"
                    name="new_password_confirmation"
                    type="password"
                    required
                    minlength="8"
                    maxlength="72"
                    autocomplete="new-password"
                    aria-describedby="passwordConfirmationMessage"
                    placeholder="Confirm your new password">
                <button class="changePasswordToggle" type="button" data-password-target="confirmNewPassword" aria-label="Show password confirmation" aria-pressed="false">
                    <svg class="changePasswordIcon changePasswordIconClosed" aria-hidden="true">
                        <use href="#change-password-eye-closed"></use>
                    </svg>
                    <svg class="changePasswordIcon changePasswordIconOpen" aria-hidden="true">
                        <use href="#change-password-eye-open"></use>
                    </svg>
                </button>
            </div>
        </div>

        <div class="passwordRequirements" id="passwordRequirements" aria-label="Password requirements">
            <label class="passwordRequirement" data-password-requirement="length">
                <input type="checkbox" disabled>
                <span>At least 8 characters</span>
            </label>
            <label class="passwordRequirement" data-password-requirement="number">
                <input type="checkbox" disabled>
                <span>Include numbers</span>
            </label>
            <label class="passwordRequirement" data-password-requirement="special">
                <input type="checkbox" disabled>
                <span>Include a special character</span>
            </label>
        </div>

        <p class="passwordConfirmationMessage" id="passwordConfirmationMessage" aria-live="polite"></p>
        <p class="changePasswordMessage" id="changePasswordMessage" role="status" aria-live="polite"></p>

        <div class="changePasswordActions">
            <button class="confirmPasswordButton" id="confirmPasswordButton" type="submit">Confirm</button>
        </div>
    </form>
</section>