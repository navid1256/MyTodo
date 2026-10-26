<?php

declare(strict_types=1);

use App\Localization\Translator;

/** @var string $csrfToken */
/** @var array<int, string> $profileErrors */
/** @var array<string, string> $profileFields */
/** @var string|null $profileSuccess */
/** @var string $avatarUrl */
/** @var string $currentDisplayName */
/** @var string $calendarCssClass */
/** @var int $userId */
/** @var Translator $translator */


$profileErrors = isset($profileErrors) && is_array($profileErrors) ? $profileErrors : [];
$profileSuccess = isset($profileSuccess) && is_string($profileSuccess) ? $profileSuccess : null;
$avatarUrl = isset($avatarUrl) && is_string($avatarUrl) && $avatarUrl !== ''
    ? $avatarUrl
    : '/assets/img/user-default-avatar.webp';
$currentDisplayName = isset($currentDisplayName) && is_string($currentDisplayName)
    ? $currentDisplayName
    : 'User';
$userId = isset($userId) ? (int) $userId : 0;
$csrfToken = isset($csrfToken) && is_string($csrfToken) ? $csrfToken : '';
$calendarCssClass = isset($calendarCssClass) && is_string($calendarCssClass)
    ? $calendarCssClass
    : 'gregorianCalendar';
