/*! © SpryMedia Ltd - datatables.net/license */
!(function (o) {
  var t;
  "function" == typeof define && define.amd
    ? define(["jquery", "datatables.net"], function (e) {
        return o(e, window, document);
      })
    : "object" == typeof exports
    ? ((t = require("jquery")),
      "undefined" == typeof window
        ? (module.exports = function (e, n) {
            return (
              (e = e || window),
              (n = n || t(e)),
              o(n, 0, e.document)
            );
          })
        : (require("datatables.net")(window, t),
          (module.exports = o(t, window, window.document))))
    : o(jQuery, window, document);
})(function (t, e, i) {
  "use strict";
  var u = t.fn.dataTable;
  return (
    t(i).on("preInit.dt", function (e, n) {
      var o;
      "dt" === e.namespace &&
        (n.oInit.scrollToTop || u.defaults.scrollToTop) &&
        (o = new u.Api(n)).on("page", function () {
          setTimeout(function () {
            // Updating the div for scroll to top.
            var a = document.querySelector('#block-gin-sitepremenutextheader');
            t(i).scrollTop(a).top;
          }, 10);
        });
    }),
    u
  );
});
