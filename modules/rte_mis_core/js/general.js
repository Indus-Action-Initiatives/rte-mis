(function ($, Drupal, drupalSettings) {
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

      $(document).ready(function() {
        var role = drupalSettings.role ?? null;
        if (role == 'district') {
          $('.location-details div[data-level="0"] select').prop('disabled', true);
        }
        else if (role == 'block') {
          $('.location-details div[data-level="0"] select, .location-details div[data-level="1"] select').prop('disabled', true);
        }
      });
    }
  };
})(jQuery, Drupal, drupalSettings);
