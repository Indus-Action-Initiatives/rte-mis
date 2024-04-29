(function ($, Drupal, once) {
  Drupal.behaviors.resendTimerTest = {
    attach: function (context, settings) {
      // Check if the timer wrapper is available. If 'YES' then show the timer
      // to the end user.
      // Proceed only if the mail settings as available in the settings.
      if (settings.rte_mis_student) {
        // Check which wrapper is available for resend otp.
        if (document.getElementById('resend-timer')) {
          const {
            resend_time: resendTime,
          } = settings.rte_mis_student;

          // Show the timer only if the send time is set.
          if (resendTime) {
            var d = $.fn.resendOtp(resendTime, 'resend-timer', 'otp-send-button');
          }
        }
      }

      $('#phone-number-field').each(function() {
        // console.log('called');
        const $input = $(this);
        const buttonClass = $('.otp-send-button');
        const val = $input.val();
        $input.keyup(function (e) {
          if (val !== $(this).val()) {
            clearInterval(d);
            settings.rte_mis_student = false;
            // Enable the resend button.
            buttonClass.removeAttr('disabled');
            buttonClass.removeClass('is-disabled');
            $('#resend-timer').remove();
          }
        })
      })
    }
  }

  /**
   * Callback function to set the interval for resend OTP.
   */
  $.fn.resendOtp = function (resendTime, wrapperId, buttonClass) {
    // Clear the previous interval if any.
    clearInterval();
    // Show the timer only if the send time is set.
    if (resendTime) {
      const element = document.createElement('div');
      const countDownDate = new Date(resendTime * 1000 + 30000).getTime();
      // Prepare the time in the desired format.
      // Update the count down every 1 second.
      var x = setInterval(function () {
        // Get today's date and time
        var now = new Date().getTime();

        // Find the distance between now and the count down date
        var distance = countDownDate - now;

        // Time calculations for seconds
        var seconds = Math.floor((distance % (1000 * 60)) / 1000);
        $('.' + buttonClass).prop('disabled');
        $('.' + buttonClass).addClass('is-disabled');

        // Display the result in the element with id="demo"
        element.innerHTML = "You can request new OTP in: " + seconds + "s";

        // If the count down is finished, write some text
        if (distance < 0) {
          clearInterval(x);
          element.innerHTML = "";
          // Enable the resend button.
          $('.' + buttonClass).removeAttr('disabled');
          $('.' + buttonClass).removeClass('is-disabled');
        }
      }, 1000);

      $('#' + wrapperId).html(element);
      return x;
    }
  }
})(jQuery, Drupal, once);
