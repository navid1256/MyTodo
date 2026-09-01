<?php

declare(strict_types=1);

use App\Localization\Translator;

/** @var array{language?: string, calendar_system?: string, timezone?: string, is_persisted?: bool} $accountSettings */
/** @var array<int, string> $timezoneOptions */
/** @var string $csrfToken */
/** @var Translator $translator */

$settings = isset($accountSettings) && is_array($accountSettings) ? $accountSettings : [];
$selectedLanguage = (string) ($settings['language'] ?? 'default');
$selectedCalendar = (string) ($settings['calendar_system'] ?? 'gregorian');
$selectedTimezone = (string) ($settings['timezone'] ?? 'UTC');
$settingsArePersisted = (bool) ($settings['is_persisted'] ?? false);
$availableTimezones = isset($timezoneOptions) && is_array($timezoneOptions) ? $timezoneOptions : ['UTC'];

$isSelected = static fn(string $value, string $selected): string => $value === $selected ? ' selected' : '';
?>

<div class="accountSettingsPage">
    <header class="accountSettingsHeader dashboardSectionHeader">
        <a
            class="dashboardBackButton"
            href="/"
            data-dashboard-back
            data-i18n-aria-label="common.back_to_previous"
            aria-label="<?= htmlspecialchars($translator->translate('common.back_to_previous'), ENT_QUOTES, 'UTF-8') ?>">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            <span data-i18n="common.back"><?= htmlspecialchars($translator->translate('common.back'), ENT_QUOTES, 'UTF-8') ?></span>
        </a>
        <i class="accountSettingsIcon fa-solid fa-user-gear" aria-hidden="true"></i>
        <h1 data-i18n="settings.title"><?= htmlspecialchars($translator->translate('settings.title'), ENT_QUOTES, 'UTF-8') ?></h1>
    </header>

    <form
        class="accountSettingsForm"
        id="accountSettingsForm"
        method="post"
        action="/api/settings"
        data-settings-persisted="<?= $settingsArePersisted ? '1' : '0' ?>"
        data-save-label="<?= htmlspecialchars($translator->translate('common.save'), ENT_QUOTES, 'UTF-8') ?>"
        data-saving-message="<?= htmlspecialchars($translator->translate('common.saving'), ENT_QUOTES, 'UTF-8') ?>"
        data-cache-unavailable-message="<?= htmlspecialchars($translator->translate('settings.cache_unavailable'), ENT_QUOTES, 'UTF-8') ?>"
        data-save-failed-message="<?= htmlspecialchars($translator->translate('settings.save_failed'), ENT_QUOTES, 'UTF-8') ?>"
        data-i18n-aria-label="settings.form_label"
        aria-label="<?= htmlspecialchars($translator->translate('settings.form_label'), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

        <div class="accountSettingsFields">
            <div class="accountSettingsField">
                <label for="dateTimeSetting" data-i18n="settings.calendar_system"><?= htmlspecialchars($translator->translate('settings.calendar_system'), ENT_QUOTES, 'UTF-8') ?></label>
                <select id="dateTimeSetting" name="calendar_system" data-account-setting="calendar-system">
                    <option value="gregorian" data-i18n="settings.calendar.gregorian"<?= $isSelected('gregorian', $selectedCalendar) ?>><?= htmlspecialchars($translator->translate('settings.calendar.gregorian'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="jalali" data-i18n="settings.calendar.jalali"<?= $isSelected('jalali', $selectedCalendar) ?>><?= htmlspecialchars($translator->translate('settings.calendar.jalali'), ENT_QUOTES, 'UTF-8') ?></option>
                </select>
            </div>

            <div class="accountSettingsField">
                <label for="languageSetting" data-i18n="settings.language"><?= htmlspecialchars($translator->translate('settings.language'), ENT_QUOTES, 'UTF-8') ?></label>
                <select id="languageSetting" name="language" data-account-setting="language">
                    <option value="default" data-i18n="settings.language.default"<?= $isSelected('default', $selectedLanguage) ?>><?= htmlspecialchars($translator->translate('settings.language.default'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="english" data-i18n="settings.language.english"<?= $isSelected('english', $selectedLanguage) ?>><?= htmlspecialchars($translator->translate('settings.language.english'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="persian" data-i18n="settings.language.persian"<?= $isSelected('persian', $selectedLanguage) ?>><?= htmlspecialchars($translator->translate('settings.language.persian'), ENT_QUOTES, 'UTF-8') ?></option>
                </select>
            </div>

            <div class="accountSettingsField">
                <label for="timezoneSetting" data-i18n="settings.timezone"><?= htmlspecialchars($translator->translate('settings.timezone'), ENT_QUOTES, 'UTF-8') ?></label>
                <select id="timezoneSetting" name="timezone" data-account-setting="timezone">
                    <?php foreach ($availableTimezones as $timezone): ?>
                        <option
                            value="<?= htmlspecialchars($timezone, ENT_QUOTES, 'UTF-8') ?>"
                            <?= $isSelected($timezone, $selectedTimezone) ?>>
                            <?= htmlspecialchars($timezone, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <p class="accountSettingsHint" data-i18n="settings.timezone_hint"><?= htmlspecialchars($translator->translate('settings.timezone_hint'), ENT_QUOTES, 'UTF-8') ?></p>
            <p class="accountSettingsStatus" id="accountSettingsStatus" role="status" aria-live="polite" hidden></p>
        </div>

        <div class="accountSettingsActions">
            <button
                class="accountSettingsSaveButton"
                id="accountSettingsSaveButton"
                type="submit"
                data-i18n="common.save"
                aria-describedby="accountSettingsStatus">
                <?= htmlspecialchars($translator->translate('common.save'), ENT_QUOTES, 'UTF-8') ?>
            </button>
        </div>
    </form>
</div>
