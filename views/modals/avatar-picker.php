<div class="avatarPickerBackdrop" id="avatarPickerModal" hidden>
    <section
        class="avatarPickerModal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="avatarPickerTitle">
        <button class="avatarPickerClose" id="closeAvatarPicker" type="button" aria-label="Close avatar picker">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>

        <h2 id="avatarPickerTitle">Choose a profile picture</h2>
        <p class="avatarPickerHint">Select a Boring Avatar or choose a picture from your device.</p>

        <div class="avatarGalleryPanel" id="avatarGalleryPanel">
            <div class="boringAvatarGrid" id="boringAvatarGrid" role="radiogroup" aria-label="Boring Avatar options"></div>
            <button class="chooseDeviceButton" id="chooseAvatarFromDevice" type="button">Choose from your device</button>
            <input id="avatarFileInput" type="file" accept="image/jpeg,image/png,image/webp" hidden>
        </div>

        <div class="avatarCropPanel" id="avatarCropPanel" hidden>
            <button class="backToAvatarGallery" id="backToAvatarGallery" type="button">Back to avatars</button>
            <div class="avatarCropViewport" id="avatarCropViewport">
                <img id="avatarCropImage" alt="Profile picture crop preview" draggable="false">
                <span class="avatarCropRing" aria-hidden="true"></span>
            </div>
            <p class="avatarCropHint">Drag the picture to reposition it.</p>
            <div class="avatarZoomControl">
                <button id="avatarZoomOut" type="button" aria-label="Zoom out">−</button>
                <label class="srOnly" for="avatarZoom">Zoom profile picture</label>
                <input id="avatarZoom" type="range" min="-100" max="100" step="1" value="0" aria-valuetext="100%">
                <button id="avatarZoomIn" type="button" aria-label="Zoom in">+</button>
            </div>
        </div>

        <p class="avatarPickerMessage" id="avatarPickerMessage" role="alert" aria-live="polite"></p>

        <div class="avatarPickerActions">
            <button class="cancelAvatarButton" id="cancelAvatarPicker" type="button">Cancel</button>
            <button class="applyAvatarButton" id="applyAvatarSelection" type="button" disabled>Use Picture</button>
        </div>
    </section>
</div>
