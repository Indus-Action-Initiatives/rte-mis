(function ($, Drupal, once) {
  Drupal.behaviors.validateCshsElement = {
    attach: function (context, settings) {
      $(document).ready(function() {
        // Disable 1st and 2nd level of cshs select list.
        $('.school-details-cshs div[data-level="0"] select, .school-details-cshs div[data-level="1"] select').prop('disabled', true);

        once('cshs-validation', '.school-details-cshs select', context).forEach(function (element) {
          var $element = $(element);
          var level = $element.closest('div[data-level]').data('level');

          if (level === 0 || level === 1) {
            var selectedText = $element.find('option:selected').text();
            var $readonlyDiv = $('<span>').addClass('readonly-text').text(selectedText).css({ 'padding': '8px', });
            $readonlyDiv.css({ 'display': 'inline-block'});
            $element.after($readonlyDiv); 
            $element.hide();
          }
        })
      })
    }
  };
})(jQuery, Drupal, once);
