(function ($, Drupal) {
  Drupal.behaviors.general = {
    attach: function (context, settings) {
      $('.udise-number').each(function () {
        $(this).on('input', function () {
          // Remove non-numeric characters
          this.value = this.value.replace(/\D/g, '');
          // Enforce 11-digit limit
          if (this.value.length > 11) {
            this.value = this.value.slice(0, 11);
          }
        });
      });

    }
  };
})(jQuery, Drupal);
