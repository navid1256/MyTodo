<?php
defined('BASE_PATH') OR die("Permision Denied !");

function getCurrentUser(): ?array
{
    return isset($_SESSION['user']) && is_array($_SESSION['user'])
        ? $_SESSION['user']
        : null;
}

function getCurrentUserId(): int
{
    return (int) (getCurrentUser()['id'] ?? 0);
}

function getCurrentUserProfile(): ?object
{
    global $pdo;

    $statement = $pdo->prepare(
        'SELECT
            users.id,
            users.username,
            users.email,
            users_info.firstname,
            users_info.lastname,
            users_info.job_title,
            users_info.date_of_birth,
            users_info.gender,
            users_info.country,
            users_info.avatar_url
         FROM users
         LEFT JOIN users_info ON users_info.user_id = users.id
         WHERE users.id = :user_id
         LIMIT 1'
    );
    $statement->execute(['user_id' => getCurrentUserId()]);
    $profile = $statement->fetch(PDO::FETCH_OBJ);

    return $profile ?: null;
}

function updateCurrentUserProfile(array $profileData): void
{
    global $pdo;

    $statement = $pdo->prepare(
        'INSERT INTO users_info (
            user_id,
            firstname,
            lastname,
            job_title,
            date_of_birth,
            gender,
            country,
            avatar_url
         ) VALUES (
            :user_id,
            :firstname,
            :lastname,
            :job_title,
            :date_of_birth,
            :gender,
            :country,
            :avatar_url
         )
         ON DUPLICATE KEY UPDATE
            firstname = VALUES(firstname),
            lastname = VALUES(lastname),
            job_title = VALUES(job_title),
            date_of_birth = VALUES(date_of_birth),
            gender = VALUES(gender),
            country = VALUES(country),
            avatar_url = VALUES(avatar_url)'
    );
    $statement->execute([
        'user_id' => getCurrentUserId(),
        'firstname' => $profileData['firstname'] !== '' ? $profileData['firstname'] : null,
        'lastname' => $profileData['lastname'] !== '' ? $profileData['lastname'] : null,
        'job_title' => $profileData['job_title'] !== '' ? $profileData['job_title'] : null,
        'date_of_birth' => $profileData['date_of_birth'] !== '' ? $profileData['date_of_birth'] : null,
        'gender' => $profileData['gender'] !== '' ? $profileData['gender'] : null,
        'country' => $profileData['country'] !== '' ? $profileData['country'] : null,
        'avatar_url' => $profileData['avatar_url'] !== '' ? $profileData['avatar_url'] : null,
    ]);
}

function boringAvatarHashCode(string $name): int
{
    $hash = 0;

    foreach (str_split($name) as $character) {
        $hash = (($hash << 5) - $hash + ord($character)) & 0xFFFFFFFF;

        if ($hash >= 0x80000000) {
            $hash -= 0x100000000;
        }
    }

    return abs($hash);
}

function boringAvatarDigit(int $number, int $position): int
{
    return (int) floor($number / (10 ** $position)) % 10;
}

function boringAvatarUnit(int $number, int $range, ?int $position = null): int
{
    $value = $number % $range;

    if ($position !== null && $position !== 0 && boringAvatarDigit($number, $position) % 2 === 0) {
        return -$value;
    }

    return $value;
}

function boringAvatarContrast(string $hexColor): string
{
    $hexColor = ltrim($hexColor, '#');
    $red = hexdec(substr($hexColor, 0, 2));
    $green = hexdec(substr($hexColor, 2, 2));
    $blue = hexdec(substr($hexColor, 4, 2));
    $yiq = (($red * 299) + ($green * 587) + ($blue * 114)) / 1000;

    return $yiq >= 128 ? '#000000' : '#FFFFFF';
}

