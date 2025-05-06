/**
 * Page User List
 */

'use strict';
var memberList = [];
var dt_user = null;
var groupList = [];
var configList = [];
async function get_member() {
    memberList = [];
    const response = await axios.get(`/api/index.php?model=member&method=get_member`);
    // check if the response is successful
    if (response.status !== 200 || !response.data || !response.data.list) {
      handleErrors(response.data);
    }
    memberList = response.data.list;
    groupList = response.data.group;
    configList = response.data.list_config;

    return memberList;
}

function generateGroupList(){
    const groupListElement = document.getElementById('UserGroup');
    const roleListElement = document.getElementById('UserRole');
    const addGroupListElement = document.getElementById('add-user-group');
    const editGroupListElement = document.getElementById('edit-user-group');
    const addTypeListElement = document.getElementById('add-user-type');
    const editTypeListElement = document.getElementById('edit-user-type');
    Object.entries(groupList).forEach(([key, value]) => {
        const option = document.createElement('option');
        option.value = value;
        option.textContent = value;
        const option2 = document.createElement('option');
        option2.value = value;
        option2.textContent = value;
        const option3 = document.createElement('option');
        option3.value = value;
        option3.textContent = value;
        groupListElement.appendChild(option);
        addGroupListElement.appendChild(option2);
        editGroupListElement.appendChild(option3);
    });
    Object.entries(configList).forEach(([key, value]) => {
        const option = document.createElement('option');
        option.value = value.config_name;
        option.textContent = value.config_name;
        const option2 = document.createElement('option');
        option2.value = value.config_name;
        option2.textContent = value.config_name;
        addTypeListElement.appendChild(option);
        editTypeListElement.appendChild(option2);
    });

    groupListElement.addEventListener('change', (e) => {
        dt_user.column(3).search(e.target.value).draw();
    });

    roleListElement.addEventListener('change', (e) => {
        dt_user.column(2).search(e.target.value).draw();
    });

}

function drawTable(data){
    if (dt_user) {
        dt_user.clear().rows.add(Object.values(data)).draw();
    }
}

async function changeData(){
    displayHourglass();
    const data = await get_member();
    drawTable(data);
    hideHourglass();
    return data;
  }

