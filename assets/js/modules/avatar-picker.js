import { createBoringBeamAvatarDataUrl } from '../vendor/boring-avatar.js';

const AVATAR_OPTION_COUNT = 12;
const MAX_SOURCE_FILE_SIZE = 10 * 1024 * 1024;
const OUTPUT_SIZE = 512;
const ALLOWED_FILE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

function clamp(value, minimum, maximum) {
    return Math.min(Math.max(value, minimum), maximum);
}

export function initAvatarPicker() {
    const modal = document.getElementById('avatarPickerModal');
    const openButton = document.getElementById('openAvatarPicker');
    const closeButton = document.getElementById('closeAvatarPicker');
    const cancelButton = document.getElementById('cancelAvatarPicker');
    const applyButton = document.getElementById('applyAvatarSelection');
    const galleryPanel = document.getElementById('avatarGalleryPanel');
    const gallery = document.getElementById('boringAvatarGrid');
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
        !modal || !openButton || !gallery || !cropViewport || !cropImage
        || !zoomInput || !avatarAction || !avatarChoice || !avatarData
    ) {
        return;
    }

    const seedBase = openButton.dataset.avatarSeedBase || 'mytodo-user';
    let selectedMode = '';
    let selectedChoice = 0;
    let selectedPreviewUrl = '';
    let sourceObjectUrl = '';
    let naturalWidth = 0;
    let naturalHeight = 0;
    let baseScale = 1;
    let minimumZoom = 1;
    let zoom = 1;
    let offsetX = 0;
    let offsetY = 0;
    let displayedWidth = 0;
    let displayedHeight = 0;
    let imageLeft = 0;
    let imageTop = 0;
    let dragState = null;
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

    function revokeSourceObjectUrl() {
        if (sourceObjectUrl) {
            URL.revokeObjectURL(sourceObjectUrl);
            sourceObjectUrl = '';
        }
    }

    function setSelectedAvatarButton(choice) {
        gallery.querySelectorAll('.boringAvatarOption').forEach(function (button) {
            const isSelected = Number(button.dataset.avatarChoice) === choice;
            button.classList.toggle('is-selected', isSelected);
            button.setAttribute('aria-checked', String(isSelected));
        });
    }

    function buildAvatarGallery() {
        gallery.textContent = '';

        for (let choice = 1; choice <= AVATAR_OPTION_COUNT; choice++) {
            const seed = `${seedBase}:avatar-${choice}`;
            const imageUrl = createBoringBeamAvatarDataUrl(seed);
            const button = document.createElement('button');
            const image = document.createElement('img');

            button.className = 'boringAvatarOption';
            button.type = 'button';
            button.dataset.avatarChoice = String(choice);
            button.setAttribute('role', 'radio');
            button.setAttribute('aria-checked', 'false');
            button.setAttribute('aria-label', `Choose avatar ${choice}`);
            image.src = imageUrl;
            image.alt = '';
            image.width = 76;
            image.height = 76;
            button.appendChild(image);

            button.addEventListener('click', function () {
                selectedMode = 'boring';
                selectedChoice = choice;
                selectedPreviewUrl = imageUrl;
                setSelectedAvatarButton(choice);
                setApplyEnabled(true);
                setMessage('');
            });

            gallery.appendChild(button);
        }
    }

    function showGallery() {
        if (galleryPanel) {
            galleryPanel.hidden = false;
        }

        if (cropPanel) {
            cropPanel.hidden = true;
        }

        selectedMode = '';
        selectedChoice = 0;
        selectedPreviewUrl = '';
        setSelectedAvatarButton(0);
        setApplyEnabled(false);
        setMessage('');
    }

    function getCropMetrics() {
        const stageWidth = cropViewport.clientWidth || 420;
        const stageHeight = cropViewport.clientHeight || 300;
        const cropSize = cropRing ? cropRing.clientWidth : Math.min(240, stageWidth, stageHeight);

        return {
            stageWidth: stageWidth,
            stageHeight: stageHeight,
            cropSize: cropSize,
            cropLeft: (stageWidth - cropSize) / 2,
            cropTop: (stageHeight - cropSize) / 2
        };
    }

    function renderCropImage() {
        if (!naturalWidth || !naturalHeight) {
            return;
        }

        const metrics = getCropMetrics();
        const currentScale = baseScale * zoom;

        displayedWidth = naturalWidth * currentScale;
        displayedHeight = naturalHeight * currentScale;

        const maximumOffsetX = Math.max(0, (displayedWidth - metrics.cropSize) / 2);
        const maximumOffsetY = Math.max(0, (displayedHeight - metrics.cropSize) / 2);

        offsetX = clamp(offsetX, -maximumOffsetX, maximumOffsetX);
        offsetY = clamp(offsetY, -maximumOffsetY, maximumOffsetY);
        imageLeft = (metrics.stageWidth - displayedWidth) / 2 + offsetX;
        imageTop = (metrics.stageHeight - displayedHeight) / 2 + offsetY;

        cropImage.style.width = `${displayedWidth}px`;
        cropImage.style.height = `${displayedHeight}px`;
        cropImage.style.left = `${imageLeft}px`;
        cropImage.style.top = `${imageTop}px`;
    }

    function initialiseCropImage() {
        naturalWidth = cropImage.naturalWidth;
        naturalHeight = cropImage.naturalHeight;

        if (!naturalWidth || !naturalHeight) {
            setMessage('The selected picture could not be loaded.');
            return;
        }

        const metrics = getCropMetrics();
        const containScale = Math.min(
            metrics.stageWidth / naturalWidth,
            metrics.stageHeight / naturalHeight
        );
        const cropCoverScale = Math.max(
            metrics.cropSize / naturalWidth,
            metrics.cropSize / naturalHeight
        );

        baseScale = Math.max(containScale, cropCoverScale);
        minimumZoom = clamp(cropCoverScale / baseScale, 0.5, 1);
        zoom = 1;
        offsetX = 0;
        offsetY = 0;
        zoomInput.min = String(minimumZoom);
        zoomInput.value = '1';
        renderCropImage();
        setApplyEnabled(true);
    }

    function showCropPanel(file) {
        if (!ALLOWED_FILE_TYPES.includes(file.type)) {
            setMessage('Choose a JPEG, PNG, or WebP picture.');
            return;
        }

        if (file.size > MAX_SOURCE_FILE_SIZE) {
            setMessage('The selected picture must not exceed 10 MB.');
            return;
        }

        revokeSourceObjectUrl();
        sourceObjectUrl = URL.createObjectURL(file);
        selectedMode = 'upload';
        selectedChoice = 0;
        selectedPreviewUrl = '';
        setSelectedAvatarButton(0);
        setApplyEnabled(false);
        setMessage('');

        if (galleryPanel) {
            galleryPanel.hidden = true;
        }

        if (cropPanel) {
            cropPanel.hidden = false;
        }

        cropImage.onload = initialiseCropImage;
        cropImage.onerror = function () {
            setMessage('The selected picture could not be loaded.');
            setApplyEnabled(false);
        };
        cropImage.src = sourceObjectUrl;
    }

    function createCroppedAvatar() {
        const metrics = getCropMetrics();
        const currentScale = baseScale * zoom;
        const sourceX = Math.max(0, (metrics.cropLeft - imageLeft) / currentScale);
        const sourceY = Math.max(0, (metrics.cropTop - imageTop) / currentScale);
        const sourceSize = metrics.cropSize / currentScale;
        const canvas = document.createElement('canvas');
        const context = canvas.getContext('2d');

        canvas.width = OUTPUT_SIZE;
        canvas.height = OUTPUT_SIZE;

        if (!context) {
            throw new Error('Your browser could not prepare the profile picture.');
        }

        context.imageSmoothingEnabled = true;
        context.imageSmoothingQuality = 'high';
        context.drawImage(
            cropImage,
            sourceX,
            sourceY,
            sourceSize,
            sourceSize,
            0,
            0,
            OUTPUT_SIZE,
            OUTPUT_SIZE
        );

        return canvas.toDataURL('image/jpeg', 0.9);
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
        revokeSourceObjectUrl();
        openButton.focus();
    }

    function openPicker() {
        captureOpenSnapshot();
        showGallery();
        modal.hidden = false;
        document.body.classList.add('avatar-picker-open');

        window.requestAnimationFrame(function () {
            const firstOption = gallery.querySelector('.boringAvatarOption');

            if (firstOption) {
                firstOption.focus();
            }
        });
    }

    buildAvatarGallery();

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
        backButton.addEventListener('click', function () {
            revokeSourceObjectUrl();
            showGallery();
        });
    }

    function setZoom(nextZoom) {
        zoom = clamp(nextZoom, minimumZoom, 3);
        zoomInput.value = String(zoom);
        renderCropImage();
    }

    zoomInput.addEventListener('input', function () {
        setZoom(Number(zoomInput.value));
    });

    if (zoomOutButton) {
        zoomOutButton.addEventListener('click', function () {
            setZoom(zoom - 0.15);
        });
    }

    if (zoomInButton) {
        zoomInButton.addEventListener('click', function () {
            setZoom(zoom + 0.1);
        });
    }

    cropViewport.addEventListener('wheel', function (event) {
        if (!naturalWidth || !naturalHeight) {
            return;
        }

        event.preventDefault();
        setZoom(zoom + (event.deltaY < 0 ? 0.08 : -0.08));
    }, { passive: false });

    cropViewport.addEventListener('pointerdown', function (event) {
        if (!naturalWidth || !naturalHeight) {
            return;
        }

        dragState = {
            pointerId: event.pointerId,
            startX: event.clientX,
            startY: event.clientY,
            offsetX: offsetX,
            offsetY: offsetY
        };
        cropViewport.setPointerCapture(event.pointerId);
        cropViewport.classList.add('is-dragging');
    });

    cropViewport.addEventListener('pointermove', function (event) {
        if (!dragState || dragState.pointerId !== event.pointerId) {
            return;
        }

        offsetX = dragState.offsetX + event.clientX - dragState.startX;
        offsetY = dragState.offsetY + event.clientY - dragState.startY;
        renderCropImage();
    });

    function stopDragging(event) {
        if (!dragState || dragState.pointerId !== event.pointerId) {
            return;
        }

        dragState = null;
        cropViewport.classList.remove('is-dragging');
    }

    cropViewport.addEventListener('pointerup', stopDragging);
    cropViewport.addEventListener('pointercancel', stopDragging);

    if (applyButton) {
        applyButton.addEventListener('click', function () {
            try {
                let previewUrl = selectedPreviewUrl;

                if (selectedMode === 'boring') {
                    avatarAction.value = 'boring';
                    avatarChoice.value = String(selectedChoice);
                    avatarData.value = '';
                } else if (selectedMode === 'upload') {
                    previewUrl = createCroppedAvatar();
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
