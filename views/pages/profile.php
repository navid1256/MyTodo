<?php

/** @var string $csrfToken */
/** @var array $profileErrors */
/** @var array $profileFields */
/** @var string|null $profileSuccess */
/** @var string $avatarUrl */
/** @var string $currentDisplayName */
?>
<section class="profilePage" aria-labelledby="profilePageTitle">
    <header class="profilePageHeader dashboardSectionHeader">
        <a class="dashboardBackButton" href="?view=home" data-dashboard-back aria-label="Back to previous page">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            <span>Back</span>
        </a>
        <i class="profilePageHeaderIcon fa-regular fa-user" aria-hidden="true"></i>
        <h1 id="profilePageTitle">My Profile</h1>
    </header>
    <?php if ($profileSuccess): ?>
        <div class="profileMessage profileMessageSuccess" role="status">
            <?= htmlspecialchars($profileSuccess, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>
    <?php if ($profileErrors): ?>
        <div class="profileMessage profileMessageError" role="alert">
            <?php foreach ($profileErrors as $profileError): ?>
                <p><?= htmlspecialchars((string) $profileError, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form class="profileOverview" action="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>index.php?view=profile" method="POST">
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
                data-avatar-seed-base="user-<?= getCurrentUserId() ?>"
                aria-haspopup="dialog">
                <img data-user-avatar src="<?= htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($currentDisplayName, ENT_QUOTES, 'UTF-8') ?> profile picture">
                <span class="profilePictureOverlay">Change</span>
            </button>
            <span>Profile picture</span>
            <small id="avatarSelectionStatus">Click the picture to change it</small>
        </div>

        <div class="profileFields">
            <label class="profileField">
                <span>First Name :</span>
                <input type="text" name="firstname" placeholder="Enter your first name" value="<?= htmlspecialchars($profileFields['firstname'], ENT_QUOTES, 'UTF-8') ?>">
            </label>
            <label class="profileField">
                <span>Last Name :</span>
                <input type="text" name="lastname" placeholder="Enter your last name" value="<?= htmlspecialchars($profileFields['lastname'], ENT_QUOTES, 'UTF-8') ?>">
            </label>
            <label class="profileField">
                <span>Email :</span>
                <input type="email" placeholder="Email address" value="<?= htmlspecialchars($profileFields['email'], ENT_QUOTES, 'UTF-8') ?>" readonly>
            </label>
            <label class="profileField">
                <span>Username :</span>
                <input type="text" placeholder="Username" value="<?= htmlspecialchars($profileFields['username'], ENT_QUOTES, 'UTF-8') ?>" readonly>
            </label>
            <label class="profileField">
                <span>Job Title :</span>
                <input type="text" name="job_title" placeholder="Enter your job title" value="<?= htmlspecialchars($profileFields['job_title'], ENT_QUOTES, 'UTF-8') ?>">
            </label>
            <label class="profileField">
                <span>Date of Birth :</span>
                <input type="date" name="date_of_birth" placeholder="Select your date of birth" value="<?= htmlspecialchars($profileFields['date_of_birth'], ENT_QUOTES, 'UTF-8') ?>">
            </label>
            <label class="profileField">
                <span>Gender :</span>
                <select name="gender">
                    <option value="" <?= $profileFields['gender'] === '' ? 'selected' : '' ?>>Select gender</option>
                    <option value="male" <?= $profileFields['gender'] === 'male' ? 'selected' : '' ?>>Male</option>
                    <option value="female" <?= $profileFields['gender'] === 'female' ? 'selected' : '' ?>>Female</option>
                    <option value="other" <?= $profileFields['gender'] === 'other' ? 'selected' : '' ?>>Other</option>
                </select>
            </label>
            <label class="profileField">
                <span>Country :</span>
                <input type="text" name="country" placeholder="Enter your country" value="<?= htmlspecialchars($profileFields['country'], ENT_QUOTES, 'UTF-8') ?>">
            </label>
        </div>

        <div class="profileActions">
            <button class="saveProfileButton" type="submit">Save</button>
        </div>
    </form>

    <?php require __DIR__ . '/../modals/avatar-picker.php'; ?>


    <div class="profileSecurity">
        <h2>Security</h2>
        <form class="changePasswordNavigation" method="GET">
            <input type="hidden" name="view" value="change-password">
            <button class="changePasswordButton" type="submit">Change Password</button>
        </form>
    </div>
</section>
