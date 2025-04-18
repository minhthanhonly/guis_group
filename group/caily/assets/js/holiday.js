'use strict';
var holidayList = [];

var dt_table = null

async function get_holiday() {
  holidayList = [];
  const response = await axios.get(`/api/index.php?model=timecard&method=get_holiday`);
  // check if the response is successful
  if (response.status !== 200 || !response.data || !response.data.list) {
    handleErrors(response.data);
  }
  holidayList = response.data.list;
 
  return holidayList;
}

//add event listener for selectpicker
function addEvent(){
  document.querySelector('.datatables-holiday').addEventListener('click', function (e) {
    if (e.target.closest('.item-edit')) {
      e.preventDefault();

      // Get the row data (assuming DataTable is used)
      const row = dt_table.row(e.target.closest('tr')).data();

      // Populate the modal with the row data
      document.querySelector('#modalEditID').value = row.id;
      document.querySelector('#modalEditDate').value = row.date;
      document.querySelector('#modalEditDateOld').value = row.date;
      document.querySelector('#modalEditName').value = row.name;

      // Open the modal
      const editModal = new bootstrap.Modal(document.getElementById('editRoleModal'));
      editModal.show();
    }


    if (e.target.closest('.item-delete')) {
      e.preventDefault();
      // Get the row data (assuming DataTable is used)
      const row = dt_table.row(e.target.closest('tr')).data();
      // Populate the modal with the row data
      var id = row.id;
      // call delete function

      Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete it!',
        customClass: {
          confirmButton: 'btn btn-primary',
          cancelButton: 'btn btn-label-secondary'
        },
        buttonsStyling: false
      }).then(function (result) {
        if (result.value) {

          displayHourglass();
          axios({
            method: 'post',
            url: '/api/index.php?model=timecard&method=delete_holiday',
            data: {
              id: id,
            },
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded'
            }
          })
            .then(function (response) {
              if (response.status === 200 && response.data && response.data.status === 'success') {
                handleSuccess(response.data.message_code);
                // delete row from datatable
                dt_table.row(e.target.closest('tr')).remove().draw();
              } else {
                handleErrors(response.data.message_code);
              }
            }).catch(function (error) {
              handleErrors(error);
            });
          
        }
      });

      
      
    }
  });
  
}

async function changeData(){
  displayHourglass();
  const data = await get_holiday();
  drawTable(data);
  hideHourglass();
}

//add event listener for selectpicker
async function initTable(){
  changeData();
}


function drawTable(data){
  if (dt_table) {
    dt_table.clear().rows.add(Object.values(data)).draw();
  }
}


// Filter form control to default size
// ? setTimeout used for multilingual table initialization
setTimeout(() => {
  const elementsToModify = [
    { selector: '.dt-buttons .btn', classToRemove: 'btn-secondary' },
    { selector: '.dt-buttons.btn-group .btn-group', classToRemove: 'btn-group' },
    { selector: '.dt-buttons.btn-group', classToRemove: 'btn-group', classToAdd: 'd-flex' },
    { selector: '.dt-search .form-control', classToRemove: 'form-control-sm' },
    { selector: '.dt-length .form-select', classToRemove: 'form-select-sm' },
    { selector: '.dt-length', classToAdd: 'mb-md-6 mb-0' },
    { selector: '.dt-layout-start', classToAdd: 'ps-3 mt-0' },
    {
      selector: '.dt-layout-end',
      classToRemove: 'justify-content-between',
      classToAdd: 'justify-content-md-between justify-content-center d-flex flex-wrap gap-4 mt-0 mb-md-0 mb-6'
    },
    { selector: '.dt-layout-table', classToRemove: 'row mt-2' },
    { selector: '.dt-layout-full', classToRemove: 'col-md col-12', classToAdd: 'table-responsive' }
  ];

  // Delete record
  elementsToModify.forEach(({ selector, classToRemove, classToAdd }) => {
    document.querySelectorAll(selector).forEach(element => {
      if (classToRemove) {
        classToRemove.split(' ').forEach(className => element.classList.remove(className));
      }
      if (classToAdd) {
        classToAdd.split(' ').forEach(className => element.classList.add(className));
      }
    });
  });
}, 1000);

document.addEventListener('DOMContentLoaded', function () {
  whenContainerReady()
});


