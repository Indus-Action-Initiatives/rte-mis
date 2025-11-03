/**
 * State Admin Dashboard JavaScript
 */
(function ($, Drupal) {
  'use strict';

  Drupal.behaviors.stateAdminDashboard = {
    attach: function (context, settings) {
      // Dashboard initialization
      $('.state-admin-dashboard', context).once('state-dashboard').each(function () {
        console.log('State Admin Dashboard initialized');
        
        // Animate progress bars on page load
        $('.progress-bar').each(function () {
          var $bar = $(this);
          var width = $bar.css('width');
          $bar.css('width', '0');
          setTimeout(function () {
            $bar.css('width', width);
          }, 300);
        });
      });
    }
  };

})(jQuery, Drupal);