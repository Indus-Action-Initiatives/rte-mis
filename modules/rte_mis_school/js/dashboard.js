(function ($, Drupal) {
  'use strict';

  /**
   * Dashboard behavior.
   */
  Drupal.behaviors.rteMisSchoolDashboard = {
    attach: function (context, settings) {
      $('.rte-mis-school-dashboard', context).once('dashboard-init').each(function () {
        
        // Add animation to cards
        $('.card').each(function(index) {
          $(this).css({
            'opacity': '0',
            'transform': 'translateY(20px)'
          }).delay(index * 100).animate({
            'opacity': '1'
          }, 500, function() {
            $(this).css('transform', 'translateY(0)');
          });
        });

        // Highlight row on click
        $('.data-table tbody tr').on('click', function() {
          $(this).addClass('highlight-row').siblings().removeClass('highlight-row');
        });

        // Add tooltips to large numbers
        $('.card-value').each(function() {
          var value = parseInt($(this).text());
          if (value > 1000) {
            $(this).attr('title', 'Total: ' + value.toLocaleString());
          }
        });

        console.log('RTE MIS School Dashboard initialized');
      });
    }
  };

})(jQuery, Drupal);