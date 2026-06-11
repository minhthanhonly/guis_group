(function () {
    'use strict';

    if (typeof Vue === 'undefined') {
        return;
    }

    var ROOT = (typeof window.__APP_ROOT === 'string') ? window.__APP_ROOT : '/';
    var modalEl = null;
    var select2Ready = false;

    function emptyCustomer() {
        return {
            company_name: '',
            company_name_kana: '',
            name: '',
            name_kana: '',
            branch: '',
            position: '',
            department: '',
            title: '様',
            tel: '',
            fax: '',
            phone: '',
            email: '',
            zip: '',
            address1: '',
            address2: '',
            memo: '',
            status: 1,
            category_id: 0,
            guis_department: []
        };
    }

    var app = Vue.createApp({
        data: function () {
            return {
                categories: [],
                departments: [],
                selectedCategory: null,
                editingCustomer: null,
                newCustomer: emptyCustomer(),
                customerErrors: {
                    company_name: '',
                    name: '',
                    branch: '',
                    guis_department: ''
                },
                ready: false
            };
        },
        methods: {
            apiUrl: function (params) {
                return ROOT + 'api/index.php?' + params;
            },
            async ensureLoaded() {
                if (this.ready) {
                    return;
                }
                await Promise.all([this.loadDepartments(), this.loadCategories()]);
                this.ready = true;
                this.$nextTick(function () {
                    this.initSelect2();
                }.bind(this));
            },
            initSelect2: function () {
                if (select2Ready || !this.$refs.guisDepartmentSelect) {
                    return;
                }
                var selectElement = $(this.$refs.guisDepartmentSelect);
                selectElement.select2({
                    dropdownParent: $('#globalCustomerModal')
                });
                selectElement.on('change', function (event) {
                    var val = $(event.target).val();
                    this.newCustomer.guis_department = val ? val : [];
                }.bind(this));
                select2Ready = true;
            },
            async loadDepartments() {
                var response = await axios.get(this.apiUrl('model=department&method=list_department'));
                this.departments = response.data || [];
            },
            async loadCategories() {
                var response = await axios.get(this.apiUrl('model=customer&method=list_category'));
                this.categories = response.data || [];
            },
            setSelectedCategory: function (categoryId) {
                var category = this.categories.find(function (item) {
                    return String(item.id) === String(categoryId);
                });
                this.selectedCategory = category || null;
            },
            normalizeCustomer: function (customer) {
                var data = Object.assign({}, customer);
                if (typeof data.guis_department === 'string') {
                    data.guis_department = data.guis_department.split(',').filter(Boolean);
                } else if (!Array.isArray(data.guis_department)) {
                    data.guis_department = [];
                }
                data.status = parseInt(data.status, 10) === 0 ? 0 : 1;
                return data;
            },
            async openById(customerId) {
                await this.ensureLoaded();
                var response = await axios.get(this.apiUrl('model=customer&method=get&id=' + encodeURIComponent(customerId)));
                if (!response.data || response.data.status !== 'success' || !response.data.data) {
                    if (typeof showMessage === 'function') {
                        showMessage('顧客の取得に失敗しました。', true);
                    }
                    return;
                }
                var customer = this.normalizeCustomer(response.data.data);
                this.editingCustomer = customer;
                this.newCustomer = Object.assign(emptyCustomer(), customer);
                this.setSelectedCategory(customer.category_id);
                this.customerErrors = { company_name: '', name: '', branch: '', guis_department: '' };
                this.$nextTick(function () {
                    if (this.$refs.guisDepartmentSelect) {
                        $(this.$refs.guisDepartmentSelect).val(this.newCustomer.guis_department).trigger('change');
                    }
                    if (!modalEl) {
                        modalEl = document.getElementById('globalCustomerModal');
                    }
                    if (modalEl && typeof bootstrap !== 'undefined') {
                        bootstrap.Modal.getOrCreateInstance(modalEl).show();
                    }
                }.bind(this));
            },
            async saveCustomer() {
                this.customerErrors = { company_name: '', name: '', branch: '', guis_department: '' };
                var hasError = false;
                if (!this.newCustomer.company_name) {
                    this.customerErrors.company_name = '会社名は必須です。';
                    hasError = true;
                }
                if (!this.newCustomer.name) {
                    this.customerErrors.name = '担当者名は必須です。';
                    hasError = true;
                }
                if (!this.newCustomer.branch || this.newCustomer.branch.trim() === '') {
                    this.customerErrors.branch = '支店名は必須です。';
                    hasError = true;
                }
                if (!this.newCustomer.guis_department || this.newCustomer.guis_department.length === 0) {
                    this.customerErrors.guis_department = '自社担当部署名は必須です。';
                    hasError = true;
                }
                if (hasError) {
                    return;
                }

                try {
                    var response;
                    if (this.editingCustomer) {
                        response = await axios.post(
                            this.apiUrl('model=customer&method=edit_customer&id=' + this.editingCustomer.id),
                            this.newCustomer,
                            { headers: { 'Content-Type': 'application/x-www-form-urlencoded' } }
                        );
                    } else {
                        response = await axios.post(
                            this.apiUrl('model=customer&method=add_customer'),
                            this.newCustomer,
                            { headers: { 'Content-Type': 'application/x-www-form-urlencoded' } }
                        );
                    }

                    if (!response.data || response.data.status !== 'success') {
                        if (typeof showMessage === 'function') {
                            showMessage((response.data && response.data.message_code) || '担当者の保存に失敗しました。', true);
                        }
                        return;
                    }

                    if (this.editingCustomer) {
                        try {
                            var formData = new URLSearchParams();
                            formData.append('customer_id', this.editingCustomer.id);
                            formData.append('company_name', this.newCustomer.company_name);
                            formData.append('branch_name', this.newCustomer.branch);
                            formData.append('contact_name', this.newCustomer.name);
                            await axios.post(
                                this.apiUrl('model=parentproject&method=updateCustomerInfoForAllProjects'),
                                formData,
                                { headers: { 'Content-Type': 'application/x-www-form-urlencoded' } }
                            );
                        } catch (error) {
                            console.error('Error updating parent projects:', error);
                        }
                    }

                    if (typeof showMessage === 'function') {
                        showMessage('担当者を保存しました。');
                    }
                    if (modalEl && typeof bootstrap !== 'undefined') {
                        bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                    }
                    this.editingCustomer = null;
                    this.newCustomer = emptyCustomer();
                } catch (error) {
                    console.error('Error saving customer:', error);
                    if (typeof showMessage === 'function') {
                        showMessage('担当者の保存に失敗しました。', true);
                    }
                }
            },
            searchAddressCustomer: function () {
                var postalCode = this.newCustomer.zip;
                if (!postalCode || postalCode.length < 7) {
                    if (typeof showMessage === 'function') {
                        showMessage('郵便番号が正しくありません。', true);
                    }
                    return;
                }
                axios.get('https://zipcloud.ibsnet.co.jp/api/search?zipcode=' + encodeURIComponent(postalCode))
                    .then(function (response) {
                        if (response.data.results && response.data.results.length > 0) {
                            var result = response.data.results[0];
                            this.newCustomer.address1 = result.address1;
                            this.newCustomer.address2 = result.address2;
                        } else if (typeof showMessage === 'function') {
                            showMessage('郵便番号が見つかりません。', true);
                        }
                    }.bind(this))
                    .catch(function () {
                        if (typeof showMessage === 'function') {
                            showMessage('住所の検索に失敗しました。', true);
                        }
                    });
            }
        }
    });

    var vm = app.mount('#global-customer-modal-app');
    window.openGlobalCustomerModal = function (customerId) {
        return vm.openById(customerId);
    };
})();
