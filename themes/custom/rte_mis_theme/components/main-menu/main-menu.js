function attachMainMenuToggles(context) {
  // Use context.querySelectorAll if available, fallback to document
  const root = context && context.querySelectorAll ? context : document;
  const toggles = root.querySelectorAll('.c-main-menu__toggle:not(.js-processed)');
  
  toggles.forEach((toggle) => {
    toggle.classList.add('js-processed');
    toggle.addEventListener('click', () => {
      const wrapper = toggle.closest('.c-main-menu-wrapper');
      if (wrapper) {
        const isOpen = wrapper.classList.toggle('is-open');
        toggle.setAttribute('aria-expanded', isOpen);
      }
    });
  });
}

if (typeof Drupal !== 'undefined' && Drupal.behaviors) {
  Drupal.behaviors.mainMenuToggle = {
    attach: function (context) {
      attachMainMenuToggles(context);
    }
  };
} else {
  // Storybook / Standalone fallback
  if (document.readyState !== 'loading') {
    attachMainMenuToggles(document);
  } else {
    document.addEventListener('DOMContentLoaded', () => attachMainMenuToggles(document));
  }
}
