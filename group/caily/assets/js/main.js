/**
 * Main
 */

'use strict';

/**
 * Tooltip giờ Việt Nam khi hover lên giờ Nhật. Định dạng: "VN DD/MM/YYYY HH:mm"
 * @param {string} jpDateTimeStr - Ngày giờ JST (VD: "2025-01-30 14:00:00" hoặc "2025/01/30 14:00")
 * @returns {string} "VN DD/MM/YYYY HH:mm" hoặc "" nếu không parse được
 */
window.formatVietnamTimeTooltip = function (jpDateTimeStr) {
  if (!jpDateTimeStr || typeof jpDateTimeStr !== 'string') return '';
  var s = String(jpDateTimeStr).trim().replace(/\//g, '-');
  if (!/^\d{4}-\d{2}-\d{2}/.test(s)) return '';
  var m = window.moment && window.moment.parseZone ? window.moment.parseZone(s + '+09:00') : null;
  if (!m || !m.isValid()) return '';
  var vn = m.clone().subtract(2, 'hours');
  return 'VN ' + vn.format('DD/MM/YYYY HH:mm');
};

window.isRtl = window.Helpers.isRtl();
window.isDarkStyle = window.Helpers.isDarkStyle();
let menu,
  animate,
  isHorizontalLayout = false;

if (document.getElementById('layout-menu')) {
  isHorizontalLayout = document.getElementById('layout-menu').classList.contains('menu-horizontal');
}
document.addEventListener('DOMContentLoaded', function () {
  // class for ios specific styles
  if (navigator.userAgent.match(/iPhone|iPad|iPod/i)) {
    document.body.classList.add('ios');
  }
});

(function () {
  // Window scroll function for navbar
  function onScroll() {
    var layoutPage = document.querySelector('.layout-page');
    if (layoutPage) {
      if (window.scrollY > 0) {
        layoutPage.classList.add('window-scrolled');
      } else {
        layoutPage.classList.remove('window-scrolled');
      }
    }
  }
  // On load time out
  setTimeout(() => {
    onScroll();
  }, 200);

  // On window scroll
  window.onscroll = function () {
    onScroll();
  };

  setTimeout(function () {
    window.Helpers.initCustomOptionCheck();
  }, 1000);

  // To remove russian country specific scripts from Sweet Alert 2
  if (
    typeof window !== 'undefined' &&
    /^ru\b/.test(navigator.language) &&
    location.host.match(/\.(ru|su|by|xn--p1ai)$/)
  ) {
    localStorage.removeItem('swal-initiation');

    document.body.style.pointerEvents = 'system';
    setInterval(() => {
      if (document.body.style.pointerEvents === 'none') {
        document.body.style.pointerEvents = 'system';
      }
    }, 100);
    HTMLAudioElement.prototype.play = function () {
      return Promise.resolve();
    };
  }

  if (typeof Waves !== 'undefined') {
    Waves.init();
    Waves.attach(
      ".btn[class*='btn-']:not(.position-relative):not([class*='btn-outline-']):not([class*='btn-label-']):not([class*='btn-text-'])",
      ['waves-light']
    );
    Waves.attach("[class*='btn-outline-']:not(.position-relative)");
    Waves.attach("[class*='btn-label-']:not(.position-relative)");
    Waves.attach("[class*='btn-text-']:not(.position-relative)");
    Waves.attach('.pagination:not([class*="pagination-outline-"]) .page-item.active .page-link', ['waves-light']);
    Waves.attach('.pagination .page-item .page-link');
    Waves.attach('.dropdown-menu .dropdown-item');
    Waves.attach('[data-bs-theme="light"] .list-group .list-group-item-action');
    Waves.attach('[data-bs-theme="dark"] .list-group .list-group-item-action', ['waves-light']);
    Waves.attach('.nav-tabs:not(.nav-tabs-widget) .nav-item .nav-link');
    Waves.attach('.nav-pills .nav-item .nav-link', ['waves-light']);
  }

  // Initialize menu
  //-----------------

  let layoutMenuEl = document.querySelectorAll('#layout-menu');
  layoutMenuEl.forEach(function (element) {
    menu = new Menu(element, {
      orientation: isHorizontalLayout ? 'horizontal' : 'vertical',
      closeChildren: isHorizontalLayout ? true : false,
      // ? This option only works with Horizontal menu
      showDropdownOnHover: localStorage.getItem('templateCustomizer-' + templateName + '--ShowDropdownOnHover') // If value(showDropdownOnHover) is set in local storage
        ? localStorage.getItem('templateCustomizer-' + templateName + '--ShowDropdownOnHover') === 'true' // Use the local storage value
        : window.templateCustomizer !== undefined // If value is set in config.js
          ? window.templateCustomizer.settings.defaultShowDropdownOnHover // Use the config.js value
          : true // Use this if you are not using the config.js and want to set value directly from here
    });
    // Change parameter to true if you want scroll animation
    window.Helpers.scrollToActive((animate = false));
    window.Helpers.mainMenu = menu;
  });

  // Initialize menu togglers and bind click on each
  let menuToggler = document.querySelectorAll('.layout-menu-toggle');
  menuToggler.forEach(item => {
    item.addEventListener('click', event => {
      event.preventDefault();
      window.Helpers.toggleCollapsed();
      // Enable menu state with local storage support if enableMenuLocalStorage = true from config.js
      if (config.enableMenuLocalStorage && !window.Helpers.isSmallScreen()) {
        try {
          localStorage.setItem(
            'templateCustomizer-' + templateName + '--LayoutCollapsed',
            String(window.Helpers.isCollapsed())
          );
          // Update customizer checkbox state on click of menu toggler
          let layoutCollapsedCustomizerOptions = document.querySelector('.template-customizer-layouts-options');
          if (layoutCollapsedCustomizerOptions) {
            let layoutCollapsedVal = window.Helpers.isCollapsed() ? 'collapsed' : 'expanded';
            layoutCollapsedCustomizerOptions.querySelector(`input[value="${layoutCollapsedVal}"]`).click();
          }
        } catch (e) {}
      }
    });
  });

  // Menu swipe gesture

  // Detect swipe gesture on the target element and call swipe In
  window.Helpers.swipeIn('.drag-target', function (e) {
    window.Helpers.setCollapsed(false);
  });

  // Detect swipe gesture on the target element and call swipe Out
  window.Helpers.swipeOut('#layout-menu', function (e) {
    if (window.Helpers.isSmallScreen()) window.Helpers.setCollapsed(true);
  });

  // Display in main menu when menu scrolls
  let menuInnerContainer = document.getElementsByClassName('menu-inner'),
    menuInnerShadow = document.getElementsByClassName('menu-inner-shadow')[0];
  if (menuInnerContainer.length > 0 && menuInnerShadow) {
    menuInnerContainer[0].addEventListener('ps-scroll-y', function () {
      if (this.querySelector('.ps__thumb-y').offsetTop) {
        menuInnerShadow.style.display = 'block';
      } else {
        menuInnerShadow.style.display = 'none';
      }
    });
  }

  // Get style from local storage or use 'system' as default
  let storedStyle =
    localStorage.getItem('templateCustomizer-' + templateName + '--Theme') || // if no template style then use Customizer style
    (window.templateCustomizer?.settings?.defaultStyle ?? document.documentElement.getAttribute('data-bs-theme')); //!if there is no Customizer then use default style as light

  // Run switchImage function based on the stored style
  window.Helpers.switchImage(storedStyle);

  // Update light/dark image based on current style
  window.Helpers.setTheme(window.Helpers.getPreferredTheme());

  window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
    const storedTheme = window.Helpers.getStoredTheme();
    if (storedTheme !== 'light' && storedTheme !== 'dark') {
      window.Helpers.setTheme(window.Helpers.getPreferredTheme());
    }
  });

  function getScrollbarWidth() {
    const scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
    document.body.style.setProperty('--bs-scrollbar-width', `${scrollbarWidth}px`);
  }
  getScrollbarWidth();
  window.addEventListener('DOMContentLoaded', () => {
    window.Helpers.showActiveTheme(window.Helpers.getPreferredTheme());
    getScrollbarWidth();
    // Toggle Universal Sidebar
    window.Helpers.initSidebarToggle();
    document.querySelectorAll('[data-bs-theme-value]').forEach(toggle => {
      toggle.addEventListener('click', () => {
        const theme = toggle.getAttribute('data-bs-theme-value');
        window.Helpers.setStoredTheme(templateName, theme);
        window.Helpers.setTheme(theme);
        window.Helpers.showActiveTheme(theme, true);
        window.Helpers.syncCustomOptions(theme);
        let currTheme = theme;
        if (theme === 'system') {
          currTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        const semiDarkL = document.querySelector('.template-customizer-semiDark');
        if (semiDarkL) {
          if (theme === 'dark') {
            semiDarkL.classList.add('d-none');
          } else {
            semiDarkL.classList.remove('d-none');
          }
        }
        window.Helpers.switchImage(currTheme);
      });
    });
  });

  // Internationalization (Language Dropdown)
  // ---------------------------------------

  if (typeof i18next !== 'undefined' && typeof i18NextHttpBackend !== 'undefined') {
    i18next
      .use(i18NextHttpBackend)
      .init({
        lng: window.templateCustomizer ? window.templateCustomizer.settings.lang : 'en',
        debug: false,
        fallbackLng: 'en',
        backend: {
          loadPath: assetsPath + 'json/locales/{{lng}}.json?v=' + (window.cacheVersion || '')
        },
        returnObjects: true
      })
      .then(function (t) {
        localize();
      });
  }

  let languageDropdown = document.getElementsByClassName('dropdown-language');

  if (languageDropdown.length) {
    let dropdownItems = languageDropdown[0].querySelectorAll('.dropdown-item');

    for (let i = 0; i < dropdownItems.length; i++) {
      dropdownItems[i].addEventListener('click', function () {
        let currentLanguage = this.getAttribute('data-language');
        let textDirection = this.getAttribute('data-text-direction');
        const savedLang = localStorage.getItem('templateCustomizer-' + templateName + '--Lang');
        if (savedLang === currentLanguage) return;

        localStorage.setItem('templateCustomizer-' + templateName + '--Lang', currentLanguage);
        if (window.templateCustomizer) {
          try {
            window.templateCustomizer.setLang(currentLanguage, true, true);
          } catch (e) {}
        }
        directionChange(textDirection);
        if (window.Helpers && typeof window.Helpers.syncCustomOptionsRtl === 'function') {
          window.Helpers.syncCustomOptionsRtl(textDirection);
        }
        window.location.reload();
      });
    }
    function directionChange(textDirection) {
      document.documentElement.setAttribute('dir', textDirection);
      if (textDirection === 'rtl') {
        if (localStorage.getItem('templateCustomizer-' + templateName + '--Rtl') !== 'true')
          window.templateCustomizer ? window.templateCustomizer.setRtl(true) : '';
      } else {
        if (localStorage.getItem('templateCustomizer-' + templateName + '--Rtl') === 'true')
          window.templateCustomizer ? window.templateCustomizer.setRtl(false) : '';
      }
    }
  }

  function localize() {
    let i18nList = document.querySelectorAll('[data-i18n]');
    // Set the current language in dd
    let currentLanguageEle = document.querySelector('.dropdown-item[data-language="' + i18next.language + '"]');

    if (currentLanguageEle) {
      currentLanguageEle.click();
    }

    i18nList.forEach(function (item) {
      const key = item.dataset.i18n;
      const translated = i18next.t(key);
      // Nếu là input/textarea thì ưu tiên dịch placeholder, nếu không thì innerHTML như cũ
      if (item.tagName === 'INPUT' || item.tagName === 'TEXTAREA') {
        if (item.hasAttribute('placeholder')) {
          item.setAttribute('placeholder', translated || item.getAttribute('placeholder') || key);
        } else {
          item.value = translated || item.value || key;
        }
      } else {
        item.innerHTML = translated || key;
      }
      /* FIX: Uncomment the following line to hide elements with the i18n attribute before translation to prevent text change flicker */
      // item.style.visibility = 'visible';
    });
  }

  /** Gọi sau khi Vue (hoặc DOM động) đã vẽ xong để dịch [data-i18n] trong root (vd: #app). */
  window.applyDataI18n = function (rootElement) {
    var root = rootElement && rootElement.nodeType === 1 ? rootElement : document;
    var list = root.querySelectorAll('[data-i18n]');
    if (typeof i18next === 'undefined' || !i18next.t) return;
    list.forEach(function (item) {
      var key = item.dataset.i18n;
      var translated = i18next.t(key);
      if (item.tagName === 'INPUT' || item.tagName === 'TEXTAREA') {
        if (item.hasAttribute('placeholder')) {
          item.setAttribute('placeholder', translated || item.getAttribute('placeholder') || key);
        } else {
          item.value = translated || item.value || key;
        }
      } else {
        item.innerHTML = translated || key;
      }
    });
  };

  // Notification
  // ------------
  const notificationMarkAsReadAll = document.querySelector('.dropdown-notifications-all');
  const notificationMarkAsReadList = document.querySelectorAll('.dropdown-notifications-read');

  // Notification: Mark as all as read
  if (notificationMarkAsReadAll) {
    notificationMarkAsReadAll.addEventListener('click', event => {
      notificationMarkAsReadList.forEach(item => {
        item.closest('.dropdown-notifications-item').classList.add('marked-as-read');
      });
    });
  }
  // Notification: Mark as read/unread onclick of dot
  if (notificationMarkAsReadList) {
    notificationMarkAsReadList.forEach(item => {
      item.addEventListener('click', event => {
        item.closest('.dropdown-notifications-item').classList.toggle('marked-as-read');
      });
    });
  }

  // Notification: Mark as read/unread onclick of dot
  const notificationArchiveMessageList = document.querySelectorAll('.dropdown-notifications-archive');
  notificationArchiveMessageList.forEach(item => {
    item.addEventListener('click', event => {
      item.closest('.dropdown-notifications-item').remove();
    });
  });

  // Init helpers & misc
  // --------------------

  // Init BS Tooltip
  const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
  });

  // Accordion active class
  const accordionActiveFunction = function (e) {
    if (e.type == 'show.bs.collapse' || e.type == 'show.bs.collapse') {
      e.target.closest('.accordion-item').classList.add('active');
    } else {
      e.target.closest('.accordion-item').classList.remove('active');
    }
  };

  const accordionTriggerList = [].slice.call(document.querySelectorAll('.accordion'));
  const accordionList = accordionTriggerList.map(function (accordionTriggerEl) {
    accordionTriggerEl.addEventListener('show.bs.collapse', accordionActiveFunction);
    accordionTriggerEl.addEventListener('hide.bs.collapse', accordionActiveFunction);
  });

  // Auto update layout based on screen size
  window.Helpers.setAutoUpdate(true);

  // Toggle Password Visibility
  window.Helpers.initPasswordToggle();

  // Speech To Text
  window.Helpers.initSpeechToText();

  // Init PerfectScrollbar in Navbar Dropdown (i.e notification)
  window.Helpers.initNavbarDropdownScrollbar();

  let horizontalMenuTemplate = document.querySelector("[data-template^='horizontal-menu']");
  if (horizontalMenuTemplate) {
    // if screen size is small then set navbar fixed
    if (window.innerWidth < window.Helpers.LAYOUT_BREAKPOINT) {
      window.Helpers.setNavbarFixed('fixed');
    } else {
      window.Helpers.setNavbarFixed('');
    }
  }

  // On window resize listener
  // -------------------------
  window.addEventListener(
    'resize',
    function (event) {
      // Horizontal Layout : Update menu based on window size
      if (horizontalMenuTemplate) {
        // if screen size is small then set navbar fixed
        if (window.innerWidth < window.Helpers.LAYOUT_BREAKPOINT) {
          window.Helpers.setNavbarFixed('fixed');
        } else {
          window.Helpers.setNavbarFixed('');
        }
        setTimeout(function () {
          if (window.innerWidth < window.Helpers.LAYOUT_BREAKPOINT) {
            if (document.getElementById('layout-menu')) {
              if (document.getElementById('layout-menu').classList.contains('menu-horizontal')) {
                menu.switchMenu('vertical');
              }
            }
          } else {
            if (document.getElementById('layout-menu')) {
              if (document.getElementById('layout-menu').classList.contains('menu-vertical')) {
                menu.switchMenu('horizontal');
              }
            }
          }
        }, 100);
      }
    },
    true
  );

  // Manage menu expanded/collapsed with templateCustomizer & local storage
  //------------------------------------------------------------------

  // If current layout is horizontal OR current window screen is small (overlay menu) than return from here
  if (isHorizontalLayout || window.Helpers.isSmallScreen()) {
    return;
  }

  // If current layout is vertical and current window screen is > small
  // Auto update menu collapsed/expanded based on the themeConfig
  if (typeof window.templateCustomizer !== 'undefined') {
    if (window.templateCustomizer.settings.defaultMenuCollapsed) {
      window.Helpers.setCollapsed(true, false);
    } else {
      window.Helpers.setCollapsed(false, false);
    }

    if (window.templateCustomizer.settings.semiDark) {
      document.querySelector('#layout-menu').setAttribute('data-bs-theme', 'dark');
    }
  }

  // Manage menu expanded/collapsed state with local storage support If enableMenuLocalStorage = true in config.js
  if (typeof config !== 'undefined') {
    if (config.enableMenuLocalStorage) {
      try {
        if (localStorage.getItem('templateCustomizer-' + templateName + '--LayoutCollapsed') !== null)
          window.Helpers.setCollapsed(
            localStorage.getItem('templateCustomizer-' + templateName + '--LayoutCollapsed') === 'true',
            false
          );
      } catch (e) {}
    }
  }
})();

