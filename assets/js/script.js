(function() {
  /*
Inspired by dribble.com/shots/1507858-Dashboard
*/
  var userMenuToggle = document.getElementById('userMenuToggle');
  var profileDropdown = document.getElementById('profileDropdown');
  var profileMenu = document.querySelector('.profileMenu');

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
