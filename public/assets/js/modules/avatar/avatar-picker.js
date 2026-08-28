import { createAvatarCropper } from './avatar-cropper.js';
import { createAvatarGallery } from './avatar-gallery.js';

export function initAvatarPicker() {
    const modal = document.getElementById('avatarPickerModal');
    const openButton = document.getElementById('openAvatarPicker');
    const closeButton = document.getElementById('closeAvatarPicker');
    const cancelButton = document.getElementById('cancelAvatarPicker');
    const applyButton = document.getElementById('applyAvatarSelection');
    const galleryPanel = document.getElementById('avatarGalleryPanel');
    const galleryElement = document.getElementById('boringAvatarGrid');
    const chooseDeviceButton = document.getElementById('chooseAvatarFromDevice');
    const fileInput = document.getElementById('avatarFileInput');
    const cropPanel = document.getElementById('avatarCropPanel');
    const backButton = document.getElementById('backToAvatarGallery');
    const cropViewport = document.getElementById('avatarCropViewport');
    const cropImage = document.getElementById('avatarCropImage');
    const cropRing = document.querySelector('.avatarCropRing');
    const zoomInput = document.getElementById('avatarZoom');
    const zoomOutButton = document.getElementById('avatarZoomOut');
    const zoomInButton = document.getElementById('avatarZoomIn');
    const message = document.getElementById('avatarPickerMessage');
    const avatarAction = document.getElementById('avatarAction');
    const avatarChoice = document.getElementById('avatarChoice');
    const avatarData = document.getElementById('avatarData');
    const selectionStatus = document.getElementById('avatarSelectionStatus');
    const avatarImages = Array.from(document.querySelectorAll('[data-user-avatar]'));

    if (
        !modal || !openButton || !galleryElement || !cropViewport || !cropImage
        || !zoomInput || !avatarAction || !avatarChoice || !avatarData
    ) {
        return;
    }

    const seedBase = openButton.dataset.avatarSeedBase || 'mytodo-user';
    let selectedMode = '';
    let selectedChoice = 0;
    let selectedPreviewUrl = '';
    let openSnapshot = null;

    function setMessage(text) {
        if (message) {
            message.textContent = text;
        }
    }

    function setApplyEnabled(isEnabled) {
        if (applyButton) {
            applyButton.disabled = !isEnabled;
        }
    }

    const avatarGallery = createAvatarGallery({
        element: galleryElement,
        seedBase,
        onSelect: function (selection) {
            selectedMode = 'boring';
            selectedChoice = selection.choice;
            selectedPreviewUrl = selection.previewUrl;
            setApplyEnabled(true);
            setMessage('');
        }
    });

    const avatarCropper = createAvatarCropper({
        viewport: cropViewport,
        image: cropImage,
        ring: cropRing,
        zoomInput,
        zoomOutButton,
        zoomInButton,
        setMessage,
        setApplyEnabled
    });

    function showGallery() {
        if (galleryPanel) {
            galleryPanel.hidden = false;
        }

        if (cropPanel) {
            cropPanel.hidden = true;
        }

        avatarCropper.reset();
        avatarGallery.clearSelection();
        selectedMode = '';
        selectedChoice = 0;
        selectedPreviewUrl = '';
        setApplyEnabled(false);
        setMessage('');
    }

    function showCropPanel(file) {
        if (!avatarCropper.loadFile(file)) {
            return;
        }

        selectedMode = 'upload';
        selectedChoice = 0;
        selectedPreviewUrl = '';
        avatarGallery.clearSelection();

        if (galleryPanel) {
            galleryPanel.hidden = true;
        }

        if (cropPanel) {
            cropPanel.hidden = false;
        }
    }

    function captureOpenSnapshot() {
        openSnapshot = {
            action: avatarAction.value,
            choice: avatarChoice.value,
            data: avatarData.value,
            imageSources: avatarImages.map(function (image) { return image.src; }),
            status: selectionStatus ? selectionStatus.textContent : ''
        };
    }

    function restoreOpenSnapshot() {
        if (!openSnapshot) {
            return;
        }

        avatarAction.value = openSnapshot.action;
        avatarChoice.value = openSnapshot.choice;
        avatarData.value = openSnapshot.data;
        avatarImages.forEach(function (image, index) {
            image.src = openSnapshot.imageSources[index];
        });

        if (selectionStatus) {
            selectionStatus.textContent = openSnapshot.status;
        }
    }

    function closePicker(restoreSnapshot) {
        if (restoreSnapshot) {
            restoreOpenSnapshot();
        }

        modal.hidden = true;
        document.body.classList.remove('avatar-picker-open');
        setMessage('');
        avatarCropper.reset();
        openButton.focus();
    }

    function openPicker() {
        captureOpenSnapshot();
        showGallery();
        modal.hidden = false;
        document.body.classList.add('avatar-picker-open');

        window.requestAnimationFrame(function () {
            avatarGallery.focusFirst();
        });
    }

    openButton.addEventListener('click', openPicker);

    if (closeButton) {
        closeButton.addEventListener('click', function () {
            closePicker(true);
        });
    }

    if (cancelButton) {
        cancelButton.addEventListener('click', function () {
            closePicker(true);
        });
    }

    if (chooseDeviceButton && fileInput) {
        chooseDeviceButton.addEventListener('click', function () {
            fileInput.value = '';
            fileInput.click();
        });

        fileInput.addEventListener('change', function () {
            const file = fileInput.files && fileInput.files[0];

            if (file) {
                showCropPanel(file);
            }
        });
    }

    if (backButton) {
        backButton.addEventListener('click', showGallery);
    }

    if (applyButton) {
        applyButton.addEventListener('click', function () {
            try {
                let previewUrl = selectedPreviewUrl;

                if (selectedMode === 'boring') {
                    avatarAction.value = 'boring';
                    avatarChoice.value = String(selectedChoice);
                    avatarData.value = '';
                } else if (selectedMode === 'upload') {
                    previewUrl = avatarCropper.createAvatarDataUrl();
                    avatarAction.value = 'upload';
                    avatarChoice.value = '';
                    avatarData.value = previewUrl;
                } else {
                    setMessage('Choose a profile picture first.');
                    return;
                }

                avatarImages.forEach(function (image) {
                    image.src = previewUrl;
                });

                if (selectionStatus) {
                    selectionStatus.textContent = 'New picture ready — click Save to keep it';
                }

                closePicker(false);
            } catch (error) {
                setMessage(error.message || 'The profile picture could not be prepared.');
            }
        });
    }

    modal.addEventListener('click', function (event) {
        if (event.target === modal) {
            closePicker(true);
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !modal.hidden) {
            closePicker(true);
        }
    });
}