// Search Configuration
const SearchConfig = {
  container: '#autocomplete',
  placeholder: window.__COMMAND_PALETTE_ENABLED ? 'Search' : 'Search [CTRL + K]',
  classNames: {
    detachedContainer: 'd-flex flex-column',
    detachedFormContainer: 'd-flex align-items-center justify-content-between border-bottom',
    form: 'd-flex align-items-center',
    input: 'search-control border-none',
    detachedCancelButton: 'btn-search-close',
    panel: 'flex-grow content-wrapper overflow-hidden position-relative',
    panelLayout: 'h-100',
    clearButton: 'd-none',
    item: 'd-block'
  }
};

// Search state and data
let data = {};
let currentFocusIndex = -1;

// Utils
function isMacOS() {
  return /Mac|iPod|iPhone|iPad/.test(navigator.userAgent);
}

// Load search data
function loadSearchData() {
  const searchJson = $('#layout-menu').hasClass('menu-horizontal') ? 'search-horizontal.json' : 'search-vertical.json';

  fetch(assetsPath + 'json/' + searchJson)
    .then(response => {
      if (!response.ok) throw new Error('Failed to fetch data');
      return response.json();
    })
    .then(json => {
      data = json;
      initializeAutocomplete();
    })
    .catch(error => console.error('Error loading JSON:', error));
}

