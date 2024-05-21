(function ($, Drupal) {
  Drupal.behaviors.validateNumberInput = {
    attach: function (context, settings) {
      $(once('number-validation', '.number-validate', context)).on('keypress', function (e) {
        var charCode = (typeof e.which == "undefined") ? e.keyCode : e.which;
        var charStr = String.fromCharCode(charCode);
        if (!charStr.match(/^[0-9]+$/)) {
          e.preventDefault();
        }
      });
      $(once('decimal-validation', '.decimal-validate', context)).on('keypress', function (e) {
        var charCode = (typeof e.which === "undefined") ? e.keyCode : e.which;
        var charStr = String.fromCharCode(charCode);
        // Allow numbers, single decimal point, and backspace (8), delete (46), and arrow keys (37-40)
        if (!charStr.match(/^[0-9.]$/) && ![8, 46, 37, 38, 39, 40].includes(charCode)) {
          e.preventDefault();
        }
        // Prevent multiple decimal points
        if (charStr === '.' && $(this).val().indexOf('.') !== -1) {
          e.preventDefault();
        }
      });
      // Prevent invalid characters on paste
      $(once('paste-validation', '.paste-validate', context)).on('paste', function (e) {
        var pastedData = (e.originalEvent || e).clipboardData.getData('text/plain');
        if (!pastedData.match(/^[0-9]*\.?[0-9]*$/)) {
          e.preventDefault();
        }
      });
    }
  };
})(jQuery, Drupal);
