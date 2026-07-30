<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>MyTodo Authentication</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/auth.css">

</head>

<body>
    <!-- partial:index.partial.html -->
    <div id="background" class="<?= $activeAuthForm === 'register' ? 'two' : '' ?>">
        <div id="panel-box">
            <div class="panel">
                <div class="auth-form <?= $activeAuthForm === 'login' ? 'on' : '' ?>" id="login">
                    <div id="form-title">Log In</div>
                    <form action="<?= BASE_URL ?>auth.php?action=login" method="POST">
                        <?php if ($activeAuthForm === 'login' && $authErrors): ?>
                            <div class="auth-errors" role="alert">
                                <?php foreach ($authErrors as $error): ?>
                                    <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <input
                            name="username"
                            type="text"
                            required
                            autocomplete="username"
                            placeholder="Username"
                            value="<?= htmlspecialchars($oldInput['username'], ENT_QUOTES, 'UTF-8') ?>" />
                        <input
                            name="password"
                            type="password"
                            required
                            autocomplete="current-password"
                            placeholder="Password" />
                        <button type="submit">Log In</button>
                    </form>
                </div>
                <div class="auth-form <?= $activeAuthForm === 'register' ? 'on' : '' ?>" id="signup">
                    <div id="form-title">Register</div>
                    <form action="<?= BASE_URL ?>auth.php?action=register" method="POST">
                        <?php if ($activeAuthForm === 'register' && $authErrors): ?>
                            <div class="auth-errors" role="alert">
                                <?php foreach ($authErrors as $error): ?>
                                    <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <input
                            name="email"
                            type="email"
                            required
                            autocomplete="email"
                            placeholder="Email"
                            value="<?= htmlspecialchars($oldInput['email'], ENT_QUOTES, 'UTF-8') ?>" />
                        <input
                            name="username"
                            type="text"
                            required
                            minlength="3"
                            maxlength="50"
                            pattern="[A-Za-z0-9_]+"
                            autocomplete="username"
                            placeholder="Username"
                            value="<?= htmlspecialchars($oldInput['username'], ENT_QUOTES, 'UTF-8') ?>" />
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
                <div id="switch" class="<?= $activeAuthForm === 'register' ? 'two' : '' ?>">
                    <?= $activeAuthForm === 'register' ? 'Log In' : 'Sign Up' ?>
                </div>
                <div id="image-overlay" class="<?= $activeAuthForm === 'register' ? 'two' : '' ?>"></div>
                <div id="image-side"></div>
            </div>
        </div>
    </div>
    <!-- partial -->
    <script src='https://code.jquery.com/jquery-3.3.1.min.js'></script>
    <script>
        $('#switch').click(function() {
            $(this).text(function(i, text) {
                return text === "Sign Up" ? "Log In" : "Sign Up";
            });
            $('#login').toggleClass("on");
            $('#signup').toggleClass("on");
            $(this).toggleClass("two");
            $('#background').toggleClass("two");
            $('#image-overlay').toggleClass("two");
        })
    </script>

</body>

</html>
