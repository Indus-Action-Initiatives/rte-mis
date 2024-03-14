(function ($, Drupal, once) {
  Drupal.behaviors.entryClass = {
    attach: function (context, settings) {
      $(once('entry-class', 'body', context)).each(function () {

        $('#entry-class-wrapper .entry-class-select').each(function() {
          // Check if the current value is "_none"
          if ($(this).val() === '_none') {
            $(this).children('option[value!="_none"]').remove();
            // Populate select list with values of selected radio buttons
            var $wrapper = $('#entry-class-wrapper');
            var $radioButtons = $('#default-entry-class input[type="radio"]:checked, #optional-entry-class input[type="radio"]:checked');
            // var radioButtons = $('#default-entry-class input[type="radio"]:checked');
            $radioButtons.each(function() {
              var radioValue = $(this).val();
              var radioLabel = $(this).siblings('label').text();
              console.log();
              // Add radio button value as an option to the select list
              $wrapper.find('.entry-class-select').append('<option value="' + radioValue + '">' + radioLabel + '</option>');
            });
          }
          else {
            var $select = $(this);
            $select.children('option:not([value="_none"]):not(:selected)').remove();
             // Get the value of the checked radio button from optional-entry-class
            var optionalRadioValue = $('#optional-entry-class input[type="radio"]:checked').val();
            var optionalRadioLabel = $('#optional-entry-class input[type="radio"]:checked + label').text();
            // Get the value of the checked radio button from default-entry-class
            var defaultRadioValue = $('#default-entry-class input[type="radio"]:checked').val();
            var defaultRadioLabel = $('#default-entry-class input[type="radio"]:checked + label').text();
            // Merge both values and labels
            var radioValues = [optionalRadioValue, defaultRadioValue];
            var radioLabels = [optionalRadioLabel, defaultRadioLabel];
            // Add the option from the checked radio button to the select list if it's not already there
            for (var i = 0; i < radioValues.length; i++) {
              var radioValue = radioValues[i];
              var radioLabel = radioLabels[i];
              if (radioValue && !$select.find('option[value="' + radioValue + '"]').length) {
                $select.append('<option value="' + radioValue + '">' + radioLabel + '</option>');
              }
            }
          }
        });
      })
    }
  };
})(jQuery, Drupal, once);
