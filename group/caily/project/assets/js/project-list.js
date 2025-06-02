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
                    title: '案件番号'
                },
                { 
                    data: 'name',
                    render: function(data, type, row) {
                        return `<div class="d-flex align-items-start justify-content-start flex-column">
                                    <a href="detail.php?id=${row.id}" class="text-decoration-none">${data}</a>
                                </div>`;
                    },
                    title: 'お施主様名'
                },
                { 
                    data: 'name',
                    render: function(data, type, row) {
                        return `<div class="d-flex align-items-start justify-content-start flex-column">
                                    <div class="mt-1">
                                        <small class="text-muted d-block">会社名: ${row.company_name || '-'}</small>
                                        <small class="text-muted d-block">支店名: ${row.branch_name || '-'}</small>
                                        <small class="text-muted d-block">担当者: ${row.contact_name || '-'}</small>
                                    </div>
                                </div>`;
                    },
                    title: '顧客情報'
                },
                { 
                    data: 'building_type',
                    render: function(data, type, row) {
                        return `<div class="d-flex align-items-start justify-content-start flex-column">
                                    <span class="project-type">${row.building_type || '-'}</span>
                                    <span class="project-type">${row.building_size|| '-'}</span>
                                </div>`;
                    },
                    title: '建物情報'
                },
                {
                    data: 'manager_id',
                    render: function(data) {
                        return `<div class="d-flex align-items-center mt-1">
                              <div class="avatar-wrapper me-2">
                                  <div class="avatar avatar-xs">
                                      <img src="/assets/img/avatars/1.png" alt="" class="rounded-circle">
                                  </div>
                              </div>
                              <small class="text-nowrap text-heading">${data || '-'}</small>
                          </div>`;
                    },
                    title: '担当者'
                },
                {
                    data: 'assignment_id',
                    render: function(data) {
                        return data || '-';
                    },
                    title: 'メンバー'
                },
                {
                    data: 'priority',
                    render: function(data) {
                        const priority = priorities.find(priority => priority.key === data);
                        return `<span class="badge bg-${priority?.color || 'secondary'}">${priority?.name || data}</span>`;
                    },
                    title: '優先度'
                },
                {
                    data: 'status',
                    render: function(data) {
                        const status = statuses.find(status => status.key === data);
                        return `<span class="badge bg-${status?.color || 'secondary'}">${status?.name || data}</span>`;
                    },
                    title: '案件状況'
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
                    title: '進捗率'
                },
                { data: 'start_date', title: '開始日', render: function(data) {
                    if(data) {
                        return moment(data).format('YYYY/MM/DD');
                    } else {
                        return '-';
                    }
                }},
                { data: 'end_date', title: '終了日', render: function(data) {
                    if(data) {
                        return moment(data).format('YYYY/MM/DD');
                    } else {
                        return '-';
                    }
                }},
                {
                    data: null,
                    render: function(data, type, row) {
                        return `<div class="d-flex align-items-center gap-1">
                                    <button class="btn btn-sm btn-primary item-edit" data-id="${row.id}">編集</button>
                                    <button class="btn btn-sm btn-danger item-delete" data-id="${row.id}">削除</button>
                                </div>`;
                    },
                    title: '操作'
                }
            ],
           
            pageLength: 50,
            ordering: true,
            responsive: true,
            language: {
                search: "検索:",
                lengthMenu: "_MENU_ 件表示",
                info: " _TOTAL_ 件中 _START_ から _END_ まで表示",
                paginate: {
                    first: "先頭",
                    previous: "前",
                    next: "次",
                    last: "最終"
                }
            },
            
        });

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
            category_id.wrap('<div class="position-relative"></div>').select2({
                placeholder: '選択してください',
                dropdownParent: category_id.parent(),
                allowClear: true,
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
            });

            // Ẩn company_name và customer_id ban đầu
            $('#company_name').closest('.form-group').hide();
            $('#customer_id').closest('.form-group').hide();

            // Kiểm tra giá trị ban đầu của company_name và customer_id
            if (company_name.val()) {
                $('#company_name').closest('.form-group').show();
            }
            if (customer_id.val()) {
                $('#customer_id').closest('.form-group').show();
            }

            // Khi category_id thay đổi
            category_id.on('change', function() {
                if ($(this).val()) {
                    // Hiện company_name khi có category_id
                    $('#company_name').closest('.form-group').show();
                    // Reset và refresh company_name
                    company_name.val(null).trigger('change');
                    // Ẩn và reset customer_id
                    $('#customer_id').closest('.form-group').hide();
                    customer_id.val(null).trigger('change');
                } else {
                    // Ẩn cả company_name và customer_id khi không có category_id
                    $('#company_name').closest('.form-group').hide();
                    $('#customer_id').closest('.form-group').hide();
                    company_name.val(null).trigger('change');
                    customer_id.val(null).trigger('change');
                }
            });
        }
        
        if (company_name.length) {
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
            });

            // Khi company_name thay đổi
            company_name.on('change', function() {
                if ($(this).val()) {
                    // Hiện customer_id khi có company_name
                    $('#customer_id').closest('.form-group').show();
                    // Reset và refresh customer_id
                    customer_id.val(null).trigger('change');
                } else {
                    // Ẩn và reset customer_id khi không có company_name
                    $('#customer_id').closest('.form-group').hide();
                    customer_id.val(null).trigger('change');
                }
            });
        }
        
        if (customer_id.length) {
            customer_id.wrap('<div class="position-relative"></div>').select2({
                placeholder: '選択してください',
                dropdownParent: customer_id.parent(),
                allowClear: true,
                escapeMarkup: function(markup) {
                    return markup;
                },
                language: {
                    noResults: function() {
                        return '見つかりません。<button class="btn btn-warning btn-sm w-50" onclick="app.openNewCustomerModal()">新規顧客を追加</button>';
                    },
                },
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
            });
        }
        
    });


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
                newProject: {
                    name: '',
                    description: '',
                    status: 'draft',
                    priority: 'medium',
                    start_date: '',
                    end_date: '',
                    members: [],
                    manager: [],
                    department_id: '',
                    building_size: '',
                    building_type: '',
                    project_number: '',
                    project_order_type: 'new',
                    estimated_hours: '',
                    amount: '',
                    customer_id: '',
                    company_name: '',
                    branch_id: '',
                    contact_name: '',
                    contact_phone: ''
                },
                categories: [],
                companies: [],
                contacts: [],
                users: [],
                isEdit: false,
                perPage: 20,
                currentPage: 1,
                editingId: null,
                deletingId: null
            }
        },
        mounted() {
            this.loadDepartments();
            this.loadUsers();
            this.loadBranches();
            // this.loadCategories();
            // this.loadCompanies();
            // this.loadContacts();
            
        },
        methods: {
            async loadDepartments() {
                try {
                    const response = await axios.get('/api/index.php?model=department&method=list');
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
                    this.showMessage('部署の読み込みに失敗しました。', true);
                }
            },
            async loadUsers() {
                try {
                    const response = await axios.get('/api/index.php?model=user&method=getList');
                    this.users = response.data.list || [];
                } catch (error) {
                    console.error('Error loading users:', error);
                }
            },
            async loadBranches() {
                try {
                    const response = await axios.get('/api/index.php?model=branch&method=list');
                    this.branches = response.data || [];
                } catch (error) {
                    console.error('Error loading branches:', error);
                }
            },
            viewProjects(department) {
                this.selectedDepartment = department;
                this.loadProjects();
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
                   // this.showMessage('プロジェクトの読み込みに失敗しました。', true);
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
                    project_order_type: project.project_order_type || 'new',
                    estimated_hours: project.estimated_hours || '',
                    amount: project.amount || '',
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
                try {
                    const formData = new FormData();
                    formData.append('model', 'project');
                    formData.append('method', this.isEdit ? 'edit' : 'add');
                    
                    if (this.isEdit) {
                        formData.append('id', this.editingId);
                    }
                    
                    // Append all project data
                    Object.keys(this.newProject).forEach(key => {
                        if (key === 'members' && Array.isArray(this.newProject[key])) {
                            this.newProject[key].forEach(member => {
                                formData.append('members[]', member);
                            });
                        } else {
                            formData.append(key, this.newProject[key]);
                        }
                    });
                    
                    const response = await axios.post('/api/index.php?model=project&method=add', formData);
                    
                    if (response.data) {
                        this.showMessage(this.isEdit ? 'プロジェクトを更新しました。' : 'プロジェクトを作成しました。');
                        bootstrap.Modal.getInstance(document.getElementById('newProjectModal')).hide();
                        this.loadProjects();
                        this.resetProjectForm();
                    }
                } catch (error) {
                    console.error('Error saving project:', error);
                    this.showMessage('プロジェクトの保存に失敗しました。', true);
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
                        this.showMessage('プロジェクトを削除しました。');
                        bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
                        this.loadProjects();
                    }
                } catch (error) {
                    console.error('Error deleting project:', error);
                    if (error.response?.data?.error) {
                        this.showMessage(error.response.data.error, true);
                    } else {
                        this.showMessage('プロジェクトの削除に失敗しました。', true);
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
                    members: [],
                    department_id: '',
                    building_size: '',
                    building_type: '',
                    project_number: '',
                    project_order_type: 'new',
                    estimated_hours: '',
                    amount: '',
                    customer_id: '',
                    company_name: '',
                    category_id: '',
                    contact_name: '',
                    contact_phone: ''
                };
                this.companies = [];
                this.contacts = [];
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
                // Đóng dropdown của select2
                $('#customer_id').select2('close');
                // Mở modal thêm khách hàng mới
                const modal = new bootstrap.Modal(document.getElementById('newCustomerModal'));
                modal.show();
            },
        },
    }).mount('#app');