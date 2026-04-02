(function ($, Drupal, once) {
  Drupal.behaviors.testimonialSlider = {
    attach: function (context, settings) {
      $(once('testimonial-slider', '.js-testimonial-slider', context)).each(function () {
        const $this = $(this);
        const itemCount = $this.children('.c-testimonial-items__card').length;

        if (itemCount > 1 && !$this.hasClass('slick-initialized')) {
          if ($.fn.slick) {
            $this.slick({
              dots: true,
              arrows: false,
              infinite: true,
              speed: 500,
              slidesToShow: 3,
              slidesToScroll: 3,
              autoplay: true,
              // autoplaySpeed: 5000,
              responsive: [
                {
                  breakpoint: 1200,
                  settings: {
                    slidesToShow: 3,
                    slidesToScroll: 3
                  }
                },
                {
                  breakpoint: 1024,
                  settings: {
                    slidesToShow: 2,
                    slidesToScroll: 2
                  }
                },
                {
                  breakpoint: 768,
                  settings: {
                    slidesToShow: 2,
                    slidesToScroll: 2,
                    dots: true
                  }
                },
                {
                  breakpoint: 600,
                  settings: {
                    slidesToShow: 1,
                    slidesToScroll: 1,
                    dots: true
                  }
                }
              ]
            });
          } else {
            console.warn('Slick library not loaded');
          }
        }
      });
    }
  };
})(jQuery, Drupal, once);
