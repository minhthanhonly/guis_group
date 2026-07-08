const { createApp } = Vue;

const SERVER_TASK_TIMEZONE = 'Asia/Tokyo';
const VIETNAM_TASK_TIMEZONE = 'Asia/Ho_Chi_Minh';
const PROJECT_DATETIME_MOMENT_FORMAT = 'YYYY/M/D HH:mm';
const PROJECT_DATETIME_JA_DISPLAY_FORMAT = 'YYYY年M月D日 HH:mm';
const PROJECT_DATETIME_JA_SHORT_FORMAT = 'M月D日 HH:mm';

function isDrawingsVietnameseLocale() {
    return typeof i18next !== 'undefined'
        && i18next.isInitialized
        && String(i18next.language || '').startsWith('vi');
}

function getDrawingsDisplayTimezone() {
    return isDrawingsVietnameseLocale() ? VIETNAM_TASK_TIMEZONE : SERVER_TASK_TIMEZONE;
}

function parseDrawingDateMomentServer(value) {
    if (value === undefined || value === null) return null;
    const s = String(value).trim();
    if (!s || s === '-') return null;
    const normalized = s.replace(/\//g, '-');
    if (typeof moment !== 'undefined') {
        const formats = ['YYYY-MM-DD HH:mm:ss', 'YYYY-MM-DD HH:mm', 'YYYY-M-D HH:mm', 'YYYY-MM-DD', 'YYYY-M-D'];
        const m = typeof moment.tz === 'function'
            ? moment.tz(normalized, formats, SERVER_TASK_TIMEZONE)
            : moment(normalized, formats, true);
        if (m.isValid()) return m;
    }
    const d = new Date(normalized);
    if (isNaN(d.getTime())) return null;
    return typeof moment !== 'undefined' ? moment(d) : null;
}

function formatDrawingDateTimeForDisplay(value) {
    const parsed = parseDrawingDateMomentServer(value);
    if (!parsed) return '';
    const localized = moment.tz
        ? parsed.clone().tz(getDrawingsDisplayTimezone())
        : parsed;
    return localized.format(
        isDrawingsVietnameseLocale()
            ? PROJECT_DATETIME_MOMENT_FORMAT
            : PROJECT_DATETIME_JA_SHORT_FORMAT
    );
}

const DEFAULT_TASK_DRAWING_PRICE_PERCENTS = {
    'お客様との連絡・調整・納品対応': 0.15,
    '全図面のチェック・確認作業': 0.20
};

createApp({
    data() {
        return {
            project: null,
            drawings: [],
            selectedDrawings: [],
            searchQuery: '',
            statusFilter: '',
            
            // Sorting
            sortField: 'created_at',
            sortDirection: 'asc',
            
            // Form data
            editingDrawing: {
                id: null,
                name: '',
                status: 'todo',
                file_path: '',
                price: null,
                drawing_count: 1,
                task_id: null
            },
            
            // Bulk operations
            bulkStatus: 'todo',
            
            // Import data
            importFiles: [],
            clipboardText: '',
            
            // Loading states
            loading: false,
            
            // Drawing statuses for dropdown
            drawingStatuses: [
                { value: 'todo', label: '未開始', color: 'secondary' },
                { value: 'in-progress', label: '進行中', color: 'primary' },
                { value: 'confirming', label: '確認中', color: 'warning' },
                { value: 'paused', label: '一時停止', color: 'warning' },
                { value: 'completed', label: '完了', color: 'success' },
                { value: 'cancelled', label: 'キャンセル', color: 'danger' }
            ],
            
            // Drag selection
            isDragging: false,
            dragStartIndex: -1,
            dragEndIndex: -1,
            hoveredRow: null,
            
            // Click selection
            lastClickedIndex: -1,
            isCtrlPressed: false,
            isShiftPressed: false,
            permission: {},
            permissionLoaded: false,
            
            // Project members for assignment
            projectMembers: [],
            assigneeModal: {
                show: false,
                drawingId: null,
                isBulk: false,
                selected: []
            }
        }
    },
    
    computed: {
        canViewDrawings() {
            if ((typeof USER_ROLE !== 'undefined' && USER_ROLE == 'administrator')
                || (typeof IS_ADMINISTRATOR !== 'undefined' && IS_ADMINISTRATOR)) {
                return true;
            }
            if (!this.permission) return false;
            if (this.permission.can_manage_project) return true;
            const rule = this.permission.rule;
            if (!rule) return false;
            return rule.project_director_stat == 1
                || rule.project_director_view == 1
                || rule.project_director_edit == 1
                || rule.project_director == 1;
        },
        
        filteredDrawings() {
            let filtered = this.drawings;
            
            if (this.searchQuery) {
                const query = this.searchQuery.toLowerCase();
                filtered = filtered.filter(drawing => 
                    drawing.name.toLowerCase().includes(query)
                );
            }
            
            if (this.statusFilter) {
                filtered = filtered.filter(drawing => drawing.status === this.statusFilter);
            }
            
            // Sort the filtered results
            filtered.sort((a, b) => {
                let aValue, bValue;
                
                aValue = a[this.sortField];
                bValue = b[this.sortField];
                
                // Handle null/undefined values
                if (aValue === null || aValue === undefined) aValue = '';
                if (bValue === null || bValue === undefined) bValue = '';
                
                // Convert to string for comparison
                aValue = String(aValue);
                bValue = String(bValue);
                
                // Use localeCompare for proper Japanese character sorting
                let comparison = aValue.localeCompare(bValue, 'ja');
                
                return this.sortDirection === 'asc' ? comparison : -comparison;
            });
            
            return filtered;
        },
        
        isAllSelected() {
            return this.filteredDrawings.length > 0 && this.selectedDrawings.length === this.filteredDrawings.length;
        },
        
        isCtrlMode() {
            return this.isCtrlPressed;
        },
        
        isShiftMode() {
            return this.isShiftPressed;
        },
        
        totalDrawingsPrice() {
            return this.drawings.reduce((sum, drawing) => sum + this.getEffectiveDrawingPrice(drawing), 0);
        },
        
        projectAmount() {
            return this.project && this.project.amount ? parseFloat(this.project.amount) : 0;
        },
        
        isPriceExceedingAmount() {
            return this.projectAmount > 0 && this.totalDrawingsPrice > this.projectAmount;
        },
        
        priceDifference() {
            return this.totalDrawingsPrice - this.projectAmount;
        },
        
        // Tổng giá đã phân bổ: đơn giá đã nhập + phần cố định 15%/20% của bản vẽ task mặc định đã hoàn thành
        totalPriceOfDrawingsWithPrice() {
            return this.drawings.reduce((sum, d) => {
                const effective = this.getEffectiveDrawingPrice(d);
                if (effective > 0) {
                    return sum + effective;
                }
                const reserved = this.getDefaultTaskDrawingReservedPrice(d);
                return sum + (reserved || 0);
            }, 0);
        },
        // Các bản vẽ còn lại được chia phần dư (không gồm task mặc định / task chưa hoàn thành)
        remainingDrawingsList() {
            return this.drawings.filter(d => this.isDrawingEligibleForRemainderAutoPrice(d));
        },
        remainingDrawingsCount() {
            return this.remainingDrawingsList.reduce((sum, d) => sum + this.getDrawingQuantity(d), 0);
        },
        autoPricePerRemaining() {
            if (this.projectAmount <= 0 || this.remainingDrawingsCount <= 0) return null;
            const rest = this.projectAmount - this.totalPriceOfDrawingsWithPrice;
            if (rest < 0) return null;
            return Math.floor(rest / this.remainingDrawingsCount);
        },
        autoPriceListForRemaining() {
            if (this.projectAmount <= 0 || this.remainingDrawingsCount <= 0) return [];
            const rest = this.projectAmount - this.totalPriceOfDrawingsWithPrice;
            if (rest < 0) return [];
            const rows = this.remainingDrawingsList;
            const totalQty = this.remainingDrawingsCount;
            let allocated = 0;
            return rows.map((d, index) => {
                if (index === rows.length - 1) {
                    return rest - allocated;
                }
                const qty = this.getDrawingQuantity(d);
                const share = Math.floor((rest * qty) / totalQty);
                allocated += share;
                return share;
            });
        },
        canAutoCalculateRemaining() {
            return this.autoPricePerRemaining != null && this.remainingDrawingsCount > 0;
        },
        
        countableDrawings() {
            return this.drawings.filter(d => !this.isDefaultTaskDrawing(d));
        },
        stats() {
            const sumQty = (list) => this.sumCountableDrawingQuantity(list);
            return [
                {
                    label: '図面総数',
                    value: sumQty(this.countableDrawings),
                    icon: 'fa fa-file-alt text-primary',
                    color: 'text-primary',
                    status: ''
                },
                {
                    label: '未開始',
                    value: sumQty(this.countableDrawings.filter(d => d.status === 'todo')),
                    icon: 'fa fa-pencil-alt text-secondary',
                    color: 'text-secondary',
                    status: 'todo'
                },
                {
                    label: '進行中',
                    value: sumQty(this.countableDrawings.filter(d => d.status === 'in-progress')),
                    icon: 'fa fa-play text-primary',
                    color: 'text-primary',
                    status: 'in-progress'
                },
                {
                    label: '確認中',
                    value: sumQty(this.countableDrawings.filter(d => d.status === 'confirming')),
                    icon: 'fa fa-search text-warning',
                    color: 'text-warning',
                    status: 'confirming'
                },
                {
                    label: '一時停止',
                    value: sumQty(this.countableDrawings.filter(d => d.status === 'paused')),
                    icon: 'fa fa-pause text-warning',
                    color: 'text-warning',
                    status: 'paused'
                },
                {
                    label: '完了',
                    value: sumQty(this.countableDrawings.filter(d => d.status === 'completed')),
                    icon: 'fa fa-check-circle text-success',
                    color: 'text-success',
                    status: 'completed'
                },
                {
                    label: 'キャンセル',
                    value: sumQty(this.countableDrawings.filter(d => d.status === 'cancelled')),
                    icon: 'fa fa-times-circle text-danger',
                    color: 'text-danger',
                    status: 'cancelled'
                }
            ];
        },

        totalStat() {
            return this.stats.find(s => s.status === '');
        },

        statusStats() {
            return this.stats.filter(s => s.status !== '');
        },

        hasActiveFilter() {
            return !!(this.searchQuery || this.statusFilter);
        },

        hasUnassignedDrawings() {
            return this.drawings.some(d => !this.isUserAssigned(d));
        }
    },
    
    mounted() {
        this.loadPermission();
        this.loadProject();
        this.loadProjectMembers();
        this.loadDrawings();

        
        // Add click outside listener to close dropdowns
        document.addEventListener('click', (event) => {
            // Don't close if clicking on dropdown toggle button
            if (event.target.closest('.dropdown-toggle')) {
                return;
            }
            // Close if clicking outside dropdown
            if (!event.target.closest('.dropdown')) {
                this.closeAllDropdowns();
            }
        });
        
        // Add global mouse event listeners for drag selection
        document.addEventListener('mousemove', (event) => {
            if (this.isDragging) {
                // Find the row under the mouse
                const row = event.target.closest('tr');
                if (row && row.dataset.index !== undefined) {
                    const index = parseInt(row.dataset.index);
                    // Only update if the index is valid and different
                    if (index >= 0 && index < this.filteredDrawings.length && index !== this.dragEndIndex) {
                        this.updateDragSelection(event, index);
                    }
                }
            }
        });
        
        document.addEventListener('mouseup', () => {
            this.endDragSelection();
        });
        
        // Add keyboard event listeners for Ctrl / Shift / Esc keys
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Control' || event.key === 'Meta') {
                this.handleCtrlKeyChange(true);
            }
            if (event.key === 'Shift') {
                this.handleShiftKeyChange(true);
            }
            // ESC: clear bulk selection (hide bulk actions bar)
            if (event.key === 'Escape') {
                if (this.selectedDrawings && this.selectedDrawings.length > 0) {
                    this.clearSelection();
                }
            }
        });
        
        document.addEventListener('keyup', (event) => {
            if (event.key === 'Control' || event.key === 'Meta') {
                this.handleCtrlKeyChange(false);
            }
            if (event.key === 'Shift') {
                this.handleShiftKeyChange(false);
            }
        });
        
        // Reset key states when window loses focus
        window.addEventListener('blur', () => {
            this.handleCtrlKeyChange(false);
            this.handleShiftKeyChange(false);
        });

        window.addEventListener('ai-action-success', (event) => {
            const { action } = event.detail || {};
            const pid = action && (action.id || (action.params && action.params.project_id));
            if (typeof PROJECT_ID !== 'undefined' && pid && String(pid) === String(PROJECT_ID)) {
                this.loadProject();
                this.loadProjectMembers();
                this.loadDrawings();
            }
        });
    },
    
    methods: {
        // Phương thức để dịch label động
        $t(label) {
            if (typeof i18next !== 'undefined' && i18next.isInitialized) {
                return i18next.t(label) || label;
            }
            return label;
        },
        getDrawingQuantity(drawing) {
            const n = parseInt(drawing?.drawing_count, 10);
            return (!Number.isNaN(n) && n > 0) ? n : 1;
        },
        resolveDefaultTaskDrawingTitleKey(drawing) {
            const name = (drawing?.name || '').trim();
            return Object.prototype.hasOwnProperty.call(DEFAULT_TASK_DRAWING_PRICE_PERCENTS, name)
                ? name
                : null;
        },
        isDefaultTaskDrawing(drawing) {
            return this.resolveDefaultTaskDrawingTitleKey(drawing) !== null;
        },
        sumCountableDrawingQuantity(drawings) {
            return (drawings || []).reduce((sum, d) => {
                if (this.isDefaultTaskDrawing(d)) {
                    return sum;
                }
                return sum + this.getDrawingQuantity(d);
            }, 0);
        },
        getDefaultTaskDrawingPricePercent(drawing) {
            const key = this.resolveDefaultTaskDrawingTitleKey(drawing);
            if (!key || (drawing?.status || '') !== 'completed') {
                return null;
            }
            return DEFAULT_TASK_DRAWING_PRICE_PERCENTS[key];
        },
        getDefaultTaskDrawingReservedPrice(drawing) {
            const pct = this.getDefaultTaskDrawingPricePercent(drawing);
            if (pct == null || this.projectAmount <= 0) {
                return 0;
            }
            return Math.round(this.projectAmount * pct * 100) / 100;
        },
        getEffectiveDrawingPrice(drawing) {
            if (!drawing) return 0;
            if (this.isDefaultTaskDrawing(drawing) && (drawing.status || '') !== 'completed') {
                return 0;
            }
            const price = parseFloat(drawing.price);
            return Number.isNaN(price) || price < 0 ? 0 : price;
        },
        isDrawingEligibleForRemainderAutoPrice(drawing) {
            if (!drawing || this.hasPrice(drawing)) {
                return false;
            }
            if (this.isDefaultTaskDrawing(drawing)) {
                return false;
            }
            const taskId = parseInt(drawing.task_id, 10);
            if (!Number.isNaN(taskId) && taskId > 0 && (drawing.status || '') !== 'completed') {
                return false;
            }
            return true;
        },
        // Bản vẽ đã có đơn giá: price không rỗng (null/undefined/'')
        hasPrice(d) {
            return d.price != null && d.price !== '' && d.price !== 0;
        },

        canEditDrawingPrice() {
            if (typeof USER_ROLE !== 'undefined' && USER_ROLE === 'administrator') {
                return true;
            }
            return !!(this.permission && this.permission.can_manage_project);
        },

        canManageDrawingAssignee() {
            if (typeof USER_ROLE !== 'undefined' && USER_ROLE === 'administrator') {
                return true;
            }
            return !!(this.permission && this.permission.can_manage_project);
        },
        
        async loadPermission() {
            try {
                const response = await axios.get('/api/index.php?model=task&method=getPermission&project_id=' + PROJECT_ID);
                this.permission = (response.data && typeof response.data === 'object') ? response.data : {};
            } catch (error) {
                console.error('Error loading permission:', error);
                this.permission = {};
            } finally {
                this.permissionLoaded = true;
            }
        },
        // Project loading
        async loadProject() {
            try {
                const response = await axios.get(`/api/index.php?model=project&method=getById&id=${PROJECT_ID}`);
                if (response.data && response.data.id) {
                    this.project = response.data;
                }
            } catch (error) {
                console.error('Error loading project:', error);
                this.showError('プロジェクトの読み込みに失敗しました');
            }
        },
        
        async loadProjectMembers() {
            try {
                const response = await axios.get(`/api/index.php?model=project&method=getMembers&project_id=${PROJECT_ID}`);
                let members = response.data || [];
                // Remove duplicate userid
                const seen = new Set();
                this.projectMembers = members.filter(m => {
                    if (!m || !m.userid || seen.has(m.userid)) return false;
                    seen.add(m.userid);
                    return true;
                });
            } catch (error) {
                console.error('Error loading project members:', error);
            }
        },

        initTooltips() {
            // Initialize Bootstrap tooltips
            if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                tooltipTriggerList.map(function (tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                });
            } else if (typeof $ !== 'undefined' && $.fn.tooltip) {
                // Fallback to jQuery tooltip if Bootstrap is not available
                $('[data-bs-toggle="tooltip"]').tooltip();
            }
        },
        
        // Drawings loading
        async loadDrawings() {
            this.loading = true;
            try {
                const response = await axios.get(`/api/index.php?model=drawing&method=list&project_id=${PROJECT_ID}`);
                if (Array.isArray(response.data)) {
                    this.drawings = response.data.map(d => {
                        const drawing = {
                            ...d,
                            price: d.price !== undefined && d.price !== null ? Number(d.price) : null
                        };
                        if (this.isDefaultTaskDrawing(drawing) && (drawing.status || '') !== 'completed') {
                            drawing.price = 0;
                        }
                        return drawing;
                    });
                } else {
                    this.showError('ファイルの読み込みに失敗しました');
                }
            } catch (error) {
                console.error('Error loading drawings:', error);
                this.showError('ファイルの読み込みに失敗しました');
            } finally {
                this.loading = false;

                this.$nextTick(() => {
                    this.initTooltips();
                });
            }
        },

        getUserAvatar(userid) {
            const appChatContacts = document.getElementById('app-chat-contacts');
            const user = appChatContacts.querySelector(`[data-userid="${userid}"]`);
            let avatar = '';
            if (user) {
                avatar = user.querySelector('img').src;
                if(avatar.includes('/1.png')) {
                    avatar = '';
                }
            }
            return avatar;
        },

        getUserAvatarCreatedByText(drawing, index) {
            const name = drawing.created_by_names.split(',')[index];
            return this.getInitials(name);
        },
        getUserCreatedByFullNameText(drawing, index) {
            const name = drawing.created_by_names.split(',')[index];
            return name;
        },


        getUserCheckerAvatarText(drawing, index) {
            const name = drawing.checked_by_name.split(',')[index];
            return this.getInitials(name);
        },
        getUserCheckerFullNameText(drawing, index) {
            const name = drawing.checked_by_name.split(',')[index];
            return name;
        },

        getUserReviseByAvatarText(drawing, index) {
            const name = drawing.revise_by_name.split(',')[index];
            return this.getInitials(name);
        },
        getUserReviseByFullNameText(drawing, index) {
            const name = drawing.revise_by_name.split(',')[index];
            return name;
        },
        
        // Drag and Drop handlers for modal
        onDrop(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const files = Array.from(e.dataTransfer.files);
            this.processDroppedFiles(files);
        },
        
        onFileSelect(event) {
            const files = Array.from(event.target.files);
            this.processDroppedFiles(files);
        },
        
        processDroppedFiles(files) {
            files.forEach(file => {
                const fileInfo = {
                    name: file.name,
                    file_path: '' // Only save filename, not full path
                };
                this.importFiles.push(fileInfo);
            });
        },
        
        // Modal management
        openAddModal() {
            this.resetForm();
            const modal = new bootstrap.Modal(document.getElementById('drawingModal'));
            modal.show();
        },
        
        closeAddModal() {
            const modal = bootstrap.Modal.getInstance(document.getElementById('drawingModal'));
            if (modal) {
                modal.hide();
            }
            this.resetForm();
        },
        
        openEditModal(drawing) {
            this.editingDrawing = { ...drawing };
            const modal = new bootstrap.Modal(document.getElementById('drawingModal'));
            modal.show();
        },
        
        closeEditModal() {
            const modal = bootstrap.Modal.getInstance(document.getElementById('drawingModal'));
            if (modal) {
                modal.hide();
            }
            this.resetForm();
        },
        
        parseClipboardText() {
            if (!this.clipboardText.trim()) {
                this.showError('クリップボードのテキストを入力してください');
                return;
            }
            
            const lines = this.clipboardText.split('\n').filter(line => line.trim());
            this.importFiles = [];
            
            lines.forEach(line => {
                // Remove quotes and trim
                const cleanLine = line.replace(/^["']|["']$/g, '').trim();
                if (cleanLine) {
                    const fileName = this.extractFileName(cleanLine);
                    const fileInfo = {
                        name: fileName,
                        file_path: '' // Only save filename, not full path
                    };
                    this.importFiles.push(fileInfo);
                }
            });
            
            if (this.importFiles.length === 0) {
                this.showError('有効なファイルパスが見つかりませんでした');
            }
        },
        
        extractFileName(filePath) {
            // Extract filename from full path
            const parts = filePath.split(/[\\\/]/);
            return parts[parts.length - 1] || filePath;
        },
        
        removeImportFile(index) {
            this.importFiles.splice(index, 1);
        },
        
        async performImport() {
            if (this.importFiles.length === 0) {
                this.showError('インポートするファイルがありません');
                return;
            }
            
            try {
                let successCount = 0;
                let errorCount = 0;
                let replacedCount = 0;
                
                for (const fileInfo of this.importFiles) {
                    try {
                        const formData = new FormData();
                        formData.append('project_id', PROJECT_ID);
                        formData.append('name', fileInfo.name);
                        formData.append('status', 'todo');
                        
                        const response = await axios.post('/api/index.php?model=drawing&method=add', formData);
                        
                        if (response.data && response.data.status === 'success') {
                            successCount++;
                            // Check if this was a replacement
                            if (response.data.replaced) {
                                replacedCount++;
                            }
                        } else {
                            errorCount++;
                        }
                    } catch (error) {
                        console.error('Error importing file:', fileInfo.name, error);
                        errorCount++;
                    }
                }
                
                if (successCount > 0) {
                    let message = `${successCount}個のファイルをインポートしました`;
                    if (replacedCount > 0) {
                        message += ` (${replacedCount}個の重複ファイルを置き換えました)`;
                    }
                    this.showSuccess(message);
                    // Close modal using Bootstrap
                    const modal = bootstrap.Modal.getInstance(document.getElementById('importModal'));
                    if (modal) {
                        modal.hide();
                    }
                    this.importFiles = [];
                    this.clipboardText = '';
                    this.loadDrawings();
                }
                
                if (errorCount > 0) {
                    this.showError(`${errorCount}個のファイルのインポートに失敗しました`);
                }
                
            } catch (error) {
                console.error('Error during import:', error);
                this.showError('インポートに失敗しました');
            }
        },
        
        async saveDrawing() {
            if (!this.editingDrawing.name) {
                this.showError(this.$t('タスク名は必須です。') || 'タスク名は必須です。');
                return;
            }
            
            try {
                let response;
                if (this.editingDrawing.id) {
                    // Update existing drawing
                    const formData = new FormData();
                    formData.append('id', this.editingDrawing.id);
                    formData.append('name', this.editingDrawing.name);
                    formData.append('status', this.editingDrawing.status);
                    if (this.editingDrawing.price != null && this.editingDrawing.price !== '') {
                        formData.append('price', this.editingDrawing.price);
                    }
                    if (!this.editingDrawing.task_id) {
                        formData.append('drawing_count', this.getDrawingQuantity(this.editingDrawing));
                    }

                    response = await axios.post('/api/index.php?model=drawing&method=edit', formData);
                } else {
                    // Create new drawing
                    const formData = new FormData();
                    formData.append('project_id', PROJECT_ID);
                    formData.append('name', this.editingDrawing.name);
                    formData.append('status', this.editingDrawing.status);
                    formData.append('drawing_count', this.getDrawingQuantity(this.editingDrawing));
                    if (this.editingDrawing.price != null && this.editingDrawing.price !== '') {
                        formData.append('price', this.editingDrawing.price);
                    }
                    
                    response = await axios.post('/api/index.php?model=drawing&method=add', formData);
                }
                
                if (response.data && response.data.status === 'success') {
                    this.showSuccess(this.editingDrawing.id ? 'ファイルを更新しました' : 'ファイルを追加しました');
                    // Close modal using Bootstrap
                    const modal = bootstrap.Modal.getInstance(document.getElementById('drawingModal'));
                    if (modal) {
                        modal.hide();
                    }
                    this.loadDrawings();
                } else {
                    this.showError(response.data?.message || '保存に失敗しました');
                }
            } catch (error) {
                console.error('Error saving drawing:', error);
                this.showError('保存に失敗しました');
            }
        },
        
        async deleteDrawing(id) {
            const result = await Swal.fire({
                title: 'ファイルを削除しますか？',
                text: "この操作は元に戻せません！",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: '削除する',
                cancelButtonText: 'キャンセル'
            });

            if (!result.isConfirmed) {
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('id', id);
                
                const response = await axios.post('/api/index.php?model=drawing&method=delete', formData);
                
                if (response.data && response.data.status === 'success') {
                    this.showSuccess('ファイルを削除しました');
                    this.loadDrawings();
                } else {
                    this.showError(response.data?.message || '削除に失敗しました');
                }
            } catch (error) {
                console.error('Error deleting drawing:', error);
                this.showError('削除に失敗しました');
            }
        },
        
        async updateStatus(id, status) {
            try {
                // Update local data immediately for better UX
                const drawing = this.drawings.find(d => d.id === id);
                if (drawing) {
                    drawing.status = status;
                }
                
                // Close the dropdown
                this.closeAllDropdowns();
                
                const formData = new FormData();
                formData.append('id', id);
                formData.append('status', status);
                
                const response = await axios.post('/api/index.php?model=drawing&method=updateStatus', formData);
                
                if (response.data && response.data.status === 'success') {
                    // Success - local data already updated
                } else {
                    // Revert local change on error
                    if (drawing) {
                        drawing.status = response.data?.original_status || 'todo';
                    }
                    this.showError(response.data?.message || 'ステータスの更新に失敗しました');
                }
            } catch (error) {
                console.error('Error updating status:', error);
                // Revert local change on error
                const drawing = this.drawings.find(d => d.id === id);
                if (drawing) {
                    drawing.status = 'todo';
                }
                this.showError('ステータスの更新に失敗しました');
            }
        },
        
        toggleSelectAll() {
            if (this.isAllSelected) {
                this.selectedDrawings = [];
            } else {
                this.selectedDrawings = this.filteredDrawings.map(d => d.id);
            }
        },
        
        toggleSelectDrawing(id) {
            const index = this.selectedDrawings.indexOf(id);
            if (index > -1) {
                this.selectedDrawings.splice(index, 1);
            } else {
                this.selectedDrawings.push(id);
            }
        },
        
        // Enhanced row click handler with Ctrl and Shift support
        handleRowClick(event, drawingId, index) {
            // Don't handle clicks on interactive elements
            if (event.target.closest('button, input, .btn-group, .dropdown')) {
                return;
            }
            
            // Prevent default behavior for checkbox clicks
            if (event.target.type === 'checkbox') {
                return;
            }
            
            // Don't handle row clicks if the click target is within a checkbox cell
            if (event.target.closest('td:first-child')) {
                return;
            }
            
            // Stop event propagation to prevent conflicts
            event.stopPropagation();
            
            if (this.isCtrlPressed) {
                // Ctrl+Click: Toggle selection of clicked item
                this.toggleSelectDrawing(drawingId);
                this.lastClickedIndex = index;
            } else if (this.isShiftPressed && this.lastClickedIndex !== -1) {
                // Shift+Click: Select range from last clicked to current
                this.selectRange(this.lastClickedIndex, index);
            } else {
                // Regular click: Select only the clicked item
                this.selectedDrawings = [drawingId];
                this.lastClickedIndex = index;
            }
        },
        
        // Handle Ctrl key state changes
        handleCtrlKeyChange(isPressed) {
            this.isCtrlPressed = isPressed;
        },
        
        // Handle Shift key state changes
        handleShiftKeyChange(isPressed) {
            this.isShiftPressed = isPressed;
        },
        
        // Select range of drawings
        selectRange(startIndex, endIndex) {
            const start = Math.min(startIndex, endIndex);
            const end = Math.max(startIndex, endIndex);
            
            // Get the filtered drawings in the current view
            const visibleDrawings = this.filteredDrawings;
            
            // Validate indices
            if (start < 0 || end >= visibleDrawings.length) return;
            
            // Select the range
            for (let i = start; i <= end; i++) {
                if (visibleDrawings[i] && visibleDrawings[i].id) {
                    if (!this.selectedDrawings.includes(visibleDrawings[i].id)) {
                        this.selectedDrawings.push(visibleDrawings[i].id);
                    }
                }
            }
        },
        
        // Handle individual checkbox click
        handleCheckboxClick(drawingId) {
            // Update last clicked index for range selection
            const index = this.filteredDrawings.findIndex(d => d.id === drawingId);
            if (index !== -1) {
                this.lastClickedIndex = index;
            }
        },
        
        
        // Update price for a single drawing (by id + value); used by inline edit and auto-calc
        // isAutoCalc: khi true thì backend không kiểm tra đã gán user cho bản vẽ
        async updatePriceById(drawingId, price, isAutoCalc = false) {
            const formData = new FormData();
            formData.append('id', drawingId);
            formData.append('price', price != null && price !== '' ? price : '');
            if (isAutoCalc) formData.append('auto_calc', '1');
            const response = await axios.post('/api/index.php?model=drawing&method=updatePrice', formData);
            return response;
        },
        // Update price for a single drawing when input changes
        async updatePrice(drawing) {
            if (!this.canEditDrawingPrice()) {
                return;
            }
            try {
                const response = await this.updatePriceById(
                    drawing.id,
                    drawing.price != null && drawing.price !== '' ? drawing.price : ''
                );
                if (!(response.data && response.data.status === 'success')) {
                    this.showError(response.data?.message || '単価の更新に失敗しました');
                    this.loadDrawings();
                }
            } catch (error) {
                console.error('Error updating price:', error);
                this.showError('単価の更新に失敗しました');
                this.loadDrawings();
            }
        },
        // Tự động tính giá các bản vẽ còn lại: (n-1) bản đầu = floor(rest/n), bản cuối = phần dư → tổng không vượt
        async autoCalculateRemainingPrices() {
            if (!this.canAutoCalculateRemaining) {
                this.showError(this.$t('残り図面がありません') || '残り図面がありません');
                return;
            }
            const priceList = this.autoPriceListForRemaining;
            const count = this.remainingDrawingsCount;
            const unitPrice = this.autoPricePerRemaining ?? 0;
            const msg = (this.$t('残り図面の単価を自動計算') || '残り図面の単価を自動計算') + ` (${count}枚、約¥${this.formatNumber(unitPrice)}/枚、最終行で端数調整)`;
            const result = await Swal.fire({
                title: this.$t('残り図面の単価を自動計算') || '残り図面の単価を自動計算',
                html: msg + '<br><br>' + (this.$t('実行しますか？') || '実行しますか？'),
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: this.$t('実行') || '実行',
                cancelButtonText: this.$t('キャンセル') || 'キャンセル'
            });
            if (!result.isConfirmed) return;
            try {
                for (let i = 0; i < this.remainingDrawingsList.length; i++) {
                    const d = this.remainingDrawingsList[i];
                    const price = priceList[i];
                    const res = await this.updatePriceById(d.id, price, true);
                    if (!(res.data && res.data.status === 'success')) {
                        this.showError(res.data?.message || '単価の更新に失敗しました');
                        this.loadDrawings();
                        return;
                    }
                }
                this.showSuccess(this.$t('残り図面の単価を更新しました') || '残り図面の単価を更新しました');
                this.loadDrawings();
            } catch (error) {
                console.error('Error auto-calculating prices:', error);
                this.showError('単価の一括更新に失敗しました');
                this.loadDrawings();
            }
        },
        
        bulkChangeStatus() {
            if (this.selectedDrawings.length === 0) {
                this.showError('ファイルを選択してください');
                return;
            }
            const modal = new bootstrap.Modal(document.getElementById('bulkStatusModal'));
            modal.show();
        },
        
        async confirmBulkStatusChange() {
            try {
                const formData = new FormData();
                formData.append('ids', JSON.stringify(this.selectedDrawings));
                formData.append('status', this.bulkStatus);
                
                const response = await axios.post('/api/index.php?model=drawing&method=bulkUpdateStatus', formData);
                
                if (response.data && response.data.status === 'success') {
                    this.showSuccess('ステータスを一括更新しました');
                    // Close modal using Bootstrap
                    const modal = bootstrap.Modal.getInstance(document.getElementById('bulkStatusModal'));
                    if (modal) {
                        modal.hide();
                    }
                    this.loadDrawings();
                } else {
                    this.showError(response.data?.message || '一括更新に失敗しました');
                }
            } catch (error) {
                console.error('Error bulk updating status:', error);
                this.showError('一括更新に失敗しました');
            }
        },
        
        async bulkDelete() {
            if (this.selectedDrawings.length === 0) {
                this.showError('ファイルを選択してください');
                return;
            }
            
            const result = await Swal.fire({
                title: `${this.selectedDrawings.length}個のファイルを削除しますか？`,
                text: "この操作は元に戻せません！",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: '削除する',
                cancelButtonText: 'キャンセル'
            });

            if (!result.isConfirmed) {
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('ids', JSON.stringify(this.selectedDrawings));
                
                const response = await axios.post('/api/index.php?model=drawing&method=bulkDelete', formData);
                
                if (response.data && response.data.status === 'success') {
                    this.showSuccess('ファイルを一括削除しました');
                    this.loadDrawings();
                } else {
                    this.showError(response.data?.message || '一括削除に失敗しました');
                }
            } catch (error) {
                console.error('Error bulk deleting:', error);
                this.showError('一括削除に失敗しました');
            }
        },

        // Xóa nhanh đơn giá cho các bản vẽ đã chọn (bulk)
        async bulkClearPrice() {
            if (this.selectedDrawings.length === 0) {
                this.showError('ファイルを選択してください');
                return;
            }
            const count = this.selectedDrawings.length;
            const msg = (this.$t('単価をクリア') || '単価をクリア') + ` (${count}件)`;
            const result = await Swal.fire({
                title: this.$t('単価をクリア') || '単価をクリア',
                html: msg + '<br><br>' + (this.$t('実行しますか？') || '実行しますか？'),
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: this.$t('実行') || '実行',
                cancelButtonText: this.$t('キャンセル') || 'キャンセル'
            });
            if (!result.isConfirmed) return;
            try {
                for (const id of this.selectedDrawings) {
                    const res = await this.updatePriceById(id, '', true);
                    if (!(res.data && res.data.status === 'success')) {
                        this.showError(res.data?.message || '単価のクリアに失敗しました');
                        this.loadDrawings();
                        return;
                    }
                }
                this.showSuccess(this.$t('単価をクリアしました') || '単価をクリアしました');
                this.clearSelection();
                this.loadDrawings();
            } catch (error) {
                console.error('Error bulk clear price:', error);
                this.showError('単価の一括クリアに失敗しました');
                this.loadDrawings();
            }
        },

        // Bulk copy selected drawing names
        bulkCopyNames() {
            if (this.selectedDrawings.length === 0) {
                this.showError('ファイルを選択してください');
                return;
            }
            
            // Get the names of selected drawings
            const selectedNames = this.drawings
                .filter(drawing => this.selectedDrawings.includes(drawing.id))
                .map(drawing => drawing.name);
            
            // Join names with newlines
            const namesText = selectedNames.join('\n');
            
            // Copy to clipboard
            this.copyToClipboard(namesText);
        },

        // Bulk assign current user to selected drawings
        async bulkAssign() {
            if (!this.canManageDrawingAssignee()) {
                return;
            }
            if (this.selectedDrawings.length === 0) {
                this.showError('ファイルを選択してください');
                return;
            }
            
            this.openAssigneeModal(null, true);
        },

        // Bulk unassign current user from selected drawings
        async bulkUnassign() {
            if (!this.canManageDrawingAssignee()) {
                return;
            }
            if (this.selectedDrawings.length === 0) {
                this.showError('ファイルを選択してください');
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('ids', JSON.stringify(this.selectedDrawings));
                
                const response = await axios.post('/api/index.php?model=drawing&method=bulkUnassignUser', formData);
                
                if (response.data && response.data.status === 'success') {
                    this.showSuccess(response.data.message || '一括割り当て解除が完了しました');
                    this.loadDrawings();
                } else {
                    this.showError(response.data?.message || '一括割り当て解除に失敗しました');
                }
            } catch (error) {
                console.error('Error bulk unassigning:', error);
                this.showError('一括割り当て解除に失敗しました');
            }
        },

        // Clear all selections
        clearSelection() {
            this.selectedDrawings = [];
        },

        // Assign current user to drawing
        openAssigneeModal(drawingId = null, isBulk = false) {
            this.assigneeModal.drawingId = drawingId;
            this.assigneeModal.isBulk = isBulk;
            this.assigneeModal.selected = [];
            
            // If single assignment, pre-select current assignee if exists
            if (!isBulk && drawingId) {
                const drawing = this.drawings.find(d => d.id === drawingId);
                if (drawing && drawing.created_by) {
                    const assignees = drawing.created_by.split(',').map(id => id.trim()).filter(id => id);
                    this.assigneeModal.selected = assignees;
                }
            }
            
            this.assigneeModal.show = true;
        },
        
        // Get userid from member object
        getMemberUserid(member) {
            return member.userid || member.user_id || '';
        },
        
        closeAssigneeModal() {
            this.assigneeModal.show = false;
            this.assigneeModal.drawingId = null;
            this.assigneeModal.isBulk = false;
            this.assigneeModal.selected = [];
        },
        
        toggleAssignee(userId) {
            // Only allow one user selection - replace previous selection
            if (this.assigneeModal.selected.includes(userId)) {
                // If clicking the same user, deselect
                this.assigneeModal.selected = [];
            } else {
                // Select only this user
                this.assigneeModal.selected = [userId];
            }
        },
        
        async confirmAssigneeModal() {
            if (!this.canManageDrawingAssignee()) {
                this.closeAssigneeModal();
                return;
            }
            if (this.assigneeModal.isBulk) {
                // Bulk assignment
                if (this.selectedDrawings.length === 0) {
                    this.showError('ファイルを選択してください');
                    this.closeAssigneeModal();
                    return;
                }
                
                if (this.assigneeModal.selected.length === 0) {
                    this.showError('担当者を選択してください');
                    return;
                }
                
                // Only use the first selected user (single selection)
                const selectedUserid = this.assigneeModal.selected[0];
                
                try {
                    const formData = new FormData();
                    formData.append('ids', JSON.stringify(this.selectedDrawings));
                    // Send only the first selected user (single user per drawing)
                    formData.append('user_ids', JSON.stringify([selectedUserid]));
                    
                    const response = await axios.post('/api/index.php?model=drawing&method=bulkAssignUser', formData);
                    
                    if (response.data && response.data.status === 'success') {
                        this.showSuccess(response.data.message || '一括割り当てが完了しました');
                        this.closeAssigneeModal();
                        this.loadDrawings();
                    } else {
                        this.showError(response.data?.message || '一括割り当てに失敗しました');
                    }
                } catch (error) {
                    console.error('Error bulk assigning:', error);
                    this.showError('一括割り当てに失敗しました');
                }
            } else {
                // Single assignment
                if (!this.assigneeModal.drawingId) {
                    this.showError('ファイルIDがありません');
                    this.closeAssigneeModal();
                    return;
                }
                
                if (this.assigneeModal.selected.length === 0) {
                    this.showError('担当者を選択してください');
                    return;
                }
                
                // Only use the first selected user (single selection)
                const selectedUserid = this.assigneeModal.selected[0];
                
                try {
                    const formData = new FormData();
                    formData.append('drawing_id', this.assigneeModal.drawingId);
                    // Send only the first selected user (single user per drawing)
                    formData.append('user_ids', JSON.stringify([selectedUserid]));
                    
                    const response = await axios.post('/api/index.php?model=drawing&method=assignUser', formData);
                    
                    if (response.data && response.data.status === 'success') {
                        this.showSuccess('割り当てが完了しました');
                        this.closeAssigneeModal();
                        this.loadDrawings();
                    } else {
                        this.showError(response.data?.message || '割り当てに失敗しました');
                    }
                } catch (error) {
                    console.error('Error assigning drawing:', error);
                    this.showError('割り当てに失敗しました');
                }
            }
        },
        
        async assignDrawing(drawingId) {
            if (!this.canManageDrawingAssignee()) {
                return;
            }
            this.openAssigneeModal(drawingId, false);
        },

        // Unassign current user from drawing
        async unassignDrawing(drawingId) {
            if (!this.canManageDrawingAssignee()) {
                return;
            }
            try {
                const formData = new FormData();
                formData.append('drawing_id', drawingId);
                
                const response = await axios.post('/api/index.php?model=drawing&method=unassignUser', formData);
                
                if (response.data && response.data.status === 'success') {
                    this.showSuccess('割り当てを解除しました');
                    this.loadDrawings(); // Reload to get updated data
                } else {
                    this.showError(response.data?.message || '割り当て解除に失敗しました');
                }
            } catch (error) {
                console.error('Error unassigning drawing:', error);
                this.showError('割り当て解除に失敗しました');
            }
        },

        // Check if any user is assigned to drawing
        isUserAssigned(drawing) {
            if (!drawing.created_by) return false;
            const userIds = drawing.created_by.split(',').map(id => id.trim()).filter(id => id);
            return userIds.length > 0;
        },
        
        clearFilters() {
            this.searchQuery = '';
            this.statusFilter = '';
        },
        
        sortBy(field) {
            if (this.sortField === field) {
                // Toggle direction if same field
                this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                // Set new field and default to ascending
                this.sortField = field;
                this.sortDirection = 'asc';
            }
        },
        
        getSortIcon(field) {
            if (this.sortField !== field) {
                return 'fa-sort';
            }
            return this.sortDirection === 'asc' ? 'fa-sort-up' : 'fa-sort-down';
        },
        
        getStatusLabel(status) {
            const s = this.drawingStatuses.find(s => s.value === status);
            if (s) return this.$t(s.label);
            const legacyLabels = {
                draft: '未開始',
                review: '確認中',
                revision: '進行中',
                revised: '完了',
                approved: '完了',
                rejected: 'キャンセル'
            };
            if (legacyLabels[status]) return this.$t(legacyLabels[status]);
            return status;
        },
        
        getStatusButtonClass(status) {
            const s = this.drawingStatuses.find(s => s.value === status);
            return `btn-${s?.color || 'secondary'}`;
        },
        getStatusBadgeClass(status) {
            const s = this.drawingStatuses.find(s => s.value === status);
            return 'bg-label-' + (s?.color || 'secondary');
        },
        
        closeAllDropdowns() {
            // Close all open dropdowns
            document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                menu.classList.remove('show');
                const button = menu.previousElementSibling;
                if (button && button.classList.contains('dropdown-toggle')) {
                    button.setAttribute('aria-expanded', 'false');
                }
            });
        },
        
        // Drag selection methods
        startDragSelection(event, index) {
            // Don't start drag on interactive elements
            if (event.target.closest('button, input, .btn-group, .dropdown')) {
                return;
            }
            
            // Don't start drag if clicking on checkbox
            if (event.target.type === 'checkbox') {
                return;
            }
            
            // Don't start drag if clicking in checkbox cell
            if (event.target.closest('td:first-child')) {
                return;
            }
            
            this.isDragging = true;
            this.dragStartIndex = index;
            this.dragEndIndex = index;
            
            // Prevent text selection during drag
            event.preventDefault();
            
            // Add drag selection class to body
            document.body.classList.add('drag-selecting');
        },
        
        updateDragSelection(event, index) {
            if (!this.isDragging) return;
            
            this.dragEndIndex = index;
            this.updateSelectionRange();
        },
        
        endDragSelection() {
            if (!this.isDragging) return;
            
            this.isDragging = false;
            this.dragStartIndex = -1;
            this.dragEndIndex = -1;
            
            // Remove drag selection class from body
            document.body.classList.remove('drag-selecting');
        },
        
        updateSelectionRange() {
            if (this.dragStartIndex === -1 || this.dragEndIndex === -1) return;
            
            const start = Math.min(this.dragStartIndex, this.dragEndIndex);
            const end = Math.max(this.dragStartIndex, this.dragEndIndex);
            
            // Get the filtered drawings in the current view
            const visibleDrawings = this.filteredDrawings;
            
            // Validate indices
            if (start < 0 || end >= visibleDrawings.length) return;
            
            // Clear current selection and select the range
            this.selectedDrawings = [];
            for (let i = start; i <= end; i++) {
                if (visibleDrawings[i] && visibleDrawings[i].id) {
                    this.selectedDrawings.push(visibleDrawings[i].id);
                }
            }
        },
        
        formatDateTime(dateString) {
            if (!dateString) return '-';
            const formatted = formatDrawingDateTimeForDisplay(dateString);
            return formatted || '-';
        },
        formatLastEditor(drawing) {
            if (!drawing) return '-';
            const name = drawing.updated_by_name || '';
            const dateStr = drawing.updated_at ? formatDrawingDateTimeForDisplay(drawing.updated_at) : '';
            if (!name && !dateStr) return '-';
            if (!dateStr) return name;
            return name ? name + ' (' + dateStr + ')' : '(' + dateStr + ')';
        },
        
        downloadDrawing(drawing) {
            if (drawing.file_path) {
                const link = document.createElement('a');
                link.href = this.getDrawingDownloadUrl(drawing);
                link.download = drawing.name;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        },
        
        getDrawingDownloadUrl(drawing) {
            if (drawing.file_path) {
                return `/storage/download.php?file=${encodeURIComponent(drawing.file_path)}`;
            }
            return '#';
        },
        
        getAvatarSrc(member) {
            if (member && member.user_image) {
                return '/assets/upload/avatar/' + member.user_image;
            }
            return '';
        },
        
        handleAvatarError(member) {
            if (member) {
                member.avatarError = true;
            }
        },
        
        getInitials(name) {
            return getAvatarName(name);
        },

        // Copy text to clipboard
        copyToClipboard(text) {
            if (navigator.clipboard && window.isSecureContext) {
                // Use modern clipboard API
                navigator.clipboard.writeText(text).then(() => {
                    this.showSuccess('ファイル名をコピーしました');
                }).catch(err => {
                    console.error('Failed to copy: ', err);
                    this.fallbackCopyToClipboard(text);
                });
            } else {
                // Fallback for older browsers
                this.fallbackCopyToClipboard(text);
            }
        },

        // Fallback copy method for older browsers
        fallbackCopyToClipboard(text) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            textArea.style.top = '-999999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                document.execCommand('copy');
                this.showSuccess('ファイル名をコピーしました');
            } catch (err) {
                console.error('Fallback copy failed: ', err);
                this.showError('コピーに失敗しました');
            }
            
            document.body.removeChild(textArea);
        },
        
        showSuccess(message) {
            showMessage(message);
        },
        
        showError(message) {
            showMessage(message, true);
        },
        
        formatNumber(num) {
            if (!num && num !== 0) return '0';
            const numValue = parseFloat(num);
            if (isNaN(numValue)) return '0';
            return Math.round(numValue).toLocaleString('ja-JP', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            });
        },
        
        resetForm() {
            this.editingDrawing = {
                id: null,
                name: '',
                status: 'todo',
                file_path: '',
                drawing_count: 1,
                task_id: null
            };
        },
        
        filterByStatus(status) {
            this.statusFilter = status;
        }
    }
}).mount('#app'); 