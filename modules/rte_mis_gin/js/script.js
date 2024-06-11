(function ($, Drupal, once) {
    Drupal.behaviors.wrapBlockMenu = {
        attach: function (context, settings) {
            $(once('wrapAll', '.content-wrapper .menu--social-links, .content-wrapper .menu--get-in-touch', context)).wrapAll('<div class="menu-social-links-wrapper block"></div>');
            $(once('wrapAll', '.content-wrapper .block-system', context)).wrapAll('<div class="layout-container"></div>');
            var $footerItems = $(once('wrapAll', '.content-wrapper .footer__site-logo-wrapper, .content-wrapper .menu--quick-links, .content-wrapper .menu--support, .content-wrapper .menu-social-links-wrapper, .content-wrapper .block-site__menu-text-section-block'), context);
            $footerItems.wrapAll("<div class='footer-wrapper'></div>");
            $(once('wrapAll', '.region-subheader-wrapper .block-site-logo-section-block, .region-subheader-wrapper .menu--main-menu, .region-subheader-wrapper .menu--account', context)).wrapAll('<div class="main-menu--wrapper"><div class="main-menu-wrapper"></div></div>');
            $('.hamburger').off('click');
            $('.hamburger').on('click', function() {
                $(this).toggleClass('active');
                $('.main-menu--wrapper .menu').toggleClass('collapsed-menu');
                $('body').toggleClass('mobil-menu-active');
            });            
            $('.menu-item--expanded').click(function() {
                var viewportWidth = $(window).width();
                if (viewportWidth <= 992) {
                    console.log('Clicked: .menu-item--expanded');
                    console.log('Viewport width:', viewportWidth);
                    var collapsedMenu = $(this).find('.collapsed-menu');
                    if (collapsedMenu.length > 0) {
                        console.log('Found .collapsed-menu element:', collapsedMenu);
                        collapsedMenu.toggleClass('abcxyz');
                        console.log('Toggled class .abcxyz');
                    } else {
                        console.log('No .collapsed-menu element found');
                    }
                    $(this).toggleClass('active');
                    console.log('Toggled class .active on .menu-item--expanded');
                }
            });
            function updateMenuPosition() {
                if ($(window).width() <= 992) {
                    var preMenuHeight = $('.block-site-pre-menu-text-section-block').outerHeight();
                    $('.main-menu--wrapper').addClass('fixed')
                        .css({
                            'top': preMenuHeight + 'px',
                            'height': 'calc(100vh - ' + preMenuHeight + 'px)'
                        });
                } else {
                    $('.main-menu--wrapper').addClass('fixed').css({
                        'top': '',
                        'height': '100%'
                    });
                }
            }
            updateMenuPosition();
            $(window).resize(function() {
                updateMenuPosition();
            });
        }
    };
})(jQuery, Drupal, once);