// Initialize autocomplete
function initializeAutocomplete() {
  const searchElement = document.getElementById('autocomplete');
  if (!searchElement) return;
  if (typeof autocomplete !== 'function') return;

  return autocomplete({
    ...SearchConfig,
    openOnFocus: true,
    onStateChange({ state, setQuery }) {
      // When autocomplete is opened
      if (state.isOpen) {
        // Hide body scroll and add padding to prevent layout shift
        document.body.style.overflow = 'hidden';
        document.body.style.paddingRight = 'var(--bs-scrollbar-width)';
        // Replace "Cancel" text with icon
        const cancelIcon = document.querySelector('.aa-DetachedCancelButton');
        if (cancelIcon) {
          cancelIcon.innerHTML =
            '<span class="text-body-secondary">[esc]</span> <span class="icon-base icon-md ti tabler-x text-heading"></span>';
        }

        // Perfect Scrollbar
        if (!window.autoCompletePS) {
          const panel = document.querySelector('.aa-Panel');
          if (panel) {
            window.autoCompletePS = new PerfectScrollbar(panel);
          }
        }
      } else {
        // When autocomplete is closed
        if (state.status === 'idle' && state.query) {
          setQuery('');
        }

        // Restore body scroll and padding when autocomplete is closed
        document.body.style.overflow = 'auto';
        document.body.style.paddingRight = '';
      }
    },
    render(args, root) {
      const { render, html, children, state } = args;

      // Initial Suggestions
      if (!state.query) {
        const initialSuggestions = html`
          <div class="p-5 p-lg-12">
            <div class="row g-4">
              ${Object.entries(data.suggestions || {}).map(
                ([section, items]) => html`
                  <div class="col-md-6 suggestion-section">
                    <p class="search-headings mb-2">${section}</p>
                    <div class="suggestion-items">
                      ${items.map(
                        item => html`
                          <a href="${item.url}" class="suggestion-item d-flex align-items-center">
                            <i class="icon-base ti ${item.icon}"></i>
                            <span>${item.name}</span>
                          </a>
                        `
                      )}
                    </div>
                  </div>
                `
              )}
            </div>
          </div>
        `;

        render(initialSuggestions, root);
        return;
      }

      // No items
      if (!args.sections.length) {
        render(
          html`
            <div class="search-no-results-wrapper">
              <div class="d-flex justify-content-center align-items-center h-100">
                <div class="text-center text-heading">
                  <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24">
                    <g
                      fill="none"
                      stroke="currentColor"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="0.6">
                      <path d="M14 3v4a1 1 0 0 0 1 1h4" />
                      <path d="M17 21H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7l5 5v11a2 2 0 0 1-2 2m-5-4h.01M12 11v3" />
                    </g>
                  </svg>
                  <h5 class="mt-2">No results found</h5>
                </div>
              </div>
            </div>
          `,
          root
        );
        return;
      }

      render(children, root);
      window.autoCompletePS?.update();
    },
    getSources() {
      const sources = [];

      // Add navigation sources if available
      if (data.navigation) {
        // Add other navigation sources first
        const navigationSources = Object.keys(data.navigation)
          .filter(section => section !== 'files' && section !== 'members')
          .map(section => ({
            sourceId: `nav-${section}`,
            getItems({ query }) {
              const items = data.navigation[section];
              if (!query) return items;
              return items.filter(item => item.name.toLowerCase().includes(query.toLowerCase()));
            },
            getItemUrl({ item }) {
              return item.url;
            },
            templates: {
              header({ items, html }) {
                if (items.length === 0) return null;
                return html`<span class="search-headings">${section}</span>`;
              },
              item({ item, html }) {
                return html`
                  <a href="${item.url}" class="d-flex justify-content-between align-items-center">
                    <span class="item-wrapper"><i class="icon-base ti ${item.icon}"></i>${item.name}</span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="20px" height="20px" viewBox="0 0 24 24">
                      <g
                        fill="none"
                        stroke="currentColor"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.8"
                        color="currentColor">
                        <path d="M11 6h4.5a4.5 4.5 0 1 1 0 9H4" />
                        <path d="M7 12s-3 2.21-3 3s3 3 3 3" />
                      </g>
                    </svg>
                  </a>
                `;
              }
            }
          }));
        sources.push(...navigationSources);

        // Add Files source second
        if (data.navigation.files) {
          sources.push({
            sourceId: 'files',
            getItems({ query }) {
              const items = data.navigation.files;
              if (!query) return items;
              return items.filter(item => item.name.toLowerCase().includes(query.toLowerCase()));
            },
            getItemUrl({ item }) {
              return item.url;
            },
            templates: {
              header({ items, html }) {
                if (items.length === 0) return null;
                return html`<span class="search-headings">Files</span>`;
              },
              item({ item, html }) {
                return html`
                  <a href="${item.url}" class="d-flex align-items-center position-relative px-4 py-2">
                    <div class="file-preview me-2">
                      <img src="${assetsPath}${item.src}" alt="${item.name}" class="rounded" width="42" />
                    </div>
                    <div class="flex-grow-1">
                      <h6 class="mb-0">${item.name}</h6>
                      <small class="text-body-secondary">${item.subtitle}</small>
                    </div>
                    ${item.meta
                      ? html`
                          <div class="position-absolute end-0 me-4">
                            <span class="text-body-secondary small">${item.meta}</span>
                          </div>
                        `
                      : ''}
                  </a>
                `;
              }
            }
          });
        }

        // Add Members source last
        if (data.navigation.members) {
          sources.push({
            sourceId: 'members',
            getItems({ query }) {
              const items = data.navigation.members;
              if (!query) return items;
              return items.filter(item => item.name.toLowerCase().includes(query.toLowerCase()));
            },
            getItemUrl({ item }) {
              return item.url;
            },
            templates: {
              header({ items, html }) {
                if (items.length === 0) return null;
                return html`<span class="search-headings">Members</span>`;
              },
              item({ item, html }) {
                return html`
                  <a href="${item.url}" class="d-flex align-items-center py-2 px-4">
                    <div class="avatar me-2">
                      <img src="${assetsPath}${item.src}" alt="${item.name}" class="rounded-circle" width="32" />
                    </div>
                    <div class="flex-grow-1">
                      <h6 class="mb-0">${item.name}</h6>
                      <small class="text-body-secondary">${item.subtitle}</small>
                    </div>
                  </a>
                `;
              }
            }
          });
        }
      }

      return sources;
    }
  });
}

