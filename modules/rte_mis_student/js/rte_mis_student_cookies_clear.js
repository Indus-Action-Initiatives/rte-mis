(function ($, Drupal, once) {
  Drupal.behaviors.studentClearToken = {
    attach: function (context, settings) {
      once('studentClearToken', 'body', context).forEach(function () {
        // Function to get the value of a specific cookie
        function getCookie(name) {
          const value = `; ${document.cookie}`;
          const parts = value.split(`; ${name}=`);
          if (parts.length === 2) {
            return parts.pop().split(';').shift();
          }
        }

        // Function to extract 'code' from a URL query string or destination parameter.
        function extractCodeFromUrl(url) {
          const urlParams = new URLSearchParams(url);
          if (urlParams.has('code')) {
            return urlParams.get('code');
          }
          if (urlParams.has('destination')) {
            const decodedDestination = decodeURIComponent(urlParams.get('destination'));
            if (decodedDestination.includes('?')) {
              const queryString = decodedDestination.split('?')[1];
              const destinationParams = new URLSearchParams(queryString);
              return destinationParams.get('code');
            }
          }
          return null;
        }

        // Retrieve the 'student-token' cookie.
        const tokenCookie = getCookie('student-token');
        
        if (tokenCookie) {
          // Extract the code from the current URL
          const code = extractCodeFromUrl(window.location.search);
          if (code) {
            // Push an initial state into the browser history.
            history.pushState(null, document.title, window.location.href);

            window.addEventListener('popstate', function () {
              const userConfirmed = confirm(
                Drupal.t("Are you sure you want to go back? You will be logged out if 'Ok' is pressed.")
              );

              if (userConfirmed) {
                // Expire the 'student-token' cookie
                Cookies.remove('student-token', { path: '/' });
                location.reload();
              } else {
                history.pushState(null, document.title, window.location.href);
              }
            });
          }
        }
      });
    },
  };
})(jQuery, Drupal, once);
