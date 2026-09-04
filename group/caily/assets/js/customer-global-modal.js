(function () {
    'use strict';

    // Prevent double-execution when footer + page both include this script
    if (window.__globalCustomerModalScriptLoaded) {
        return;
    }
    window.__globalCustomerModalScriptLoaded = true;

    var ROOT = (typeof window.__APP_ROOT === 'string')
        ? window.__APP_ROOT
        : ((typeof window.ROOT === 'string') ? window.ROOT : '/');
    var modalEl = null;
    var select2Ready = false;
    var vm = null;
    var initPromise = null;

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

    function createAppInstance() {
        return Vue.createApp({
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
                    ready: false,
                    savingCustomer: false,
                    isProjectManager: !!(
                        (typeof window.IS_PROJECT_MANAGER !== 'undefined' && window.IS_PROJECT_MANAGER) ||
                        (typeof IS_PROJECT_MANAGER !== 'undefined' && IS_PROJECT_MANAGER)
                    )
                };
            },
            computed: {
                canEditCustomer: function () {
                    return !!this.isProjectManager;
                }
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
                    if (typeof window.jQuery === 'undefined') {
                        return;
                    }
                    var selectElement = window.jQuery(this.$refs.guisDepartmentSelect);
                    selectElement.select2({
                        dropdownParent: window.jQuery('#globalCustomerModal'),
                        disabled: !this.canEditCustomer
                    });
                    selectElement.on('change', function (event) {
                        var val = window.jQuery(event.target).val();
                        this.newCustomer.guis_department = val ? val : [];
                    }.bind(this));
                    select2Ready = true;
                },
                syncSelect2Disabled: function () {
                    if (!this.$refs.guisDepartmentSelect || typeof window.jQuery === 'undefined') {
                        return;
                    }
                    var $el = window.jQuery(this.$refs.guisDepartmentSelect);
                    if ($el.data('select2')) {
                        $el.prop('disabled', !this.canEditCustomer).trigger('change.select2');
                    }
                },
                async loadDepartments() {
                    var response = await axios.get(this.apiUrl('model=department&method=list_department'));
                    // API may return raw array or { data: [...] }
                    this.departments = Array.isArray(response.data)
                        ? response.data
                        : ((response.data && response.data.data) || []);
                },
                async loadCategories() {
                    var response = await axios.get(this.apiUrl('model=customer&method=list_category'));
                    this.categories = Array.isArray(response.data)
                        ? response.data
                        : ((response.data && response.data.data) || []);
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
                showModalElement: function () {
                    modalEl = document.getElementById('globalCustomerModal');
                    if (!modalEl) {
                        if (typeof showMessage === 'function') {
                            showMessage('顧客モーダルを開けません。', true);
                        }
                        return;
                    }
                    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                        bootstrap.Modal.getOrCreateInstance(modalEl).show();
                    } else if (typeof window.jQuery !== 'undefined' && window.jQuery.fn && window.jQuery.fn.modal) {
                        window.jQuery(modalEl).modal('show');
                    } else if (typeof showMessage === 'function') {
                        showMessage('顧客モーダルを開けません。', true);
                    }
                },
                async openById(customerId) {
                    try {
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
                        var self = this;
                        await this.$nextTick();
                        if (self.$refs.guisDepartmentSelect && typeof window.jQuery !== 'undefined') {
                            window.jQuery(self.$refs.guisDepartmentSelect).val(self.newCustomer.guis_department).trigger('change');
                        }
                        self.syncSelect2Disabled();
                        this.showModalElement();
                    } catch (error) {
                        console.error('Error opening customer modal:', error);
                        if (typeof showMessage === 'function') {
                            showMessage('顧客情報の読み込みに失敗しました。', true);
                        }
                    }
                },
                async saveCustomer() {
                    if (!this.canEditCustomer) {
                        if (typeof showMessage === 'function') {
                            showMessage('顧客情報を編集する権限がありません。', true);
                        }
                        return;
                    }
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

                    if (this.editingCustomer) {
                        var confirmResult;
                        if (typeof Swal !== 'undefined' && Swal.fire) {
                            confirmResult = await Swal.fire({
                                title: '確認',
                                text: 'お客様情報を更新すると、このお客様の情報を利用している他の建物の情報もすべて更新されます。更新しますか？',
                                icon: 'warning',
                                showCancelButton: true,
                                confirmButtonText: '更新する',
                                cancelButtonText: 'キャンセル',
                                confirmButtonColor: '#3085d6',
                                cancelButtonColor: '#6c757d'
                            });
                            if (!confirmResult.isConfirmed) {
                                return;
                            }
                        } else if (!window.confirm('お客様情報を更新すると、このお客様の情報を利用している他の建物の情報もすべて更新されます。更新しますか？')) {
                            return;
                        }
                    }

                    this.savingCustomer = true;
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
                            showMessage(this.editingCustomer ? '顧客情報を更新しました。' : '担当者を保存しました。');
                        }
                        modalEl = document.getElementById('globalCustomerModal');
                        if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                            bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                        }
                        this.editingCustomer = null;
                        this.newCustomer = emptyCustomer();
                    } catch (error) {
                        console.error('Error saving customer:', error);
                        if (typeof showMessage === 'function') {
                            showMessage('担当者の保存に失敗しました。', true);
                        }
                    } finally {
                        this.savingCustomer = false;
                    }
                },
                searchAddressCustomer: function () {
                    if (!this.canEditCustomer) {
                        return;
                    }
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
    }

    function initVm() {
        if (vm && typeof vm.openById === 'function') {
            return Promise.resolve(vm);
        }
        if (initPromise) {
            return initPromise;
        }

        initPromise = new Promise(function (resolve, reject) {
            function tryInit(attempt) {
                attempt = attempt || 0;
                if (typeof Vue === 'undefined' || typeof Vue.createApp !== 'function') {
                    if (attempt < 40) {
                        setTimeout(function () { tryInit(attempt + 1); }, 50);
                        return;
                    }
                    reject(new Error('Vue is not available'));
                    return;
                }
                if (typeof axios === 'undefined') {
                    if (attempt < 40) {
                        setTimeout(function () { tryInit(attempt + 1); }, 50);
                        return;
                    }
                    reject(new Error('axios is not available'));
                    return;
                }

                var host = document.getElementById('global-customer-modal-app');
                if (!host) {
                    if (attempt < 40) {
                        setTimeout(function () { tryInit(attempt + 1); }, 50);
                        return;
                    }
                    reject(new Error('global-customer-modal-app not found in DOM'));
                    return;
                }

                try {
                    if (host.__vue_app__ && window.__globalCustomerModalVm) {
                        vm = window.__globalCustomerModalVm;
                    } else {
                        var app = createAppInstance();
                        vm = app.mount(host);
                        window.__globalCustomerModalVm = vm;
                    }
                    window.__globalCustomerModalApiReady = true;
                    resolve(vm);
                } catch (err) {
                    reject(err);
                }
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function () { tryInit(0); }, { once: true });
            } else {
                tryInit(0);
            }
        }).catch(function (err) {
            initPromise = null;
            console.error('Failed to init global customer modal:', err);
            throw err;
        });

        return initPromise;
    }

    // Always expose API immediately (lazy init on first call)
    window.openGlobalCustomerModal = function (customerId) {
        return initVm().then(function (instance) {
            if (!instance || typeof instance.openById !== 'function') {
                if (typeof showMessage === 'function') {
                    showMessage('顧客モーダルを開けません。', true);
                }
                return;
            }
            return instance.openById(customerId);
        }).catch(function (err) {
            console.error('openGlobalCustomerModal failed:', err);
            if (typeof showMessage === 'function') {
                showMessage('顧客モーダルを開けません。ページを再読み込みしてください。', true);
            }
        });
    };

    // Warm-up
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initVm().catch(function () {});
        }, { once: true });
    } else {
        initVm().catch(function () {});
    }
})();
