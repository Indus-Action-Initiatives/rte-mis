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

  Drupal.behaviors.academicYear = {
    attach: function (context, settings) {
      $(document).ready(function () {
        var academicYearSelector = $('.field--name-field-academic-year .form-select', context);
        if ($('.read-only-text', context).length) {
          return;
        }

        if (!academicYearSelector.length) {
          return;
        }

        // Find associated label text
        var labelText = $('label[for="' + academicYearSelector.attr('id') + '"]', context).text();

        // Hide original select wrapper
        academicYearSelector.parent().hide();

        // Get selected option text
        var currentYear = academicYearSelector.find('option:selected').text();

        // Build read-only markup
        var readOnlyWrapper = $('<div>')
          .addClass('read-only-text')
          .css({
            display: 'flex',
            alignItems: 'center',
            gap: '6px'
          })
          .append(
            $('<span>').addClass('field__label').text(labelText + ' : '),
            $('<span>').addClass('field__item').text(currentYear)
          );

        // Insert after hidden field
        academicYearSelector.parent().after(readOnlyWrapper);
      });
    }
  };

})(jQuery, Drupal, once);
