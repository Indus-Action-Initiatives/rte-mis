let $ = jQuery;
(function ($, Drupal, once) {
    Drupal.behaviors.registerformMailvalidation = {
        attach: function (context, settings) {
            $(once('wrapAll', '.content-wrapper .menu--social-links, .content-wrapper .menu--get-in-touch', context)).wrapAll('<div class="menu-social-links-wrapper block"></div>');
            var $footerItems = $(once('wrapAll', '.content-wrapper .footer__site-logo-wrapper, .content-wrapper .menu--quick-links, .content-wrapper .menu--support, .content-wrapper .menu-social-links-wrapper, .content-wrapper .block-site__menu-text-section-block'), context);
            $footerItems.wrapAll("<div class='footer-wrapper'></div>");
            $(once('wrapAll', '.region-subheader-wrapper .block-site-logo-section-block, .region-subheader-wrapper .menu--main-menu, .region-subheader-wrapper .menu--account', context)).wrapAll('<div class="main-menu-wrapper"></div>');
            $('.hamburger').click(function () {
                $(this).toggleClass('active');
                $('.main-menu-wrapper .menu').toggleClass('collapsed-menu');
            });
            var line = "<div class='school-note-wrapper'><p>Note: Email ID and mobile number if entered then both verification is mandatory rewrite</p></div>";
            $('.js-form-item.form-item.js-form-type-email.form-type--email.js-form-item-mail.form-item--mail').before(line);
            if ($('#email-container .verify-email-button').length) {
                $('.school-note-wrapper').addClass('active');
            }
        }
    };
})(jQuery, Drupal, once);
