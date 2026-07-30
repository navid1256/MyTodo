(function() {
  /*
Inspired by dribble.com/shots/1507858-Dashboard
*/
  var userMenuToggle = document.getElementById('userMenuToggle');
  var profileDropdown = document.getElementById('profileDropdown');
  var profileMenu = document.querySelector('.profileMenu');
  var navigationItems = document.querySelectorAll('.navigation-list li');
  var themeToggle = document.getElementById('themeToggle');
  var themeIcon = document.getElementById('themeIcon');
  var themeLabel = document.getElementById('themeLabel');

  function applyTheme(isDarkMode, shouldSave) {
    document.documentElement.classList.toggle('dark-mode', isDarkMode);

    if (themeToggle) {
      themeToggle.setAttribute('aria-pressed', String(isDarkMode));
    }

    if (themeLabel) {
      themeLabel.textContent = isDarkMode ? 'Light Mode' : 'Dark Mode';
    }

    if (themeIcon) {
      themeIcon.classList.toggle('fa-moon', !isDarkMode);
      themeIcon.classList.toggle('fa-sun', isDarkMode);
    }

    if (shouldSave) {
      try {
        localStorage.setItem('mytodo-theme', isDarkMode ? 'dark' : 'light');
      } catch (error) {
        // Theme switching still works when browser storage is unavailable.
      }
    }
  }

  applyTheme(document.documentElement.classList.contains('dark-mode'), false);

  if (themeToggle) {
    themeToggle.addEventListener('click', function() {
      var isDarkMode = !document.documentElement.classList.contains('dark-mode');
      applyTheme(isDarkMode, true);
    });
  }

  function activateNavigationItem(activeItem) {
    navigationItems.forEach(function(item) {
      var control = item.querySelector('a, button');
      var isActive = item === activeItem;

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

  if (navigationItems.length) {
    var savedNavigationId = null;
    var serverActiveItem = document.querySelector('.navigation-list li.active');

    try {
      savedNavigationId = sessionStorage.getItem('activeNavigationItem');
    } catch (error) {
      savedNavigationId = null;
    }

    if (serverActiveItem) {
      activateNavigationItem(serverActiveItem);
    } else if (savedNavigationId) {
      var savedNavigationItem = document.querySelector(
        '.navigation-list li[data-nav-id="' + savedNavigationId + '"]'
      );

      if (savedNavigationItem) {
        activateNavigationItem(savedNavigationItem);
      }
    }

    navigationItems.forEach(function(item) {
      var control = item.querySelector('a, button');

      if (!control) {
        return;
      }

      control.addEventListener('click', function() {
        activateNavigationItem(item);

        try {
          sessionStorage.setItem('activeNavigationItem', item.dataset.navId);
        } catch (error) {
          // The active color still works when browser storage is unavailable.
        }
      });
    });
  }

  if (!userMenuToggle || !profileDropdown || !profileMenu) {
    return;
  }

  function setProfileMenuState(isOpen) {
    userMenuToggle.setAttribute('aria-expanded', String(isOpen));
    profileDropdown.hidden = !isOpen;
  }

  userMenuToggle.addEventListener('click', function() {
    var isOpen = userMenuToggle.getAttribute('aria-expanded') === 'true';
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

}).call(this);

//# sourceMappingURL=data:application/json;base64,eyJ2ZXJzaW9uIjozLCJmaWxlIjoiIiwic291cmNlUm9vdCI6IiIsInNvdXJjZXMiOlsiPGFub255bW91cz4iXSwibmFtZXMiOltdLCJtYXBwaW5ncyI6IkFBQUE7RUFBQTs7OztBQUFBIiwic291cmNlc0NvbnRlbnQiOlsiIyMjXG5JbnNwaXJlZCBieSBkcmliYmxlLmNvbS9zaG90cy8xNTA3ODU4LURhc2hib2FyZFxuIyMjIl19
//# sourceURL=coffeescript