// Initialize search shortcut (disabled when Command Palette uses Ctrl+K)
if (!window.__COMMAND_PALETTE_ENABLED) {
  document.addEventListener('keydown', event => {
    if ((event.ctrlKey || event.metaKey) && event.key === 'k') {
      event.preventDefault();
      document.querySelector('.aa-DetachedSearchButton')?.click();
    }
  });
}

// Load search data on page load (navbar search; skipped when Algolia bundle not loaded)
if (!window.__COMMAND_PALETTE_ENABLED && document.documentElement.querySelector('#autocomplete')) {
  if (typeof autocomplete === 'function') {
    loadSearchData();
  }
}

// Initialize the displayHourglass
function displayHourglass() {
  var optionSection = document.querySelector('#option-block');
  if (optionSection) {
    Block.hourglass('#option-block', {
      backgroundColor: 'rgba(' + window.Helpers.getCssVar('black-rgb') + ', 0)',
      svgSize: '40px',
      svgColor: config.colors.white,
      top: '0',
    });
  }
}
function hideHourglass() {
  var optionSection = document.querySelector('#option-block');
  if (optionSection) {
    Block.remove('#option-block', 300);
  }
}

// Add a custom addDays method to the Date prototype
Date.prototype.addDays = function (days) {
  const date = new Date(this.valueOf());
  date.setDate(date.getDate() + days);
  return date;
};

function formatTime(time) {
  var hour = parseInt(time / 60) < 10 ? `0${parseInt(time / 60)}` : `${parseInt(time / 60)}`;
  var minute = time % 60 < 10 ? `0${time % 60}` : `${time % 60}`;
  return `${hour}:${minute}`;
}

function addLeadingZero(number){
  number = parseInt(number);
  return number < 10 ? `0${number}` : `${number}`;
}

function handleErrors(response) {
  Swal.fire({
    title: 'Error!',
    text: response,
    icon: 'error',
    customClass: {
      confirmButton: 'btn btn-primary'
    },
    buttonsStyling: false
  })
  hideHourglass();
  throw new Error(response);
}
function handleSuccess(response) {
  Swal.fire({
    title: 'Success!',
    text: response,
    icon: 'success',
    customClass: {
      confirmButton: 'btn btn-primary'
    },
    buttonsStyling: false
  })
  hideHourglass();
}

function showMessage(message, isError = false) {
  Swal.fire({
    title: isError ? 'Error!' : 'Success!', 
    text: message,
    icon: isError ? 'error' : 'success',
    customClass: {
      confirmButton: 'btn btn-primary'
    },
    buttonsStyling: false
  })
  hideHourglass();
}


const _log = console.log;

function decodeHtmlEntities(str) {
  return str.replace(/&amp;/g, '&')
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&quot;/g, '"')
    .replace(/&#039;/g, "'");
}



function generatePermit($level = 'public', $option = null, $type = '', $element = null, $id = 1, $data = null) {
  $element.innerHTML = '';
  var $string = '';
  var $typeStr = '';

  App.level = $level;
		
  if (!Array.isArray($option)) {
    if ($level == 'public') {
      $option = ['公開', '非公開', '公開するグループ・ユーザーを設定'];
    } else {
      $option = ['許可', '登録者のみ', '許可するグループ・ユーザーを設定'];
    }
  }
  $option.forEach(function(value, key) {
    $string += `<option value="${key}">${value}</option>`;
  });
 
  if ($type == 1) {
    $typeStr = ', 1';
  } else {
    $typeStr = '';
  }
  var wrapId = 'selectedWrap' + '_' + $id;
  var string = `<div class="d-flex gap-2 align-items-center"><select name="${$level}_level" class="form-select">${$string}</select>&nbsp;
  <span class="operator btn btn-primary btn-search flex-shrink-0" id="${$level}search" onclick="App.permitlevelApi(this, '${$level}', '', '${wrapId}')">検索</span></div><div class="selected_items"></div>`;
  if ($element) {
    $element.innerHTML = string;
  }

  try {
    var $select = $element.querySelector(`select[name="${$level}_level"]`);
    var $button = $element.querySelector(`.btn-search`);
    var $selected = $element.querySelector(`.selected_items`);
    
    $selected.setAttribute('id', wrapId);
    if($data && $data[$level+ '_level']){
      $select.value = $data[$level + '_level'];
    }
    $button.style.display = 'none';
    if($data && $data[$level + '_level'] && $data[$level + '_level'] == 2){
      $button.style.display = 'inline';
    }
    $select.addEventListener('change', function() {
      if ($select.value != 2) {
        $button.style.display = 'none';
        $selected.innerHTML = '';
      } else {
        $button.style.display = 'inline';
        App.permitlistApi(null, $type, wrapId);
      }
    });
	} catch(e) {
		alert(e.message);
	}
  if($data && $data[$level + '_level'] == 2){
    appParse($level, 'group', $data, $element);
    appParse($level, 'user', $data, $element);
  }
}

function appParse($level, $type, $data, $element) {
  let $array = {};
  if(!$data || !$data[$level + '_' + $type]){
    return;
  }
  $array = $data[$level + '_' + $type];
  let $string = '';
  Object.entries($array).forEach(($value, $key) => {
    const $id = $level + $type + $value[0];
    $string += `<div><input type="checkbox" name="${$level}[${$type}][${$value[0]}]"`;
    $string += ` id="${$id}" value="${$value[1]}" checked="checked" />`;
    $string += `<label for="${$id}">${$value[1]}</label></div>`;
  });
  var $selected = $element.querySelector(`.selected_items`);
  $selected.innerHTML = $string;
}

function getAppLanguage() {
  try {
    if (typeof i18next !== 'undefined' && i18next.language) {
      return String(i18next.language);
    }
  } catch (e) { /* ignore */ }
  try {
    if (typeof templateName !== 'undefined') {
      return localStorage.getItem('templateCustomizer-' + templateName + '--Lang') || 'en';
    }
  } catch (e) { /* ignore */ }
  return 'en';
}

