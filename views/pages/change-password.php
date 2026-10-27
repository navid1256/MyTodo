<?php

declare(strict_types=1);

use App\Localization\Translator;

/** @var string $csrfToken */
/** @var Translator $translator */
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

    <a class="backToProfileLink" href="/profile">
        <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
        <span data-i18n="password.back_to_profile"><?= htmlspecialchars($translator->translate('password.back_to_profile'), ENT_QUOTES, 'UTF-8') ?></span>
    </a>

    <header class="changePasswordHeader">
        <h1 id="changePasswordTitle" data-i18n="password.title"><?= htmlspecialchars($translator->translate('password.title'), ENT_QUOTES, 'UTF-8') ?></h1>
        <p data-i18n="password.description"><?= htmlspecialchars($translator->translate('password.description'), ENT_QUOTES, 'UTF-8') ?></p>
    </header>

    <form class="changePasswordForm" id="changePasswordForm" action="/auth/change-password" method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

        <div class="changePasswordField">
            <label for="currentPassword" data-i18n="password.current"><?= htmlspecialchars($translator->translate('password.current'), ENT_QUOTES, 'UTF-8') ?></label>
            <div class="changePasswordInput">
                <input
                    id="currentPassword"
                    name="current_password"
                    type="password"
                    required
                    autocomplete="current-password"
                    data-i18n-placeholder="password.current_placeholder"
                    placeholder="<?= htmlspecialchars($translator->translate('password.current_placeholder'), ENT_QUOTES, 'UTF-8') ?>">
                <button
                    class="changePasswordToggle"
                    type="button"
                    data-password-target="currentPassword"
                    data-show-label-key="password.show_current"
                    data-hide-label-key="password.hide_current"
                    data-i18n-aria-label="password.show_current"
                    aria-label="<?= htmlspecialchars($translator->translate('password.show_current'), ENT_QUOTES, 'UTF-8') ?>"
                    aria-pressed="false">
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
            <label for="newPassword" data-i18n="password.new"><?= htmlspecialchars($translator->translate('password.new'), ENT_QUOTES, 'UTF-8') ?></label>
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
                    data-i18n-placeholder="password.new_placeholder"
                    placeholder="<?= htmlspecialchars($translator->translate('password.new_placeholder'), ENT_QUOTES, 'UTF-8') ?>">
                <button
                    class="changePasswordToggle"
                    type="button"
                    data-password-target="newPassword"
                    data-show-label-key="password.show_new"
                    data-hide-label-key="password.hide_new"
                    data-i18n-aria-label="password.show_new"
                    aria-label="<?= htmlspecialchars($translator->translate('password.show_new'), ENT_QUOTES, 'UTF-8') ?>"
                    aria-pressed="false">
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
            <label for="confirmNewPassword" data-i18n="password.confirmation"><?= htmlspecialchars($translator->translate('password.confirmation'), ENT_QUOTES, 'UTF-8') ?></label>
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
                    data-i18n-placeholder="password.confirmation_placeholder"
                    placeholder="<?= htmlspecialchars($translator->translate('password.confirmation_placeholder'), ENT_QUOTES, 'UTF-8') ?>">
                <button
                    class="changePasswordToggle"
                    type="button"
                    data-password-target="confirmNewPassword"
                    data-show-label-key="password.show_confirmation"
                    data-hide-label-key="password.hide_confirmation"
                    data-i18n-aria-label="password.show_confirmation"
                    aria-label="<?= htmlspecialchars($translator->translate('password.show_confirmation'), ENT_QUOTES, 'UTF-8') ?>"
                    aria-pressed="false">
                    <svg class="changePasswordIcon changePasswordIconClosed" aria-hidden="true">
                        <use href="#change-password-eye-closed"></use>
                    </svg>
                    <svg class="changePasswordIcon changePasswordIconOpen" aria-hidden="true">
                        <use href="#change-password-eye-open"></use>
                    </svg>
                </button>
            </div>
        </div>

        <div class="passwordRequirements" id="passwordRequirements" data-i18n-aria-label="password.requirements" aria-label="<?= htmlspecialchars($translator->translate('password.requirements'), ENT_QUOTES, 'UTF-8') ?>">
            <label class="passwordRequirement" data-password-requirement="length">
                <input type="checkbox" disabled>
                <span data-i18n="password.requirement.length"><?= htmlspecialchars($translator->translate('password.requirement.length'), ENT_QUOTES, 'UTF-8') ?></span>
            </label>
            <label class="passwordRequirement" data-password-requirement="number">
                <input type="checkbox" disabled>
                <span data-i18n="password.requirement.number"><?= htmlspecialchars($translator->translate('password.requirement.number'), ENT_QUOTES, 'UTF-8') ?></span>
            </label>
            <label class="passwordRequirement" data-password-requirement="special">
                <input type="checkbox" disabled>
                <span data-i18n="password.requirement.special"><?= htmlspecialchars($translator->translate('password.requirement.special'), ENT_QUOTES, 'UTF-8') ?></span>
            </label>
        </div>

        <output class="passwordConfirmationMessage" id="passwordConfirmationMessage" aria-live="polite"></output>
        <output class="changePasswordMessage" id="changePasswordMessage" aria-live="polite"></output>

        <div class="changePasswordActions">
            <button class="confirmPasswordButton" id="confirmPasswordButton" type="submit" data-i18n="password.confirm"><?= htmlspecialchars($translator->translate('password.confirm'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
    </form>
</section>
