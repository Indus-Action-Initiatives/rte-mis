(function ($, Drupal, once) {
    Drupal.behaviors.registerformMailvalidation = {
        attach: function (context, settings) {
            $('.testimonials--wrapper .view-content').slick({
                dots: true,
                arrows: true,
                infinite: false,
                speed: 300,
                slidesToShow: 4,
                slidesToScroll: 4,
                prevArrow: '<div class="prev-icon"></div>',
                nextArrow: '<div class="next-icon"></div>',
                responsive: [
                  {
                    breakpoint: 1440,
                    settings: {
                      slidesToShow: 3,
                      slidesToScroll: 3,
                      infinite: false,
                      dots: true,
                      arrows: true
                    }
                  },
                  {
                    breakpoint: 1280,
                    settings: {
                      slidesToShow: 2,
                      slidesToScroll: 2,
                      dots: true,
                      arrows: false
                    }
                  },
                  {
                    breakpoint: 768,
                    settings: {
                      slidesToShow: 1,
                      slidesToScroll: 1,
                      dots: true,
                      arrows: false
                    }
                  }
                ]
              });
        }
    };
})(jQuery, Drupal, once);
