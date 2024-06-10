(function ($, Drupal, once) {
    Drupal.behaviors.wrapBlockMenu = {
        attach: function (context, settings) {
            // about us content wrapper
            var $tempContainer = $('<div class="about-us-content-notification"></div>');
            $('.about-us-wrapper .notifications-wrapper').appendTo($tempContainer);
            $('.about-us-wrapper .about-us-content .layout-container').appendTo($tempContainer);
            $tempContainer.wrapAll('<div class="about-us-content-notification-wrapper"></div>');
            $('.about-us-wrapper').prepend($tempContainer.parent());
        }
    };
})(jQuery, Drupal, once);
