let $ = jQuery;
(function ($, Drupal, once) {
    Drupal.behaviors.wrapBlockMenu = {
        attach: function (context, settings) {
            $(once('wrapAll', '.content-wrapper .menu--social-links, .content-wrapper .menu--get-in-touch', context)).wrapAll('<div class="menu-social-links-wrapper block"></div>');
            var $footerItems = $(once('wrapAll', '.content-wrapper .footer__site-logo-wrapper, .content-wrapper .menu--quick-links, .content-wrapper .menu--support, .content-wrapper .menu-social-links-wrapper, .content-wrapper .block-site__menu-text-section-block'), context);
            $footerItems.wrapAll("<div class='footer-wrapper abcxyz'></div>");
            $(once('wrapAll', '.region-subheader-wrapper .block-site-logo-section-block, .region-subheader-wrapper .menu--main-menu', context)).wrapAll('<div class="main-menu-wrapper"></div>');
        }
    };
})(jQuery, Drupal, once);

$(document).ready(function(){
    $('.hamburger').click(function(){
      $(this).toggleClass('active');
      $('.main-menu-wrapper .menu').toggleClass('collapsed-menu');
    });
});