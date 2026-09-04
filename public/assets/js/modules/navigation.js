import { applyTranslations, translate } from '../utils/i18n.js';

const DASHBOARD_AJAX_VIEWS = ['home', 'activity', 'manage-tasks', 'messages'];

export function getDashboardNavigationView(targetUrl, baseUrl) {
  try {
    const base = new URL(baseUrl);
    const target = new URL(targetUrl, base);

    if (target.origin !== base.origin) {
      return null;
    }

    const path = target.pathname.replace(/\/+$/, '') || '/';
    const pathToView = {
      '/': 'home',
      '/home': 'home',
      '/activity': 'activity',
      '/manage-tasks': 'manage-tasks',
      '/messages': 'messages'
    };

    if (pathToView[path]) {
      return pathToView[path];
    }

    const view = target.searchParams.get('view');
    return (view && DASHBOARD_AJAX_VIEWS.includes(view)) ? view : null;
  } catch (error) {
    return null;
  }
}

export function initNavigation(options) {
  const navigationItems = document.querySelectorAll('.navigation-list li');
  const onViewLoaded = options && typeof options.onViewLoaded === 'function'
    ? options.onViewLoaded
    : function () { };
  let activeRequestController = null;

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

  function activateNavigationView(view) {
    const activeItem = document.querySelector(
      '.navigation-list li[data-nav-id="' + view + '"]'
    );

    if (activeItem) {
      activateNavigationItem(activeItem);
    }
  }

  function updateStylesheet(id, href) {
    const existingLink = document.getElementById(id);

    if (!href) {
      if (existingLink) {
        existingLink.remove();
      }

      return Promise.resolve();
    }

    const absoluteHref = new URL(href, document.baseURI).href;

    if (existingLink && existingLink.href === absoluteHref) {
      return Promise.resolve();
    }

    return new Promise(function (resolve, reject) {
      const link = existingLink || document.createElement('link');

      link.id = id;
      link.rel = 'stylesheet';
      link.addEventListener('load', resolve, { once: true });
      link.addEventListener('error', reject, { once: true });

      if (!existingLink) {
        const themeStylesheet = document.querySelector('link[href$="assets/css/theme.css"]');
        document.head.insertBefore(link, themeStylesheet || null);
      }

      link.href = href;
    });
  }

  function updateNotificationBadge(count) {
    const notificationButton = document.querySelector('.notificationButton');

    if (!notificationButton) {
      return;
    }

    const safeCount = Math.max(0, Number(count) || 0);
    let badge = notificationButton.querySelector('.notificationBadge');

    notificationButton.setAttribute('aria-label', translate('header.sent_notifications', { count: safeCount }));

    if (safeCount === 0) {
      if (badge) {
        badge.remove();
      }

      return;
    }

    if (!badge) {
      badge = document.createElement('span');
      badge.className = 'notificationBadge';
      notificationButton.appendChild(badge);
    }

    badge.textContent = safeCount > 99 ? '99+' : String(safeCount);
  }

  function replaceDashboardView(html) {
    const currentView = document.getElementById('tasks');
    const template = document.createElement('template');

    template.innerHTML = String(html).trim();
    const nextView = template.content.firstElementChild;

    if (!currentView || !nextView || nextView.id !== 'tasks') {
      throw new Error(translate('navigation.view_invalid', {}, 'The requested dashboard view is invalid.'));
    }

    currentView.replaceWith(nextView);

    return nextView;
  }

  async function loadDashboardView(targetUrl, shouldPushHistory) {
    const target = new URL(targetUrl, window.location.href);
    const view = getDashboardNavigationView(target.href, window.location.href);

    if (!view) {
      window.location.assign(target.href);
      return;
    }

    if (activeRequestController) {
      activeRequestController.abort();
    }

    const requestController = new AbortController();
    activeRequestController = requestController;
    const currentView = document.getElementById('tasks');

    if (currentView) {
      currentView.setAttribute('aria-busy', 'true');
    }

    try {
      const requestUrl = new URL(target.href);
      requestUrl.hash = '';
      requestUrl.searchParams.set('partial', '1');

      const response = await fetch(requestUrl.href, {
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        signal: requestController.signal
      });

      if (!response.ok) {
        throw new Error(translate('navigation.view_load_failed', {}, 'The dashboard view could not be loaded.'));
      }

      const payload = await response.json();

      if (!payload || payload.activeView !== view || typeof payload.html !== 'string') {
        throw new Error(translate('navigation.response_invalid', {}, 'The dashboard response is invalid.'));
      }

      await updateStylesheet('taskModalStylesheet', payload.taskModalStylesheet);
      await updateStylesheet('activePageStylesheet', payload.pageStylesheet);

      if (requestController.signal.aborted) {
        return;
      }

      const nextView = replaceDashboardView(payload.html);
      document.body.dataset.activeView = payload.activeView;
      document.body.dataset.renderTimezone = payload.renderTimezone || '';
      document.body.dataset.timezonePersisted = payload.timezoneIsPersisted ? '1' : '0';
      document.body.dataset.effectiveLanguage = payload.effectiveLanguage || 'english';
      document.body.dataset.calendarSystem = payload.calendarSystem === 'jalali' ? 'jalali' : 'gregorian';
      document.documentElement.lang = payload.effectiveLanguage === 'persian' ? 'fa' : 'en';
      document.documentElement.dir = payload.direction === 'rtl' ? 'rtl' : 'ltr';
      applyTranslations(payload.translations);
      document.body.dataset.renderDate = payload.renderDate || '';
      activateNavigationView(payload.activeView);
      updateNotificationBadge(payload.sentNotificationCount);

      try {
        sessionStorage.setItem('activeNavigationItem', payload.activeView);
      } catch (error) {
        // Navigation remains functional when browser storage is unavailable.
      }

      if (shouldPushHistory) {
        window.history.pushState({ dashboardView: payload.activeView }, '', target.href);
      }

      onViewLoaded(payload);

      nextView.removeAttribute('aria-busy');
      nextView.setAttribute('tabindex', '-1');
      nextView.focus({ preventScroll: target.hash === '' });
      nextView.removeAttribute('tabindex');

      if (target.hash === '#tasks') {
        nextView.scrollIntoView({ block: 'start' });
      }
    } catch (error) {
      if (error.name !== 'AbortError') {
        window.location.assign(target.href);
      }
    } finally {
      if (activeRequestController === requestController) {
        activeRequestController = null;
        const activeView = document.getElementById('tasks');

        if (activeView) {
          activeView.removeAttribute('aria-busy');
        }
      }
    }
  }

  if (!navigationItems.length) {
    return;
  }

  const serverActiveItem = document.querySelector('.navigation-list li.active');

  if (serverActiveItem) {
    activateNavigationItem(serverActiveItem);
  }

  navigationItems.forEach(function (item) {
    const control = item.querySelector('a');

    if (!control) {
      return;
    }

    control.addEventListener('click', function (event) {
      if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
      }

      const view = getDashboardNavigationView(control.href, window.location.href);

      if (!view) {
        return;
      }

      event.preventDefault();

      if (document.body.dataset.activeView === view) {
        activateNavigationItem(item);
        return;
      }

      loadDashboardView(control.href, true);
    });
  });

  document.addEventListener('click', function (event) {
    const dashboardBackLink = event.target.closest('a[data-dashboard-back]');

    if (dashboardBackLink
      && event.button === 0
      && !event.metaKey
      && !event.ctrlKey
      && !event.shiftKey
      && !event.altKey
      && window.history.length > 1) {
      event.preventDefault();
      window.history.back();
      return;
    }

    const dashboardLink = event.target.closest('a[data-dashboard-link]');

    if (!dashboardLink
      || event.button !== 0
      || event.metaKey
      || event.ctrlKey
      || event.shiftKey
      || event.altKey) {
      return;
    }

    const view = getDashboardNavigationView(dashboardLink.href, window.location.href);

    if (!view) {
      return;
    }

    event.preventDefault();
    loadDashboardView(dashboardLink.href, true);
  });

  window.history.replaceState(
    { dashboardView: document.body.dataset.activeView || null },
    '',
    window.location.href
  );

  window.addEventListener('popstate', function () {
    const view = getDashboardNavigationView(window.location.href, window.location.href);

    if (!view) {
      window.location.reload();
      return;
    }

    loadDashboardView(window.location.href, false);
  });
}