/** Avatar text uses カタカナ (user_ruby) when UI language is en or ja. */
function shouldUseAvatarRuby() {
  const lang = String(getAppLanguage() || '').toLowerCase();
  return lang === 'en' || lang === 'ja' || lang.indexOf('en-') === 0 || lang.indexOf('ja-') === 0;
}

function formatAvatarInitials(name) {
  if (!name) return '?';
  const text = String(name).trim();
  if (!text) return '?';
  const hasJapanese = /[\u3040-\u309f\u30a0-\u30ff\u4e00-\u9faf]/.test(text);
  if (hasJapanese) {
    return text.substring(0, 2);
  }
  const words = text.split(/\s+/);
  return words[words.length - 1] || text.charAt(0) || '?';
}

function resolveAvatarRuby(name, rubyOrMeta) {
  let ruby = '';
  let userid = '';
  if (typeof rubyOrMeta === 'string') {
    ruby = rubyOrMeta;
  } else if (rubyOrMeta && typeof rubyOrMeta === 'object') {
    ruby = rubyOrMeta.user_ruby || rubyOrMeta.ruby || '';
    userid = rubyOrMeta.userid || rubyOrMeta.user_id || rubyOrMeta.userId || '';
  }
  if (ruby && String(ruby).trim()) {
    return String(ruby).trim();
  }
  const map = (typeof window !== 'undefined') ? window.CAILY_AVATAR_RUBY : null;
  if (!map) return '';
  if (userid && map.byUserId && map.byUserId[userid]) {
    return map.byUserId[userid];
  }
  if (name && map.byRealname && map.byRealname[name]) {
    return map.byRealname[name];
  }
  return '';
}

/**
 * Avatar initials. When UI lang is en/ja and user_ruby exists, use ruby text.
 * @param {string|object} nameOrUser - display name, or user object with realname/user_name/user_ruby
 * @param {string|object} [rubyOrMeta] - ruby string or { user_ruby, userid }
 */
function getAvatarName(nameOrUser, rubyOrMeta) {
  let name = '';
  let meta = rubyOrMeta;
  if (nameOrUser && typeof nameOrUser === 'object') {
    name = nameOrUser.realname || nameOrUser.user_name || nameOrUser.name || '';
    meta = Object.assign({}, nameOrUser, (rubyOrMeta && typeof rubyOrMeta === 'object') ? rubyOrMeta : (typeof rubyOrMeta === 'string' ? { user_ruby: rubyOrMeta } : {}));
  } else {
    name = nameOrUser || '';
  }

  if (shouldUseAvatarRuby()) {
    const ruby = resolveAvatarRuby(name, meta);
    if (ruby) {
      // Full カタカナ text — do not truncate
      return ruby;
    }
  }
  return formatAvatarInitials(name);
}

/** Valid avatar filename only (skip empty / placeholders that 404). */
function isValidAvatarFilename(userImage) {
  if (userImage == null) return false;
  var img = String(userImage).trim();
  if (!img) return false;
  if (img === 'null' || img === 'undefined' || img === 'no-image.png' || img === '1.png' || img === 'default.png') {
    return false;
  }
  return true;
}

/** Build /assets/upload/avatar/... src, or '' if invalid. */
function getAvatarSrcFromImage(userImage) {
  if (!isValidAvatarFilename(userImage)) return '';
  var img = String(userImage).trim();
  if (img.indexOf('http') === 0 || img.indexOf('/') === 0) return img;
  return '/assets/upload/avatar/' + img;
}

/**
 * Unified user avatar HTML (initials first; reveal image on load).
 * @param {object} opts
 * @param {string} [opts.realname]
 * @param {string} [opts.userid]
 * @param {string} [opts.userImage]
 * @param {string} [opts.user_ruby]
 * @param {string} [opts.size] - '' | 'xs' | 'sm' | 'md' | 'lg' (default 'sm')
 * @param {string} [opts.extraClass]
 * @param {boolean} [opts.pullUp]
 * @param {boolean} [opts.tooltip]
 */
function renderUserAvatarHtml(opts) {
  opts = opts || {};
  var realname = opts.realname || '';
  var userid = opts.userid || opts.userId || opts.user_id || '';
  var userImage = opts.userImage || opts.user_image || '';
  var title = realname || userid || '';
  var initials = getAvatarName(realname || userid || '', {
    userid: userid,
    user_ruby: opts.user_ruby || opts.ruby || ''
  });
  var size = (opts.size !== undefined && opts.size !== null) ? String(opts.size) : 'sm';
  var sizeClass = size ? (' avatar-' + size) : '';
  var wrapClass = 'avatar' + sizeClass;
  if (opts.extraClass) wrapClass += ' ' + String(opts.extraClass).trim();
  var pullUp = opts.pullUp ? ' pull-up' : '';
  var esc = function (s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/"/g, '&quot;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  };
  var titleAttr = esc(title);
  var useridAttr = esc(userid);
  var tooltipAttrs = opts.tooltip === false
    ? ''
    : ' data-bs-toggle="tooltip" title="' + titleAttr + '"';
  var initialClass = 'avatar-initial rounded-circle bg-label-primary' + pullUp;
  var html = '<div class="' + wrapClass + '"' + tooltipAttrs
    + (userid ? ' data-userid="' + useridAttr + '"' : '')
    + '>'
    + '<span class="' + initialClass + '">' + esc(initials) + '</span>';
  var src = getAvatarSrcFromImage(userImage);
  if (src) {
    html += '<img src="' + esc(src) + '" alt="' + titleAttr + '" class="rounded-circle' + pullUp + '"'
      + ' style="display:none;"'
      + ' onload="this.style.display=\'block\';var i=this.previousElementSibling;if(i)i.style.display=\'none\';"'
      + ' onerror="this.remove();">';
  }
  html += '</div>';
  return html;
}

/** Update server-rendered avatar initials that expose data-avatar-* attrs. */
function refreshDomAvatarInitials() {
  if (typeof document === 'undefined') return;
  document.querySelectorAll('.js-avatar-initial').forEach(function (el) {
    const name = el.getAttribute('data-avatar-name') || '';
    const userid = el.getAttribute('data-avatar-userid') || '';
    const ruby = el.getAttribute('data-avatar-ruby') || '';
    el.textContent = getAvatarName(name, { userid: userid, user_ruby: ruby });
  });
}

if (typeof document !== 'undefined') {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', refreshDomAvatarInitials);
  } else {
    refreshDomAvatarInitials();
  }
}


$(function() {
  var select2 = $('.select2');
  if (select2.length) {
    select2.each(function() {
      var $this = $(this);
      $this.wrap('<div class="position-relative"></div>').select2({
        placeholder: '選択してください',
        dropdownParent: $this.parent(),
        minimumResultsForSearch: 0,
      });
    });
  }

  // $('.js-change-language').on('click', function() {
  //   var language = $(this).data('language');
  //   const savedLang = localStorage.getItem('templateCustomizer-' + templateName + '--Lang');
  //   if(savedLang == language) return;
  //   window.location.reload();
  // });

  // Hàm sử dụng i18next
  function useI18nTranslation(language) {
    if (typeof i18next !== 'undefined') {
      i18next.changeLanguage(language, (err, t) => {
        if (window.templateCustomizer) {
          window.templateCustomizer.setLang(language);
        }
        if (err) return console.log('something went wrong loading', err);
        
        // Cập nhật giao diện
        let i18nList = document.querySelectorAll('[data-i18n]');
        i18nList.forEach(function (item) {
          item.innerHTML = i18next.t(item.dataset.i18n);
        });
        
        // Lưu trạng thái
        localStorage.setItem('templateCustomizer-' + templateName + '--Lang', language);
        
        // Cập nhật trạng thái active trong dropdown
        updateLanguageDropdownState(language);
      });
    }
  }

  // Hàm cập nhật trạng thái active trong dropdown
  function updateLanguageDropdownState(language) {
    // Xóa active class từ tất cả các item
    document.querySelectorAll('.js-change-language').forEach(function(item) {
      item.classList.remove('active');
    });
    
    // Thêm active class cho item được chọn
    const selectedItem = document.querySelector(`.js-change-language[data-language="${language}"]`);
    if (selectedItem) {
      selectedItem.classList.add('active');
    }
  }

  // Khôi phục trạng thái ngôn ngữ khi tải trang
  function restoreLanguageState() {
    const savedLang = localStorage.getItem('templateCustomizer-' + templateName + '--Lang');
    
    if (savedLang) {
      // Khôi phục trạng thái i18next
     setTimeout(() => {
      useI18nTranslation(savedLang);
     }, 100);
    }
  }

  // Gọi hàm khôi phục khi trang đã tải xong
  $(document).ready(function() {
    restoreLanguageState();
  });

});



