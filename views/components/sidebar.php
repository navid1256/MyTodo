<?php

declare(strict_types=1);

use App\Localization\Translator;

/** @var string $activeView */
/** @var Translator $translator */

$activeView = isset($activeView) && is_string($activeView) ? $activeView : 'home';

$navigationItems = [
    'home' => [
        'href' => '/',
        'icon' => 'fa-solid fa-house',
        'translationKey' => 'navigation.home',
        'label' => $translator->translate('navigation.home'),
    ],
    'activity' => [
        'href' => '/activity',
        'icon' => 'fa-solid fa-chart-simple',
        'translationKey' => 'navigation.activity',
        'label' => $translator->translate('navigation.activity'),
    ],
    'manage-tasks' => [
        'href' => '/manage-tasks',
        'icon' => 'fa-solid fa-server',
        'translationKey' => 'navigation.manage_tasks',
        'label' => $translator->translate('navigation.manage_tasks'),
    ],
    'messages' => [
        'href' => '/messages',
        'icon' => 'fa-solid fa-envelope',
        'translationKey' => 'navigation.messages',
        'label' => $translator->translate('navigation.messages'),
    ],
];
?>
<nav class="nav" aria-label="<?= htmlspecialchars($translator->translate('navigation.label'), ENT_QUOTES, 'UTF-8') ?>">
    <div class="menu">
        <div class="title" data-i18n="navigation.title"><?= htmlspecialchars($translator->translate('navigation.title'), ENT_QUOTES, 'UTF-8') ?></div>
        <ul class="navigation-list">
            <?php foreach ($navigationItems as $viewKey => $item): ?>
                <?php
                $isActive = $activeView === $viewKey;
                $itemClass = $isActive ? 'active' : '';
                $ariaCurrent = $isActive ? ' aria-current="page"' : '';
                ?>
                <li class="<?= $itemClass ?>" data-nav-id="<?= htmlspecialchars($viewKey, ENT_QUOTES, 'UTF-8') ?>">
                    <a href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>"<?= $ariaCurrent ?>>
                        <i class="<?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
                        <span data-i18n="<?= htmlspecialchars($item['translationKey'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</nav>
