/**
 * Page User List
 */

'use strict';
var memberList = [];
var dt_user = null;
var groupList = [];
var configList = [];
var branchList = [];
var departmentList = [];
var user_role = 'member';
if (typeof USER_ROLE !== 'undefined') {
  user_role = USER_ROLE;
}

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
    if(user_role == 'member'){
      memberList = memberList.filter(member => member.group_name != '退職者');
    }
    return memberList;
}

async function loadBranches() {
  branchList = [];
  try {
      const response = await axios.get('/api/index.php?model=branch&method=list');
      branchList = response.data;
  } catch (error) {
      console.error('Error loading branches:', error);
      showMessage('支社の読み込みに失敗しました。', true);
  }
  return branchList;
}

async function loadDepartments() {
  departmentList = [];
  try {
      const response = await axios.get('/api/index.php?model=department&method=list');
      departmentList = response.data;
  } catch (error) {
      console.error('Error loading departments:', error);
      showMessage('部署の読み込みに失敗しました。', true);
  }
  return departmentList;
}

function generateGroupList(){
    const groupListElement = document.getElementById('UserGroup');
    const roleListElement = document.getElementById('UserRole');
    const addGroupListElement = document.getElementById('add-user-group');
    const editGroupListElement = document.getElementById('edit-user-group');
    const addTypeListElement = document.getElementById('add-user-type');
    const editTypeListElement = document.getElementById('edit-user-type');
    const addBranchListElement = document.getElementById('add-user-branch');
    const editBranchListElement = document.getElementById('edit-user-branch');
    const addDepartmentListElement = document.getElementById('add-user-department');
    const editDepartmentListElement = document.getElementById('edit-user-department');
    Object.entries(groupList).forEach(([key, value]) => {
      if(user_role == 'member' && value == '退職者'){
        return;
      }
      const option = document.createElement('option');
      option.value = key;
      option.textContent = value;
      const option2 = document.createElement('option');
      option2.value = key;
      option2.textContent = value;
      const option3 = document.createElement('option');
      option3.value = key;
      option3.textContent = value;
      groupListElement.appendChild(option);
      addGroupListElement.appendChild(option2);
      editGroupListElement.appendChild(option3);
      
    });
    Object.entries(configList).forEach(([key, value]) => {
        const option = document.createElement('option');
        option.value = value.config_type;
        option.textContent = value.config_name;
        const option2 = document.createElement('option');
        option2.value = value.config_type;
        option2.textContent = value.config_name;
        addTypeListElement.appendChild(option);
        editTypeListElement.appendChild(option2);
    });


    roleListElement.addEventListener('change', (e) => {
        dt_user.column(2).search(e.target.value).draw();
    });

    groupListElement.addEventListener('change', (e) => {
      if(e.target.value == ''){
        dt_user.column(3).search('').draw();
      } else{
        var text = e.target.options[e.target.selectedIndex].text;
        dt_user.column(3).search(text).draw();
      }
    });

    branchList.forEach(branch => {
      const option = document.createElement('option');
      const option2 = document.createElement('option');
      option.value = branch.id;
      option.textContent = branch.name;
      option2.value = branch.id;
      option2.textContent = branch.name;
      addBranchListElement.appendChild(option);
      editBranchListElement.appendChild(option2);
    });

    departmentList.forEach(department => {
      const option = document.createElement('option');
      const option2 = document.createElement('option');
      option.value = department.id;
      option.textContent = department.name;
      option2.value = department.id;
      option2.textContent = department.name;
      addDepartmentListElement.appendChild(option);
      editDepartmentListElement.appendChild(option2);
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
    userView = '/member/view.php';
   
  let featureButtons = [
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
              columns: [1,2,3,4,5],
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
              columns: [1,2,3,4,5],
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
              columns: [1,2,3,4,5],
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
          //     columns: [1,2,3,4,5],
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
          'data-bs-toggle': 'modal',
          'data-bs-target': '#modalAddUser'
      }
    }
  ];

  if(user_role != 'administrator' && user_role != 'manager'){
    featureButtons.pop();
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
            { 
              data: 'branch_id',
              title: '支店'
            },
            { 
              data: 'department_id',
              title: '部署'
            },
            { data: 'member_type_name',
              title: '種類'
             },
            { data: 'status',
              title: 'ステータス'
            },
            { data: 'action',
              title: '操作'
            }
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
              // Group
              targets: 3,
              render: function (data, type, full, meta) {
                  const plan = full['group_name'];

                  return '<span class="text-heading">' + plan + '</span>';
              }
            },
            {
              // Branch
              targets: 4,
              render: function (data, type, full, meta) {
                const branch = branchList.find(branch => branch.id == data)?.name || '';
                return branch;
              }
            },
            {
              // Department
              targets: 5,
              render: function (data, type, full, meta) {
                const departmentL = data.map(department => {
                  const departmentName = departmentList.find(d => d.id == department)?.name || '';
                  return departmentName;
                });
                return departmentL.join(', ');
              }
            },
            {
              // User Status
              targets: 7,
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
              title: '操作',
              searchable: false,
              orderable: false,
              render: (data, type, full, meta) => {
                if(user_role == 'administrator' || user_role == 'manager'){
                  return `
                  <div class="d-flex align-items-center">
                      <a href="${userView}?id=${full['id']}" class="btn btn-text-secondary rounded-pill waves-effect btn-icon">
                      <i class="icon-base ti tabler-eye icon-22px"></i>
                      </a>
                      <a href="javascript:;" class="btn btn-text-secondary rounded-pill waves-effect btn-icon dropdown-toggle hide-arrow" data-bs-toggle="dropdown">
                      <i class="icon-base ti tabler-dots-vertical icon-22px"></i>
                      </a>
                      <div class="dropdown-menu dropdown-menu-end m-0">
                      <a href="javascript:;" data-id="${full['id']}" data-realname="${full['realname']}" class="dropdown-item item-edit">編集</a>
                      ${full['is_suspend'] != 1 ? `<a href="javascript:;" data-id="${full['id']}" data-realname="${full['realname']}" class="dropdown-item item-suspend">停止する</a>` : ''}
                      ${full['is_suspend'] == 1 ? `<a href="javascript:;" data-id="${full['id']}" data-realname="${full['realname']}" class="dropdown-item item-active">アクティブする</a>` : ''}
                      ${full['group_name'] != '退職者' ? `<a href="javascript:;" data-id="${full['id']}" data-realname="${full['realname']}" class="dropdown-item item-resign">退職者へ変更</a>` : ''}
                     <!-- <a href="javascript:;" data-id="${full['id']}" data-realname="${full['realname']}" class="dropdown-item item-delete">削除</a> -->
                      <a href="javascript:;" data-id="${full['id']}" data-realname="${full['realname']}" class="dropdown-item item-change-password">パスワードを変更</a>
                      </div>
                  </div>
                  `;
                }else{
                  return `
                    <div class="d-flex align-items-center">
                        <a href="${userView}?id=${full['id']}" class="btn btn-text-secondary rounded-pill waves-effect btn-icon">
                        <i class="icon-base ti tabler-eye icon-22px"></i>
                        </a>
                    </div>`;
                }
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
                    placeholder: 'メンバーを検索',
                    text: '_INPUT_'
                }
                },
                {
                buttons: featureButtons
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
            searchPlaceholder: 'メンバーを検索',
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
    });


    function bindEvent() {
      document.querySelector('.datatables-users').addEventListener('click', async function (e) {
        if (e.target.closest('.item-edit')) {
          e.preventDefault();
          const id = e.target.closest('.item-edit').dataset.id;
          const response = await axios.post('/api/index.php?model=member&method=get_user_by_id', {
            id: id
          }, {
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded'
            }
          });
          if(response.status === 200 && response.data && response.data.status === 'success'){
            // Open the modal
            var userinfo = response.data.data;
            document.getElementById('edit-user-id').value = userinfo.id;
            document.getElementById('edit-user-userName').value = userinfo.userid;
            document.getElementById('edit-user-lastname').value = userinfo.lastname;
            document.getElementById('edit-user-firstname').value = userinfo.firstname;
            document.getElementById('edit-user-email').value = userinfo.user_email;
            document.getElementById('edit-user-contact').value = userinfo.user_phone;
            $('#edit-user-role').val(userinfo.authority).trigger('change');
            $('#edit-user-group').val(userinfo.user_group).trigger('change');
            $('#edit-user-type').val(userinfo.member_type).trigger('change');
            document.getElementById('edit-user-position').value = userinfo.position;
            document.getElementById('edit-user-permit').innerHTML = '';

            $('#edit-user-branch').val(userinfo.branch_id).trigger('change');
            const departmentSelect = document.getElementById('edit-user-department');
            // For multiple select, pass an array of values
            $(departmentSelect).val(userinfo.department_id).trigger('change');

            const permitData = {};
            permitData.edit_level = userinfo.edit_level;
            permitData.edit_group = response.data.group;
            permitData.edit_user = response.data.user;
            
            generatePermit('edit', null, '', document.getElementById('edit-user-permit'), 2, permitData);
            const editModal = new bootstrap.Modal(document.getElementById('modalEditUser'));
            editModal.show();
          }else{
            showMessage(response.data.error, true);
          }
        }

        if (e.target.closest('.item-change-password')) {
          e.preventDefault();
          const id = e.target.closest('.item-change-password').dataset.id;
          const realname = e.target.closest('.item-change-password').dataset.realname;
          document.getElementById('change-password-id').value = id;
          Swal.fire({
            title: realname + 'さんのアカウントのパスワードを変更しますか？',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'はい、変更します',
            cancelButtonText: 'キャンセル',
            customClass: {
              confirmButton: 'btn btn-primary',
              cancelButton: 'btn btn-label-secondary'
            },
            buttonsStyling: false
          }).then(function (result) {
            if (result.value) {
              const changePasswordModal = new bootstrap.Modal(document.getElementById('modalChangePassword'));
              changePasswordModal.show();
            }
          });
          
        }

        if (e.target.closest('.item-suspend')) {
          e.preventDefault();

          const id = e.target.closest('.item-suspend').dataset.id;
          const realname = e.target.closest('.item-suspend').dataset.realname;
          Swal.fire({
            title: realname + 'さんのアカウントを停止しますか？',
            text: '停止するとログインできなくなります。',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'はい、停止します',
            cancelButtonText: 'キャンセル',
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
                url: '/api/index.php?model=member&method=suspend_member',
                data: {
                  id: id,
                },
                headers: {
                  'Content-Type': 'application/x-www-form-urlencoded'
                }
              })
                .then(function (response) {
                  if (response.status === 200 && response.data && response.data.status === 'success') {
                    showMessage('メンバーを停止しました');
                    // delete row from datatable
                    changeData();
                  } else {
                    showMessage('メンバーを停止できませんでした', true);
                  }
                }).catch(function (error) {
                  handleErrors(error);
                });
              
            }
          });
        }

        if (e.target.closest('.item-active')) {
          e.preventDefault();

          const id = e.target.closest('.item-active').dataset.id;
          const realname = e.target.closest('.item-active').dataset.realname;
          Swal.fire({
            title: realname + 'さんのアカウントをアクティブにしますか？',
            text: 'アクティブにするとログインできるようになります。',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'はい、アクティブにします',
            cancelButtonText: 'キャンセル',
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
                url: '/api/index.php?model=member&method=active_member',
                data: {
                  id: id,
                },
                headers: {
                  'Content-Type': 'application/x-www-form-urlencoded'
                }
              })
                .then(function (response) {
                  if (response.status === 200 && response.data && response.data.status === 'success') {
                    showMessage('メンバーをアクティブにしました');
                    // delete row from datatable
                    changeData();
                  } else {
                    showMessage('メンバーをアクティブにできませんでした', true);
                  }
                }).catch(function (error) {
                  handleErrors(error);
                });
              
            }
          });
        }

        if (e.target.closest('.item-resign')) {
          e.preventDefault();

          const id = e.target.closest('.item-resign').dataset.id;
          const realname = e.target.closest('.item-resign').dataset.realname;
          Swal.fire({
            title: realname + 'さんのアカウントを退職者へ変更しますか？',
            text: '退職者へ変更するとログインできなくなります。',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'はい、退職者へ変更します',
            cancelButtonText: 'キャンセル',
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
                url: '/api/index.php?model=member&method=resign_member',
                data: {
                  id: id,
                },
                headers: {
                  'Content-Type': 'application/x-www-form-urlencoded'
                }
              })
                .then(function (response) {
                  if (response.status === 200 && response.data && response.data.status === 'success') {
                    showMessage('メンバーを退職者へ変更しました');
                    // delete row from datatable
                    changeData();
                  } else {
                    showMessage('メンバーを退職者へ変更できませんでした', true);
                  }
                }).catch(function (error) {
                  handleErrors(error);
                });
              
            }
          });
        }
    
        if (e.target.closest('.item-delete')) {
          e.preventDefault();
          const id = e.target.closest('.item-delete').dataset.id;
          const realname = e.target.closest('.item-delete').dataset.realname;
          Swal.fire({
            title: realname + 'さんのアカウントを削除しますか？',
            text: 'メンバーを削除すると元に戻すことはできません。',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'はい、削除します',
            cancelButtonText: 'キャンセル',
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
                url: '/api/index.php?model=member&method=delete_member',
                data: {
                  id: id,
                },
                headers: {
                  'Content-Type': 'application/x-www-form-urlencoded'
                }
              })
                .then(function (response) {
                  if (response.status === 200 && response.data && response.data.status === 'success') {
                    showMessage('メンバーを削除しました');
                    // delete row from datatable
                    dt_user.row(e.target.closest('tr')).remove().draw();
                  } else {
                    showMessage('メンバーを削除できませんでした', true);
                  }
                }).catch(function (error) {
                  handleErrors(error);
                });
              
            }
          });
    
          
          
        }
      });
    }

    // Initial event binding
    bindEvent();
    await loadBranches();
    await loadDepartments();
    await changeData();
    generateGroupList();
  }

  $('#modalEditUser').on('hidden.bs.modal', function () {
     //reset form
     document.getElementById('editUserForm').reset();
     $('#edit-user-department').val('').trigger('change');
     $('#edit-user-role').val('').trigger('change');
     $('#edit-user-branch').val('').trigger('change');
     $('#edit-user-group').val('').trigger('change');
     $('#edit-user-type').val('').trigger('change');
  });
  $('#modalAddUser').on('hidden.bs.modal', function () {
    //reset form
    document.getElementById('addNewUserForm').reset();
    $('#add-user-department').val('').trigger('change');
    $('#add-user-role').val('').trigger('change');
    $('#add-user-branch').val('').trigger('change');
    $('#add-user-group').val('').trigger('change');
    $('#add-user-type').val('').trigger('change');
  });

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
  // if (phoneMaskList) {
  //   phoneMaskList.forEach(function (phoneMask) {
  //     phoneMask.addEventListener('input', event => {
  //       const cleanValue = event.target.value.replace(/\D/g, '');
  //       phoneMask.value = formatGeneral(cleanValue, {
  //         blocks: [3, 3, 4],
  //         delimiters: ['-', '-']
  //       });
  //     });
  //     registerCursorTracker({
  //       input: phoneMask,
  //       delimiter: ' '
  //     });
  //   });
  // }
  // Add New User Form Validation
  const fv = FormValidation.formValidation(addNewUserForm, {
    fields: {
      userid: {
        validators: {
          notEmpty: {
            message: 'ユーザー名を入力してください'
          },
          stringLength: {
            min: 4,
            message: '4文字以上入力してください'
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
      
      lastname: {
        validators: {
          notEmpty: {
            message: '姓を入力してください'
          }
        }
      },
      firstname: {
        validators: {
          notEmpty: {
            message: '名を入力してください'
          }
        }
      },
      user_email: {
        validators: {
          notEmpty: {
            message: 'メールアドレスを入力してください'
          },
          emailAddress: {
            message: 'メールアドレスが不正です'
          }
        }
      },
      user_phone: {
        validators: {
          notEmpty: {
            message: '電話番号を入力してください'
          }
        }
      },
      position: {
        validators: {
          stringLength: {
            min: 2,
            message: '2文字以上入力してください'
          },
          notEmpty: {
            message: '役職を入力してください'
          }
        }
      },
      branch_id: {
        validators: {
          notEmpty: {
            message: '支社を選択してください'
          }
        }
      },
      'department_id[]': {
        validators: {
          notEmpty: {
            message: '部署を選択してください'
          }
        }
      },
      user_group: {
        validators: {
          notEmpty: {
            message: 'グループを選択してください'  
          }
        }
      },
      member_type: {
        validators: {
          notEmpty: {
            message: '従業員の種類を選択してください'
          }
        }
      },
      authority: {
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
              $('#modalAddUser').modal('hide');
            } else {
              if(response.data.error){
                showMessage(response.data.error, true);
              }else{
                showMessage('メンバーを追加できませんでした', true);
              }
            }
          })
          .catch(function (error) {
            console.error('Error:', error);
            if (error.response) {
              // Server responded with error status
              console.error('Response data:', error.response.data);
              console.error('Response status:', error.response.status);
              console.error('Response headers:', error.response.headers);
              showMessage('サーバーエラーが発生しました (500)', true);
            } else if (error.request) {
              // Request made but no response received
              console.error('Request:', error.request);
              showMessage('サーバーに接続できませんでした', true);
            } else {
              // Error in request setup
              console.error('Error message:', error.message);
              showMessage('リクエストエラーが発生しました', true);
            }
          });
      }
    });
  });

  generatePermit('edit', null, '', document.getElementById('add-user-permit'));


  const editUserForm = document.getElementById('editUserForm');
  const fvEdit = FormValidation.formValidation(editUserForm, {
    fields: {
      userid: {
        validators: {
          notEmpty: {
            message: 'ユーザー名を入力してください'
          },
          stringLength: {
            min: 4,
            message: '4文字以上入力してください'
          }
        }
      },
      lastname: {
        validators: {
          notEmpty: {
            message: '姓を入力してください'
          }
        }
      },
      firstname: {
        validators: {
          notEmpty: {
            message: '名を入力してください'
          }
        }
      },
      user_email: {
        validators: {
          notEmpty: {
            message: 'メールアドレスを入力してください'
          },
          emailAddress: {
            message: 'メールアドレスが不正です'
          }
        }
      },
      user_phone: {
        validators: {
          notEmpty: {
            message: '電話番号を入力してください'
          }
        }
      },
      position: {
        validators: {
          stringLength: {
            min: 2,
            message: '2文字以上入力してください'
          },
          notEmpty: {
            message: '役職を入力してください'
          }
        }
      },
      branch_id: {
        validators: {
          notEmpty: {
            message: '支社を選択してください'
          }
        }
      },
      'department_id[]': {
        validators: {
          notEmpty: {
            message: '部署を選択してください'
          }
        }
      },
      user_group: {
        validators: {
          notEmpty: {
            message: 'グループを選択してください'  
          }
        }
      },
      member_type: {
        validators: {
          notEmpty: {
            message: '従業員の種類を選択してください'
          }
        }
      },
      authority: {
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

  editUserForm.addEventListener('submit', function (e) {
    e.preventDefault();
    fvEdit.validate().then(function (status) {
      if (status === 'Valid') {
        displayHourglass();
        const formData = new FormData(editUserForm);
        axios.post('/api/index.php?model=member&method=edit_member', formData)
          .then(function (response) {
            if (response.status === 200 && response.data && response.data.status === 'success') {
              showMessage('メンバーを編集しました');
              editUserForm.reset();
              changeData();
              
            } else {
              if(response.data.error){
                showMessage(response.data.error, true);
              }else{
                showMessage('メンバーを編集できませんでした', true);
              }
            }
            $('#modalEditUser').modal('hide'); 
            
          })
          .catch(function (error) {
            handleErrors(error);
            $('#modalEditUser').modal('hide');
          });
      }
    });
  });

  const changePasswordForm = document.getElementById('changePasswordForm');
  const fvChangePassword = FormValidation.formValidation(changePasswordForm, {
    fields: {
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
  changePasswordForm.addEventListener('submit', function (e) {
    e.preventDefault();
    fvChangePassword.validate().then(function (status) {
      if (status === 'Valid') {
        displayHourglass();
        const formData = new FormData(changePasswordForm);
        axios.post('/api/index.php?model=member&method=change_password_api', formData)
          .then(function (response) {
            if (response.status === 200 && response.data && response.data.status === 'success') {
              showMessage('パスワードを変更しました');
              changePasswordForm.reset();
            } else {
              if(response.data.message_code){
                showMessage(response.data.message_code, true);
              }else{
                showMessage('パスワードを変更できませんでした', true);
              }
            }
            $('#modalChangePassword').modal('hide'); 
          })
          .catch(function (error) {
            handleErrors(error);
            $('#modalChangePassword').modal('hide');
          });
      }
    });

  });
});
