export function initProfileMenu() {
  const userMenuToggle = document.getElementById('userMenuToggle');
  const profileDropdown = document.getElementById('profileDropdown');
  const profileMenu = document.querySelector('.profileMenu');

  if (!userMenuToggle || !profileDropdown || !profileMenu) {
    return;
  }

  function setProfileMenuState(isOpen) {
    userMenuToggle.setAttribute('aria-expanded', String(isOpen));
    profileDropdown.hidden = !isOpen;
  }

  userMenuToggle.addEventListener('click', function() {
    const isOpen = userMenuToggle.getAttribute('aria-expanded') === 'true';
    setProfileMenuState(!isOpen);
  });

  document.addEventListener('click', function(event) {
    if (!profileMenu.contains(event.target)) {
      setProfileMenuState(false);
    }
  });

  document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape' && userMenuToggle.getAttribute('aria-expanded') === 'true') {
      setProfileMenuState(false);
      userMenuToggle.focus();
    }
  });
}
