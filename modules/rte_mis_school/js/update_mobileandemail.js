console.log('update_mobileandemail.js loaded');
(function ($, Drupal, once) {
    Drupal.behaviors.updateEmailMobile = {
    attach: function (context, settings) {
      //on ajax complete update email and mobile fields
      $(document).ajaxComplete(function() {
        var school = $('.user-register-form .field--name-field-school-name input');
        var mobile = school.attr('mobile');
        var email = school.attr('email');
        if (mobile) {
          $('.user-register-form .mobile-number-field input.local-number').val(mobile);
        }
        if (email) {
          $('.user-register-form .form-item--mail input.form-email').val(email);
        }
      });
    }
  };
})(jQuery, Drupal, once);