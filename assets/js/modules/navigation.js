export function initNavigation() {
  const navigationItems = document.querySelectorAll('.navigation-list li');

  function activateNavigationItem(activeItem) {
    navigationItems.forEach(function (item) {
      const control = item.querySelector('a, button');
      const isActive = item === activeItem;

      item.classList.toggle('active', isActive);

      if (control) {
        if (isActive) {
          control.setAttribute('aria-current', 'page');
        } else {
          control.removeAttribute('aria-current');
        }
      }
    });
  }

  if (!navigationItems.length) {
    return;
  }

  let savedNavigationId = null;
  const serverActiveItem = document.querySelector('.navigation-list li.active');

  try {
    savedNavigationId = sessionStorage.getItem('activeNavigationItem');
  } catch (error) {
    savedNavigationId = null;
  }

  if (serverActiveItem) {
    activateNavigationItem(serverActiveItem);
  } else if (savedNavigationId) {
    const savedNavigationItem = document.querySelector(
      '.navigation-list li[data-nav-id="' + savedNavigationId + '"]'
    );

    if (savedNavigationItem) {
      activateNavigationItem(savedNavigationItem);
    }
  }

  navigationItems.forEach(function (item) {
    const control = item.querySelector('a, button');

    if (!control) {
      return;
    }

    control.addEventListener('click', function () {
      activateNavigationItem(item);

      try {
        sessionStorage.setItem('activeNavigationItem', item.dataset.navId);
      } catch (error) {
        // The active color still works when browser storage is unavailable.
      }
    });
  });
}
