<?php

declare(strict_types=1);

/** @var array{language?: string, calendar_system?: string, timezone?: string, is_persisted?: bool} $accountSettings */
/** @var array<int, string> $timezoneOptions */
/** @var string $csrfToken */

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
        <a class="dashboardBackButton" href="/" data-dashboard-back aria-label="Back to previous page">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            <span>Back</span>
        </a>
        <i class="accountSettingsIcon fa-solid fa-user-gear" aria-hidden="true"></i>
        <h1>Account Settings</h1>
    </header>

    <form
        class="accountSettingsFields"
        id="accountSettingsForm"
        method="post"
        action="/api/settings"
        data-settings-persisted="<?= $settingsArePersisted ? '1' : '0' ?>"
        aria-label="Account settings">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

        <div class="accountSettingsField">
            <label for="dateTimeSetting">Calendar System :</label>
            <select id="dateTimeSetting" name="calendar_system" data-account-setting="calendar-system">
                <option value="gregorian"<?= $isSelected('gregorian', $selectedCalendar) ?>>Gregorian</option>
                <option value="jalali"<?= $isSelected('jalali', $selectedCalendar) ?>>Jalali</option>
            </select>
        </div>

        <div class="accountSettingsField">
            <label for="languageSetting">Language :</label>
            <select id="languageSetting" name="language" data-account-setting="language">
                <option value="default"<?= $isSelected('default', $selectedLanguage) ?>>Default</option>
                <option value="english"<?= $isSelected('english', $selectedLanguage) ?>>English</option>
                <option value="persian"<?= $isSelected('persian', $selectedLanguage) ?>>Persian</option>
            </select>
        </div>

        <div class="accountSettingsField">
            <label for="timezoneSetting">Time Zone :</label>
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

        <p class="accountSettingsHint">Your browser time zone is selected automatically the first time.</p>
        <p class="accountSettingsStatus" id="accountSettingsStatus" role="status" aria-live="polite" hidden></p>
    </form>
</div>