// Datatable (js)
document.addEventListener('DOMContentLoaded', async function (e) {
  let borderColor, bodyBg, headingColor;

  borderColor = config.colors.borderColor;
  bodyBg = config.colors.bodyBg;
  headingColor = config.colors.headingColor;

  // Variable declaration for table
  const dt_user_table = document.querySelector('.datatables-users'),
    userView = '/member/view.php',
    statusObj = {
      1: { title: 'Pending', class: 'bg-label-warning' },
      2: { title: 'Active', class: 'bg-label-success' },
      3: { title: 'Inactive', class: 'bg-label-secondary' }
    };
  var select2 = $('.select2');

  if (select2.length) {
    var $this = select2;
    $this.wrap('<div class="position-relative"></div>').select2({
      placeholder: 'Select Country',
      dropdownParent: $this.parent()
    });
  }

  // Users datatable
  if (dt_user_table) {
    dt_user = new DataTable(dt_user_table, {
        pageLength: 50,
        columns: [
            { data: 'id' },
            { data: 'realname' },
            { data: 'authority' },
            { data: 'group_name' },
            { data: 'member_type_name' },
            { data: 'status' },
            { data: 'action' }
        ],
        columnDefs: [
            {
            // For Responsive
            className: 'control',
            searchable: false,
            orderable: false,
            responsivePriority: 1,
            targets: 0,
            render: function (data, type, full, meta) {
                return '';
            }
            },
            {
            targets: 1,
            responsivePriority: 2,
            render: function (data, type, full, meta) {
                var name = full['realname'];
                var email = full['user_email'];
                var image = full['user_image'];
                var output;

                if (image) {
                // For Avatar image
                output = '<img src="' + assetsPath + 'upload/avatar/' + image + '" alt="Avatar" class="rounded-circle">';
                } else {
                output = '<img src="' + assetsPath + 'img/avatars/1.png" alt="Avatar" class="rounded-circle">';
                }

                // Creates full output for row
                var row_output =
                '<div class="d-flex justify-content-start align-items-center user-name">' +
                '<div class="avatar-wrapper">' +
                '<div class="avatar avatar-sm me-4">' +
                output +
                '</div>' +
                '</div>' +
                '<div class="d-flex flex-column">' +
                '<a href="' +
                userView +
                '?id=' +
                full['id'] +
                '" class="text-heading text-truncate"><span class="fw-medium">' +
                name +
                '</span></a>' +
                '<small>' +
                email +
                '</small>' +
                '</div>' +
                '</div>';
                return row_output;
            }
            },
            {
            targets: 2,
            render: function (data, type, full, meta) {
                var role = full['authority'];
                var roleBadgeObj = {
                member: '<i class="icon-base ti tabler-user icon-md text-primary me-2"></i>',
                editor: '<i class="icon-base ti tabler-edit icon-md text-warning me-2"></i>',
                manager: '<i class="icon-base ti tabler-crown icon-md text-success me-2"></i>',
                administrator: '<i class="icon-base ti tabler-device-desktop icon-md text-danger me-2"></i>'
                };
                return (
                "<span class='text-truncate d-flex align-items-center text-heading'>" +
                (roleBadgeObj[role] || '') + // Ensures badge exists for the role
                role +
                '</span>'
                );
            }
            },
            {
            // Plans
            targets: 3,
            render: function (data, type, full, meta) {
                const plan = full['group_name'];

                return '<span class="text-heading">' + plan + '</span>';
            }
            },
            {
            // User Status
            targets: 5,
            render: function (data, type, full, meta) {
                const status = full['is_suspend'];
                if(status == 1){
                return '<span class="badge bg-label-warning">停止</span>';
                }else{
                return '<span class="badge bg-label-success">アクティブ</span>';
                }
            }
            },
            {
            targets: -1,
            title: 'Actions',
            searchable: false,
            orderable: false,
            render: (data, type, full, meta) => {
                return `
                <div class="d-flex align-items-center">
                    <a href="${userView}?id=${full['id']}" class="btn btn-text-secondary rounded-pill waves-effect btn-icon">
                    <i class="icon-base ti tabler-eye icon-22px"></i>
                    </a>
                    <a href="javascript:;" class="btn btn-text-secondary rounded-pill waves-effect btn-icon dropdown-toggle hide-arrow" data-bs-toggle="dropdown">
                    <i class="icon-base ti tabler-dots-vertical icon-22px"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end m-0">
                    <a href="javascript:;" class="dropdown-item">編集</a>
                    <a href="javascript:;" class="dropdown-item">退職者へ変更</a>
                    <a href="javascript:;" class="dropdown-item">停止する</a>
                    </div>
                </div>
                `;
            }
            }
        ],
        layout: {
            topStart: {
            rowClass: 'row m-3 my-0 justify-content-between',
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
                    placeholder: 'Search User',
                    text: '_INPUT_'
                }
                },
                {
                buttons: [
                    {
                    extend: 'collection',
                    className: 'btn btn-label-secondary dropdown-toggle',
                    text: '<span class="d-flex align-items-center gap-2"><i class="icon-base ti tabler-upload icon-xs"></i> <span class="d-none d-sm-inline-block">書き出し</span></span>',
                    buttons: [
                        {
                        extend: 'print',
                        text: `<span class="d-flex align-items-center"><i class="icon-base ti tabler-printer me-1"></i>印刷</span>`,
                        className: 'dropdown-item',
                        exportOptions: {
                            columns: [1,2,3,4,5,6],
                            format: {
                            body: function (inner, coldex, rowdex) {
                                if (inner.length <= 0) return inner;

                                // Check if inner is HTML content
                                if (inner.indexOf('<') > -1) {
                                const parser = new DOMParser();
                                const doc = parser.parseFromString(inner, 'text/html');

                                // Get all text content
                                let text = '';

                                // Handle specific elements
                                const userNameElements = doc.querySelectorAll('.user-name');
                                if (userNameElements.length > 0) {
                                    userNameElements.forEach(el => {
                                    // Get text from nested structure
                                    const nameText =
                                        el.querySelector('.fw-medium')?.textContent ||
                                        el.querySelector('.d-block')?.textContent ||
                                        el.textContent;
                                    text += nameText.trim() + ' ';
                                    });
                                } else {
                                    // Get regular text content
                                    text = doc.body.textContent || doc.body.innerText;
                                }

                                return text.trim();
                                }

                                return inner;
                            }
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
                        text: `<span class="d-flex align-items-center"><i class="icon-base ti tabler-file-text me-1"></i>Csv</span>`,
                        className: 'dropdown-item',
                        exportOptions: {
                            columns: [1,2,3,4,5,6],
                            format: {
                            body: function (inner, coldex, rowdex) {
                                if (inner.length <= 0) return inner;

                                // Parse HTML content
                                const parser = new DOMParser();
                                const doc = parser.parseFromString(inner, 'text/html');

                                let text = '';

                                // Handle user-name elements specifically
                                const userNameElements = doc.querySelectorAll('.user-name');
                                if (userNameElements.length > 0) {
                                userNameElements.forEach(el => {
                                    // Get text from nested structure - try different selectors
                                    const nameText =
                                    el.querySelector('.fw-medium')?.textContent ||
                                    el.querySelector('.d-block')?.textContent ||
                                    el.textContent;
                                    text += nameText.trim() + ' ';
                                });
                                } else {
                                // Handle other elements (status, role, etc)
                                text = doc.body.textContent || doc.body.innerText;
                                }

                                return text.trim();
                            }
                            }
                        }
                        },
                        {
                        extend: 'excel',
                        text: `<span class="d-flex align-items-center"><i class="icon-base ti tabler-file-spreadsheet me-1"></i>Excel</span>`,
                        className: 'dropdown-item',
                        exportOptions: {
                            columns: [1,2,3,4,5,6],
                            format: {
                            body: function (inner, coldex, rowdex) {
                                if (inner.length <= 0) return inner;

                                // Parse HTML content
                                const parser = new DOMParser();
                                const doc = parser.parseFromString(inner, 'text/html');

                                let text = '';

                                // Handle user-name elements specifically
                                const userNameElements = doc.querySelectorAll('.user-name');
                                if (userNameElements.length > 0) {
                                userNameElements.forEach(el => {
                                    // Get text from nested structure - try different selectors
                                    const nameText =
                                    el.querySelector('.fw-medium')?.textContent ||
                                    el.querySelector('.d-block')?.textContent ||
                                    el.textContent;
                                    text += nameText.trim() + ' ';
                                });
                                } else {
                                // Handle other elements (status, role, etc)
                                text = doc.body.textContent || doc.body.innerText;
                                }

                                return text.trim();
                            }
                            }
                        }
                        },
                        // {
                        // extend: 'pdf',
                        // text: `<span class="d-flex align-items-center"><i class="icon-base ti tabler-file-description me-1"></i>Pdf</span>`,
                        // className: 'dropdown-item',
                        // exportOptions: {
                        //     columns: [1,2,3,4,5,6],
                        //     format: {
                        //     body: function (inner, coldex, rowdex) {
                        //         if (inner.length <= 0) return inner;

                        //         // Parse HTML content
                        //         const parser = new DOMParser();
                        //         const doc = parser.parseFromString(inner, 'text/html');

                        //         let text = '';

                        //         // Handle user-name elements specifically
                        //         const userNameElements = doc.querySelectorAll('.user-name');
                        //         if (userNameElements.length > 0) {
                        //         userNameElements.forEach(el => {
                        //             // Get text from nested structure - try different selectors
                        //             const nameText =
                        //             el.querySelector('.fw-medium')?.textContent ||
                        //             el.querySelector('.d-block')?.textContent ||
                        //             el.textContent;
                        //             text += nameText.trim() + ' ';
                        //         });
                        //         } else {
                        //         // Handle other elements (status, role, etc)
                        //         text = doc.body.textContent || doc.body.innerText;
                        //         }

                        //         return text.trim();
                        //     }
                        //     }
                        // }
                        // },
                        {
                        extend: 'copy',
                        text: `<i class="icon-base ti tabler-copy me-1"></i>コピー`,
                        className: 'dropdown-item',
                        exportOptions: {
                            columns: [1,2,3,4,5,6],
                            format: {
                            body: function (inner, coldex, rowdex) {
                                if (inner.length <= 0) return inner;

                                // Parse HTML content
                                const parser = new DOMParser();
                                const doc = parser.parseFromString(inner, 'text/html');

                                let text = '';

                                // Handle user-name elements specifically
                                const userNameElements = doc.querySelectorAll('.user-name');
                                if (userNameElements.length > 0) {
                                userNameElements.forEach(el => {
                                    // Get text from nested structure - try different selectors
                                    const nameText =
                                    el.querySelector('.fw-medium')?.textContent ||
                                    el.querySelector('.d-block')?.textContent ||
                                    el.textContent;
                                    text += nameText.trim() + ' ';
                                });
                                } else {
                                // Handle other elements (status, role, etc)
                                text = doc.body.textContent || doc.body.innerText;
                                }

                                return text.trim();
                            }
                            }
                        }
                        }
                    ]
                    },
                    {
                    text: '<span class="d-flex align-items-center gap-2"><i class="icon-base ti tabler-plus icon-xs"></i> <span class="d-none d-sm-inline-block">メンバー追加</span></span>',
                    className: 'add-new btn btn-primary',
                    attr: {
                        'data-bs-toggle': 'offcanvas',
                        'data-bs-target': '#offcanvasAddUser'
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
        language: {
            sLengthMenu: '_MENU_',
            search: '',
            searchPlaceholder: 'Search User',
            paginate: {
            next: '<i class="icon-base ti tabler-chevron-right scaleX-n1-rtl icon-18px"></i>',
            previous: '<i class="icon-base ti tabler-chevron-left scaleX-n1-rtl icon-18px"></i>',
            first: '<i class="icon-base ti tabler-chevrons-left scaleX-n1-rtl icon-18px"></i>',
            last: '<i class="icon-base ti tabler-chevrons-right scaleX-n1-rtl icon-18px"></i>'
            }
        },
        // For responsive popup
        responsive: {
            details: {
            display: DataTable.Responsive.display.modal({
                header: function (row) {
                const data = row.data();
                return 'Details of ' + data['full_name'];
                }
            }),
            type: 'column',
            renderer: function (api, rowIdx, columns) {
                const data = columns
                .map(function (col) {
                    return col.title !== '' // Do not show row in modal popup if title is blank (for check box)
                    ? `<tr data-dt-row="${col.rowIndex}" data-dt-column="${col.columnIndex}">
                        <td>${col.title}:</td>
                        <td>${col.data}</td>
                        </tr>`
                    : '';
                })
                .join('');

                if (data) {
                const div = document.createElement('div');
                div.classList.add('table-responsive');
                const table = document.createElement('table');
                div.appendChild(table);
                table.classList.add('table');
                const tbody = document.createElement('tbody');
                tbody.innerHTML = data;
                table.appendChild(tbody);
                return div;
                }
                return false;
            }
            }
        },
        initComplete: function () {
            // const api = this.api();

            // // Helper function to create a select dropdown and append options
            // const createFilter = (columnIndex, containerClass, selectId, defaultOptionText) => {
            //     const column = api.column(columnIndex);
            //     const select = document.createElement('select');
            //     select.id = selectId;
            //     select.className = 'form-select text-capitalize';
            //     select.innerHTML = `<option value="">${defaultOptionText}</option>`;

            //     document.querySelector(containerClass).appendChild(select);

            //     // Add event listener for filtering
            //     select.addEventListener('change', () => {
            //         const val = select.value ? `^${select.value}$` : '';
            //         column.search(val, true, false).draw();
            //     });

            //     // Populate options based on unique column data
            //     const uniqueData = Array.from(new Set(column.data().toArray())).sort();
            //     uniqueData.forEach(d => {
            //         const option = document.createElement('option');
            //         option.value = d;
            //         option.textContent = d;
            //         select.appendChild(option);
            //     });
            // };

            // // Role filter
            // createFilter(2, '.user_role', 'UserRole', '制限');

            // // Plan filter
            // createFilter(4, '.user_plan', 'UserPlan', 'グループ');

            // // Status filter
            // const statusFilter = document.createElement('select');
            // statusFilter.id = 'FilterTransaction';
            // statusFilter.className = 'form-select text-capitalize';
            // statusFilter.innerHTML = '<option value="">ステータス</option>';
            // document.querySelector('.user_status').appendChild(statusFilter);
            // statusFilter.addEventListener('change', () => {
            // const val = statusFilter.value ? `^${statusFilter.value}$` : '';
            // api.column(6).search(val, true, false).draw();
            // });

            // const statusColumn = api.column(6);
            // const uniqueStatusData = Array.from(new Set(statusColumn.data().toArray())).sort();
            // uniqueStatusData.forEach(d => {
            // const option = document.createElement('option');
            // option.value = statusObj[d]?.title || d;
            // option.textContent = statusObj[d]?.title || d;
            // option.className = 'text-capitalize';
            // statusFilter.appendChild(option);
            // });
        }
    });

    //? The 'delete-record' class is necessary for the functionality of the following code.
    function deleteRecord(event) {
      let row = document.querySelector('.dtr-expanded');
      if (event) {
        row = event.target.parentElement.closest('tr');
      }
      if (row) {
        dt_user.row(row).remove().draw();
      }
    }

    function bindDeleteEvent() {
      const userListTable = document.querySelector('.datatables-users');
      const modal = document.querySelector('.dtr-bs-modal');

      if (userListTable && userListTable.classList.contains('collapsed')) {
        if (modal) {
          modal.addEventListener('click', function (event) {
            if (event.target.parentElement.classList.contains('delete-record')) {
              deleteRecord();
              const closeButton = modal.querySelector('.btn-close');
              if (closeButton) closeButton.click(); // Simulates a click on the close button
            }
          });
        }
      } else {
        const tableBody = userListTable?.querySelector('tbody');
        if (tableBody) {
          tableBody.addEventListener('click', function (event) {
            if (event.target.parentElement.classList.contains('delete-record')) {
              deleteRecord(event);
            }
          });
        }
      }
    }

    // Initial event binding
    bindDeleteEvent();

    // Re-bind events when modal is shown or hidden
    document.addEventListener('show.bs.modal', function (event) {
      if (event.target.classList.contains('dtr-bs-modal')) {
        bindDeleteEvent();
      }
    });

    document.addEventListener('hide.bs.modal', function (event) {
      if (event.target.classList.contains('dtr-bs-modal')) {
        bindDeleteEvent();
      }
    });

    await changeData();
    generateGroupList();
  }

  // Filter form control to default size
  // ? setTimeout used for user-list table initialization
  setTimeout(() => {
    const elementsToModify = [
      { selector: '.dt-buttons .btn', classToRemove: 'btn-secondary' },
      { selector: '.dt-search .form-control', classToRemove: 'form-control-sm' },
      { selector: '.dt-length .form-select', classToRemove: 'form-select-sm', classToAdd: 'ms-0' },
      { selector: '.dt-length', classToAdd: 'mb-md-6 mb-0' },
      {
        selector: '.dt-layout-end',
        classToRemove: 'justify-content-between',
        classToAdd: 'd-flex gap-md-4 justify-content-md-between justify-content-center gap-2 flex-wrap'
      },
      { selector: '.dt-buttons', classToAdd: 'd-flex gap-4 mb-md-0 mb-4' },
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
  }, 100);

  // Validation & Phone mask
  const phoneMaskList = document.querySelectorAll('.phone-mask'),
    addNewUserForm = document.getElementById('addNewUserForm');

  // Phone Number
  if (phoneMaskList) {
    phoneMaskList.forEach(function (phoneMask) {
      phoneMask.addEventListener('input', event => {
        const cleanValue = event.target.value.replace(/\D/g, '');
        phoneMask.value = formatGeneral(cleanValue, {
          blocks: [3, 3, 4],
          delimiters: [' ', ' ']
        });
      });
      registerCursorTracker({
        input: phoneMask,
        delimiter: ' '
      });
    });
  }
  // Add New User Form Validation
  const fv = FormValidation.formValidation(addNewUserForm, {
    fields: {
      userid: {
        validators: {
          notEmpty: {
            message: 'ユーザー名を入力してください'
          },
          stringLength: {
            min: 6,
            message: '6文字以上入力してください'
          }
        }
      },
      password: {
        validators: {
          notEmpty: {
            message: 'パスワードを入力してください'
          },
          stringLength: {
            min: 6,
            message: '6文字以上入力してください'
          }
        }
      },
      realname: {
        validators: {
          notEmpty: {
            message: '氏名を入力してください'
          }
        }
      },
      email: {
        validators: {
          emailAddress: {
            message: 'メールアドレスが不正です'
          }
        }
      },
      // gender: {
      //   validators: {
      //     notEmpty: {
      //       message: '性別を選択してください'
      //     }
      //   }
      // },
      group: {
        validators: {
          notEmpty: {
            message: 'グループを選択してください'  
          }
        }
      },
      type: {
        validators: {
          notEmpty: {
            message: '従業員の種類を選択してください'
          }
        }
      },
      role: {
        validators: {
          notEmpty: {
            message: '制限を選択してください'
          }
        }
      }
    },
    plugins: {
      trigger: new FormValidation.plugins.Trigger(),
      bootstrap5: new FormValidation.plugins.Bootstrap5({
        eleValidClass: '',
        rowSelector: '.form-control-validation'
      }),
    },
  });

  addNewUserForm.addEventListener('submit', function (e) {
    e.preventDefault();
    fv.validate().then(function (status) {
      if (status === 'Valid') {
        displayHourglass();
        const formData = new FormData(addNewUserForm);
        axios.post('/api/index.php?model=member&method=add_member', formData)
          .then(function (response) {
            if (response.status === 200 && response.data && response.data.status === 'success') {
              showMessage('メンバーを追加しました');
              addNewUserForm.reset();
              changeData();
            } else {
              showMessage('メンバーを追加できませんでした', true);
            }
          })
          .catch(function (error) {
            handleErrors(error);
          });
      }
    });
  });

  generatePermit('edit', null, '', document.getElementById('add-user-permit'));
});
