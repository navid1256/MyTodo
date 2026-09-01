<?php

declare(strict_types=1);

/** @var string $csrfToken */
/** @var array<int, string> $profileErrors */
/** @var array<string, string> $profileFields */
/** @var string|null $profileSuccess */
/** @var string $avatarUrl */
/** @var string $currentDisplayName */
/** @var string $calendarCssClass */
/** @var int $userId */


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
        <a class="dashboardBackButton" href="/" data-dashboard-back aria-label="Back to previous page">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            <span>Back</span>
        </a>
        <i class="profilePageHeaderIcon fa-regular fa-user" aria-hidden="true"></i>
        <h1 id="profilePageTitle">My Profile</h1>
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
                <span class="profilePictureOverlay">Change</span>
            </button>
            <span>Profile picture</span>
            <small id="avatarSelectionStatus">Click the picture to change it</small>
        </div>

        <div class="profileFields">
            <label class="profileField">
                <span>First Name :</span>
                <input type="text" name="firstname" placeholder="Enter your first name" value="<?= htmlspecialchars($profileFields['firstname'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </label>
            <label class="profileField">
                <span>Last Name :</span>
                <input type="text" name="lastname" placeholder="Enter your last name" value="<?= htmlspecialchars($profileFields['lastname'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </label>
            <label class="profileField">
                <span>Email :</span>
                <input type="email" placeholder="Email address" value="<?= htmlspecialchars($profileFields['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" readonly>
            </label>
            <label class="profileField">
                <span>Username :</span>
                <input type="text" placeholder="Username" value="<?= htmlspecialchars($profileFields['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>" readonly>
            </label>
            <label class="profileField">
                <span>Job Title :</span>
                <input type="text" name="job_title" placeholder="Enter your job title" value="<?= htmlspecialchars($profileFields['job_title'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </label>
            <div class="profileField profileBirthDateField">
                <span id="profileBirthDateLabel">Date of Birth :</span>
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
                        <span id="profileBirthDateDisplay">Select your date of birth</span>
                        <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                    </button>

                    <dialog
                        class="profileBirthDatePopover"
                        id="profileBirthDatePopover"
                        aria-label="Choose date of birth">
                        <div class="profileBirthDateSelectors">
                            <label class="srOnly" for="profileBirthMonth">Month</label>
                            <select id="profileBirthMonth"></select>
                            <label class="srOnly" for="profileBirthYear">Year</label>
                            <select id="profileBirthYear"></select>
                        </div>
                        <div class="profileBirthDateWeekdays" id="profileBirthDateWeekdays" aria-hidden="true"></div>
                        <div
                            class="profileBirthDateDays"
                            id="profileBirthDateDays"
                            role="grid"
                            aria-label="Date of birth calendar"></div>
                        <p class="srOnly" id="profileBirthDateStatus" role="status" aria-live="polite"></p>
                    </dialog>
                </div>
            </div>
            <label class="profileField">
                <span>Gender :</span>
                <select name="gender">
                    <option value="" <?= ($profileFields['gender'] ?? '') === '' ? 'selected' : '' ?>>Select gender</option>
                    <option value="male" <?= ($profileFields['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Male</option>
                    <option value="female" <?= ($profileFields['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Female</option>
                    <option value="other" <?= ($profileFields['gender'] ?? '') === 'other' ? 'selected' : '' ?>>Other</option>
                </select>
            </label>
            <label class="profileField">
                <span>Country :</span>
                <input type="text" name="country" placeholder="Enter your country" value="<?= htmlspecialchars($profileFields['country'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </label>
        </div>

        <div class="profileActions">
            <button class="saveProfileButton" type="submit">Save</button>
        </div>
    </form>

    <?php require_once dirname(__DIR__) . '/modals/avatar-picker.php'; ?>

    <div class="profileSecurity">
        <h2>Security</h2>
        <a class="changePasswordButton button" href="/change-password">Change Password</a>
    </div>
</section>