function createBoringBeamAvatarSvg(string $seed): string
{
    $size = 36;
    $colors = ['#92A1C6', '#146A7C', '#F0AB3D', '#C271B4', '#C20D90'];
    $number = boringAvatarHashCode($seed);
    $colorCount = count($colors);
    $wrapperColor = $colors[$number % $colorCount];
    $backgroundColor = $colors[($number + 13) % $colorCount];
    $preTranslateX = boringAvatarUnit($number, 10, 1);
    $preTranslateY = boringAvatarUnit($number, 10, 2);
    $wrapperTranslateX = $preTranslateX < 5 ? $preTranslateX + ($size / 9) : $preTranslateX;
    $wrapperTranslateY = $preTranslateY < 5 ? $preTranslateY + ($size / 9) : $preTranslateY;
    $wrapperRotate = boringAvatarUnit($number, 360);
    $wrapperScale = 1 + (boringAvatarUnit($number, (int) ($size / 12)) / 10);
    $isMouthOpen = boringAvatarDigit($number, 2) % 2 === 0;
    $isCircle = boringAvatarDigit($number, 1) % 2 === 0;
    $eyeSpread = boringAvatarUnit($number, 5);
    $mouthSpread = boringAvatarUnit($number, 3);
    $faceRotate = boringAvatarUnit($number, 10, 3);
    $faceTranslateX = $wrapperTranslateX > ($size / 6)
        ? $wrapperTranslateX / 2
        : boringAvatarUnit($number, 8, 1);
    $faceTranslateY = $wrapperTranslateY > ($size / 6)
        ? $wrapperTranslateY / 2
        : boringAvatarUnit($number, 7, 2);
    $faceColor = boringAvatarContrast($wrapperColor);
    $mouth = $isMouthOpen
        ? sprintf(
            '<path d="M15 %s c2 1 4 1 6 0" stroke="%s" fill="none" stroke-linecap="round"/>',
            19 + $mouthSpread,
            $faceColor
        )
        : sprintf(
            '<path d="M13,%s a1,0.75 0 0,0 10,0" fill="%s"/>',
            19 + $mouthSpread,
            $faceColor
        );

    return sprintf(
        '<svg viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">'
        . '<mask id="avatar-mask" maskUnits="userSpaceOnUse" x="0" y="0" width="36" height="36">'
        . '<rect width="36" height="36" rx="72" fill="#FFFFFF"/></mask>'
        . '<g mask="url(#avatar-mask)"><rect width="36" height="36" fill="%s"/>'
        . '<rect width="36" height="36" transform="translate(%s %s) rotate(%s 18 18) scale(%s)" fill="%s" rx="%s"/>'
        . '<g transform="translate(%s %s) rotate(%s 18 18)">%s'
        . '<rect x="%s" y="14" width="1.5" height="2" rx="1" fill="%s"/>'
        . '<rect x="%s" y="14" width="1.5" height="2" rx="1" fill="%s"/>'
        . '</g></g></svg>',
        $backgroundColor,
        $wrapperTranslateX,
        $wrapperTranslateY,
        $wrapperRotate,
        $wrapperScale,
        $wrapperColor,
        $isCircle ? $size : $size / 6,
        $faceTranslateX,
        $faceTranslateY,
        $faceRotate,
        $mouth,
        14 - $eyeSpread,
        $faceColor,
        20 + $eyeSpread,
        $faceColor
    );
}

function getProfileAvatarStorageDirectory(): string
{
    $directory = BASE_PATH . 'storage' . DIRECTORY_SEPARATOR . 'avatars';

    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('The avatar storage directory could not be created.');
    }

    return $directory;
}

function writeProfileAvatarFile(string $contents, string $extension): string
{
    $directory = getProfileAvatarStorageDirectory();
    $fileName = sprintf(
        'user-%d-%s.%s',
        getCurrentUserId(),
        bin2hex(random_bytes(8)),
        $extension
    );
    $absolutePath = $directory . DIRECTORY_SEPARATOR . $fileName;

    if (file_put_contents($absolutePath, $contents, LOCK_EX) === false) {
        throw new RuntimeException('The profile picture could not be saved.');
    }

    return 'storage/avatars/' . $fileName;
}

function storeCurrentUserProfileAvatar(string $avatarAction, int $avatarChoice, string $avatarData): string
{
    if ($avatarAction === 'boring') {
        if ($avatarChoice < 1 || $avatarChoice > 12) {
            throw new InvalidArgumentException('Please choose a valid avatar.');
        }

        $seed = sprintf('user-%d:avatar-%d', getCurrentUserId(), $avatarChoice);

        return writeProfileAvatarFile(createBoringBeamAvatarSvg($seed), 'svg');
    }

    if ($avatarAction !== 'upload') {
        throw new InvalidArgumentException('Please choose a valid profile picture option.');
    }

    if (strlen($avatarData) > 2_500_000) {
        throw new InvalidArgumentException('The cropped profile picture is too large.');
    }

    if (!preg_match('#^data:image/(jpeg|png|webp);base64,#i', $avatarData, $matches)) {
        throw new InvalidArgumentException('The cropped profile picture is invalid.');
    }

    $encodedImage = substr($avatarData, strpos($avatarData, ',') + 1);
    $imageContents = base64_decode($encodedImage, true);

    if ($imageContents === false || strlen($imageContents) > 1_800_000) {
        throw new InvalidArgumentException('The cropped profile picture is invalid or too large.');
    }

    $imageInfo = @getimagesizefromstring($imageContents);
    $allowedMimeTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!$imageInfo || !isset($allowedMimeTypes[$imageInfo['mime']])) {
        throw new InvalidArgumentException('Only JPEG, PNG, and WebP profile pictures are allowed.');
    }

    if ((int) $imageInfo[0] !== 512 || (int) $imageInfo[1] !== 512) {
        throw new InvalidArgumentException('The cropped profile picture must be 512 by 512 pixels.');
    }

    return writeProfileAvatarFile($imageContents, $allowedMimeTypes[$imageInfo['mime']]);
}