// =============================================================================
// Global Web Speech helper: Ctrl + Shift + H to show voice input bar
// Works for any focused textarea / input / contenteditable
// =============================================================================
if (typeof window !== 'undefined') {
  ;(function () {
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition
    if (!SpeechRecognition) {
      window.initGlobalSpeechHelper = function () {
        console.warn('SpeechRecognition is not supported in this browser.')
      }
      return
    }

    let recognition = null
    let currentTarget = null
    let currentLang = null
    let lastFocused = null
    let barEl = null
    let fabEl = null

    function clearTargetHighlight() {
      if (!currentTarget) return
      if (currentTarget._speechPrevBorderColor !== undefined) {
        currentTarget.style.borderColor = currentTarget._speechPrevBorderColor
        delete currentTarget._speechPrevBorderColor
      }
      if (currentTarget._speechPrevBoxShadow !== undefined) {
        currentTarget.style.boxShadow = currentTarget._speechPrevBoxShadow
        delete currentTarget._speechPrevBoxShadow
      }
      currentTarget.classList.remove('speech-recording')
    }

    function ensureRecognition() {
      if (recognition) return recognition
      recognition = new SpeechRecognition()
      recognition.continuous = false
      recognition.interimResults = false

      recognition.onresult = event => {
        if (!currentTarget) return
        const transcript = Array.from(event.results)
          .map(r => r[0].transcript)
          .join('')
        if (!transcript) return

        if (currentTarget.tagName === 'TEXTAREA' || (currentTarget.tagName === 'INPUT' && currentTarget.type === 'text')) {
          const prev = currentTarget.value || ''
          currentTarget.value = prev ? prev.replace(/\s*$/, '') + '\n' + transcript.trim() : transcript.trim()
          const inputEvent = new Event('input', { bubbles: true })
          currentTarget.dispatchEvent(inputEvent)
        } else if (currentTarget.isContentEditable) {
          const prev = currentTarget.innerText || ''
          currentTarget.innerText = prev ? prev.replace(/\s*$/, '') + '\n' + transcript.trim() : transcript.trim()
          const inputEvent = new Event('input', { bubbles: true })
          currentTarget.dispatchEvent(inputEvent)
        }
      }

      recognition.onend = () => {
        currentLang = null
        clearTargetHighlight()
        updateBarState(null)
      }

      recognition.onerror = e => {
        console.error('Speech recognition error:', e)
        currentLang = null
        clearTargetHighlight()
        updateBarState(null)
      }

      return recognition
    }

    function createBar() {
      if (barEl) return barEl
      const t = (typeof i18next !== 'undefined' && i18next.t) ? i18next.t.bind(i18next) : (k) => k
      const titleHelp = t('音声入力の使い方')
      const labelSpeech = t('音声入力')
      const labelJa = t('日本語')
      const labelVi = t('ベトナム語')
      const titleClose = t('閉じる') || '閉じる'
      barEl = document.createElement('div')
      barEl.id = 'global-speech-bar'
      barEl.innerHTML = `
        <div style="
          display:flex;
          align-items:center;
          gap:8px;
          white-space:nowrap;
        ">
          <button type="button"
                  class="btn btn-sm btn-link p-0 m-0 text-white js-global-speech-help"
                  title="${titleHelp}"
                  style="text-decoration:none;">
            <i class="fa fa-question-circle"></i>
          </button>
          <strong style="font-size:12px; white-space:nowrap;">${labelSpeech}</strong>
          <div class="btn-group btn-group-sm" role="group" style="white-space:nowrap;">
            <button type="button" class="btn btn-outline-light text-nowrap js-global-speech-ja">
              <i class="fa fa-microphone"></i><span class="ms-1">${labelJa}</span>
            </button>
            <button type="button" class="btn btn-outline-light text-nowrap js-global-speech-vi">
              <i class="fa fa-microphone"></i><span class="ms-1">${labelVi}</span>
            </button>
          </div>
          <button type="button"
                  class="btn btn-sm btn-link p-0 m-0 text-white js-global-speech-close"
                  title="${titleClose}"
                  aria-label="${titleClose}"
                  style="text-decoration:none;line-height:1;">
            <i class="fa fa-times"></i>
          </button>
        </div>
      `

      const jaBtn = barEl.querySelector('.js-global-speech-ja')
      const viBtn = barEl.querySelector('.js-global-speech-vi')
      const helpBtn = barEl.querySelector('.js-global-speech-help')
      const closeBtn = barEl.querySelector('.js-global-speech-close')

      if (jaBtn) {
        jaBtn.addEventListener('click', e => {
          e.preventDefault()
          e.stopPropagation()
          toggleSpeech('ja-JP')
        })
      }
      if (viBtn) {
        viBtn.addEventListener('click', e => {
          e.preventDefault()
          e.stopPropagation()
          toggleSpeech('vi-VN')
        })
      }
      if (closeBtn) {
        closeBtn.addEventListener('click', e => {
          e.preventDefault()
          e.stopPropagation()
          hideBar()
        })
      }
      if (helpBtn) {
        helpBtn.addEventListener('click', e => {
          e.preventDefault()
          e.stopPropagation()
          const defaultTitle = '音声入力の使い方'
          const defaultMsg =
            '1. まず、テキストを入力したいテキストエリアや入力欄をクリックしてフォーカスを当てます。\n' +
            '2. 画面右下のマイクボタンを押して「音声入力」バーを開きます（または Ctrl + Shift + H で開閉できます）。\n' +
            '3. 「日本語」または「ベトナム語」のボタンを押して話し始めます。\n' +
            '4. 認識されたテキストは、フォーカスされている入力欄の末尾に自動的に追記されます。\n' +
            '5. 同じボタンをもう一度押すと録音が停止します。'
          const t = (typeof i18next !== 'undefined' && i18next.t) ? i18next.t.bind(i18next) : null
          const lang = (typeof i18next !== 'undefined' && i18next.language) ? i18next.language : ''
          const title = t ? (t('音声入力の使い方') || defaultTitle) : defaultTitle
          const msg = (t && lang === 'vi') ? (t('speech_help_body') || defaultMsg) : defaultMsg
          const confirmText = t ? (t('OK') || 'OK') : 'OK'
          if (window.Swal && typeof window.Swal.fire === 'function') {
            const html = msg.replace(/\n/g, '<br>')
            window.Swal.fire({
              title,
              html,
              icon: 'info',
              confirmButtonText: confirmText,
              didOpen: el => {
                const htmlEl = el.querySelector('.swal2-html-container')
                if (htmlEl) {
                  htmlEl.style.textAlign = 'left'
                }
              }
            })
          } else {
            alert(msg)
          }
        })
      }

      return barEl
    }

    function showBar() {
      // Đảm bảo FAB tồn tại
      const fab = createSpeechFab()
      fab.classList.add('is-open')
      fab.classList.remove('rounded-circle')
      fab.style.width = ''
      fab.style.borderRadius = ''

      const bar = createBar()
      // Gắn bar vào trong FAB
      fab.innerHTML = ''
      fab.appendChild(bar)

      updateBarState(null)
    }

    function hideBar() {
      if (recognition && currentLang) {
        try {
          recognition.stop()
        } catch (e) {
          console.error(e)
        }
      }
      currentLang = null
      updateBarState(null)
      if (fabEl) {
        fabEl.classList.remove('is-open')
        fabEl.classList.add('rounded-circle')
        fabEl.style.width = ''
        fabEl.style.borderRadius = ''
        fabEl.innerHTML = '<i class="fa fa-microphone"></i>'
      }
    }

    function updateBarState(lang) {
      if (!barEl) return
      const jaBtn = barEl.querySelector('.js-global-speech-ja')
      const viBtn = barEl.querySelector('.js-global-speech-vi')

      jaBtn.classList.remove('btn-danger')
      jaBtn.classList.add('btn-outline-secondary')
      viBtn.classList.remove('btn-danger')
      viBtn.classList.add('btn-outline-secondary')

      if (!lang) return

      if (lang === 'ja-JP') {
        jaBtn.classList.remove('btn-outline-secondary')
        jaBtn.classList.add('btn-danger')
      } else if (lang === 'vi-VN') {
        viBtn.classList.remove('btn-outline-secondary')
        viBtn.classList.add('btn-danger')
      }
    }

    function isVisible(el) {
      if (!el) return false
      const style = window.getComputedStyle(el)
      if (style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0') return false
      const rect = el.getBoundingClientRect()
      if (rect.width === 0 || rect.height === 0) return false
      return true
    }

    function toggleSpeech(lang) {
      const rec = ensureRecognition()
      if (!lastFocused || !isEditable(lastFocused) || !isVisible(lastFocused)) {
        alert('音声入力するテキストエリアまたは入力欄を先に選択して、画面上に表示されていることを確認してください。')
        return
      }

      currentTarget = lastFocused

      // Nếu đang ghi cùng ngôn ngữ → dừng
      if (currentLang === lang) {
        try {
          rec.stop()
        } catch (e) {
          console.error(e)
        }
        currentLang = null
        clearTargetHighlight()
        updateBarState(null)
        return
      }

      // Nếu đang ghi ngôn ngữ khác → dừng, bấm lại để đổi
      if (currentLang && currentLang !== lang) {
        try {
          rec.stop()
        } catch (e) {
          console.error(e)
        }
        currentLang = null
        clearTargetHighlight()
        updateBarState(null)
        return
      }

      // Bắt đầu ghi
      currentLang = lang
      updateBarState(lang)
      try {
        rec.lang = lang
        rec.start()
        // Highlight ô đang ghi âm
        if (currentTarget) {
          if (currentTarget._speechPrevBorderColor === undefined) {
            currentTarget._speechPrevBorderColor = currentTarget.style.borderColor
          }
          if (currentTarget._speechPrevBoxShadow === undefined) {
            currentTarget._speechPrevBoxShadow = currentTarget.style.boxShadow
          }
          currentTarget.style.borderColor = '#dc3545' // bootstrap danger
          currentTarget.style.boxShadow = '0 0 0 0.2rem rgba(220,53,69,.25)'
          currentTarget.classList.add('speech-recording')
        }
      } catch (e) {
        console.error(e)
      }
    }

    function isEditable(el) {
      if (!el) return false
      if (el.tagName === 'TEXTAREA') return true
      if (el.tagName === 'INPUT' && el.type === 'text') return true
      if (el.isContentEditable) return true
      return false
    }

    // Theo dõi phần tử có focus gần nhất
    document.addEventListener(
      'focusin',
      e => {
        if (isEditable(e.target)) {
          lastFocused = e.target
        }
      },
      true
    )

    // Phím tắt Ctrl + Shift + H để toggle thanh nhập liệu
    document.addEventListener('keydown', e => {
      const key = e.key || e.code
      if (e.ctrlKey && e.shiftKey && (key === 'H' || key === 'h')) {
        e.preventDefault()
        if (!SpeechRecognition) {
          alert('ブラウザが音声入力に対応していません。\n\nWindows 10+の場合は、テキストエリアを選択してから「Win + H」で音声入力が使えます。')
          return
        }
        if (fabEl && fabEl.classList.contains('is-open')) {
          hideBar()
        } else {
          showBar()
        }
      }
    })

    window.initGlobalSpeechHelper = function () {
      // hiện tại đã tự khởi tạo trong IIFE, hàm này để sau này dùng lại nếu cần
      return true
    }

    // Floating speech button — inject vào #fab-bar nếu có, không thì fallback body
    function createSpeechFab() {
      if (fabEl) return fabEl
      fabEl = document.createElement('button')
      fabEl.id = 'global-speech-fab'
      fabEl.type = 'button'
      fabEl.className = 'btn btn-primary rounded-circle d-flex align-items-center justify-content-center'
      fabEl.innerHTML = '<i class="fa fa-microphone"></i>'
      fabEl.style.cssText = 'box-shadow:0 2px 8px rgba(0,0,0,0.2);transition:width 0.2s ease,border-radius 0.2s ease,padding 0.2s ease;'

      const fabBar = document.getElementById('fab-bar')
      if (fabBar) {
        const slot = document.createElement('div')
        slot.className = 'fab-with-label'
        slot.id = 'speech-fab-slot'
        slot.appendChild(fabEl)
        // Luôn nằm cuối danh sách FAB (sau F1 / F3 / holiday…)
        fabBar.appendChild(slot)
      } else {
        Object.assign(fabEl.style, { position: 'fixed', bottom: '10px', right: '56px', zIndex: '9998' })
        document.body.appendChild(fabEl)
      }

      fabEl.addEventListener('click', () => {
        const isOpen = fabEl.classList.contains('is-open')
        if (isOpen) hideBar()
        else showBar()
      })

      return fabEl
    }

    // Khởi tạo FAB sau khi DOM sẵn sàng
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', () => {
        createSpeechFab()
      })
    } else {
      createSpeechFab()
    }
  })()
}

