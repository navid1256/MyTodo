import { translate } from '../../utils/i18n.js';

const MAX_SOURCE_FILE_SIZE = 10 * 1024 * 1024;
const OUTPUT_SIZE = 512;
const CANVAS_QUALITY = 0.9;
const MAXIMUM_ZOOM = 3;
const ZOOM_BUTTON_STEP = 0.1;
const ZOOM_WHEEL_STEP = 0.08;
const ZOOM_SLIDER_LIMIT = 100;
const ALLOWED_FILE_TYPES = new Set(['image/jpeg', 'image/png', 'image/webp', 'image/jpg']);

function clamp(value, minimum, maximum) {
    return Math.min(Math.max(value, minimum), maximum);
}

export function createAvatarCropper(options) {
    const cropViewport = options.viewport;
    const cropImage = options.image;
    const cropRing = options.ring;
    const zoomInput = options.zoomInput;
    const zoomOutButton = options.zoomOutButton;
    const zoomInButton = options.zoomInButton;
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

    function revokeSourceObjectUrl() {
        if (sourceObjectUrl) {
            URL.revokeObjectURL(sourceObjectUrl);
            sourceObjectUrl = '';
        }
    }

    function getCropMetrics() {
        const stageWidth = cropViewport.clientWidth || 420;
        const stageHeight = cropViewport.clientHeight || 300;
        const cropSize = cropRing ? cropRing.clientWidth : Math.min(240, stageWidth, stageHeight);

        return {
            stageWidth,
            stageHeight,
            cropSize,
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
            options.setMessage(translate('profile.avatar.load_failed', {}, 'The selected picture could not be loaded.'));
            options.setApplyEnabled(false);
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
        zoomInput.value = '0';
        zoomInput.setAttribute('aria-valuetext', '100%');
        renderCropImage();
        options.setApplyEnabled(true);
    }

    function loadFile(file) {
        if (!ALLOWED_FILE_TYPES.includes(file.type)) {
            options.setMessage(translate('profile.avatar.invalid_type', {}, 'Choose a JPEG, PNG, JPG or WebP picture.'));
            return false;
        }

        if (file.size > MAX_SOURCE_FILE_SIZE) {
            options.setMessage(translate('profile.avatar.source_too_large', {}, 'The selected picture must not exceed 10 MB.'));
            return false;
        }

        revokeSourceObjectUrl();
        sourceObjectUrl = URL.createObjectURL(file);
        options.setApplyEnabled(false);
        options.setMessage('');

        cropImage.onload = initialiseCropImage;
        cropImage.onerror = function () {
            options.setMessage(translate('profile.avatar.load_failed', {}, 'The selected picture could not be loaded.'));
            options.setApplyEnabled(false);
        };
        cropImage.src = sourceObjectUrl;

        return true;
    }

    function createAvatarDataUrl() {
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
            throw new Error(translate(
                'profile.avatar.browser_prepare_failed',
                {},
                'Your browser could not prepare the profile picture.'
            ));
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

        return canvas.toDataURL('image/jpeg', CANVAS_QUALITY);
    }

    function zoomFromSliderPosition(position) {
        const safePosition = clamp(position, -ZOOM_SLIDER_LIMIT, ZOOM_SLIDER_LIMIT);

        if (safePosition < 0) {
            return 1 + (1 - minimumZoom) * (safePosition / ZOOM_SLIDER_LIMIT);
        }

        return 1 + (MAXIMUM_ZOOM - 1) * (safePosition / ZOOM_SLIDER_LIMIT);
    }

    function sliderPositionFromZoom(nextZoom) {
        if (nextZoom < 1 && minimumZoom < 1) {
            return -ZOOM_SLIDER_LIMIT * ((1 - nextZoom) / (1 - minimumZoom));
        }

        return ZOOM_SLIDER_LIMIT * ((nextZoom - 1) / (MAXIMUM_ZOOM - 1));
    }

    function updateZoomControl() {
        zoomInput.value = String(sliderPositionFromZoom(zoom));
        zoomInput.setAttribute('aria-valuetext', `${Math.round(zoom * 100)}%`);
    }

    function setZoom(nextZoom) {
        zoom = clamp(nextZoom, minimumZoom, MAXIMUM_ZOOM);
        updateZoomControl();
        renderCropImage();
    }

    function stopDragging(event) {
        if (!dragState || dragState.pointerId !== event.pointerId) {
            return;
        }

        dragState = null;
        cropViewport.classList.remove('is-dragging');
    }

    function reset() {
        revokeSourceObjectUrl();
        naturalWidth = 0;
        naturalHeight = 0;
        baseScale = 1;
        minimumZoom = 1;
        zoom = 1;
        offsetX = 0;
        offsetY = 0;
        displayedWidth = 0;
        displayedHeight = 0;
        imageLeft = 0;
        imageTop = 0;
        dragState = null;
        cropImage.onload = null;
        cropImage.onerror = null;
        cropImage.removeAttribute('src');
        cropImage.removeAttribute('style');
        cropViewport.classList.remove('is-dragging');
        zoomInput.value = '0';
        zoomInput.setAttribute('aria-valuetext', '100%');
    }

    zoomInput.addEventListener('input', function () {
        zoom = zoomFromSliderPosition(Number(zoomInput.value));
        zoomInput.setAttribute('aria-valuetext', `${Math.round(zoom * 100)}%`);
        renderCropImage();
    });

    if (zoomOutButton) {
        zoomOutButton.addEventListener('click', function () {
            setZoom(zoom - ZOOM_BUTTON_STEP);
        });
    }

    if (zoomInButton) {
        zoomInButton.addEventListener('click', function () {
            setZoom(zoom + ZOOM_BUTTON_STEP);
        });
    }

    cropViewport.addEventListener('wheel', function (event) {
        if (!naturalWidth || !naturalHeight) {
            return;
        }

        event.preventDefault();
        setZoom(zoom + (event.deltaY < 0 ? ZOOM_WHEEL_STEP : -ZOOM_WHEEL_STEP));
    }, { passive: false });

    cropViewport.addEventListener('pointerdown', function (event) {
        if (!naturalWidth || !naturalHeight) {
            return;
        }

        dragState = {
            pointerId: event.pointerId,
            startX: event.clientX,
            startY: event.clientY,
            offsetX,
            offsetY
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

    cropViewport.addEventListener('pointerup', stopDragging);
    cropViewport.addEventListener('pointercancel', stopDragging);

    return {
        createAvatarDataUrl,
        loadFile,
        reset
    };
}
