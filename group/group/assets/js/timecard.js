'use strict';

// Datatable (js)
document.addEventListener('DOMContentLoaded', function () {
  // Variable declaration for table
  const dt_user_table = document.querySelector('.datatables-users');

  // Users datatable
  if (dt_user_table) {
    const dt_user = new DataTable(dt_user_table, {
      ajax: {
        url: '/api/index.php?model=timecard&method=index&member=takasaki', // Replace with your API endpoint
        dataSrc: 'list' // Specify the key in the JSON to use as the data source
      },
      columns: [
        { data: 'timecard_date', title: 'Date' },
        { data: 'timecard_open', title: 'Open Time' },
        { data: 'timecard_close', title: 'Close Time' },
        { data: 'timecard_time', title: 'Work Time' },
        { data: 'timecard_timeinterval', title: 'Break Time' },
        { data: 'timecard_comment', title: 'Comment' },
        { data: 'owner', title: 'Owner' }
      ],
      columnDefs: [
        {
          // Add custom rendering for the "Owner" column
          targets: 6,
          render: function (data, type, full, meta) {
            return `<span class="badge bg-label-primary">${data}</span>`;
          }
        }
      ],
      order: [[0, 'asc']], // Order by the first column (Date) in ascending order
      language: {
        search: 'Search:',
        lengthMenu: 'Show _MENU_ entries',
        info: 'Showing _START_ to _END_ of _TOTAL_ entries',
        paginate: {
          next: 'Next',
          previous: 'Previous'
        }
      }
    });
  }
});