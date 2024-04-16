(function ($, Drupal, once) {
  Drupal.behaviors.validateCshsElement = {
    attach: function (context, settings) {
      $(document).ready(function() {
        // Disable 1st and 2nd level of cshs select list.
        $('.school-details-cshs div[data-level="0"] select, .school-details-cshs div[data-level="1"] select').prop('disabled', true);
      })
    }
  };
})(jQuery, Drupal, once);
