(function (Drupal) {
  Drupal.behaviors = Drupal.behaviors || {};
  Drupal.behaviors.newsCardModal = {
    attach(context) {
      const cards = context.querySelectorAll('.c-news-card');

      cards.forEach((card) => {
        const trigger = card.querySelector('.c-news-card__modal-trigger');
        const modal = card.querySelector('.c-news-card__modal');

        if (trigger && modal) {
          trigger.addEventListener('click', (e) => {
            e.preventDefault();
            modal.showModal();
            document.body.style.overflow = 'hidden';
          });

          // Handle close buttons and overlay clicks
          const closeBtns = modal.querySelectorAll('[data-close-modal]');
          closeBtns.forEach((btn) => {
            btn.addEventListener('click', () => {
              modal.close();
            });
          });

          // The backdrop and close events natively provided by <dialog>
          modal.addEventListener('close', () => {
            document.body.style.overflow = '';
          });
        }
      });
    },
  };
})(window.Drupal || (window.Drupal = { behaviors: {} }));

// In environments like Storybook where Drupal.attachBehaviors isn't called automatically:
if (!window.Drupal.attachBehaviors) {
  document.addEventListener('DOMContentLoaded', () => {
    if (window.Drupal.behaviors.newsCardModal && window.Drupal.behaviors.newsCardModal.attach) {
      window.Drupal.behaviors.newsCardModal.attach(document);
    }
  });
}
