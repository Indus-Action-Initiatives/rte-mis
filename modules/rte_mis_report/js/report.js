(function ($, Drupal, drupalSettings,once) {
    Drupal.behaviors.customLogViewer = {
      attach: function (context, settings) {
        $('.school-reports').DataTable();
      }
    };
  })(jQuery, Drupal, drupalSettings, once);
