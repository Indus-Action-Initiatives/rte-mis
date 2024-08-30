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
          order: [[0, "desc"]],
          ajax: {
            url: URL,
            type: "GET",
          },
          columns: columns,
          error: function (xhr, status, error) {
            console.error('Error:', error);
          },
          initComplete: function () {
            var api = this.api();
            var $tfoot = $(api.table().footer());
            var $tr = $('<tr>');
            // Enable column filtering for each specified column
            this.api().columns().every(function () {
              var column = this;
              var headerText = $(column.header()).text();
              // Skip creating the filter input for the "Channel" column
              if (headerText.toLowerCase() === 'channel') {
                $tr.append($('<td>')); // Append an empty cell to maintain the layout
                return;
              }
              // Create search input for each column in the footer
              var $input = $('<input type="text" placeholder="Search ' + headerText + '" />');
              // Column-wise filtering.
              $input.on('keyup', function () {
                var searchValue = $(this).val().toLowerCase();
                column.search(searchValue).draw();
              });
              // Append input to row
              $tr.append($('<td>').append($input));
            });            
            // Append row to footer
            $tfoot.empty().append($tr);
          }
        });
      });
    }
  };
})(jQuery, Drupal, drupalSettings, once);
