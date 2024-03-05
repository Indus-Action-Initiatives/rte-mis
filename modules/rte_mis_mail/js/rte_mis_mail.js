(function ($, Drupal, once) {
  Drupal.behaviors.customBehavior = {
    attach: function (context, settings) {
      // Show the `otp prompt` that will used to enter OTP on the event -
      // `verify email` button is clicked, OTP is incorrect, OTP is empty or
      // resend OTP, else hide it.
      if (settings['otp_prompt']) {
        $('#otp-container').addClass('show').removeClass('hide');
      }
      else {
        $('#otp-container').addClass('hide').removeClass('show');
      }
      // Show the `verify email` button on the event - mail is already
      // registered, mail is not valid or mail input is empty, else hide
      // this button.
      if (settings['email_verify_prompt']) {
        $('.verify-email-button').addClass('show').removeClass('hide');
      }else if (settings['otp_prompt']) {
        $('.verify-email-button').addClass('hide').removeClass('show');
      }
      // Once the email is verified hide the `verify email` button and add class
      // to show the mail is verified.
      if (settings['otp_verified']) {
        $('.verify-email-button').addClass('hide').removeClass('show');
        $(once('otp-verify','#email-container', context)).find('.form-item.verified').addClass('show');

      }
      // If user is typing in the mail input, then show the `verify mail` button
      // Hide the `otp prompt` and remove the verified class from mail input.
      $(once('email-verify','#email-container .container-email-field', context)).each(function () {
        var $input = $(this);
        var val = $input.val();
        $input.keyup(function (e) {
          if (val !== $(this).val()) {
            val = $(this).val();
            var container = $input.parents('#email-container');
            container.find('.verify-email-button').removeClass('hide').addClass('show');
            container.find('#otp-container input[type="number"]').val('');
            container.find('#otp-container').addClass('hide').removeClass('show');
            container.find('.form-item.verified').removeClass('show').addClass('hide');
          }
        });
      })
      // Hide the `otp prompt` on the page load.
      $(once('otp-container', 'body', context)).each(function () {
        $('#otp-container').addClass('hide').removeClass('show');
      })
    },
  };
})(jQuery, Drupal, once);
