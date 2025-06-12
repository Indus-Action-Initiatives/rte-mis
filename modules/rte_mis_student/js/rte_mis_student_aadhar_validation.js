(function ($, Drupal) {
  // a validation function.
  function validateIntegerField(input) {
    var value = $(input).val();
    // if the value is not empty.
    if (value !== '') {
      // If the last character entered is not a number, remove it.
      if (!/^\d$/.test(value.slice(-1))) {
        $(input).val(value.slice(0, -1));
        showError(input, "Enter the last 4 digits of the Aadhar Number");
      } else {
        $(input).removeClass('error').next('.error-message').remove();
      }
    }
  }

  // Function to show error message.
  function showError(input, message) {
    $(input).addClass('error')
      .next('.error-message').remove()
      .end().after('<div class="error-message" style="color: red;">' + message + '</div>');
  }

  // Function to attach validation to input elements.
  function attachValidation(selector) {
    $(selector).on('keypress input', function (event) {
      if (event.type === 'input') {
        // When user starts typing again, remove the error message.
        $(this).removeClass('error').next('.error-message').remove();
      } else if (event.which < 48 || event.which > 57) {
        event.preventDefault();
        showError(this, "Enter the last 4 digits of the Aadhar Number");
        return false;
      }
      // Call the validation function for the current input element.
      validateIntegerField(this);
    });
  }

  Drupal.behaviors.customValidation = {
    attach: function (context, settings) {
      // Attach the validation to all input elements with the specified classes.
      attachValidation('.form-item--field-gaurdian-aadhar-number-0-value, ' +
        '.form-item--field-father-aadhar-number-0-value, ' +
        '.form-item--field-mother-aadhar-number-0-value, ' +
        '.form-item--field-student-aadhar-number-0-value', context);
      var i = 0;
      while (true) {
        var selector = '.form-item--field-siblings-details-' + i + '-subform-field-aadhaar-card-0-value';
        if (!$(selector, context).length) break; // Break loop if no matching elements found.
        attachValidation(selector, context);
        i++;
      }
      const radios = $('.field--name-field-parent-type input[type="radio"]', context);

      function attachInputListeners(selectedRadio, selectedInnerRadio = null) {
        const container = $(context).find('.field-group-tabs-wrapper');
        // Detach any previous input handlers to avoid duplicate event listeners
        container.off('input', 'input[type="text"]');
        
        let fieldsToCompare = [];
        if (selectedRadio == 'father_mother') {
            fieldsToCompare = [
                '.form-item--field-father-aadhar-number-0-value input[type="text"]',
                '.form-item--field-mother-aadhar-number-0-value input[type="text"]',
                '.form-item--field-student-aadhar-number-0-value input[type="text"]'
            ];
        } else if (selectedRadio == 'single_parent') {
            if (!selectedInnerRadio) {
                const innerRadios = $('.field--name-field-single-parent-type input[type="radio"]', context);
                selectedInnerRadio = innerRadios.filter(':checked').val();
                // Attach event listeners to inner radios
                innerRadios.on('change', function () {
                    const updatedInnerRadio = $(this).val();
                    attachInputListeners(selectedRadio, updatedInnerRadio);
                });
            }
            if (selectedInnerRadio == 'father') {
                fieldsToCompare = [
                    '.form-item--field-father-aadhar-number-0-value input[type="text"]',
                    '.form-item--field-student-aadhar-number-0-value input[type="text"]'
                ];
            } else if (selectedInnerRadio == 'mother') {
                fieldsToCompare = [
                    '.form-item--field-mother-aadhar-number-0-value input[type="text"]',
                    '.form-item--field-student-aadhar-number-0-value input[type="text"]'
                ];
            }
        } else if (selectedRadio == 'guardian') {
            fieldsToCompare = [
                '.form-item--field-gaurdian-aadhar-number-0-value input[type="text"]',
                '.form-item--field-student-aadhar-number-0-value input[type="text"]'
            ];
        }
        
        // Add input event listener only to the fields to compare.
        fieldsToCompare.forEach(selector => {
            container.on('input', selector, function () {
                const currentInput = $(this);
                const currentInputValue = currentInput.val();
                let isDuplicate = false;
                fieldsToCompare.forEach(innerSelector => {
                    if (currentInput.is(innerSelector)) return;
                    const fieldValue = container.find(innerSelector).val();
                    if (currentInputValue === fieldValue) {
                        isDuplicate = true;
                    }
                });
                if (isDuplicate) {
                    currentInput.addClass('error');
                    showError(currentInput.parent(), 'Parents/Guardian and Student cannot have the same aadhar number.');
                } else {
                    currentInput.removeClass('error');
                    currentInput.parent().removeClass('error').next('.error-message').remove();
                }
            });
        });
    }
    
    // Attach input listeners based on the initially selected radio button
    const initiallySelectedRadio = radios.filter(':checked').val();
    if (initiallySelectedRadio) {
        attachInputListeners(initiallySelectedRadio);
    }
    
    // Attach event listeners when radio buttons are changed
    radios.on('change', function () {
        const selectedRadio = $(this).val();
        attachInputListeners(selectedRadio);
    });

    }
  };

  // Check for the pin code field.
  Drupal.behaviors.pincodeValidation = {
    attach: function (context, settings) {
      // Use once() to ensure we only attach the event once per element.
      $(once('pincode-check', '[name="field_pincode[0][value]"]', context)).on('input', function () {
        const value = $(this).val().trim();

        // Remove any existing error message
        $(this).removeClass('error').next('.error-message').remove();

        if (!/^\d{6}$/.test(value)) {
          $(this).addClass('error').after('<div class="error-message" style="color: red;">Pincode must be exactly 6 digits.</div>');
        }
      });
    }
  };
})(jQuery, Drupal);