$profileFields = isset($profileFields) && is_array($profileFields) ? $profileFields : [
    'firstname' => '',
    'lastname' => '',
    'email' => '',
    'username' => '',
    'job_title' => '',
    'date_of_birth' => '',
    'gender' => '',
    'country' => '',
];
?>
<section class="profilePage" aria-labelledby="profilePageTitle">
    <header class="profilePageHeader dashboardSectionHeader">
        <a
            class="dashboardBackButton"
            href="/"
            data-dashboard-back
            data-i18n-aria-label="common.back_to_previous"
            aria-label="<?= htmlspecialchars($translator->translate('common.back_to_previous'), ENT_QUOTES, 'UTF-8') ?>">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            <span data-i18n="common.back"><?= htmlspecialchars($translator->translate('common.back'), ENT_QUOTES, 'UTF-8') ?></span>
        </a>
        <i class="profilePageHeaderIcon fa-regular fa-user" aria-hidden="true"></i>
        <h1 id="profilePageTitle" data-i18n="profile.title"><?= htmlspecialchars($translator->translate('profile.title'), ENT_QUOTES, 'UTF-8') ?></h1>
    </header>
    <?php if ($profileSuccess): ?>
        <output class="profileMessage profileMessageSuccess" aria-live="polite">
            <?= htmlspecialchars($profileSuccess, ENT_QUOTES, 'UTF-8') ?>
        </output>
    <?php endif; ?>
    <?php if (!empty($profileErrors)): ?>
        <div class="profileMessage profileMessageError" role="alert">
            <?php foreach ($profileErrors as $profileError): ?>
                <p><?= htmlspecialchars((string) $profileError, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form class="profileOverview" action="/profile" method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="profile_action" value="save">
        <input id="avatarAction" type="hidden" name="avatar_action" value="unchanged">
        <input id="avatarChoice" type="hidden" name="avatar_choice" value="">
        <input id="avatarData" type="hidden" name="avatar_data" value="">
        <div class="profilePicture">
            <button
                class="profilePictureButton"
                id="openAvatarPicker"
                type="button"
                data-avatar-seed-base="user-<?= $userId ?>"
                aria-haspopup="dialog">
                <img data-user-avatar src="<?= htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($currentDisplayName, ENT_QUOTES, 'UTF-8') ?>">
                <span class="profilePictureOverlay" data-i18n="profile.change_picture"><?= htmlspecialchars($translator->translate('profile.change_picture'), ENT_QUOTES, 'UTF-8') ?></span>
            </button>
            <span data-i18n="profile.picture"><?= htmlspecialchars($translator->translate('profile.picture'), ENT_QUOTES, 'UTF-8') ?></span>
            <small id="avatarSelectionStatus" data-i18n="profile.picture_hint"><?= htmlspecialchars($translator->translate('profile.picture_hint'), ENT_QUOTES, 'UTF-8') ?></small>
        </div>

        <div class="profileFields">
            <label class="profileField">
                <span data-i18n="profile.first_name"><?= htmlspecialchars($translator->translate('profile.first_name'), ENT_QUOTES, 'UTF-8') ?></span>
                <input type="text" name="firstname" data-i18n-placeholder="profile.first_name_placeholder" placeholder="<?= htmlspecialchars($translator->translate('profile.first_name_placeholder'), ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars($profileFields['firstname'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </label>
            <label class="profileField">
                <span data-i18n="profile.last_name"><?= htmlspecialchars($translator->translate('profile.last_name'), ENT_QUOTES, 'UTF-8') ?></span>
                <input type="text" name="lastname" data-i18n-placeholder="profile.last_name_placeholder" placeholder="<?= htmlspecialchars($translator->translate('profile.last_name_placeholder'), ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars($profileFields['lastname'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </label>
            <label class="profileField">
                <span data-i18n="profile.email"><?= htmlspecialchars($translator->translate('profile.email'), ENT_QUOTES, 'UTF-8') ?></span>
                <input type="email" data-i18n-placeholder="profile.email_placeholder" placeholder="<?= htmlspecialchars($translator->translate('profile.email_placeholder'), ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars($profileFields['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" readonly>
            </label>
            <label class="profileField">
                <span data-i18n="profile.username"><?= htmlspecialchars($translator->translate('profile.username'), ENT_QUOTES, 'UTF-8') ?></span>
                <input type="text" data-i18n-placeholder="profile.username_placeholder" placeholder="<?= htmlspecialchars($translator->translate('profile.username_placeholder'), ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars($profileFields['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>" readonly>
            </label>
            <label class="profileField">
                <span data-i18n="profile.job_title"><?= htmlspecialchars($translator->translate('profile.job_title'), ENT_QUOTES, 'UTF-8') ?></span>
                <input type="text" name="job_title" data-i18n-placeholder="profile.job_title_placeholder" placeholder="<?= htmlspecialchars($translator->translate('profile.job_title_placeholder'), ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars($profileFields['job_title'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </label>
            <div class="profileField profileBirthDateField">
                <span id="profileBirthDateLabel" data-i18n="profile.date_of_birth"><?= htmlspecialchars($translator->translate('profile.date_of_birth'), ENT_QUOTES, 'UTF-8') ?></span>
                <div class="profileBirthDatePicker <?= $calendarCssClass ?>" id="profileBirthDatePicker">
                    <input
                        id="profileBirthDate"
                        type="hidden"
                        name="date_of_birth"
                        value="<?= htmlspecialchars($profileFields['date_of_birth'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <button
                        class="profileBirthDateButton"
                        id="profileBirthDateButton"
                        type="button"
                        aria-labelledby="profileBirthDateLabel profileBirthDateDisplay"
                        aria-haspopup="dialog"
                        aria-expanded="false">
                        <span id="profileBirthDateDisplay" data-i18n="profile.birth_date.select"><?= htmlspecialchars($translator->translate('profile.birth_date.select'), ENT_QUOTES, 'UTF-8') ?></span>
                        <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                    </button>

                    <dialog
                        class="profileBirthDatePopover"
                        id="profileBirthDatePopover"
                        data-i18n-aria-label="profile.birth_date.dialog"
                        aria-label="<?= htmlspecialchars($translator->translate('profile.birth_date.dialog'), ENT_QUOTES, 'UTF-8') ?>">
                        <div class="profileBirthDateSelectors">
                            <label class="srOnly" for="profileBirthMonth" data-i18n="profile.birth_date.month"><?= htmlspecialchars($translator->translate('profile.birth_date.month'), ENT_QUOTES, 'UTF-8') ?></label>
                            <select id="profileBirthMonth"></select>
                            <label class="srOnly" for="profileBirthYear" data-i18n="profile.birth_date.year"><?= htmlspecialchars($translator->translate('profile.birth_date.year'), ENT_QUOTES, 'UTF-8') ?></label>
                            <select id="profileBirthYear"></select>
                        </div>
                        <div class="profileBirthDateWeekdays" id="profileBirthDateWeekdays" aria-hidden="true"></div>
                        <div
                            class="profileBirthDateDays"
                            id="profileBirthDateDays"
                            role="grid"
                            data-i18n-aria-label="profile.birth_date.calendar"
                            aria-label="<?= htmlspecialchars($translator->translate('profile.birth_date.calendar'), ENT_QUOTES, 'UTF-8') ?>"></div>
                        <p class="srOnly" id="profileBirthDateStatus" role="status" aria-live="polite"></p>
                    </dialog>
                </div>
            </div>
            <label class="profileField">
                <span data-i18n="profile.gender"><?= htmlspecialchars($translator->translate('profile.gender'), ENT_QUOTES, 'UTF-8') ?></span>
                <select name="gender">
                    <option value="" data-i18n="profile.gender.select" <?= ($profileFields['gender'] ?? '') === '' ? 'selected' : '' ?>><?= htmlspecialchars($translator->translate('profile.gender.select'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="male" data-i18n="profile.gender.male" <?= ($profileFields['gender'] ?? '') === 'male' ? 'selected' : '' ?>><?= htmlspecialchars($translator->translate('profile.gender.male'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="female" data-i18n="profile.gender.female" <?= ($profileFields['gender'] ?? '') === 'female' ? 'selected' : '' ?>><?= htmlspecialchars($translator->translate('profile.gender.female'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="other" data-i18n="profile.gender.other" <?= ($profileFields['gender'] ?? '') === 'other' ? 'selected' : '' ?>><?= htmlspecialchars($translator->translate('profile.gender.other'), ENT_QUOTES, 'UTF-8') ?></option>
                </select>
            </label>
            <label class="profileField">
                <span data-i18n="profile.country"><?= htmlspecialchars($translator->translate('profile.country'), ENT_QUOTES, 'UTF-8') ?></span>
                <input type="text" name="country" data-i18n-placeholder="profile.country_placeholder" placeholder="<?= htmlspecialchars($translator->translate('profile.country_placeholder'), ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars($profileFields['country'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </label>
        </div>

        <div class="profileActions">
            <button class="saveProfileButton" type="submit" data-i18n="common.save"><?= htmlspecialchars($translator->translate('common.save'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
    </form>

    <?php require_once dirname(__DIR__) . '/modals/avatar-picker.php'; ?>

    <div class="profileSecurity">
        <h2 data-i18n="profile.security"><?= htmlspecialchars($translator->translate('profile.security'), ENT_QUOTES, 'UTF-8') ?></h2>
        <a class="changePasswordButton button" href="/change-password" data-i18n="profile.change_password"><?= htmlspecialchars($translator->translate('profile.change_password'), ENT_QUOTES, 'UTF-8') ?></a>
    </div>
</section>
