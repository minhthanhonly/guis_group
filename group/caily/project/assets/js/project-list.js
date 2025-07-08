var projectTable;
    var projectData = [];
    var statuses = [
        {
            key: 'all',
            name: 'すべて',
            color: 'secondary'
        },
        {
            key: 'open',
            name: 'オープン',
            color: 'info'
        },
        {
            key: 'confirming',
            name: '確認中',
            color: 'warning'
        },
        {
            key: 'in_progress',
            name: '進行中',
            color: 'primary'
        },
        {
            key: 'paused',
            name: '一時停止',
            color: 'warning'
        },
        {
            key: 'completed',
            name: '完了',
            color: 'success'
        },
        {
            key: 'cancelled',
            name: 'キャンセル',
            color: 'danger'
        },
        {
            key: 'draft',
            name: '下書き',
            color: 'secondary'
        },
        {
            key: 'deleted',
            name: '削除',
            color: 'danger'
        },
    ];
    var priorities = [
        {
            key: 'low',
            name: '低',
            color: 'secondary'
        },
        {
            key: 'medium',
            name: '中',
            color: 'primary'
        },
        {
            key: 'high',
            name: '高',
            color: 'warning'
        },
        {
            key: 'urgent',
            name: '緊急',
            color: 'danger'
        },
    ];
    $(document).ready(function() {

        projectData = [];
        projectTable = $('#projectTable').DataTable({
            serverSide: true,
            processing: true,
            ajax: {
                url: '/api/index.php',
                type: 'GET',
                data: function(d) {
                    return {
                        model: 'project',
                        method: 'list',
                        department_id: app.selectedDepartment?.id,
                        status: app.selectedStatus?.key,
                        draw: d.draw,
                        start: d.start,
                        length: d.length,
                        search: d.search.value,
                        order_column: d.columns[d.order[0].column].data,
                        order_dir: d.order[0].dir
                    };
                },
                dataSrc: function(response) {
                    return response.data || [];
                }
            },
            paging: true,
            info: true,
            searching: true,
            scrollX: true,
            columns: [
                { 
                    data: 'project_number',
                    render: function(data, type, row) {
                        return `<div class="d-flex align-items-center">
                                    <span class="project-id">${data || '-'}</span>
                                </div>`;
                    },
                    title: '<span data-i18n="案件番号">案件番号</span>',
                },
                
                { 
                    data: 'name',
                    render: function(data, type, row) {
                        return `<div class="d-flex align-items-start justify-content-start flex-column">
                                    <a href="detail.php?id=${row.id}" class="text-decoration-none">${data}</a>
                                </div>`;
                    },
                    title: '<span data-i18n="お施主様名">お施主様名</span>'
                },
                { 
                    data: 'name',
                    render: function(data, type, row) {
                        return `<div class="d-flex align-items-start justify-content-start flex-column">
                                    <div class="mt-1">
                                        <small class="text-muted d-block">${row.company_name || '-'}</small>
                                        <small class="text-muted d-block">${row.customer_name || '-'}</small>
                                    </div>
                                </div>`;
                    },
                    title: '<span data-i18n="顧客情報">顧客情報</span>'
                },
                // { 
                //     data: 'building_type',
                //     render: function(data, type, row) {
                //         return `<div class="d-flex align-items-start justify-content-start flex-column">
                //                     <span class="project-type">${row.building_type || '-'}</span>
                //                     <span class="project-type">${row.building_size|| '-'}</span>
                //                 </div>`;
                //     },
                //     title: '建物情報'
                // },
                { 
                    data: 'project_order_type',
                    render: function(data, type, row) {
                        if (Array.isArray(data)) {
                            return `<ul class="mb-0 ps-3">${data.map(item => `<li>${item}</li>`).join('')}</ul>`;
                        } else if (typeof data === 'string') {
                            try {
                                const decoded = decodeHtmlEntities(data);
                                const arr = JSON.parse(decoded);
                                if (Array.isArray(arr)) {
                                    return `<ul class="mb-0 list-unstyled">${arr.map(item => `<li>${item}</li>`).join('')}</ul>`;
                                }
                            } catch (e) {
                                return `<span>${data || '-'}</span>`;
                            }
                        }
                        return `<span>-</span>`;
                    },
                    title: '<span data-i18n="受注形態">受注形態</span>'
                },
                {
                    data: 'manager_id',
                    render: function(data) {
                        if (!data) return '-';
                        const members = data.split('|').filter(member => member.trim() !== '');
                        if (members.length === 0) return '-';

                        let html = '<div class="d-flex align-items-center">';
                        const maxAvatars = 3;
                        members.slice(0, maxAvatars).forEach(member => {
                            const [userId, realname, userImage] = member.split(':');
                            html += `<div class="avatar me-1" data-bs-toggle="tooltip" title="${realname || userId}">
                                <img src="/assets/upload/avatar/${userImage ?? 'no-image.png'}" alt="${realname || userId}" 
                                    class="rounded-circle  pull-up" width="32" height="32" 
                                    onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-flex';">
                                <span class="avatar-initial rounded-circle bg-label-primary pull-up" style="display:none;">
                                    ${getInitials(realname || userId)}
                                </span>
                            </div>`;
                        });

                        if (members.length > maxAvatars) {
                            const remaining = members.slice(maxAvatars).map(member => {
                                const [, realname,] = member.split(':');
                                return realname;
                            }).join(', ');
                            html += `
                                <span class="avatar-initial rounded-circle pull-up" 
                                    data-bs-toggle="tooltip" title="${remaining}" 
                                    style="display:inline-flex;">
                                    +${members.length - maxAvatars}
                                </span>
                            `;
                        }
                        html += '</div>';
                        return html;
                    },
                    title: '<span data-i18n="管理">管理</span>'
                },
                {
                    data: 'assignment_id',
                    render: function(data) {
                        if (!data) return '-';
                        const members = data.split('|').filter(member => member.trim() !== '');
                        if (members.length === 0) return '-';

                        let html = '<div class="d-flex align-items-center">';
                        const maxAvatars = 3;
                        members.slice(0, maxAvatars).forEach(member => {
                            const [userId, realname, userImage] = member.split(':');
                            html += `<div class="avatar me-1" data-bs-toggle="tooltip" title="${realname || userId}">
                                <img src="/assets/upload/avatar/${userImage ?? 'no-image.png'}" alt="${realname || userId}" 
                                    class="rounded-circle pull-up" width="32" height="32" 
                                    onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-flex';">
                                <span class="avatar-initial rounded-circle bg-label-primary pull-up" style="display:none;">
                                    ${getInitials(realname || userId)}
                                </span>
                            </div>`;
                        });

                        if (members.length > maxAvatars) {
                            const remaining = members.slice(maxAvatars).map(member => {
                                const [, realname,] = member.split(':');
                                return realname;
                            }).join(', ');
                            html += `
                                <span class="avatar-initial rounded-circle pull-up" 
                                    data-bs-toggle="tooltip" title="${remaining}" 
                                    style="display:inline-flex;">
                                    +${members.length - maxAvatars}
                                </span>
                            `;
                        }
                        html += '</div>';
                        return html;
                    },
                    title: '<span data-i18n="メンバー">メンバー</span>'
                },
                {
                    data: 'priority',
                    render: function(data) {
                        const priority = priorities.find(priority => priority.key === data);
                        return `<span class="badge bg-${priority?.color || 'secondary'}">${priority?.name || data}</span>`;
                    },
                    title: '<span data-i18n="優先度">優先度</span>'
                },
                {
                    data: 'status',
                    render: function(data) {
                        const status = statuses.find(status => status.key === data);
                        return `<span class="badge bg-${status?.color || 'secondary'}">${status?.name || data}</span>`;
                    },
                    title: '<span data-i18n="案件状況">案件状況</span>'
                },
                {
                    data: 'progress',
                    render: function(data) {
                        const color = data === 100 ? 'success' : 'primary';
                        return `<div class="progress" style="width: 100px;">
                                    <div class="progress-bar bg-${color}" role="progressbar" 
                                            style="width: ${data}%" aria-valuenow="${data}" 
                                            aria-valuemin="0" aria-valuemax="100">
                                    </div>
                                </div>
                                <small class="text-muted">${data}%</small>`;
                    },
                    title: '<span data-i18n="進捗率">進捗率</span>'
                },
                { data: 'start_date', title: '<span data-i18n="開始日">開始日</span>', render: function(data) {
                    if(data) {
                        return `<span class="text-muted small text-nowrap">${moment(data).format('YYYY年M月D日 H:mm')}</span>`;
                    } else {
                        return '-';
                    }
                }},
                { data: 'end_date', title: '<span data-i18n="終了日">終了日</span>', render: function(data, type, row) {
                    if(data) {
                        const timeRemaining = getTimeRemaining(data, row.status);
                        const dateStr = moment(data).format('M月D日 H:mm');
                        
                        if (timeRemaining) {
                            const pulseClass = timeRemaining.isOverdue ? 'pulse-animation' : '';
                            return `<div class="d-flex flex-column">
                                        <span class="text-muted small text-nowrap">${dateStr}</span>
                                        <span class="badge ${timeRemaining.class} ${pulseClass} mt-1" 
                                             title="${timeRemaining.isOverdue ? '期限を超過しています' : '残り時間'}"
                                             style="font-size: 0.7rem; padding: 0.2rem 0.4rem;">
                                             ${timeRemaining.text}
                                        </span>
                                    </div>`;
                        } else {
                            return `<span class="text-nowrap text-muted small">${dateStr}</span>`;
                        }
                    } else {
                        return '-';
                    }
                }},
                {
                    data: null,
                    render: function(data, type, row) {
                        return `<div class="d-flex align-items-center gap-1">
                                    <a href="detail.php?id=${row.id}" class="btn btn-sm bg-label-primary"><i class="fa fa-eye"></i></a>
                                </div>`;
                    },
                    title: '<span data-i18n="操作">操作</span>'
                }
            ],
            order: [[10, 'asc']],
           
            pageLength: 50,
            ordering: true,
            responsive: true,
            language: {
                search: '<span data-i18n="検索">検索</span>:',
                lengthMenu: '<span data-i18n="表示">表示</span>: _MENU_',
                info: '<span data-i18n="合計">合計</span>: _TOTAL_ <span data-i18n="件中">件中</span> _START_ <span data-i18n="から">から</span> _END_ <span data-i18n="まで">まで</span>',
                paginate: {
                    first: '<span data-i18n="先頭">先頭</span>',
                    previous: '<span data-i18n="前">前</span>',
                    next: '<span data-i18n="次">次</span>',
                    last: '<span data-i18n="最終">最終</span>'
                }
            },
            
        });

        // Khởi tạo lại tooltip mỗi khi DataTable vẽ lại
        $('#projectTable').on('draw.dt', function() {
            if (window.bootstrap && bootstrap.Tooltip) {
                // Bootstrap 5
                var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                tooltipTriggerList.map(function (tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                });
            } else if ($.fn.tooltip) {
                // Bootstrap 4 hoặc jQuery
                $('[data-bs-toggle="tooltip"]').tooltip();
            }
        });

        // Timer để cập nhật thời gian còn lại mỗi phút
        setInterval(function() {
            if (projectTable) {
                projectTable.ajax.reload(null, false); // false để giữ nguyên trang hiện tại
            }
        }, 60000); // Cập nhật mỗi phút

        // イベントハンドラー
        $(document).on('click', '.item-edit', function() {
            const id = $(this).data('id');
            console.log('Looking for project with id:', id);
            
            // Since projectData is a Proxy of Array, we can use it directly
            console.log(projectData);
            const project = projectData.find(p => p.id === id);
         
            
            if (project) {
                app.editProject(project);
            } else {
                console.error('Project not found with id:', id);
            }
        });

        $(document).on('click', '.item-delete', function() {
            const id = $(this).data('id');
            app.deleteProject(id);
        });

        $('#start_date').flatpickr({    
            dateFormat: 'Y-m-d',
            onChange: function(date) {
                console.log(date);
            }
        });

        $('#end_date').flatpickr({
            dateFormat: 'Y-m-d',
            onChange: function(date) {
                console.log(date);
            }
        });

        var category_id = $('#category_id');
        var company_name = $('#company_name');
        var customer_id = $('#customer_id');
        if (category_id.length) {
            // Initialize Select2 for category
            category_id.wrap('<div class="position-relative"></div>').select2({
                placeholder: '選択してください',
                dropdownParent: category_id.parent(),
                allowClear: true,
                minimumResultsForSearch: 0,
                ajax: {
                    url: '/api/index.php?model=customer&method=list_categories',
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            search: params.term,
                            page: params.page || 1
                        };
                    },
                    processResults: function(data) {
                        return {
                            results: data.data.map(function(item) {
                                return {
                                    id: item.id,
                                    text: item.name
                                };
                            })
                        };
                    }
                }
            }).on('select2:select', function(e) {
                console.log('Category selected:', e.params.data);
                app.newProject.category_id = e.params.data.id;
            }).on('select2:unselect', function() {
                console.log('Category unselected');
                app.newProject.category_id = '';
            });

            // Hide company_name and customer_id initially and disable their validation
            $('#company_name').closest('.form-group').hide();
            $('#customer_id').closest('.form-group').hide();
            
            // Use setTimeout to ensure app.formValidator is initialized
            setTimeout(() => {
                if (app.formValidator) {
                    app.formValidator.disableValidator('company_name');
                    app.formValidator.disableValidator('customer_id');
                }
            }, 100);

            // Check initial values of company_name and customer_id
            if (company_name.val()) {
                $('#company_name').closest('.form-group').show();
                setTimeout(() => {
                    if (app.formValidator) {
                        app.formValidator.enableValidator('company_name');
                    }
                }, 100);
            }
            if (customer_id.val()) {
                $('#customer_id').closest('.form-group').show();
                setTimeout(() => {
                    if (app.formValidator) {
                        app.formValidator.enableValidator('customer_id');
                    }
                }, 100);
            }

            // When category_id changes
            category_id.on('change', function() {
                const categoryValue = $(this).val();
                console.log('Category changed:', categoryValue);
                
                if (categoryValue) {
                    // Show company_name and enable its validation when category_id has value
                    $('#company_name').closest('.form-group').show();
                    setTimeout(() => {
                        if (app.formValidator) {
                            app.formValidator.enableValidator('company_name');
                            app.formValidator.revalidateField('category_id');
                        }
                    }, 100);
                    // Reset and refresh company_name
                    company_name.val(null).trigger('change');
                    // Hide customer_id, disable its validation and reset
                    $('#customer_id').closest('.form-group').hide();
                    setTimeout(() => {
                        if (app.formValidator) {
                            app.formValidator.disableValidator('customer_id');
                        }
                    }, 100);
                    customer_id.val(null).trigger('change');
                } else {
                    // Hide both fields and disable their validation when no category_id
                    $('#company_name').closest('.form-group').hide();
                    $('#customer_id').closest('.form-group').hide();
                    setTimeout(() => {
                        if (app.formValidator) {
                            app.formValidator.disableValidator('company_name');
                            app.formValidator.disableValidator('customer_id');
                        }
                    }, 100);
                    company_name.val(null).trigger('change');
                    customer_id.val(null).trigger('change');
                }
            });
        }
        
        if (company_name.length) {
            // Initialize Select2 for company
            company_name.wrap('<div class="position-relative"></div>').select2({
                placeholder: '選択してください',
                dropdownParent: company_name.parent(),
                allowClear: true,
                escapeMarkup: function(markup) {
                    return markup;
                },
                language: {
                    noResults: function() {
                        return '見つかりません。<button class="btn btn-warning btn-sm w-50" onclick="app.openNewCustomerModal()">新規顧客を追加</button>';
                    },
                },
                minimumResultsForSearch: 0,
                ajax: {
                    url: '/api/index.php?model=customer&method=list_companies_by_category',
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            search: params.term,
                            page: params.page || 1,
                            category_id: category_id.val()
                        };
                    },
                    processResults: function(data) {
                        return {
                            results: data.data.map(function(item) {
                                return {
                                    id: item.company_name,
                                    text: item.company_name
                                };
                            })
                        };
                    }
                }
            }).on('select2:select', function(e) {
                console.log('Company selected:', e.params.data);
                app.newProject.company_name = e.params.data.id;
            }).on('select2:unselect', function() {
                console.log('Company unselected');
                app.newProject.company_name = '';
            });

            // When company_name changes
            company_name.on('change', function() {
                const companyValue = $(this).val();
                console.log('Company changed:', companyValue);
                
                if (companyValue) {
                    // Show customer_id and enable its validation when company_name has value
                    $('#customer_id').closest('.form-group').show();
                    setTimeout(() => {
                        if (app.formValidator) {
                            app.formValidator.enableValidator('customer_id');
                            app.formValidator.revalidateField('company_name');
                        }
                    }, 100);
                    // Reset and refresh customer_id
                    customer_id.val(null).trigger('change');
                } else {
                    // Hide customer_id, disable its validation and reset when no company_name
                    $('#customer_id').closest('.form-group').hide();
                    setTimeout(() => {
                        if (app.formValidator) {
                            app.formValidator.disableValidator('customer_id');
                        }
                    }, 100);
                    customer_id.val(null).trigger('change');
                }
            });
        }

        if (customer_id.length) {
            // Initialize Select2 for customer
            customer_id.wrap('<div class="position-relative"></div>').select2({
                placeholder: '選択してください',
                dropdownParent: customer_id.parent(),
                allowClear: true,
                minimumResultsForSearch: 0,
                ajax: {
                    url: '/api/index.php?model=customer&method=list_contacts_by_company',
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            search: params.term,
                            page: params.page || 1,
                            company_name: company_name.val()
                        };
                    },
                    processResults: function(data) {
                        return {
                            results: data.data.map(function(item) {
                                return {
                                    id: item.id,
                                    text: item.name
                                };
                            })
                        };
                    }
                }
            }).on('select2:select', function(e) {
                console.log('Customer selected:', e.params.data);
                app.newProject.customer_id = e.params.data.id;
            }).on('select2:unselect', function() {
                console.log('Customer unselected');
                app.newProject.customer_id = '';
            });
        }
        
    });

    // Helper function to get initials from name
    function getInitials(name) {
        return getAvatarName(name);
    }

    function decodeHtmlEntities(str) {
        var txt = document.createElement('textarea');
        txt.innerHTML = str;
        return txt.value;
    }

    function getTimeRemaining(endDate, status) {
        if (!endDate || status === 'completed' || status === 'deleted' || status === 'draft' || status === 'cancelled') {
            return null;
        }
        
        const now = moment.tz('Asia/Tokyo');
        const end = moment.tz(endDate, 'Asia/Tokyo');
        
        if (end.isBefore(now)) {
            // Đã quá hạn
            const diff = now.diff(end);
            const days = Math.floor(diff / (1000 * 60 * 60 * 24));
            const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            
            if (days > 0) {
                return {
                    text: `${days}日超過`,
                    class: 'bg-danger',
                    isOverdue: true
                };
            } else if (hours > 0) {
                return {
                    text: `${hours}時間超過`,
                    class: 'bg-danger',
                    isOverdue: true
                };
            } else {
                return {
                    text: `${minutes}分超過`,
                    class: 'bg-danger',
                    isOverdue: true
                };
            }
        } else {
            // Còn thời gian
            const diff = end.diff(now);
            const days = Math.floor(diff / (1000 * 60 * 60 * 24));
            const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            
            if (days > 0) {
                return {
                    text: `+${days}日`,
                    class: 'bg-label-info',
                    isOverdue: false
                };
            } else if (hours > 0) {
                return {
                    text: `+${hours}時間`,
                    class: hours <= 24 ? 'bg-label-warning' : 'bg-label-info',
                    isOverdue: false
                };
            } else {
                return {
                    text: `+${minutes}分`,
                    class: 'bg-label-warning',
                    isOverdue: false
                };
            }
        }
    }

    const { createApp } = Vue;
    const app = createApp({
        data() {
            return {
                selectedDepartment: null,
                projects: [],
                departments: [],
                branches: [],
                selectedStatus: null,
                statuses: statuses,
                userPermissions: null,
                newProject: {
                    name: '',
                    description: '',
                    status: 'draft',
                    priority: 'medium',
                    start_date: '',
                    end_date: '',
                    team: [],
                    members: [],
                    manager: [],
                    department_id: '',
                    building_size: '',
                    building_type: '',
                    building_number: '',
                    building_branch: '',
                    project_number: '',
                    project_order_type: '新規',
                    estimated_hours: '',
                    amount: '',
                    customer_id: '',
                    company_name: '',
                    teams: '',
                    branch_id: '',
                    contact_name: '',
                    contact_phone: ''
                },
                categories: [],
                companies: [],
                contacts: [],
                users: [],
                teams: [],
                isEdit: false,
                perPage: 20,
                currentPage: 1,
                editingId: null,
                deletingId: null,
                tagifyInstance: null,
                teamTagifyInstance: null,
                membersTagifyInstance: null,
                managerTagifyInstance: null,
                customerTagifyInstance: null,
                formValidator: null
            }
        },
        computed: {
            createUrl() {
               if(this.selectedDepartment) {
                return `create.php?department_id=${this.selectedDepartment.id}`;
               }
               return `create.php`;
            }
        },
        mounted() {
            this.loadDepartments();

            // Initialize Tagify for project_order_type
            this.$nextTick(() => {
                const input = document.querySelector('#project_order_type');
                if (input && window.Tagify) {
                    this.tagifyInstance = new Tagify(input, {
                        whitelist: ['新規', '修正', '免震', '耐震', '計画変更'],
                        maxTags: 5,
                        dropdown: {
                            maxItems: 20,
                            classname: "tags-look",
                            enabled: 0,
                            closeOnSelect: true
                        },
                        callbacks: {
                            add: (e) => {
                                const tags = this.tagifyInstance.value.map(tag => tag.value);
                                this.newProject.project_order_type = tags;
                                if (this.formValidator) {
                                    this.formValidator.revalidateField('project_order_type');
                                }
                            },
                            remove: (e) => {
                                const tags = this.tagifyInstance.value.map(tag => tag.value);
                                this.newProject.project_order_type = tags;
                                if (this.formValidator) {
                                    this.formValidator.revalidateField('project_order_type');
                                }
                            }
                        }
                    });

                    // Set default value
                    if (!this.newProject.project_order_type || this.newProject.project_order_type.length === 0) {
                        this.tagifyInstance.addTags(['新規']);
                    } else if (Array.isArray(this.newProject.project_order_type)) {
                        this.tagifyInstance.addTags(this.newProject.project_order_type);
                    }
                }
            });

            // Initialize Tagify for team, members, and manager
            this.$nextTick(() => {
                // Initialize Tagify for team
                const teamInput = document.querySelector('input[name="team_tags"]');
                if (teamInput && window.Tagify) {
                    this.teamTagifyInstance = new Tagify(teamInput, {
                        whitelist: [],
                        maxTags: 5,
                        dropdown: {
                            maxItems: 20,
                            classname: "tags-look",
                            enabled: 0,
                            closeOnSelect: false
                        },
                        callbacks: {
                            add: (e) => {
                                const teamId = e.detail.tag.id;
                                const teams = this.teamTagifyInstance.value.map(team => team.id);
                                this.newProject.teams = teams;
                                this.loadTeamMembers(teamId);
                                if (this.formValidator) {
                                    this.formValidator.revalidateField('team_tags');
                                }
                            },
                            remove: (e) => {
                                const teams = this.teamTagifyInstance.value.map(team => team.id);
                                this.newProject.teams = teams;
                                this.newProject.members = [];
                                if (this.membersTagifyInstance) {
                                    this.membersTagifyInstance.removeAllTags();
                                }
                                if (this.formValidator) {
                                    this.formValidator.revalidateField('team_tags');
                                }
                            }
                        }
                    });
                }

                // Initialize Tagify for members
                const membersInput = document.querySelector('input[name="members_tags"]');
                if (membersInput && window.Tagify) {
                    this.membersTagifyInstance = new Tagify(membersInput, {
                        whitelist: [],
                        maxTags: 10,
                        dropdown: {
                            maxItems: 20,
                            classname: "tags-look",
                            enabled: 0,
                            closeOnSelect: true
                        },
                        callbacks: {
                            add: (e) => {
                                const members = this.membersTagifyInstance.value.map(member => member.id);
                                this.newProject.members = members;
                                if (this.formValidator) {
                                    this.formValidator.revalidateField('members_tags');
                                }
                            },
                            remove: (e) => {
                                const members = this.membersTagifyInstance.value.map(member => member.id);
                                this.newProject.members = members;
                                if (this.formValidator) {
                                    this.formValidator.revalidateField('members_tags');
                                }
                            }
                        }
                    });
                }

                // Initialize Tagify for manager
                const managerInput = document.querySelector('input[name="manager_tags"]');
                if (managerInput && window.Tagify) {
                    this.managerTagifyInstance = new Tagify(managerInput, {
                        whitelist: [],
                        maxTags: 10,
                        dropdown: {
                            maxItems: 20,
                            classname: "tags-look",
                            enabled: 0,
                            closeOnSelect: true
                        },
                        callbacks: {
                            add: (e) => {
                                const manager = this.managerTagifyInstance.value.map(manager => manager.id);
                                this.newProject.manager = manager;
                                if (this.formValidator) {
                                    this.formValidator.revalidateField('manager_tags');
                                }
                            },
                            remove: (e) => {
                                const manager = this.managerTagifyInstance.value.map(manager => manager.id);
                                this.newProject.manager = manager;
                                if (this.formValidator) {
                                    this.formValidator.revalidateField('manager_tags');
                                }
                            }
                        }
                    });
                }
            });

            // Initialize form validation
            const form = document.querySelector('#newProjectModal form');
            if (form) {
                this.formValidator = FormValidation.formValidation(form, {
                    fields: {
                        name: {
                            validators: {
                                notEmpty: {
                                    message: 'お施主様名を入力してください'
                                }
                            }
                        },
                        department_id: {
                            validators: {
                                notEmpty: {
                                    message: '部署を選択してください'
                                }
                            }
                        },
                        category_id: {
                            validators: {
                                callback: {
                                    message: 'カテゴリを選択してください',
                                    callback: function(input) {
                                        const categoryValue = $('#category_id').val();
                                        console.log('Validating category_id:', categoryValue);
                                        return categoryValue && categoryValue !== '';
                                    }
                                }
                            }
                        },
                        company_name: {
                            validators: {
                                callback: {
                                    message: '会社名を選択してください',
                                    callback: function(input) {
                                        const companyValue = $('#company_name').val();
                                        console.log('Validating company_name:', companyValue);
                                        return companyValue && companyValue !== '';
                                    }
                                }
                            }
                        },
                        customer_id: {
                            validators: {
                                callback: {
                                    message: '担当者名を選択してください',
                                    callback: function(input) {
                                        const customerValue = $('#customer_id').val();
                                        console.log('Validating customer_id:', customerValue);
                                        return customerValue && customerValue !== '';
                                    }
                                }
                            }
                        },
                        building_size: {
                            validators: {
                                notEmpty: {
                                    message: '建物規模を入力してください'
                                }
                            }
                        },
                        building_type: {
                            validators: {
                                notEmpty: {
                                    message: '建物種類を入力してください'
                                }
                            }
                        },
                        project_number: {
                            validators: {
                                notEmpty: {
                                    message: '連絡番号を入力してください'
                                }
                            }
                        },
                        project_order_type: {
                            validators: {
                                callback: {
                                    message: '受注形態を選択してください',
                                    callback: function(input) {
                                        return app.tagifyInstance && app.tagifyInstance.value.length > 0;
                                    }
                                }
                            }
                        },
                        priority: {
                            validators: {
                                notEmpty: {
                                    message: '優先度を選択してください'
                                }
                            }
                        },
                        status: {
                            validators: {
                                notEmpty: {
                                    message: '案件状況を選択してください'
                                }
                            }
                        },
                        start_date: {
                            validators: {
                                notEmpty: {
                                    message: '開始日を選択してください'
                                }
                            }
                        },
                        end_date: {
                            validators: {
                                notEmpty: {
                                    message: '終了日を選択してください'
                                }
                            }
                        },
                        team_tags: {
                            validators: {
                                callback: {
                                    message: 'チームを選択してください',
                                    callback: function(input) {
                                        return app.teamTagifyInstance && app.teamTagifyInstance.value.length > 0;
                                    }
                                }
                            }
                        },
                        members_tags: {
                            validators: {
                                callback: {
                                    message: 'メンバーを選択してください',
                                    callback: function(input) {
                                        return app.membersTagifyInstance && app.membersTagifyInstance.value.length > 0;
                                    }
                                }
                            }
                        },
                        manager_tags: {
                            validators: {
                                callback: {
                                    message: 'メンバーを選択してください',
                                    callback: function(input) {
                                        return app.managerTagifyInstance && app.managerTagifyInstance.value.length > 0;
                                    }
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
            }
        },
        beforeUnmount() {
            // Clean up Tagify instances when component is destroyed
            if (this.tagifyInstance) {
                this.tagifyInstance.destroy();
            }
            if (this.teamTagifyInstance) {
                this.teamTagifyInstance.destroy();
            }
            if (this.membersTagifyInstance) {
                this.membersTagifyInstance.destroy();
            }
            if (this.managerTagifyInstance) {
                this.managerTagifyInstance.destroy();
            }
        },
        methods: {
            async loadDepartments() {
                try {
                    const response = await axios.get('/api/index.php?model=department&method=listByUser');
                    this.departments = response.data;
                    if(!this.selectedDepartment && this.departments.length > 0) {
                        this.selectedStatus = this.statuses[0];
                        const firstDepartment = this.departments.find(d => d.can_project == 1);
                        if (firstDepartment) {
                            this.viewProjects(firstDepartment);
                        }
                    }
                } catch (error) {
                    console.error('Error loading departments:', error);
                    showMessage('部署の読み込みに失敗しました。', true);
                }
            },
            async getUserPermissions(departmentId) {
                try {
                    const response = await axios.get(`/api/index.php?model=department&method=get_user_permission_by_department&userid=${USER_ID}&department_id=${departmentId}`);
                    this.userPermissions = response.data;
                    console.log('User permissions loaded:', this.userPermissions);
                    return this.userPermissions;
                } catch (error) {
                    console.error('Error loading user permissions:', error);
                    this.userPermissions = null;
                    return null;
                }
            },
            // Check if user has specific permission
            hasPermission(permission) {
                if(USER_ROLE == 'administrator') return true;
                if (!this.userPermissions) return false;
                return this.userPermissions[permission] == 1;
            },
            // Check if user can perform project actions
            canAddProject() {
                return this.hasPermission('project_add');
            },
            canEditProject() {
                return this.hasPermission('project_edit');
            },
            canDeleteProject() {
                return this.hasPermission('project_delete');
            },
            canManageProject() {
                return this.hasPermission('project_manager');
            },
            canCommentProject() {
                return this.hasPermission('project_comment');
            },
            async loadUsers() {
                try {
                    const response = await axios.get('/api/index.php?model=department&method=get_users&department_id=' + this.selectedDepartment.id);
                    this.users = response.data || [];
                } catch (error) {
                    console.error('Error loading users:', error);
                }
            },
            async loadTeams() {
                try {
                    const response = await axios.get('/api/index.php?model=team&method=listbydepartment&department_id=' + this.selectedDepartment.id);
                    this.teams = response.data || [];
                    return this.teams;
                } catch (error) {
                    console.error('Error loading teams:', error);
                    return [];
                }
            },
            viewProjects(department) {
                this.selectedDepartment = department;
                this.loadProjects();
                
                // Load user permissions for the selected department
                this.getUserPermissions(department.id);
                
                // Reset teams and members when department changes
                if (this.teamTagifyInstance) {
                    this.teamTagifyInstance.removeAllTags();
                }
                if (this.membersTagifyInstance) {
                    this.membersTagifyInstance.removeAllTags();
                }
                this.newProject.members = [];
                
                // Reload teams for new department
                this.loadTeams().then(() => {
                    if (this.teamTagifyInstance) {
                        this.teamTagifyInstance.whitelist = this.teams.map(team => ({
                            id: team.id,
                            value: team.name,
                            name: team.name
                        }));
                    }
                });
                this.loadUsers().then(() => {
                    if (this.membersTagifyInstance) {
                        this.membersTagifyInstance.whitelist = this.users.map(user => ({
                            id: user.id,
                            value: user.user_name,
                            name: user.user_name
                        }));
                    }
                    if (this.managerTagifyInstance) {
                        this.managerTagifyInstance.whitelist = this.users.map(user => ({
                            id: user.id,
                            value: user.user_name,
                            name: user.user_name
                        }));
                    }
                });
            },
            filterProjectByStatus(status) {
                this.selectedStatus = status;
                this.loadProjects();
            },
            async loadProjects() {
                try {
                    projectTable.ajax.reload();
                } catch (error) {
                    console.error('Error loading projects:', error);
                }
            },
            openNewProjectModal() {
                this.isEdit = false;
                this.editingId = null;
                this.resetProjectForm();
                if (this.selectedDepartment) {
                    this.newProject.department_id = this.selectedDepartment.id;
                }
                const modal = new bootstrap.Modal(document.getElementById('newProjectModal'));
                modal.show();
            },
            editProject(project) {
                this.isEdit = true;
                this.editingId = project.id;
                this.newProject = {
                    name: project.name || '',
                    description: project.description || '',
                    status: project.status || 'draft',
                    priority: project.priority || 'medium',
                    start_date: project.start_date || '',
                    end_date: project.end_date || '',
                    members: [],
                    department_id: project.department_id || '',
                    building_size: project.building_size || '',
                    building_type: project.building_type || '',
                    project_number: project.project_number || '',
                    project_order_type: project.project_order_type || ['new'],
                    estimated_hours: project.estimated_hours || '',
                    amount: project.amount || '',
                    teams: project.teams || '',
                    customer_id: project.customer_id || '',
                    company_name: project.company_name || '',
                    branch_id: project.branch_id || '',
                    contact_name: project.contact_name || '',
                    contact_phone: project.contact_phone || ''
                };
                const modal = new bootstrap.Modal(document.getElementById('newProjectModal'));
                modal.show();
            },
            async saveProject() {
                if (!this.formValidator) {
                    console.error('Form validator not initialized');
                    return;
                }

                // Check field visibility and disable validation for hidden fields
                const companyNameVisible = $('#company_name').closest('.form-group').is(':visible');
                const customerIdVisible = $('#customer_id').closest('.form-group').is(':visible');

                // Store original validation state
                const originalValidators = {};
                
                if (!companyNameVisible) {
                    originalValidators.company_name = true;
                    this.formValidator.disableValidator('company_name');
                }
                if (!customerIdVisible) {
                    originalValidators.customer_id = true;
                    this.formValidator.disableValidator('customer_id');
                }

                // Validate the form
                const status = await this.formValidator.validate();

                // Restore original validation state
                Object.keys(originalValidators).forEach(field => {
                    this.formValidator.enableValidator(field);
                });

                if (status === 'Valid') {
                    try {
                        const formData = new FormData();
                        formData.append('model', 'project');
                        formData.append('method', this.isEdit ? 'edit' : 'add');
                        
                        if (this.isEdit) {
                            formData.append('id', this.editingId);
                        }
                        
                        // Get and validate Select2 values
                        const categorySelect = $('#category_id');
                        const companySelect = $('#company_name');
                        const customerSelect = $('#customer_id');
                        
                        const categoryId = categorySelect.select2('data')[0]?.id || '';
                        const companyName = companySelect.select2('data')[0]?.id || '';
                        const customerId = customerSelect.select2('data')[0]?.id || '';
                        
                        console.log('Select2 Data:');
                        console.log('Category:', categorySelect.select2('data'));
                        console.log('Company:', companySelect.select2('data'));
                        console.log('Customer:', customerSelect.select2('data'));
                        
                        // Update newProject with Select2 values
                        this.newProject.category_id = categoryId;
                        this.newProject.company_name = companyName;
                        this.newProject.customer_id = customerId;
                        
                        // Get team data from Tagify
                        const teamData = this.teamTagifyInstance ? this.teamTagifyInstance.value.map(tag => tag.id || tag.value) : [];
                        const membersData = this.membersTagifyInstance ? this.membersTagifyInstance.value.map(tag => tag.id || tag.value) : [];
                        const managerData = this.managerTagifyInstance ? this.managerTagifyInstance.value.map(tag => tag.id || tag.value) : [];
                        
                        // Append all project data
                        Object.keys(this.newProject).forEach(key => {
                            if (key === 'members' && Array.isArray(this.newProject[key])) {
                                // Use membersData from Tagify instead of this.newProject.members
                                membersData.forEach(member => {
                                    formData.append('members[]', member);
                                });
                            } else if (key === 'team') {
                                // Add team data from Tagify
                                teamData.forEach(team => {
                                    formData.append('team[]', team);
                                });
                            } else if (key === 'manager') {
                                // Add manager data from Tagify
                                managerData.forEach(manager => {
                                    formData.append('manager[]', manager);
                                });
                            } else if (key === 'project_order_type' && this.tagifyInstance) {
                                const tags = this.tagifyInstance.value.map(tag => tag.value);
                                formData.append(key, JSON.stringify(tags));
                            } else {
                                formData.append(key, this.newProject[key] || '');
                            }
                        });
                        
                        // Explicitly add Select2 values to ensure they're included
                        if (categoryId) {
                            formData.set('category_id', categoryId);
                        }
                        if (companyName) {
                            formData.set('company_name', companyName);
                        }
                        if (customerId) {
                            formData.set('customer_id', customerId);
                        }

                        for (let [key, value] of formData.entries()) {
                            console.log(`${key}: ${value}`);
                        }

                        const response = await axios.post('/api/index.php?model=project&method=add', formData);
                        
                        if (response.data.status == 'success') {
                            showMessage(this.isEdit ? 'プロジェクトを更新しました。' : 'プロジェクトを作成しました。');
                            bootstrap.Modal.getInstance(document.getElementById('newProjectModal')).hide();
                            this.loadProjects();
                            this.resetProjectForm();
                        } else{
                            showMessage('プロジェクトの保存に失敗しました。', true);
                        }
                    } catch (error) {
                        console.error('Error saving project:', error);
                        showMessage('プロジェクトの保存に失敗しました。', true);
                    }
                }
            },
            deleteProject(id) {
                this.deletingId = id;
                const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
                modal.show();
            },
            async confirmDelete() {
                try {
                    const formData = new FormData();
                    formData.append('model', 'project');
                    formData.append('method', 'delete');
                    formData.append('id', this.deletingId);
                    
                    const response = await axios.post('/api/index.php?model=project&method=delete', formData);
                    
                    if (response.data) {
                        showMessage('プロジェクトを削除しました。');
                        bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
                        this.loadProjects();
                    }
                } catch (error) {
                    console.error('Error deleting project:', error);
                    if (error.response?.data?.error) {
                        showMessage(error.response.data.error, true);
                    } else {
                        showMessage('プロジェクトの削除に失敗しました。', true);
                    }
                }
            },
            resetProjectForm() {
                this.newProject = {
                    name: '',
                    description: '',
                    status: 'draft',
                    priority: 'medium',
                    start_date: '',
                    end_date: '',
                    team: [],
                    members: [],
                    manager: [],
                    department_id: '',
                    building_size: '',
                    building_type: '',
                    building_number: '',
                    building_branch: '',
                    project_number: '',
                    project_order_type: '新規',
                    estimated_hours: '',
                    amount: '',
                    customer_id: '',
                    company_name: '',
                    branch_id: '',
                    contact_name: '',
                    contact_phone: ''
                };
                this.companies = [];
                this.contacts = [];
                
                // Reset Tagify instances
                if (this.tagifyInstance) {
                    this.tagifyInstance.removeAllTags();
                    this.tagifyInstance.addTags(['新規']);
                }
                if (this.teamTagifyInstance) {
                    this.teamTagifyInstance.removeAllTags();
                }
                if (this.membersTagifyInstance) {
                    this.membersTagifyInstance.removeAllTags();
                }
                if (this.managerTagifyInstance) {
                    this.managerTagifyInstance.removeAllTags();
                }

                // Reset Select2 dropdowns
                $('#category_id').val(null).trigger('change');
                $('#company_name').val(null).trigger('change');
                $('#customer_id').val(null).trigger('change');

                // Hide dependent fields
                $('#company_name').closest('.form-group').hide();
                $('#customer_id').closest('.form-group').hide();

                // Reset form validation
                if (this.formValidator) {
                    this.formValidator.resetForm();
                    this.formValidator.disableValidator('company_name');
                    this.formValidator.disableValidator('customer_id');
                }
            },
            async loadCategories() {
                try {
                    const response = await axios.get('/api/index.php?model=customer&method=list_categories');
                    if (response.data && response.data.data) {
                        this.categories = response.data.data;
                    }
                } catch (error) {
                    console.error('Error loading categories:', error);
                }
            },
            async loadCompanies() {
                try {
                    const response = await axios.get('/api/index.php?model=customer&method=list_companies');
                    if (response.data && response.data.data) {
                        this.companies = response.data.data;
                    }
                } catch (error) {
                    console.error('Error loading companies:', error);
                }
            },
            async loadContacts() {
                try {
                    const response = await axios.get('/api/index.php?model=customer&method=list_contacts');
                    if (response.data && response.data.data) {
                        this.contacts = response.data.data;
                    }
                } catch (error) {
                    console.error('Error loading contacts:', error);
                }
            },
            async onCategoryChange() {
                if (this.newProject.category_id) {
                    try {
                        const response = await axios.get(`/api/index.php?model=customer&method=list_companies_by_category&category_id=${this.newProject.category_id}`);
                        if (response.data && response.data.data) {
                            this.companies = response.data.data;
                            // Reset company and contact selections
                            this.newProject.company_name = '';
                            this.newProject.customer_id = '';
                            this.contacts = [];
                        }
                    } catch (error) {
                        console.error('Error loading companies:', error);
                        this.companies = [];
                    }
                } else {
                    this.companies = [];
                    this.newProject.company_name = '';
                    this.newProject.customer_id = '';
                    this.contacts = [];
                }
            },
            async onCompanyChange() {
                if (this.newProject.company_name) {
                    try {
                        const response = await axios.get(`/api/index.php?model=customer&method=list_contacts_by_company&company_name=${encodeURIComponent(this.newProject.company_name)}`);
                        if (response.data && response.data.data) {
                            this.contacts = response.data.data;
                            // Reset contact selection
                            this.newProject.customer_id = '';
                        }
                    } catch (error) {
                        console.error('Error loading contacts:', error);
                        this.contacts = [];
                    }
                } else {
                    this.contacts = [];
                    this.newProject.customer_id = '';
                }
            },
            openNewCustomerModal() {
                // Close dropdown of select2
                $('#customer_id').select2('close');
                // Open new customer modal
                const modal = new bootstrap.Modal(document.getElementById('newCustomerModal'));
                modal.show();
            },
            async loadTeamMembers(teamId) {
                try {
                    const response = await axios.get(`/api/index.php?model=team&method=get&id=${teamId}`);
                    if (response.data && response.data.members && response.data.whitelist) {
                        const members = response.data.members.map(member => ({
                            id: member.user_id,
                            value: member.user_name,
                            name: member.user_name
                        }));
                        
                        if (this.membersTagifyInstance) {
                            this.membersTagifyInstance.addTags(members);
                            this.newProject.members = [...this.newProject.members, ...members.map(m => m.id)];
                        }
                    }
                } catch (error) {
                    console.error('Error loading team members:', error);
                }
            },
        },
        watch: {
            // 'newProject': {
            //     handler: function(newVal) {
            //         console.log(newVal);
            //     },
            //     deep: true
            // }
        }
    }).mount('#app');