function whenContainerReady() {
  const $table = document.querySelector('.datatables-holiday');
  // Users datatable
  if ($table && !dt_table) {
    dt_table = new DataTable($table, {
      paging: true,
      searching: true,
      info: true,
      ordering: true,
      columns: [
        { data: 'id', title: 'ID', visible: false },
        { data: 'date', title: 'Ngày' },
        { data: 'name', title: 'Tên' },
        { data: '', title: '' },
      ],
      columnDefs: [
        {
          // Actions
          targets: -1,
          title: '#',
          searchable: false,
          className: '',
          orderable: false,
          render: function (data, type, full, meta) {
            return (
              '<div class="d-inline-block">' +
              '<a href="javascript:;" class="item-edit text-body"><i class="icon-base ti tabler-pencil"></i></a>' +
              '<a href="javascript:;" class="text-danger item-delete"><i class="icon-base ti tabler-trash"></i></a>' +
              '</div>'
            );
          }
        }
      ],
      createdRow: function (row, data, dataIndex) {
       
      },
      order: [[1, 'desc']], // Order by the first column (Date) in ascending order
      language: {
        // zeroRecords: 'データがありません',
        // emptyTable: 'データがありません',
      },
      layout: {
        topStart: {
          rowClass: 'row my-md-0 me-3 ms-0 justify-content-between',
          features: [
            {
              pageLength: {
                menu: [10, 25, 50, 100],
                text: '_MENU_'
              }
            }
          ]
        },
        topEnd: {
          features: [
            {
              search: {
                placeholder: 'Search ...',
                text: '_INPUT_',
              }
            },
            {
              buttons: [
                {
                  extend: 'collection',
                  className: 'btn btn-label-secondary dropdown-toggle me-4',
                  text: '<span class="d-flex align-items-center gap-1"><i class="icon-base ti tabler-upload icon-xs"></i> <span class="d-inline-block">Export</span></span>',
                  buttons: [
                    {
                      extend: 'print',
                      text: `<span class="d-flex align-items-center"><i class="icon-base ti tabler-printer me-1"></i>Print</span>`,
                      className: 'dropdown-item',
                      exportOptions: {
                        columns: [1, 2],
                        format: {
                        }
                      },
                      customize: function (win) {
                        win.document.body.style.color = config.colors.headingColor;
                        win.document.body.style.borderColor = config.colors.borderColor;
                        win.document.body.style.backgroundColor = config.colors.bodyBg;
                        const table = win.document.body.querySelector('table');
                        table.classList.add('compact');
                        table.style.color = 'inherit';
                        table.style.borderColor = 'inherit';
                        table.style.backgroundColor = 'inherit';
                      }
                    },
                    {
                      extend: 'csv',
                      text: `<span class="d-flex align-items-center"><i class="icon-base ti tabler-file me-1"></i>Csv</span>`,
                      className: 'dropdown-item',
                      exportOptions: {
                        columns: [1, 2],
                      }
                    },
                    {
                      extend: 'excel',
                      text: `<span class="d-flex align-items-center"><i class="icon-base ti tabler-file-export me-1"></i>Excel</span>`,
                      className: 'dropdown-item',
                      exportOptions: {
                        columns: [1, 2],
                      }
                    },
                    {
                      extend: 'pdf',
                      text: `<span class="d-flex align-items-center"><i class="icon-base ti tabler-file-text me-1"></i>Pdf</span>`,
                      className: 'dropdown-item',
                      exportOptions: {
                        columns: [1, 2],
                      }
                    },
                    {
                      extend: 'copy',
                      text: `<i class="icon-base ti tabler-copy me-1"></i>Copy`,
                      className: 'dropdown-item',
                      exportOptions: {
                        columns: [1, 2],
                      }
                    }
                  ]
                },
                {
                  text: '<i class="icon-base ti tabler-plus me-0 me-sm-1 icon-16px"></i><span class="d-none d-sm-inline-block">Thêm ngày nghỉ</span>',
                  className: 'add-new btn btn-primary rounded-2 waves-effect waves-light',
                  attr: {
                    'data-bs-toggle': 'modal',
                    'data-bs-target': '#addRoleModal'
                  }
                }
              ]
            }
          ]
        },
        bottomStart: {
          rowClass: 'row mx-3 justify-content-between',
          features: ['info']
        },
        bottomEnd: 'paging'
      },
    });
  }
  initTable();
  addEvent();



  // Initialize the DataTable
    let fv;
    (() => {
      const formAddNewRecord = document.querySelector('#formAddNewRecord');
      if (formAddNewRecord && typeof FormValidation !== 'undefined') {
    
      // Form validation for Add new record
    
      fv = FormValidation.formValidation(formAddNewRecord, {
          fields: {
            date: {
              validators: {
                notEmpty: {
                  message: 'Vui lòng nhập ngày'
                },
                stringLength: {
                  min: 6,
                  message: 'Vui lòng nhập từ 6 ký tự trở lên'
                }
              }
            },
            name: {
              validators: {
                notEmpty: {
                  message: 'Vui lòng nhập tên ngày nghỉ'
                },
                stringLength: {
                  min: 6,
                  message: 'Vui lòng nhập từ 6 ký tự trở lên'
                }
              }
            },
            
          },
          plugins: {
            trigger: new FormValidation.plugins.Trigger(),
            bootstrap5: new FormValidation.plugins.Bootstrap5({
              eleValidClass: '',
              rowSelector: '.form-control-validation'
            }),
          },
          init: instance => {
            instance.on('plugins.message.placed', e => {
              if (e.element.parentElement.classList.contains('input-group')) {
                e.element.parentElement.insertAdjacentElement('afterend', e.messageElement);
              }
            });
          }
        });
      }
    
      formAddNewRecord.addEventListener('submit', function (e) {
        e.preventDefault();
        console.log('formAddNewRecord');
        fv.validate().then(function (status) {
          if (status === 'Valid') {
            displayHourglass();
            const formData = new FormData(formAddNewRecord);
            axios.post('/api/index.php?model=timecard&method=add_holiday', formData)
              .then(function (response) {
                if (response.status === 200 && response.data && response.data.status === 'success') {
                  handleSuccess(response.data.message_code);
                  formAddNewRecord.reset();
                  changeData();
                } else {
                  handleErrors(response.data.message_code);
                }
              })
              .catch(function (error) {
                handleErrors(error);
              });
          }
        });
      }
      );
    
    })();


    let fv2;
    (() => {
      const formEditRecord = document.querySelector('#formEditRecord');
      if (formEditRecord && typeof FormValidation !== 'undefined') {
        // Form validation for Add new record
        fv2 = FormValidation.formValidation(formEditRecord, {
          fields: {
            date: {
              validators: {
                notEmpty: {
                  message: 'Vui lòng nhập ngày'
                },
                stringLength: {
                  min: 6,
                  message: 'Vui lòng nhập từ 6 ký tự trở lên'
                }
              }
            },
            name: {
              validators: {
                notEmpty: {
                  message: 'Vui lòng nhập tên ngày nghỉ'
                },
                stringLength: {
                  min: 6,
                  message: 'Vui lòng nhập từ 6 ký tự trở lên'
                }
              }
            },
            
          },
          plugins: {
            trigger: new FormValidation.plugins.Trigger(),
            bootstrap5: new FormValidation.plugins.Bootstrap5({
              eleValidClass: '',
              rowSelector: '.form-control-validation'
            }),
          },
          init: instance => {
            instance.on('plugins.message.placed', e => {
              if (e.element.parentElement.classList.contains('input-group')) {
                e.element.parentElement.insertAdjacentElement('afterend', e.messageElement);
              }
            });
          }
        });
      }
    
      formEditRecord.addEventListener('submit', function (e) {
        e.preventDefault();
        fv2.validate().then(function (status) {
          if (status === 'Valid') {
            displayHourglass();
            const formData = new FormData(formEditRecord);
            axios.post('/api/index.php?model=timecard&method=edit_holiday', formData)
              .then(function (response) {
                if (response.status === 200 && response.data && response.data.status === 'success') {
                  handleSuccess(response.data.message_code);
                  formEditRecord.reset();
                  changeData();
                  // Close the modal
                  const editModal = bootstrap.Modal.getInstance(document.getElementById('editRoleModal'));
                  if (editModal) {
                    editModal.hide();
                  }
                } else {
                  handleErrors(response.data.message_code);
                }
              })
              .catch(function (error) {
                handleErrors(error);
              });
          }
        });
      }
      );
    
    })();

  const momentFormat = 'YYYY-MM-DD';

  IMask(document.querySelector('#modalAddDate'), {
    mask: Date,
    pattern: momentFormat,
    lazy: false,
    min: new Date(1970, 0, 1),
    max: new Date(2030, 0, 1),

    format: date => moment(date).format(momentFormat),
    parse: str => moment(str, momentFormat),

    blocks: {
      YYYY: {
        mask: IMask.MaskedRange,
        from: 1970,
        to: 2030
      },
      MM: {
        mask: IMask.MaskedRange,
        from: 1,
        to: 12
      },
      DD: {
        mask: IMask.MaskedRange,
        from: 1,
        to: 31
      },
      HH: {
        mask: IMask.MaskedRange,
        from: 0,
        to: 23
      },
      mm: {
        mask: IMask.MaskedRange,
        from: 0,
        to: 59
      }
    }
  });


  IMask(document.querySelector('#modalEditDate'), {
    mask: Date,
    pattern: momentFormat,
    lazy: false,
    min: new Date(1970, 0, 1),
    max: new Date(2030, 0, 1),

    format: date => moment(date).format(momentFormat),
    parse: str => moment(str, momentFormat),

    blocks: {
      YYYY: {
        mask: IMask.MaskedRange,
        from: 1970,
        to: 2030
      },
      MM: {
        mask: IMask.MaskedRange,
        from: 1,
        to: 12
      },
      DD: {
        mask: IMask.MaskedRange,
        from: 1,
        to: 31
      },
      HH: {
        mask: IMask.MaskedRange,
        from: 0,
        to: 23
      },
      mm: {
        mask: IMask.MaskedRange,
        from: 0,
        to: 59
      }
    }
  });

}
