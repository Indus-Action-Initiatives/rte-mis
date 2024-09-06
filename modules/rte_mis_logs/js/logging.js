(function ($, Drupal, drupalSettings,once) {
  Drupal.behaviors.customLogViewer = {
    attach: function (context, settings) {
      const URL = "/admin/user/logs/ajax";
      var table;
      $(once('rte_mis_logs', '#filelog-view-table', context)).each(function () {
        var column_headers = drupalSettings.rte_mis_logs.column_headers;
        // Transform the array of strings into an array of objects
        var columns = column_headers.map(function (header) {
          return { data: header, title: header.charAt(0).toUpperCase() + header.slice(1) };
        });
        // Using data tables for getting the values of dynamic table.
        table = $("#filelog-view-table").DataTable({
          processing: true,
          autoWidth: true,
          responsive: true,
          scrollToTop: true,
          order: [[0, "desc"]],
          ajax: {
            url: URL,
            type: "GET",
          },
          columns: columns,
          error: function (xhr, status, error) {
            console.error('Error:', error);
          }
        });
      });
    }
  };
})(jQuery, Drupal, drupalSettings, once);
