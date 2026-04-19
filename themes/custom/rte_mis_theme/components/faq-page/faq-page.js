/**
 * @file
 * FAQ Page component behaviors.
 */

(function (Drupal) {
  'use strict';

  Drupal.behaviors.faqPage = {
    attach: function (context) {
      // Accordion logic is now handled by the native rte_mis_theme:accordion component.

      // 1. Smooth scrolling for sidebar links
      const sidebarLinks = context.querySelectorAll('.js-faq-scroll-link');
      
      sidebarLinks.forEach((link) => {
        if (link.dataset.scrollBound) return;
        link.dataset.scrollBound = 'true';

        link.addEventListener('click', (e) => {
          const targetId = link.getAttribute('href').substring(1);
          const targetElement = document.getElementById(targetId);
          
          if (targetElement) {
            e.preventDefault();
            
            // Update active state manually
            sidebarLinks.forEach(l => l.classList.remove('is-active'));
            link.classList.add('is-active');

            // Scroll to the element
            targetElement.scrollIntoView({
              behavior: 'smooth',
              block: 'start'
            });
            
            // Push state to URL
            history.pushState(null, null, `#${targetId}`);
          }
        });
      });

      // 3. Highlight initially if hash matches
      if (window.location.hash) {
        const activeLink = document.querySelector(`.js-faq-scroll-link[href="${window.location.hash}"]`);
        if (activeLink) {
          activeLink.classList.add('is-active');
        }
      } else if (sidebarLinks.length > 0) {
        // Default highlight first
        sidebarLinks[0].classList.add('is-active');
      }
    }
  };
})(Drupal);
