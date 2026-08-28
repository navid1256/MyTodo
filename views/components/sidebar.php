<?php

declare(strict_types=1);

/** @var string $activeView */

$activeView = isset($activeView) && is_string($activeView) ? $activeView : 'home';

$navigationItems = [
    'home' => [
        'href' => '/',
        'icon' => 'fa-solid fa-house',
        'label' => 'Home',
    ],
    'activity' => [
        'href' => '/activity',
        'icon' => 'fa-solid fa-chart-simple',
        'label' => 'Activity',
    ],
    'manage-tasks' => [
        'href' => '/manage-tasks',
        'icon' => 'fa-solid fa-server',
        'label' => 'Manage Tasks',
    ],
    'messages' => [
        'href' => '/messages',
        'icon' => 'fa-solid fa-envelope',
        'label' => 'Messages',
    ],
];
?>
<nav class="nav" aria-label="Main Navigation">
    <div class="menu">
        <div class="title">Navigation</div>
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
                        <span><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</nav>
