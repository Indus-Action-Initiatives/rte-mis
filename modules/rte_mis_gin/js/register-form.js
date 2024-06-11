(function ($, Drupal, once) {
    Drupal.behaviors.registerformMailvalidation = {
        attach: function (context, settings) {
            var line = "<div class='school-note-wrapper'><p>Note: Email ID and mobile number if entered then both verification is mandatory rewrite</p></div>";
            $('.js-form-item.form-item.js-form-type-email.form-type--email.js-form-item-mail.form-item--mail').before(line);
            if ($('#email-container .verify-email-button').length) {
                $('.school-note-wrapper').addClass('active');
            }
        }
    };
})(jQuery, Drupal, once);
