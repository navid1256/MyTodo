<?php

declare(strict_types=1);

/** @var int|null $sentNotificationCount */
/** @var string|null $activeView */
/** @var string|null $avatarUrl */
/** @var string|null $currentDisplayName */
/** @var string|null $csrfToken */
/** @var array<string, mixed>|null $currentUser */
/** @var object|null $userProfile */

$sessionUser = isset($_SESSION['user']) && is_array($_SESSION['user']) ? $_SESSION['user'] : [];
$userData = (isset($currentUser) && is_array($currentUser)) ? $currentUser : $sessionUser;
$username = trim((string) ($userData['username'] ?? 'User'));

$profile = isset($userProfile) && is_object($userProfile) ? $userProfile : null;
$firstName = trim((string) ($profile->firstname ?? ''));
$lastName = trim((string) ($profile->lastname ?? ''));
$fullName = ($firstName !== '' && $lastName !== '') ? ($firstName . ' ' . $lastName) : '';

if (isset($currentDisplayName) && is_string($currentDisplayName) && trim($currentDisplayName) !== '') {
    $displayName = trim($currentDisplayName);
} elseif ($fullName !== '') {
    $displayName = $fullName;
} elseif ($username !== '') {
    $displayName = $username;
} else {
    $displayName = 'User';
}

$defaultAvatarUrl = '/assets/img/user-default-avatar.webp';
$savedAvatarUrl = trim((string) ($profile->avatar_url ?? ''));

if (isset($avatarUrl) && is_string($avatarUrl) && trim($avatarUrl) !== '') {
    $resolvedAvatar = trim($avatarUrl);
} elseif ($savedAvatarUrl !== '') {
    $resolvedAvatar = preg_match('#^(?:https?://|data:)#i', $savedAvatarUrl)
        ? $savedAvatarUrl
        : '/' . ltrim($savedAvatarUrl, '/');
} else {
    $resolvedAvatar = $defaultAvatarUrl;
}

$sentCount = isset($sentNotificationCount) ? max(0, (int) $sentNotificationCount) : 0;
$currentView = isset($activeView) && is_string($activeView) ? $activeView : 'home';
$token = isset($csrfToken) && is_string($csrfToken) ? $csrfToken : '';
?>
<div class="pageHeader">
    <div class="title">Dashboard</div>
    <div class="userArea">
        <a
            class="notificationButton"
            href="/notifications"
            aria-label="<?= $sentCount ?> sent notifications"
            <?= $currentView === 'notifications' ? 'aria-current="page"' : '' ?>>
            <i class="fa-regular fa-bell" aria-hidden="true"></i>
            <?php if ($sentCount > 0): ?>
                <span class="notificationBadge"><?= $sentCount > 99 ? '99+' : $sentCount ?></span>
            <?php endif; ?>
        </a>

        <div class="profileMenu">
            <button
                class="userPanel"
                id="userMenuToggle"
                type="button"
                aria-expanded="false"
                aria-controls="profileDropdown">
                <img data-user-avatar src="<?= htmlspecialchars($resolvedAvatar, ENT_QUOTES, 'UTF-8') ?>" width="40" height="40" alt="<?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>">
                <span class="username"><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></span>
                <i class="profileChevron fa-solid fa-chevron-down" aria-hidden="true"></i>
            </button>

            <div class="profileDropdown" id="profileDropdown" hidden>
                <a class="profileDropdownLink" href="/profile">
                    <i class="fa-regular fa-user" aria-hidden="true"></i>
                    <span>My Profile</span>
                </a>
                <a class="profileDropdownLink" href="/account-settings">
                    <i class="fa-solid fa-user-gear" aria-hidden="true"></i>
                    <span>Account Settings</span>
                </a>
                <button id="themeToggle" type="button" aria-pressed="false">
                    <i id="themeIcon" class="fa-solid fa-moon" aria-hidden="true"></i>
                    <span id="themeLabel">Dark Mode</span>
                </button>
                <form action="/auth/logout" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
                    <button class="signOutButton" type="submit">
                        <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>
                        <span>Sign out</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
