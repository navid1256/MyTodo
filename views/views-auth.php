<?php
defined('BASE_PATH') || die('Permission Denied!');

$activeAuthForm = ($activeAuthForm ?? 'login') === 'register' ? 'register' : 'login';
$authErrors = isset($authErrors) && is_array($authErrors) ? $authErrors : [];
$oldInput = array_merge(
    ['email' => '', 'username' => ''],
    isset($oldInput) && is_array($oldInput) ? $oldInput : []
);
$baseUrl = defined('BASE_URL') ? BASE_URL : '/';
$isRegister = $activeAuthForm === 'register';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>MyTodo Authentication</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>assets/css/auth.css">

</head>

<body>
    <!-- partial:index.partial.html -->
    <div id="background" class="<?= $isRegister ? 'two' : '' ?>">
        <div id="panel-box">
            <div class="panel">
                <div class="auth-form <?= !$isRegister ? 'on' : '' ?>" id="login">
                    <div class="form-title">Log In</div>
                    <form action="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>auth.php?action=login" method="POST">
                        <?php if ($activeAuthForm === 'login' && $authErrors): ?>
                            <div class="auth-errors" role="alert">
                                <?php foreach ($authErrors as $error): ?>
                                    <p><?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <input
                            name="username"
                            type="text"
                            required
                            autocomplete="username"
                            placeholder="Username"
                            value="<?= htmlspecialchars((string) $oldInput['username'], ENT_QUOTES, 'UTF-8') ?>" />
                        <input
                            name="password"
                            type="password"
                            required
                            autocomplete="current-password"
                            placeholder="Password" />
                        <button type="submit">Log In</button>
                    </form>
                </div>
                <div class="auth-form <?= $isRegister ? 'on' : '' ?>" id="signup">
                    <div class="form-title">Register</div>
                    <form action="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>auth.php?action=register" method="POST">
                        <?php if ($activeAuthForm === 'register' && $authErrors): ?>
                            <div class="auth-errors" role="alert">
                                <?php foreach ($authErrors as $error): ?>
                                    <p><?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <input
                            name="email"
                            type="email"
                            required
                            autocomplete="email"
                            placeholder="Email"
                            value="<?= htmlspecialchars((string) $oldInput['email'], ENT_QUOTES, 'UTF-8') ?>" />
                        <input
                            name="username"
                            type="text"
                            required
                            minlength="3"
                            maxlength="50"
                            pattern="[A-Za-z0-9_]+"
                            autocomplete="username"
                            placeholder="Username"
                            value="<?= htmlspecialchars((string) $oldInput['username'], ENT_QUOTES, 'UTF-8') ?>" />
                        <input
                            name="password"
                            type="password"
                            required
                            minlength="8"
                            autocomplete="new-password"
                            placeholder="Password" />
                        <input
                            name="password_confirmation"
                            type="password"
                            required
                            minlength="8"
                            autocomplete="new-password"
                            placeholder="Confirm Password" />
                        <button type="submit">Sign Up</button>
                    </form>
                </div>
            </div>
            <div class="panel">
                <div id="switch" class="<?= $isRegister ? 'two' : '' ?>"><?= $isRegister ? 'Log In' : 'Sign Up' ?></div>
                <div id="image-overlay" class="<?= $isRegister ? 'two' : '' ?>"></div>
                <div id="image-side"></div>
            </div>
        </div>
    </div>
    <!-- partial -->
    <script>
        const switchButton = document.getElementById('switch');
        const loginForm = document.getElementById('login');
        const signupForm = document.getElementById('signup');
        const background = document.getElementById('background');
        const imageOverlay = document.getElementById('image-overlay');

        switchButton.addEventListener('click', function() {
            loginForm.classList.toggle('on');
            signupForm.classList.toggle('on');
            switchButton.textContent = signupForm.classList.contains('on') ? 'Log In' : 'Sign Up';
            switchButton.classList.toggle('two');
            background.classList.toggle('two');
            imageOverlay.classList.toggle('two');
        });
    </script>

</body>

</html>
