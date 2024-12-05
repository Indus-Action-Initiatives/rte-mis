(function ($, Drupal, once) {
  Drupal.behaviors.horizontalTabsNavigation = {
    attach: function (context, settings) {
      once('horizontalTabsNavigation', '.horizontal-tabs', context).forEach(function (tabsElement) {
        var $tabs = $(tabsElement);
        var $tabLinks = $tabs.find('.horizontal-tabs-list a');
        var $tabContent = $tabs.find('.horizontal-tabs-pane');
        var $saveButton = $('[data-drupal-selector="edit-submit"]', context);

        // Hide the save button initially.
        $saveButton.hide();

        // Create the cancel button.
        var $cancelButton = $('<button type="button" class="button cancel-tab">Cancel</button>');
        $cancelButton.click(function () {
          // Show confirmation alert.
          var confirmCancel = confirm('Are you sure you want to cancel? Unsaved changes will be lost.');
          if (confirmCancel) {
            // Redirect to the destination URL from the query parameter.
            var urlParams = new URLSearchParams(window.location.search);
            var destination = urlParams.get('destination') || '/';
            window.location.href = destination;
          }
        });

        // Add Prev and Next buttons.
        $tabContent.each(function (index) {
          var $content = $(this);

          // Create button container for alignment
          var $buttonContainer = $('<div class="button-container"></div>').css({
            display: 'flex',
            justifyContent: 'space-between',
            gap: '10px',
            marginTop: '20px'
          });

          var $prevButton = $('<button type="button" class="button prev-tab">Previous</button>');
          var $nextButton = $('<button type="button" class="button next-tab">Next</button>');

          if (index > 0) {
            $prevButton.click(function () {
              $tabLinks.eq(index - 1).trigger('click');
            });
            $buttonContainer.append($prevButton);
          }

          if (index < $tabContent.length - 1) {
            $nextButton.click(function () {
              // Trigger validation for fields in the current tab.
              var isValid = validateTabFields($content, index);
              if (isValid) {
                $tabLinks.eq(index + 1).trigger('click');
              } else {
                alert('Please fill the required fields before moving to the next tab.');
              }
            });
            $buttonContainer.append($nextButton);
          }

          if (index === $tabContent.length - 1) {
            $nextButton.hide();

            $buttonContainer.css({
              justifyContent: 'space-between'
            });

            $buttonContainer.append($prevButton);
            $buttonContainer.append($cancelButton);
            $buttonContainer.append($saveButton);

            $saveButton.show();
          }

          $content.append($buttonContainer);
        });

        // Update the save and cancel button visibility and position on tab change.
        $tabLinks.on('click', function () {
          var activeIndex = $tabLinks.index(this);
          if (activeIndex === $tabContent.length - 1) {
            var $lastTabContent = $tabContent.eq(activeIndex);
            var $buttonContainer = $lastTabContent.find('.button-container');

            // Ensure buttons are correctly appended in the last tab
            if ($buttonContainer.length) {
              $buttonContainer.append($cancelButton);
              $buttonContainer.append($saveButton);
            }
            $saveButton.show();
          } else {
            $saveButton.hide();
            $cancelButton.detach();
          }
        });

        // Function to validate fields in a tab.
        function validateTabFields($tab, index) {
          var isValid = true;

          if (index === 3) {
            var $radioGroup = $tab.find('input[name="field_applied_category"]:checked');

            if ($radioGroup.length === 0) {
              isValid = false;

              $tab.find('input[name="field_applied_category"]').each(function () {
                $(this).addClass('error');
                $(this).next('label').addClass('error');
              });

              // Remove any existing error message.
              $tab.find('.error-message').remove();
              var $firstRadioLabel = $tab.find('input[name="field_applied_category"]').last().next('label');
              if ($firstRadioLabel.length > 0) {
                $firstRadioLabel.after('<div class="error-message" style="color:red;">Please select an option from the Applied Category.</div>');
              }
            } else {
              $tab.find('input[name="field_applied_category"]').removeClass('error');
              $tab.find('input[name="field_applied_category"]').next('label').removeClass('error');

              $tab.find('.error-message').each(function () {
                if ($(this).text().includes('Applied Category')) {
                  $(this).remove();
                }
              });
            }
          }

          $tab.find('input, select, textarea').each(function () {
            var $field = $(this);

            if ($field.attr('name') === 'field_applied_category') {
              return;
            }

            if ($field.prop('required') && !$field.val()) {
              isValid = false;
              $field.addClass('error');
              $field.next('.error-message').remove();
              $field.after('<div class="error-message" style="color:red;">This field is required.</div>');
            } else {
              $field.removeClass('error');
              $field.next('.error-message').remove();
            }
          });

          return isValid;
        }
      });
    }
  };
})(jQuery, Drupal, once);
