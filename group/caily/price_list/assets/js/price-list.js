const { createApp } = Vue;

createApp({
    data() {
        return {
            products: [],
            departments: [],
            filteredProducts: [],
            paginatedProducts: [],
            selectedType: null,
            searchTerm: '',
            sortBy: 'code',
            itemsPerPage: 25,
            currentPage: 1,
            totalPages: 1,
            isEditing: false,
            saving: false,
            editingProduct: {
                id: null,
                code: '',
                name: '',
                department_id: '',
                type: '',
                unit: '',
                price: '',
                cost: '',
                notes: ''
            },
            selectedProduct: null,
            validationErrors: {},
            detailModal: null,
            showAddRow: false,
            originalProduct: null,
            productTypes: [
                { value: '新規', label: '新規' },
                { value: '修正', label: '修正' },
                { value: 'その他', label: 'その他' }
            ]
        };
    },
    computed: {
        visiblePages() {
            const pages = [];
            const start = Math.max(1, this.currentPage - 2);
            const end = Math.min(this.totalPages, this.currentPage + 2);
            
            for (let i = start; i <= end; i++) {
                pages.push(i);
            }
            return pages;
        }
    },
    mounted() {
        this.initializeModals();
        this.loadData();
    },
    methods: {
        initializeModals() {
            this.detailModal = new bootstrap.Modal(document.getElementById('detailModal'));
        },

        async loadData() {
            try {
                const [productsResponse, departmentsResponse] = await Promise.all([
                    axios.get('/api/index.php?model=pricelist&method=getAllProducts'),
                    axios.get('/api/index.php?model=department&method=getAll')
                ]);

                if (productsResponse.data && departmentsResponse.data) {
                    this.products = productsResponse.data;
                    this.departments = departmentsResponse.data;
                    
                    // Set first type as default if no type is selected
                    if (!this.selectedType) {
                        this.selectedType = this.productTypes[0];
                    }
                    
                    this.filterProducts();
                } else {
                    console.error('Failed to load data');
                }
            } catch (error) {
                console.error('Error loading data:', error);
                showMessage('データの読み込みに失敗しました。', true);
            }
        },

        filterProducts() {
            let filtered = [...this.products];

            // Filter by type (from navbar selection)
            if (this.selectedType && this.selectedType.value) {
                filtered = filtered.filter(product => (product.type || '') === this.selectedType.value);
            }

            // Filter by search term
            if (this.searchTerm) {
                const term = this.searchTerm.toLowerCase();
                filtered = filtered.filter(product => 
                    (product.code || '').toLowerCase().includes(term) ||
                    (product.name || '').toLowerCase().includes(term)
                );
            }

            // Sort products
            this.sortProducts(filtered);

            this.filteredProducts = filtered;
            this.currentPage = 1;
            this.updatePagination();
        },

        sortProducts(products = null) {
            const productsToSort = products || this.filteredProducts;
            
            productsToSort.sort((a, b) => {
                switch (this.sortBy) {
                    case 'code':
                        return (a.code || '').toString().localeCompare((b.code || '').toString());
                    case 'name':
                        return (a.name || '').toString().localeCompare((b.name || '').toString());
                    case 'type':
                        return (a.type || '').toString().localeCompare((b.type || '').toString());
                    case 'department':
                        return (a.department_name || '').toString().localeCompare((b.department_name || '').toString());
                    case 'price':
                        return parseFloat(a.price || 0) - parseFloat(b.price || 0);
                    case 'cost':
                        return parseFloat(a.cost || 0) - parseFloat(b.cost || 0);
                    default:
                        return 0;
                }
            });

            if (!products) {
                this.updatePagination();
            }
        },

        updatePagination() {
            this.totalPages = Math.ceil(this.filteredProducts.length / this.itemsPerPage);
            this.currentPage = Math.min(this.currentPage, this.totalPages);
            this.currentPage = Math.max(1, this.currentPage);
            
            const start = (this.currentPage - 1) * this.itemsPerPage;
            const end = start + this.itemsPerPage;
            this.paginatedProducts = this.filteredProducts.slice(start, end);
        },

        changePage(page) {
            if (page >= 1 && page <= this.totalPages) {
                this.currentPage = page;
                this.updatePagination();
            }
        },

        startAddRow() {
            this.showAddRow = true;
            this.isEditing = false;
            this.editingProduct = {
                id: null,
                code: '',
                name: '',
                department_id: this.selectedDepartment ? this.selectedDepartment.id : '',
                type: this.selectedType ? this.selectedType.value : '',
                unit: '',
                price: '',
                cost: '',
                notes: ''
            };
            this.validationErrors = {};
            this.originalProduct = null;
        },

        startEdit(product) {
            this.isEditing = true;
            this.editingProduct = { ...product };
            this.originalProduct = { ...product };
            this.validationErrors = {};
            this.showAddRow = false;
            
            // Set the selected type to match the product's type for editing
            if (product.type) {
                const matchingType = this.productTypes.find(t => t.value === product.type);
                if (matchingType) {
                    this.selectedType = matchingType;
                }
            }
        },

        cancelEdit() {
            this.showAddRow = false;
            this.isEditing = false;
            this.editingProduct = {
                id: null,
                code: '',
                name: '',
                department_id: '',
                type: '',
                unit: '',
                price: '',
                cost: '',
                notes: ''
            };
            this.validationErrors = {};
            this.originalProduct = null;
        },



        viewProductsByType(type) {
            this.selectedType = type;
            this.filterProducts();
        },



        showDetailModal(product) {
            this.selectedProduct = { ...product };
            this.detailModal.show();
        },

        editFromDetail() {
            this.detailModal.hide();
            setTimeout(() => {
                this.showEditModal(this.selectedProduct);
            }, 300);
        },

        async duplicateProduct(product) {
            try {
                const formData = new FormData();
                formData.append('id', product.id);
                
                const response = await axios.post('/api/index.php?model=pricelist&method=duplicateProduct', formData);
                
                if (response.data && response.data.status === 'success') {
                    this.loadData();
                    showMessage('商品を複製しました', false);
                } else {
                    showMessage(response.data?.message || 'エラーが発生しました', true);
                }
            } catch (error) {
                console.error('Error duplicating product:', error);
                showMessage('エラーが発生しました', true);
            }
        },

        async saveProduct() {
            this.validationErrors = {};
            
            // Set type from selected navbar type
            if (this.selectedType) {
                this.editingProduct.type = this.selectedType.value;
            }
            
            // Validation
            if (!this.editingProduct.code.trim()) {
                this.validationErrors.code = 'コードは必須です';
            }
            if (!this.editingProduct.name.trim()) {
                this.validationErrors.name = '商品名は必須です';
            }
            if (!this.editingProduct.unit.trim()) {
                this.validationErrors.unit = '単位は必須です';
            }
            if (!this.editingProduct.price || parseFloat(this.editingProduct.price) <= 0) {
                this.validationErrors.price = '有効な単価を入力してください';
            }
            if (this.editingProduct.cost && parseFloat(this.editingProduct.cost) < 0) {
                this.validationErrors.cost = '売上原価は0以上で入力してください';
            }

            if (Object.keys(this.validationErrors).length > 0) {
                return;
            }

            this.saving = true;

            try {
                const method = this.isEditing ? 'update' : 'create';
                const formData = new FormData();
                
                // Add all product data to formData
                Object.keys(this.editingProduct).forEach(key => {
                    if (this.editingProduct[key] !== null && this.editingProduct[key] !== '') {
                        formData.append(key, this.editingProduct[key]);
                    }
                });
                
                const response = await axios.post(`/api/index.php?model=pricelist&method=${method}`, formData);
                
                if (response.data && response.data.status === 'success') {
                    this.cancelEdit();
                    this.loadData();
                    showMessage(this.isEditing ? '商品を更新しました' : '商品を作成しました', false);
                } else {
                    showMessage(response.data?.message || 'エラーが発生しました', true);
                }
            } catch (error) {
                console.error('Error saving product:', error);
                showMessage('エラーが発生しました', true);
            } finally {
                this.saving = false;
            }
        },

        async deleteProduct(product) {
            try {
                const result = await Swal.fire({
                    title: '確認',
                    text: `「${product.name}」を削除しますか？`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: '削除',
                    cancelButtonText: 'キャンセル'
                });
                
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('id', product.id);
                    
                    const response = await axios.post('/api/index.php?model=pricelist&method=delete', formData);
                    
                    if (response.data && response.data.status === 'success') {
                        this.loadData();
                        showMessage('商品を削除しました', false);
                    } else {
                        showMessage(response.data?.message || 'エラーが発生しました', true);
                    }
                }
            } catch (error) {
                console.error('Error deleting product:', error);
                showMessage('エラーが発生しました', true);
            }
        },

        formatPrice(price) {
            return new Intl.NumberFormat('ja-JP').format(price);
        },

        formatDateTime(dateTime) {
            if (!dateTime) return '-';
            return new Date(dateTime).toLocaleString('ja-JP');
        },

        // Removed showSuccessMessage and showErrorMessage methods as we now use showMessage function
    }
}).mount('#app'); 