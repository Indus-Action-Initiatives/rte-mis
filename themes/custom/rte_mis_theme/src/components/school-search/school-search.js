(function (global) {
  'use strict';

  function initSchoolSearch(root) {
    if (!root || root.dataset.initialized) return;
    root.dataset.initialized = 'true'; // prevent duplicate init

    const toggle = root.querySelector('.c-school-search__filter-toggle');
    const filtersPanel = root.querySelector('.c-school-search__filters');
    const selects = root.querySelectorAll('.c-school-search__select');
    const clearBtn = root.querySelector('.c-school-search__btn--clear');

    // ── Toggle ──
    if (toggle && filtersPanel) {
      toggle.addEventListener('click', () => {
        const isOpen = filtersPanel.classList.toggle('is-open');
        toggle.setAttribute('aria-expanded', String(isOpen));
        filtersPanel.setAttribute('aria-hidden', String(!isOpen));
      });
    }

    // ── Select → Tags ──
    selects.forEach((select) => {
      const name = select.name;
      const tagsContainer = root.querySelector(
        `[data-filter-tags="${name}"]`
      );
      if (!tagsContainer) return;

      select.addEventListener('change', () => {
        const value = select.value;
        const label = select.options[select.selectedIndex]?.text;

        if (!value) return;

        if (tagsContainer.querySelector(`[data-tag-value="${value}"]`)) {
          return;
        }

        const tag = document.createElement('span');
        tag.className = 'c-school-search__tag';
        tag.dataset.tagValue = value;

        tag.innerHTML = `
          ${label}
          <button type="button" class="c-school-search__tag-remove">×</button>
        `;

        tag.querySelector('button').addEventListener('click', () => {
          tag.remove();
        });

        tagsContainer.appendChild(tag);
        select.selectedIndex = 0;
      });
    });

    // ── Clear All ──
    if (clearBtn) {
      clearBtn.addEventListener('click', () => {
        root.querySelectorAll('.c-school-search__tag').forEach((t) => t.remove());
        selects.forEach((s) => (s.selectedIndex = 0));
      });
    }
  }

  // ✅ Storybook / standalone
  function autoInit() {
    document.querySelectorAll('.c-school-search').forEach(initSchoolSearch);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', autoInit);
  } else {
    autoInit();
  }

  // ✅ Drupal support (optional)
  if (global.Drupal) {
    global.Drupal.behaviors.schoolSearch = {
      attach(context) {
        context.querySelectorAll('.c-school-search').forEach(initSchoolSearch);
      },
    };
  }

})(window);