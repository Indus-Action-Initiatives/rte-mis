(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.disableCshsSelect = {
    attach: function (context, settings) {
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
