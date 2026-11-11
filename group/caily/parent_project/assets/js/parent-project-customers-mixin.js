/**
 * Vue 3 mixin: customer list of a parent project (建物) with exactly one primary customer (主顧客).
 * Shared by parent_project/create.php and parent_project/detail.php together with the partials
 * customer-list-editor.php, customer-list-modal.php and customer-search-modal.php.
 *
 * The host component must provide:
 *   - data: parentProject (company_name / branch_name / contact_name / customer_id mirror the primary customer)
 *     (or override getCustomerMirrorTarget() when the edited building lives under another name)
 *   - method: showCustomerInfoModal(customer)  (existing customer detail / edit modal)
 *
 * Row shape: { customer_id, is_primary (0|1), company_name, branch_name, contact_name, tel, phone, email }
 * customer_id is null only for legacy parent projects that stored names without a customer master link.
 */
(function () {
    'use strict';

    function notify(message, isError) {
        if (typeof showMessage === 'function') {
            showMessage(message, !!isError);
        }
    }

    window.ParentProjectCustomersMixin = {
        data() {
            return {
                customerList: [],
                customerListError: '',
                // Element ids of the two modals (a host overrides them when the page holds a second pair)
                customerSearchModalId: 'customerSearchModal',
                customerListModalId: 'customerListModal',
                customerSearchKeyword: '',
                customerSearchResults: [],
                customerSearchLoading: false,
                allCustomersForSearch: [],
                customerSearchLoaded: false,
                customerSearchDepartmentNames: null
            };
        },
        computed: {
            primaryCustomer() {
                return this.getPrimaryCustomer();
            },
            // Customers other than the primary one (the "+N" shown next to the primary)
            extraCustomerCount() {
                return Math.max(0, (this.customerList || []).length - 1);
            },
            // A legacy (unlinked) row next to real customers is dropped on save: warn about it
            hasUnlinkedCustomerRow() {
                const list = this.customerList || [];
                return list.some((c) => !c.customer_id) && list.some((c) => !!c.customer_id);
            }
        },
        methods: {
            normalizeCustomerListRow(source, isPrimary) {
                const src = source || {};
                const rawId = src.customer_id != null ? src.customer_id : src.id;
                const id = rawId != null && String(rawId).trim() !== '' && Number(rawId) > 0 ? Number(rawId) : null;
                return {
                    customer_id: id,
                    is_primary: isPrimary ? 1 : 0,
                    company_name: src.company_name || '',
                    branch_name: src.branch_name != null ? (src.branch_name || '') : (src.branch || ''),
                    contact_name: src.contact_name != null ? (src.contact_name || '') : (src.name || ''),
                    tel: src.tel || '',
                    phone: src.phone || '',
                    email: src.email || ''
                };
            },
            customerListKey(row, index) {
                return row && row.customer_id ? 'c' + row.customer_id : 'legacy' + index;
            },
            formatCustomerLabel(row) {
                if (!row) {
                    return '';
                }
                return [row.company_name, row.branch_name, row.contact_name]
                    .map((part) => String(part || '').trim())
                    .filter(Boolean)
                    .join(' / ');
            },
            getPrimaryCustomer() {
                const list = this.customerList || [];
                return list.find((c) => Number(c.is_primary) === 1) || list[0] || null;
            },
            isCustomerInList(customerId) {
                const id = Number(customerId);
                if (!id) {
                    return false;
                }
                return (this.customerList || []).some((c) => Number(c.customer_id) === id);
            },
            ensureSinglePrimaryCustomer() {
                const list = this.customerList || [];
                if (list.length === 0) {
                    return;
                }
                let primaryIndex = list.findIndex((c) => Number(c.is_primary) === 1);
                if (primaryIndex < 0) {
                    primaryIndex = 0;
                }
                list.forEach((c, i) => {
                    c.is_primary = i === primaryIndex ? 1 : 0;
                });
            },
            // Load the list returned by parentproject.getById (`customers`)
            setCustomerListFromServer(rows) {
                const list = Array.isArray(rows) ? rows : [];
                this.customerList = list.map((row) => this.normalizeCustomerListRow(row, Number(row.is_primary) === 1));
                this.ensureSinglePrimaryCustomer();
                this.customerListError = '';
            },
            // Keep parentProject.company_name / branch_name / contact_name / customer_id equal to the primary customer
            // Object whose company_name / branch_name / contact_name / customer_id mirror the primary customer.
            // Hosts that edit the building under another name (index.php quick edit) override this.
            getCustomerMirrorTarget() {
                return this.parentProject;
            },
            syncPrimaryCustomerFields() {
                const target = this.getCustomerMirrorTarget();
                if (!target) {
                    return;
                }
                const primary = this.getPrimaryCustomer();
                target.company_name = primary ? primary.company_name : '';
                target.branch_name = primary ? primary.branch_name : '';
                target.contact_name = primary ? primary.contact_name : '';
                target.customer_id = primary && primary.customer_id ? String(primary.customer_id) : '';
            },
            /**
             * Add a customer master row ({id, company_name, branch, name, ...}) to the list.
             * The first customer added becomes the primary one.
             * @returns {boolean} false when it was already in the list
             */
            addCustomerToList(customer) {
                const row = this.normalizeCustomerListRow(customer, false);
                if (!row.customer_id) {
                    return false;
                }
                if (this.isCustomerInList(row.customer_id)) {
                    notify('この顧客は既にリストに追加されています。', true);
                    return false;
                }
                this.customerList.push(row);
                this.ensureSinglePrimaryCustomer();
                this.customerListError = '';
                this.syncPrimaryCustomerFields();
                return true;
            },
            removeCustomerFromList(index) {
                if (index < 0 || index >= this.customerList.length) {
                    return;
                }
                this.customerList.splice(index, 1);
                this.ensureSinglePrimaryCustomer();
                this.syncPrimaryCustomerFields();
            },
            setPrimaryCustomer(index) {
                if (index < 0 || index >= this.customerList.length) {
                    return;
                }
                this.customerList.forEach((c, i) => {
                    c.is_primary = i === index ? 1 : 0;
                });
                this.syncPrimaryCustomerFields();
            },
            validateCustomerList() {
                if (!this.customerList || this.customerList.length === 0) {
                    this.customerListError = '顧客を1件以上追加してください';
                    return false;
                }
                this.customerListError = '';
                return true;
            },
            /**
             * `customers` value for parentproject.create / update.
             * @returns {Array|null} null when no row is linked to the customer master
             *   (legacy names only): the caller then posts the plain name fields as before.
             */
            buildCustomersPayload() {
                const rows = (this.customerList || []).filter((c) => !!c.customer_id);
                if (rows.length === 0) {
                    return null;
                }
                const hasPrimary = rows.some((c) => Number(c.is_primary) === 1);
                return rows.map((c, i) => ({
                    customer_id: Number(c.customer_id),
                    is_primary: (hasPrimary ? Number(c.is_primary) === 1 : i === 0) ? 1 : 0
                }));
            },
            appendCustomersToFormData(formData) {
                const payload = this.buildCustomersPayload();
                if (payload) {
                    formData.append('customers', JSON.stringify(payload));
                }
            },
            // After a customer master row was edited: refresh its names in the list
            applyCustomerMasterUpdateToList(customer) {
                if (!customer || !customer.id) {
                    return;
                }
                const id = Number(customer.id);
                const row = (this.customerList || []).find((c) => Number(c.customer_id) === id);
                if (!row) {
                    return;
                }
                row.company_name = customer.company_name || '';
                row.branch_name = customer.branch || '';
                row.contact_name = customer.name || '';
                row.tel = customer.tel || '';
                row.phone = customer.phone || '';
                row.email = customer.email || '';
            },
            async fetchCustomerMasterById(customerId) {
                const id = Number(customerId);
                if (!id) {
                    return null;
                }
                try {
                    const response = await axios.get('/api/index.php?model=customer&method=get&id=' + encodeURIComponent(id));
                    if (response.data && response.data.status === 'success' && response.data.data) {
                        return response.data.data;
                    }
                } catch (error) {
                    console.error('Error loading customer by id:', error);
                }
                return null;
            },
            /**
             * Add a customer that was just created (customer.add_customer).
             * Uses the returned id; falls back to looking the newest match up by company / branch / name.
             */
            async addNewCustomerToList(created) {
                const info = created || {};
                let customer = info.id ? await this.fetchCustomerMasterById(info.id) : null;
                if (!customer && info.company_name && info.name) {
                    try {
                        const response = await axios.get('/api/index.php?model=customer&method=list_contacts_by_company_branch', {
                            params: { company_name: info.company_name, branch_name: info.branch || '' }
                        });
                        const matches = ((response.data && response.data.data) || [])
                            .filter((c) => c.name === info.name)
                            .sort((a, b) => Number(b.id) - Number(a.id));
                        if (matches.length > 0) {
                            customer = await this.fetchCustomerMasterById(matches[0].id);
                        }
                    } catch (error) {
                        console.error('Error resolving new customer:', error);
                    }
                }
                this.customerSearchLoaded = false; // search cache no longer complete
                if (!customer) {
                    notify('登録した顧客をリストに追加できませんでした。「顧客検索」から追加してください。', true);
                    return false;
                }
                return this.addCustomerToList(customer);
            },

            // ---- 顧客検索 (search modal) ----
            async loadCustomerSearchDepartments() {
                if (this.customerSearchDepartmentNames) {
                    return;
                }
                const map = {};
                try {
                    const response = await axios.get('/api/index.php?model=department&method=list_department');
                    const list = Array.isArray(response.data)
                        ? response.data
                        : ((response.data && response.data.data) || []);
                    list.forEach((department) => {
                        if (department && department.id != null) {
                            map[String(department.id)] = department.name || '';
                        }
                    });
                } catch (error) {
                    console.error('Error loading departments for customer search:', error);
                }
                this.customerSearchDepartmentNames = map;
            },
            customerSearchDepartmentLabel(customer) {
                const map = this.customerSearchDepartmentNames || {};
                const raw = customer ? customer.guis_department : null;
                const ids = Array.isArray(raw)
                    ? raw
                    : (typeof raw === 'string' && raw ? raw.split(',') : []);
                const names = ids
                    .map((id) => map[String(id).trim()] || '')
                    .filter(Boolean);
                return names.length > 0 ? names.join(', ') : '—';
            },
            // When one of these modals closes on top of another open modal (index.php 建物編集),
            // Bootstrap drops body.modal-open: put it back so the modal underneath keeps scrolling.
            keepModalOpenStateAfterHide(modalId) {
                const el = document.getElementById(modalId);
                if (!el) {
                    return;
                }
                el.addEventListener('hidden.bs.modal', () => {
                    if (document.querySelector('.modal.show')) {
                        document.body.classList.add('modal-open');
                    }
                }, { once: true });
            },
            async openCustomerSearchModal() {
                this.customerSearchKeyword = '';
                this.customerSearchResults = [];
                this.keepModalOpenStateAfterHide(this.customerSearchModalId);
                $('#' + this.customerSearchModalId).modal('show');
                await this.loadCustomerSearchDepartments();
                if (!this.customerSearchLoaded) {
                    await this.loadAllCustomersForSearch();
                }
            },
            async loadAllCustomersForSearch() {
                this.customerSearchLoading = true;
                try {
                    const response = await axios.get('/api/index.php?model=customer&method=list_customer&all=1');
                    if (response.data && response.data.status === 'success' && Array.isArray(response.data.data)) {
                        this.allCustomersForSearch = response.data.data;
                        this.customerSearchLoaded = true;
                    } else {
                        this.allCustomersForSearch = [];
                    }
                } catch (error) {
                    console.error('Error loading customers for search:', error);
                    this.allCustomersForSearch = [];
                    notify('顧客の読み込みに失敗しました。', true);
                } finally {
                    this.customerSearchLoading = false;
                }
                this.searchCustomersByKeyword();
            },
            searchCustomersByKeyword() {
                const keyword = (this.customerSearchKeyword || '').trim().toLowerCase();
                if (!keyword) {
                    this.customerSearchResults = [];
                    return;
                }
                const fieldsForSearch = (customer) => [
                    customer.company_name,
                    customer.company_name_kana,
                    customer.branch,
                    customer.name,
                    customer.name_kana,
                    customer.tel,
                    customer.phone,
                    customer.email,
                    this.customerSearchDepartmentLabel(customer)
                ];
                this.customerSearchResults = (this.allCustomersForSearch || [])
                    .filter((customer) => fieldsForSearch(customer).some((value) => String(value || '').toLowerCase().includes(keyword)))
                    .slice(0, 100);
            },
            // Clicking a search result adds it to the list; the modal stays open to add more
            selectCustomerFromSearch(customer) {
                if (!customer || this.isCustomerInList(customer.id)) {
                    return;
                }
                // No success popup: showMessage() is a blocking dialog and would interrupt adding several
                // customers in a row; the row turns to 追加済み and the list counter in the footer updates.
                this.addCustomerToList(customer);
            },

            // ---- 顧客情報 (list modal) ----
            openCustomerListModal() {
                this.keepModalOpenStateAfterHide(this.customerListModalId);
                $('#' + this.customerListModalId).modal('show');
            },
            async openCustomerInfoFromList(row) {
                if (!row || !row.customer_id) {
                    return;
                }
                const customer = await this.fetchCustomerMasterById(row.customer_id);
                if (!customer) {
                    notify('顧客情報が見つかりません。', true);
                    return;
                }
                if (this.$data && Object.prototype.hasOwnProperty.call(this.$data, 'customerInfoModalContext')) {
                    this.customerInfoModalContext = 'parent';
                    this.customerInfoModalChildProjectId = null;
                }
                $('#' + this.customerListModalId).modal('hide');
                await this.showCustomerInfoModal(customer);
            }
        }
    };
})();
