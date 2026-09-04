<dialog class="avatarPickerBackdrop" id="avatarPickerModal" aria-labelledby="avatarPickerTitle">
    <section
        class="avatarPickerModal">
        <button
            class="avatarPickerClose"
            id="closeAvatarPicker"
            type="button"
            data-i18n-aria-label="profile.avatar.close"
            aria-label="<?= htmlspecialchars($translator->translate('profile.avatar.close'), ENT_QUOTES, 'UTF-8') ?>">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>

        <h2 id="avatarPickerTitle" data-i18n="profile.avatar.title"><?= htmlspecialchars($translator->translate('profile.avatar.title'), ENT_QUOTES, 'UTF-8') ?></h2>
        <p class="avatarPickerHint" data-i18n="profile.avatar.hint"><?= htmlspecialchars($translator->translate('profile.avatar.hint'), ENT_QUOTES, 'UTF-8') ?></p>

        <div class="avatarGalleryPanel" id="avatarGalleryPanel">
            <div class="boringAvatarGrid" id="boringAvatarGrid" role="radiogroup" data-i18n-aria-label="profile.avatar.options_label" aria-label="<?= htmlspecialchars($translator->translate('profile.avatar.options_label'), ENT_QUOTES, 'UTF-8') ?>"></div>
            <button class="chooseDeviceButton" id="chooseAvatarFromDevice" type="button" data-i18n="profile.avatar.choose_device"><?= htmlspecialchars($translator->translate('profile.avatar.choose_device'), ENT_QUOTES, 'UTF-8') ?></button>
            <input id="avatarFileInput" type="file" accept="image/jpeg,image/png,image/webp" hidden>
        </div>

        <div class="avatarCropPanel" id="avatarCropPanel" hidden>
            <button class="backToAvatarGallery" id="backToAvatarGallery" type="button" data-i18n="profile.avatar.back"><?= htmlspecialchars($translator->translate('profile.avatar.back'), ENT_QUOTES, 'UTF-8') ?></button>
            <div class="avatarCropViewport" id="avatarCropViewport">
                <img id="avatarCropImage" data-i18n-alt="profile.avatar.crop_preview" alt="<?= htmlspecialchars($translator->translate('profile.avatar.crop_preview'), ENT_QUOTES, 'UTF-8') ?>" draggable="false">
                <span class="avatarCropRing" aria-hidden="true"></span>
            </div>
            <p class="avatarCropHint" data-i18n="profile.avatar.drag_hint"><?= htmlspecialchars($translator->translate('profile.avatar.drag_hint'), ENT_QUOTES, 'UTF-8') ?></p>
            <div class="avatarZoomControl">
                <button id="avatarZoomOut" type="button" data-i18n-aria-label="profile.avatar.zoom_out" aria-label="<?= htmlspecialchars($translator->translate('profile.avatar.zoom_out'), ENT_QUOTES, 'UTF-8') ?>">−</button>
                <label class="srOnly" for="avatarZoom" data-i18n="profile.avatar.zoom_label"><?= htmlspecialchars($translator->translate('profile.avatar.zoom_label'), ENT_QUOTES, 'UTF-8') ?></label>
                <input id="avatarZoom" type="range" min="-100" max="100" step="1" value="0" aria-valuetext="100%">
                <button id="avatarZoomIn" type="button" data-i18n-aria-label="profile.avatar.zoom_in" aria-label="<?= htmlspecialchars($translator->translate('profile.avatar.zoom_in'), ENT_QUOTES, 'UTF-8') ?>">+</button>
            </div>
        </div>

        <p class="avatarPickerMessage" id="avatarPickerMessage" role="alert" aria-live="polite"></p>

        <div class="avatarPickerActions">
            <button class="cancelAvatarButton" id="cancelAvatarPicker" type="button" data-i18n="profile.avatar.cancel"><?= htmlspecialchars($translator->translate('profile.avatar.cancel'), ENT_QUOTES, 'UTF-8') ?></button>
            <button class="applyAvatarButton" id="applyAvatarSelection" type="button" data-i18n="profile.avatar.use_picture" disabled><?= htmlspecialchars($translator->translate('profile.avatar.use_picture'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
    </section>
</dialog>