/**
 * Context menu "Thêm vào todo" for elements with [data-todo-title].
 * Opens modal to add todo with title, deadline (from data-time), priority; submits via todo api_add.
 */
(function () {
  var contextMenuEl = null
  var addToTodoModalEl = null
  var addToTodoModal = null
  var addToTodoFlatpickr = null
  var lastTodoTargetEl = null

  function getContextMenu() {
    if (contextMenuEl) return contextMenuEl
    contextMenuEl = document.createElement('div')
    contextMenuEl.id = 'addToTodoContextMenu'
    contextMenuEl.className = 'dropdown-menu show position-fixed shadow'
    contextMenuEl.style.minWidth = '160px'
    contextMenuEl.innerHTML = '<a class="dropdown-item" href="#" data-action="add-to-todo"><i class="fas fa-list-check me-2"></i><span data-i18n="追加Todo">追加Todo</span></a>'
    document.body.appendChild(contextMenuEl)
    contextMenuEl.querySelector('[data-action="add-to-todo"]').addEventListener('click', function (e) {
      e.preventDefault()
      hideContextMenu()
      if (lastTodoTargetEl) openAddToTodoModal(lastTodoTargetEl)
    })
    return contextMenuEl
  }

  function showContextMenu(x, y, targetEl) {
    lastTodoTargetEl = targetEl
    var menu = getContextMenu()
    menu.style.left = x + 'px'
    menu.style.top = y + 'px'
    menu.style.display = 'block'
    document.addEventListener('click', hideContextMenuOnce)
    document.addEventListener('contextmenu', hideContextMenuOnce)
  }

  function hideContextMenu() {
    if (contextMenuEl) contextMenuEl.style.display = 'none'
    document.removeEventListener('click', hideContextMenuOnce)
    document.removeEventListener('contextmenu', hideContextMenuOnce)
  }

  function hideContextMenuOnce() {
    hideContextMenu()
  }

  function ensureAddToTodoModal() {
    if (addToTodoModalEl) return
    var t = document.createElement('div')
    t.id = 'addToTodoFromContextModal'
    t.className = 'modal fade'
    t.setAttribute('tabindex', '-1')
    t.innerHTML =
      '<div class="modal-dialog modal-dialog-centered">' +
        '<div class="modal-content">' +
          '<div class="modal-header">' +
            '<h5 class="modal-title"><span data-i18n="追加Todo">追加Todo</span></h5>' +
            '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>' +
          '</div>' +
          '<div class="modal-body">' +
            '<div class="mb-3">' +
              '<label class="form-label"><span data-i18n="Todo">Todo</span></label>' +
              '<input type="text" class="form-control" id="addToTodoContextTitle" placeholder="">' +
            '</div>' +
            '<div class="mb-3">' +
              '<label class="form-label"><span data-i18n="期限">期限</span></label>' +
              '<input type="text" class="form-control" id="addToTodoContextDeadline" placeholder="YYYY-MM-DD HH:mm" readonly>' +
            '</div>' +
            '<div class="mb-3">' +
              '<label class="form-label"><span data-i18n="優先度">優先度</span></label>' +
              '<select class="form-select" id="addToTodoContextPriority">' +
                '<option value="10">低</option>' +
                '<option value="50" selected>中</option>' +
                '<option value="100">高</option>' +
              '</select>' +
            '</div>' +
            '<div class="mb-3">' +
              '<label class="form-label"><span data-i18n="リンク">リンク</span></label>' +
              '<input type="text" class="form-control" id="addToTodoContextLink" placeholder="">' +
            '</div>' +
            '<div class="mb-3">' +
              '<label class="form-label"><span data-i18n="備考">備考</span></label>' +
              '<textarea class="form-control" id="addToTodoContextComment" rows="3" placeholder=""></textarea>' +
            '</div>' +
          '</div>' +
          '<div class="modal-footer">' +
            '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><span data-i18n="キャンセル">キャンセル</span></button>' +
            '<button type="button" class="btn btn-primary" id="addToTodoContextSubmit"><i class="fas fa-plus me-1"></i><span data-i18n="追加">追加</span></button>' +
          '</div>' +
        '</div>' +
      '</div>'
    document.body.appendChild(t)
    addToTodoModalEl = t
    addToTodoModal = window.bootstrap && window.bootstrap.Modal ? new window.bootstrap.Modal(t) : null
    var deadlineInput = document.getElementById('addToTodoContextDeadline')
    if (deadlineInput && typeof flatpickr !== 'undefined') {
      addToTodoFlatpickr = flatpickr(deadlineInput, {
        dateFormat: 'Y-m-d H:i',
        enableTime: true,
        time_24hr: true,
        locale: typeof window.moment !== 'undefined' && window.moment.locale() === 'vi' ? 'vi' : 'ja'
      })
    }
    document.getElementById('addToTodoContextSubmit').addEventListener('click', submitAddToTodoFromContext)
  }

  function dataTimeToApiTerm(dataTime) {
    if (!dataTime || typeof dataTime !== 'string') return ''
    var s = String(dataTime).trim().replace(/\//g, '-')
    var match = s.match(/^(\d{4}-\d{2}-\d{2})[T ](\d{1,2}):(\d{2})(?::\d{2})?/)
    if (match) return match[1] + ' ' + match[2].padStart(2, '0') + ':' + match[3]
    if (/^\d{4}-\d{2}-\d{2}$/.test(s)) return s + ' 00:00'
    return s
  }

  function openAddToTodoModal(sourceEl) {
    ensureAddToTodoModal()
    var title = (sourceEl.getAttribute('data-todo-title') || '').trim()
    var time = (sourceEl.getAttribute('data-time') || '').trim()
    var link = (sourceEl.getAttribute('data-todo-link') || '').trim()
    document.getElementById('addToTodoContextTitle').value = title
    document.getElementById('addToTodoContextDeadline').value = dataTimeToApiTerm(time)
    document.getElementById('addToTodoContextPriority').value = '50'
    document.getElementById('addToTodoContextLink').value = link
    document.getElementById('addToTodoContextComment').value = ''
    if (addToTodoFlatpickr) addToTodoFlatpickr.setDate(document.getElementById('addToTodoContextDeadline').value || null, false)
    if (addToTodoModal) addToTodoModal.show()
    lastTodoTargetEl = null
  }

  window.openAddToTodoModalFromContext = function (el) {
    if (el && el.getAttribute && el.getAttribute('data-todo-title') != null) openAddToTodoModal(el)
  }

  function submitAddToTodoFromContext() {
    var titleEl = document.getElementById('addToTodoContextTitle')
    var deadlineEl = document.getElementById('addToTodoContextDeadline')
    var priorityEl = document.getElementById('addToTodoContextPriority')
    var linkEl = document.getElementById('addToTodoContextLink')
    var commentEl = document.getElementById('addToTodoContextComment')
    if (!titleEl || !deadlineEl || !priorityEl) return
    var title = (titleEl.value || '').trim()
    if (!title) {
      if (typeof window.alert === 'function') window.alert(typeof window.i18next !== 'undefined' && window.i18next.t ? window.i18next.t('Please enter a title') : 'Please enter a title')
      return
    }
    var formData = new FormData()
    formData.append('todo_title', title)
    formData.append('todo_priority', priorityEl.value || '50')
    formData.append('todo_term', (deadlineEl.value || '').trim())
    formData.append('todo_complete', '0')
    if (linkEl) formData.append('todo_link', (linkEl.value || '').trim())
    if (commentEl) formData.append('todo_comment', (commentEl.value || '').trim())
    var submitBtn = document.getElementById('addToTodoContextSubmit')
    if (submitBtn) {
      submitBtn.disabled = true
    }
    if (typeof window.axios !== 'undefined') {
      window.axios.post('/api/index.php?model=todo&method=api_add', formData)
        .then(function (response) {
          if (response.data && response.data.status === 'success') {
            if (addToTodoModal) addToTodoModal.hide()
            document.body.dispatchEvent(new CustomEvent('todo-added-from-context'))
          } else {
            if (window.alert) window.alert('Failed to add todo: ' + (response.data && response.data.message ? response.data.message : 'Unknown error'))
          }
        })
        .catch(function (err) {
          if (window.alert) window.alert('Error: ' + (err.message || 'Request failed'))
        })
        .finally(function () {
          if (submitBtn) submitBtn.disabled = false
        })
    } else {
      var xhr = new XMLHttpRequest()
      xhr.open('POST', '/api/index.php?model=todo&method=api_add')
      xhr.onload = function () {
        if (submitBtn) submitBtn.disabled = false
        try {
          var data = JSON.parse(xhr.responseText)
          if (data && data.status === 'success') {
            if (addToTodoModal) addToTodoModal.hide()
            document.body.dispatchEvent(new CustomEvent('todo-added-from-context'))
          } else {
            if (window.alert) window.alert('Failed to add todo: ' + (data && data.message ? data.message : 'Unknown error'))
          }
        } catch (e) {
          if (window.alert) window.alert('Request failed')
        }
      }
      xhr.onerror = function () {
        if (submitBtn) submitBtn.disabled = false
        if (window.alert) window.alert('Request failed')
      }
      xhr.send(formData)
    }
  }

  document.addEventListener('contextmenu', function (e) {
    var el = e.target && e.target.closest ? e.target.closest('[data-todo-title]') : null
    if (!el || !el.getAttribute('data-todo-title')) return
    if (el.closest && el.closest('#projectTable')) return
    e.preventDefault()
    showContextMenu(e.clientX, e.clientY, el)
  })

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', ensureAddToTodoModal)
  } else {
    ensureAddToTodoModal()
  }
})()