function deleteStoredProfileAvatar(string $avatarPath): void
{
    if (!preg_match('#^storage/avatars/[a-zA-Z0-9.-]+$#', $avatarPath)) {
        return;
    }

    $storageDirectory = realpath(getProfileAvatarStorageDirectory());
    $absolutePath = BASE_PATH . str_replace('/', DIRECTORY_SEPARATOR, $avatarPath);
    $parentDirectory = realpath(dirname($absolutePath));

    if (
        $storageDirectory !== false
        && $parentDirectory !== false
        && hash_equals($storageDirectory, $parentDirectory)
        && is_file($absolutePath)
    ) {
        unlink($absolutePath);
    }
}

function getCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(mixed $token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && is_string($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function findUserByUsername(string $username): ?object
{
    global $pdo;

    $statement = $pdo->prepare(
        'SELECT id, username, email, password
         FROM users
         WHERE username = :username
         LIMIT 1'
    );
    $statement->execute(['username' => $username]);
    $user = $statement->fetch(PDO::FETCH_OBJ);

    return $user ?: null;
}

function usernameExists(string $username): bool
{
    return findUserByUsername($username) !== null;
}

function emailExists(string $email): bool
{
    global $pdo;

    $statement = $pdo->prepare(
        'SELECT 1
         FROM users
         WHERE email = :email
         LIMIT 1'
    );
    $statement->execute(['email' => $email]);

    return (bool) $statement->fetchColumn();
}

function setAuthenticatedUser(object $user): void
{
    session_regenerate_id(true);

    $_SESSION['user'] = [
        'id' => (int) $user->id,
        'username' => $user->username,
        'email' => $user->email,
    ];
}

function verifyStoredPassword(string $password, string $storedPassword): bool
{
    $passwordInfo = password_get_info($storedPassword);
    $isHashedPassword = $passwordInfo['algoName'] !== 'unknown';

    return $isHashedPassword
        ? password_verify($password, $storedPassword)
        : hash_equals($storedPassword, $password);
}

function loginUser(string $username, string $password): bool
{
    global $pdo;

    $user = findUserByUsername($username);

    if ($user === null) {
        return false;
    }

    $passwordInfo = password_get_info($user->password);
    $isHashedPassword = $passwordInfo['algoName'] !== 'unknown';
    $passwordIsValid = verifyStoredPassword($password, $user->password);

    if (!$passwordIsValid) {
        return false;
    }

    if (!$isHashedPassword || password_needs_rehash($user->password, PASSWORD_DEFAULT)) {
        $statement = $pdo->prepare(
            'UPDATE users
             SET password = :password
             WHERE id = :id'
        );
        $statement->execute([
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'id' => $user->id,
        ]);
    }

    setAuthenticatedUser($user);

    return true;
}

function changeCurrentUserPassword(string $currentPassword, string $newPassword): bool
{
    global $pdo;

    $userId = getCurrentUserId();

    if ($userId === 0) {
        return false;
    }

    $statement = $pdo->prepare(
        'SELECT password
         FROM users
         WHERE id = :id
         LIMIT 1'
    );
    $statement->execute(['id' => $userId]);
    $storedPassword = $statement->fetchColumn();

    if (!is_string($storedPassword) || !verifyStoredPassword($currentPassword, $storedPassword)) {
        return false;
    }

    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

    if (!is_string($passwordHash)) {
        throw new RuntimeException('The new password could not be secured.');
    }

    $updateStatement = $pdo->prepare(
        'UPDATE users
         SET password = :password
         WHERE id = :id'
    );
    $updateStatement->execute([
        'password' => $passwordHash,
        'id' => $userId,
    ]);

    session_regenerate_id(true);

    return true;
}

function validateNewPassword(string $newPassword, string $confirmation): void
{
    if (mb_strlen($newPassword) < 8) {
        throw new InvalidArgumentException('New password must contain at least 8 characters.');
    }

    if (strlen($newPassword) > 72) {
        throw new InvalidArgumentException('New password must not exceed 72 bytes.');
    }

    if (!preg_match('/\p{N}/u', $newPassword)) {
        throw new InvalidArgumentException('New password must include at least one number.');
    }

    if (!preg_match('/[^\p{L}\p{N}\s]/u', $newPassword)) {
        throw new InvalidArgumentException('New password must include at least one special character.');
    }

    if (!hash_equals($newPassword, $confirmation)) {
        throw new InvalidArgumentException('New password confirmation does not match.');
    }
}

function registerUser(string $email, string $username, string $password): int
{
    global $pdo;

    $statement = $pdo->prepare(
        'INSERT INTO users (username, email, password)
         VALUES (:username, :email, :password)'
    );
    $statement->execute([
        'username' => $username,
        'email' => $email,
        'password' => password_hash($password, PASSWORD_DEFAULT),
    ]);

    return (int) $pdo->lastInsertId();
}

function logoutUser(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $cookieParameters = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            [
                'expires' => time() - 42000,
                'path' => $cookieParameters['path'],
                'domain' => $cookieParameters['domain'],
                'secure' => $cookieParameters['secure'],
                'httponly' => $cookieParameters['httponly'],
                'samesite' => $cookieParameters['samesite'] ?? 'Lax',
            ]
        );
    }

    session_destroy();
}
