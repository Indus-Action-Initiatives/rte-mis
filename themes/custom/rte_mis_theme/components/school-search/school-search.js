/**
 * @file school-search.js
 * Behaviour for the School Search Block component.
 *
 * Handles:
 * - Toggle the filter panel open/closed
 * - Add selected values as tag chips
 * - Remove individual tags
 * - "Clear All" button
 */

(function () {
  'use strict';

  function initSchoolSearch(root) {
    const toggle = root.querySelector('.c-school-search__filter-toggle');
    const filtersPanel = root.querySelector('.c-school-search__filters');
    const selects = root.querySelectorAll('.c-school-search__select');
    const clearBtn = root.querySelector('.c-school-search__btn--clear');
    console.log(toggle);
    if (!toggle || !filtersPanel) return;

    // ── Toggle filter panel ──
    toggle.addEventListener('click', () => {
      const isOpen = filtersPanel.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', String(isOpen));
      filtersPanel.setAttribute('aria-hidden', String(!isOpen));
    });

    // ── Select → Tag chips ──
    selects.forEach((select) => {
      const name = select.name;
      const tagsContainer = root.querySelector(
        `[data-filter-tags="${name}"]`
      );
      if (!tagsContainer) return;

      select.addEventListener('change', () => {
        const value = select.value;
        const label =
          select.options[select.selectedIndex]?.text;

        if (!value) return;

        // Prevent duplicates
        if (
          tagsContainer.querySelector(
            `[data-tag-value="${value}"]`
          )
        )
          return;

        const tag = document.createElement('span');
        tag.className = 'c-school-search__tag';
        tag.setAttribute('data-tag-value', value);
        tag.innerHTML = `
          ${label}
          <button type="button" class="c-school-search__tag-remove" aria-label="Remove ${label}">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="18" y1="6" x2="6" y2="18"></line>
              <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
          </button>
        `;

        tag
          .querySelector('.c-school-search__tag-remove')
          .addEventListener('click', () => {
            tag.remove();
          });

        tagsContainer.appendChild(tag);

        // Reset select back to placeholder
        select.selectedIndex = 0;
      });
    });

    // ── Clear All ──
    if (clearBtn) {
      clearBtn.addEventListener('click', () => {
        root
          .querySelectorAll('.c-school-search__tag')
          .forEach((tag) => tag.remove());
        selects.forEach((s) => {
          s.selectedIndex = 0;
        });
      });
    }
  }

  // Auto-init on DOMContentLoaded (Storybook / standalone)
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      document.querySelectorAll('.c-school-search').forEach(initSchoolSearch);
    });
  } else {
    document.querySelectorAll('.c-school-search').forEach(initSchoolSearch);
  }

  // Drupal behaviors
  if (typeof Drupal !== 'undefined' && Drupal.behaviors) {
    Drupal.behaviors.schoolSearch = {
      attach: function (context) {
        console.log("here");
        const roots =
          context.querySelectorAll?.('.c-school-search') ||
          (context.classList?.contains('c-school-search')
            ? [context]
            : []);
        roots.forEach(initSchoolSearch);
      },
    };
  }
})();
