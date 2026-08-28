<?php

/** @var string $activeAuthForm */
/** @var array $authErrors */
/** @var string|null $authSuccess */
/** @var array $oldInput */
/** @var string $csrfToken */
/** @var string $baseUrl */

$activeAuthForm = ($activeAuthForm ?? 'login') === 'register' ? 'register' : 'login';
$authErrors = isset($authErrors) && is_array($authErrors) ? $authErrors : [];
$authSuccess = isset($authSuccess) && is_string($authSuccess) ? $authSuccess : null;
$oldInput = array_merge(
    ['email' => '', 'username' => ''],
    isset($oldInput) && is_array($oldInput) ? $oldInput : []
);
$baseUrl = isset($baseUrl) ? $baseUrl : '/';
$isRegister = $activeAuthForm === 'register';
$csrfToken = isset($csrfToken) && is_string($csrfToken) ? $csrfToken : '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>MyTodo Authentication</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>assets/css/pages/auth.css">
</head>

<body>
    <svg class="svg-definitions" aria-hidden="true" focusable="false">
        <symbol id="password-eye-closed" viewBox="0 0 24 24">
            <path d="M3.5 9.25c1.75 3.35 4.58 5.05 8.5 5.05s6.75-1.7 8.5-5.05" />
            <path d="M5.5 12.05 4.1 13.5M8.55 13.75l-.65 1.9M12 14.3v2M15.45 13.75l.65 1.9M18.5 12.05l1.4 1.45" />
        </symbol>
        <symbol id="password-eye-open" viewBox="0 0 24 24">
            <path d="M2.75 12s3.35-5 9.25-5 9.25 5 9.25 5-3.35 5-9.25 5-9.25-5-9.25-5Z" />
            <circle cx="12" cy="12" r="2.35" />
        </symbol>
    </svg>

    <div id="background" class="<?= $isRegister ? 'two' : '' ?>">
        <?php if ($authSuccess): ?>
            <output class="auth-success" aria-live="polite">
                <?= htmlspecialchars($authSuccess, ENT_QUOTES, 'UTF-8') ?>
            </output>
        <?php endif; ?>
        <div id="panel-box">
            <div class="panel">
                <div class="auth-form <?= !$isRegister ? 'on' : '' ?>" id="login">
                    <div class="form-title">Log In</div>
                    <form action="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>login" method="POST">
                        <input
                            name="csrf_token"
                            type="hidden"
                            value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>" />
                        <?php if ($activeAuthForm === 'login' && $authErrors): ?>
                            <div class="auth-errors" role="alert">
                                <?php foreach ($authErrors as $error): ?>
                                    <p><?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <label class="srOnly" for="login-username">Username</label>
                        <input
                            id="login-username"
                            name="username"
                            type="text"
                            required
                            autocomplete="username"
                            placeholder="Username"
                            value="<?= htmlspecialchars((string) $oldInput['username'], ENT_QUOTES, 'UTF-8') ?>" />
                        <div class="password-field">
                            <label class="srOnly" for="login-password">Password</label>
                            <input
                                id="login-password"
                                name="password"
                                type="password"
                                required
                                autocomplete="current-password"
                                placeholder="Password" />
                            <button
                                class="password-toggle"
                                type="button"
                                data-password-target="login-password"
                                aria-label="Show password"
                                aria-pressed="false">
                                <svg class="password-icon password-icon-closed" aria-hidden="true">
                                    <use href="#password-eye-closed"></use>
                                </svg>
                                <svg class="password-icon password-icon-open" aria-hidden="true">
                                    <use href="#password-eye-open"></use>
                                </svg>
                            </button>
                        </div>
                        <button type="submit">Log In</button>
                    </form>
                </div>
                <div class="auth-form <?= $isRegister ? 'on' : '' ?>" id="signup">
                    <div class="form-title">Register</div>
                    <form action="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>register" method="POST">
                        <input
                            name="csrf_token"
                            type="hidden"
                            value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>" />
                        <?php if ($activeAuthForm === 'register' && $authErrors): ?>
                            <div class="auth-errors" role="alert">
                                <?php foreach ($authErrors as $error): ?>
                                    <p><?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <label class="srOnly" for="register-email">Email</label>
                        <input
                            id="register-email"
                            name="email"
                            type="email"
                            required
                            autocomplete="email"
                            placeholder="Email"
                            value="<?= htmlspecialchars((string) $oldInput['email'], ENT_QUOTES, 'UTF-8') ?>" />
                        <label class="srOnly" for="register-username">Username</label>
                        <input
                            id="register-username"
                            name="username"
                            type="text"
                            required
                            minlength="3"
                            maxlength="50"
                            pattern="[A-Za-z0-9_]+"
                            autocomplete="username"
                            placeholder="Username"
                            value="<?= htmlspecialchars((string) $oldInput['username'], ENT_QUOTES, 'UTF-8') ?>" />
                        <div class="password-field">
                            <label class="srOnly" for="register-password">Password</label>
                            <input
                                id="register-password"
                                name="password"
                                type="password"
                                required
                                minlength="8"
                                autocomplete="new-password"
                                placeholder="Password" />
                            <button
                                class="password-toggle"
                                type="button"
                                data-password-target="register-password"
                                aria-label="Show password"
                                aria-pressed="false">
                                <svg class="password-icon password-icon-closed" aria-hidden="true">
                                    <use href="#password-eye-closed"></use>
                                </svg>
                                <svg class="password-icon password-icon-open" aria-hidden="true">
                                    <use href="#password-eye-open"></use>
                                </svg>
                            </button>
                        </div>
                        <div class="password-field">
                            <label class="srOnly" for="register-password-confirmation">Confirm Password</label>
                            <input
                                id="register-password-confirmation"
                                name="password_confirmation"
                                type="password"
                                required
                                minlength="8"
                                autocomplete="new-password"
                                placeholder="Confirm Password" />
                            <button
                                class="password-toggle"
                                type="button"
                                data-password-target="register-password-confirmation"
                                aria-label="Show password"
                                aria-pressed="false">
                                <svg class="password-icon password-icon-closed" aria-hidden="true">
                                    <use href="#password-eye-closed"></use>
                                </svg>
                                <svg class="password-icon password-icon-open" aria-hidden="true">
                                    <use href="#password-eye-open"></use>
                                </svg>
                            </button>
                        </div>
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

    <script>
        const switchButton = document.getElementById('switch');
        const loginForm = document.getElementById('login');
        const signupForm = document.getElementById('signup');
        const background = document.getElementById('background');
        const imageOverlay = document.getElementById('image-overlay');
        const passwordToggles = document.querySelectorAll('.password-toggle');

        switchButton.addEventListener('click', function() {
            loginForm.classList.toggle('on');
            signupForm.classList.toggle('on');
            switchButton.textContent = signupForm.classList.contains('on') ? 'Log In' : 'Sign Up';
            switchButton.classList.toggle('two');
            background.classList.toggle('two');
            imageOverlay.classList.toggle('two');
        });

        passwordToggles.forEach(function(toggleButton) {
            toggleButton.addEventListener('click', function() {
                const passwordInput = document.getElementById(toggleButton.dataset.passwordTarget);

                if (!passwordInput) {
                    return;
                }

                const shouldShowPassword = passwordInput.type === 'password';
                passwordInput.type = shouldShowPassword ? 'text' : 'password';
                toggleButton.setAttribute('aria-pressed', String(shouldShowPassword));
                toggleButton.setAttribute(
                    'aria-label',
                    shouldShowPassword ? 'Hide password' : 'Show password'
                );
            });
        });
    </script>
</body>

</html>
