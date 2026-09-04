import { createBoringBeamAvatarDataUrl } from '../../vendor/boring-avatar.js';
import { translate } from '../../utils/i18n.js';

const AVATAR_OPTION_COUNT = 12;

export function createAvatarGallery(options) {
    const gallery = options.element;
    const seedBase = options.seedBase;

    function setSelectedChoice(choice) {
        gallery.querySelectorAll('.boringAvatarOption').forEach(function (button) {
            const isSelected = Number(button.dataset.avatarChoice) === choice;
            button.classList.toggle('is-selected', isSelected);
            button.setAttribute('aria-checked', String(isSelected));
        });
    }

    function build() {
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
            button.setAttribute(
                'aria-label',
                translate('profile.avatar.choose_option', { choice }, `Choose avatar ${choice}`)
            );
            image.src = imageUrl;
            image.alt = '';
            image.width = 76;
            image.height = 76;
            button.appendChild(image);

            button.addEventListener('click', function () {
                setSelectedChoice(choice);
                options.onSelect({
                    choice,
                    previewUrl: imageUrl
                });
            });

            gallery.appendChild(button);
        }
    }

    function clearSelection() {
        setSelectedChoice(0);
    }

    function focusFirst() {
        const firstOption = gallery.querySelector('.boringAvatarOption');

        if (firstOption) {
            firstOption.focus();
        }
    }

    build();

    return {
        clearSelection,
        focusFirst,
        setSelectedChoice
    };
}
