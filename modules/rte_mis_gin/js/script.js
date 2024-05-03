let $ = jQuery;
(function ($, Drupal, once) {
    Drupal.behaviors.wrapBlockMenu = {
        attach: function (context, settings) {
            $(once('wrapAll', '.content-wrapper .menu--social-links, .content-wrapper .menu--get-in-touch', context)).wrapAll('<div class="menu-social-links-wrapper block"></div>');
            $(once('wrapAll', '.content-wrapper .block-system', context)).wrapAll('<div class="layout-container"></div>');
            var $footerItems = $(once('wrapAll', '.content-wrapper .footer__site-logo-wrapper, .content-wrapper .menu--quick-links, .content-wrapper .menu--support, .content-wrapper .menu-social-links-wrapper, .content-wrapper .block-site__menu-text-section-block'), context);
            $footerItems.wrapAll("<div class='footer-wrapper'></div>");
            $(once('wrapAll', '.region-subheader-wrapper .block-site-logo-section-block, .region-subheader-wrapper .menu--main-menu, .region-subheader-wrapper .menu--account', context)).wrapAll('<div class="main-menu-wrapper"></div>');
            $('.hamburger').click(function () {
                $(this).toggleClass('active');
                $('.main-menu-wrapper .menu').toggleClass('collapsed-menu');
            });
        }
    };
})(jQuery, Drupal, once);
