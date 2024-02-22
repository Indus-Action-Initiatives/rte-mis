(function ($, Drupal) {
  var timerInterval;
  var isTimerRunning = false;
  Drupal.behaviors.customBehavior = {
    attach: function (context, settings) {
      function startTimer() {
        var $button = $('.otp-resend-button');
        if ($button.length && $button.prop('disabled') && !isTimerRunning) {
          isTimerRunning = true;
          // Disable the button and start the reverse timer.
          var timer = 30;
          var interval = setInterval(function () {
            if (timer === 0) {
              clearInterval(interval); // Clearing the interval when timer reaches 0
              isTimerRunning = false;
              $button.prop('disabled', false);
              if ($button.hasClass('is-disabled')) {
                $button.removeClass('is-disabled'); // Removing the 'is-disabled' class if present
              }
              $('#timer').remove();
            } else {
              var minutes = Math.floor(timer / 60);
              var seconds = timer % 60;
              var timeText = 'You can send another OTP after:' + minutes + 'm ' + seconds + 's';
              // Update the timer element
              $('#timer').html(timeText);
              timer--;
            }
          }, 1000);
          timerInterval = interval;
        }
      }
      // Function to stop the timer
      function stopTimer() {
        if (timerInterval) {
          clearInterval(timerInterval);
        }
        var $button = $('#otp-resend-button');
        if ($button.length && !$button.prop('disabled')) {
          $button.prop('disabled', false);
          $button.removeClass('is-disabled');
          $('#timer').empty();
        }
      }
      
      // Listen for the AJAX response and perform actions accordingly.
      $(document).ajaxComplete(function (event, xhr, settings) {

        var emailVerifiedContent = $('.email-verified').length;

        var ajax_url = settings.url;
        verify_url = '/user/register?ajax_form=1&_wrapper_format=drupal_ajax';
        submit_resend_url = '/user/register?ajax_form=1&_wrapper_format=drupal_ajax&_wrapper_format=drupal_ajax'
        if (ajax_url == verify_url) {
          startTimer();
        }
        else if (ajax_url == submit_resend_url && emailVerifiedContent == 1) {
          stopTimer();
        }
        else if (ajax_url == submit_resend_url) {
          startTimer();
        }
      });
      if ($('.email-verified').length > 0) {
        // Attach event listener only if email-verified class is present
        $('.container-email-field').on('input', function () {
          var email = $(this).val();
          var $container = $('#email-container');
          var $verify_btn = $('.verify-email-button');
          if (email.trim() !== '') {
            $('.verify-email-button').removeAttr('style');
            if ($verify_btn.hasClass('is-disabled')) {
              $verify_btn.removeClass('is-disabled'); // Removing the 'is-disabled' class if present
            }
            if (!$('#account-creation-button').hasClass('is-disabled')) {
              $('#account-creation-button').prop('disabled', true);
              $('#account-creation-button').addClass('is-disabled');
            }
            $container.removeClass('email-verified');
          }
        });
      }
    }
  };
})(jQuery, Drupal);

