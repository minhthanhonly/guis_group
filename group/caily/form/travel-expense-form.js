export default {
  props: {
    defaultData: { type: Object, default: () => ({}) },
    mode: { type: String, default: 'add' }
  },
  data() {
    return {
      formData: {
        attachment: '',
        attachment_original: '',
        receipts: [],
        note: '',
        approver_user_id: ''
      },
      errors: {},
      modalTitle: this.mode === 'edit' ? '交通費精算書 編集' : '交通費精算書',
      submitting: false,
      uploading: false,
      uploadingReceipts: false,
      uploadProgress: 0,
      approvers: [],
      fileInputRef: 'travelExpenseFileInput',
      receiptsInputRef: 'travelExpenseReceiptsInput'
    };
  },
  created() {
    if (this.defaultData && Object.keys(this.defaultData).length > 0) {
      const raw = Object.assign({
        attachment: '',
        attachment_original: '',
        receipts: [],
        note: '',
        approver_user_id: ''
      }, this.defaultData);
      raw.receipts = Array.isArray(raw.receipts) ? raw.receipts : [];
      this.formData = raw;
    }
  },
  mounted() {
    this.loadApprovers();
  },
  watch: {
    defaultData: {
      handler(newVal) {
        if (newVal && Object.keys(newVal).length > 0) {
          const raw = Object.assign({
            attachment: '',
            attachment_original: '',
            receipts: [],
            note: '',
            approver_user_id: ''
          }, newVal);
          raw.receipts = Array.isArray(raw.receipts) ? raw.receipts : [];
          this.formData = raw;
        }
      },
      immediate: true,
      deep: true
    }
  },
  methods: {
    async loadApprovers() {
      try {
        const res = await axios.get('/api/index.php?model=member&method=list_request_approvers');
        this.approvers = Array.isArray(res.data) ? res.data : [];
      } catch (e) {
        this.approvers = [];
      }
    },
    onFileSelect(event) {
      const file = event.target.files && event.target.files[0];
      if (!file) return;
      if (file.size > 20 * 1024 * 1024) {
        if (typeof showMessage === 'function') showMessage('ファイルサイズは20MB以下にしてください。', true);
        event.target.value = '';
        return;
      }
      this.uploadFile(file);
      event.target.value = '';
    },
    async uploadFile(file) {
      this.uploading = true;
      this.uploadProgress = 0;
      try {
        const formData = new FormData();
        formData.append('file', file);
        const xhr = new XMLHttpRequest();
        const url = '/api/index.php?model=request&method=uploadFormFile';
        const result = await new Promise((resolve, reject) => {
          xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) this.uploadProgress = Math.round((e.loaded / e.total) * 100);
          });
          xhr.addEventListener('load', () => {
            try {
              const res = JSON.parse(xhr.responseText);
              resolve(res);
            } catch (e) {
              resolve({ success: false, error: 'Invalid response' });
            }
          });
          xhr.addEventListener('error', () => reject(new Error('Network error')));
          xhr.open('POST', url);
          xhr.send(formData);
        });
        if (result && result.success) {
          this.formData.attachment = result.filename;
          this.formData.attachment_original = result.original_name || file.name;
          this.validateField('attachment');
        } else {
          if (typeof showMessage === 'function') showMessage(result && result.error ? result.error : 'アップロードに失敗しました。', true);
        }
      } catch (e) {
        if (typeof showMessage === 'function') showMessage('アップロードに失敗しました。', true);
      }
      this.uploading = false;
      this.uploadProgress = 0;
    },
    clearFile() {
      this.formData.attachment = '';
      this.formData.attachment_original = '';
      this.validateField('attachment');
    },
    async onReceiptsSelect(event) {
      const files = event.target.files ? Array.from(event.target.files) : [];
      event.target.value = '';
      if (!files.length) return;
      const maxSize = 20 * 1024 * 1024;
      const toUpload = files.filter(f => {
        if (f.size > maxSize) {
          if (typeof showMessage === 'function') showMessage(`ファイル「${f.name}」は20MB以下にしてください。`, true);
          return false;
        }
        return true;
      });
      if (!toUpload.length) return;
      this.uploadingReceipts = true;
      for (const file of toUpload) {
        await this.uploadReceiptFile(file);
      }
      this.uploadingReceipts = false;
    },
    async uploadReceiptFile(file) {
      try {
        const formData = new FormData();
        formData.append('file', file);
        const xhr = new XMLHttpRequest();
        const url = '/api/index.php?model=request&method=uploadFormFile';
        const result = await new Promise((resolve, reject) => {
          xhr.addEventListener('load', () => {
            try {
              resolve(JSON.parse(xhr.responseText));
            } catch (e) {
              resolve({ success: false, error: 'Invalid response' });
            }
          });
          xhr.addEventListener('error', () => reject(new Error('Network error')));
          xhr.open('POST', url);
          xhr.send(formData);
        });
        if (result && result.success) {
          if (!Array.isArray(this.formData.receipts)) this.formData.receipts = [];
          this.formData.receipts.push({
            filename: result.filename,
            original_name: result.original_name || file.name
          });
        } else {
          if (typeof showMessage === 'function') showMessage(result && result.error ? result.error : 'アップロードに失敗しました。', true);
        }
      } catch (e) {
        if (typeof showMessage === 'function') showMessage('アップロードに失敗しました。', true);
      }
    },
    removeReceipt(index) {
      this.formData.receipts.splice(index, 1);
    },
    receiptDownloadUrl(item) {
      if (!item || !item.filename) return '#';
      const requestId = this.mode === 'edit' && this.defaultData && this.defaultData.id ? this.defaultData.id : '';
      if (!requestId) return '#';
      return 'download.php?file=' + encodeURIComponent(item.filename) + '&request_id=' + encodeURIComponent(requestId);
    },
    validate() {
      this.errors = {};
      let valid = true;
      if (!this.formData.attachment || !String(this.formData.attachment).trim()) {
        this.errors.attachment = '交通費精算書のファイルをアップロードしてください。';
        valid = false;
      }
      if (!this.formData.approver_user_id) {
        this.errors.approver_user_id = '承認者を選択してください。';
        valid = false;
      }
      return valid;
    },
    validateField(field) {
      const err = { ...this.errors };
      if (field === 'attachment') {
        if (!this.formData.attachment || !String(this.formData.attachment).trim()) err.attachment = '交通費精算書のファイルをアップロードしてください。';
        else { delete err.attachment; }
      } else if (field === 'approver_user_id') {
        if (!this.formData.approver_user_id) err.approver_user_id = '承認者を選択してください。';
        else { delete err.approver_user_id; }
      }
      this.errors = err;
    },
    async submit(status = 'pending') {
      if (!this.validate()) return;
      this.submitting = true;
      try {
        const payloadBase = {
          data: this.formData,
          approver_user_id: this.formData.approver_user_id || ''
        };
        if (this.mode === 'edit') {
          const payload = Object.assign({ id: this.defaultData.id }, payloadBase);
          await axios.post('/api/index.php?model=request&method=edit', payload, {
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
          });
          this.$emit('submitted', this.formData);
          this.close();
        } else {
          const payload = Object.assign({ type: 'travel_expense', status }, payloadBase);
          await axios.post('/api/index.php?model=request&method=add', payload, {
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
          });
          if (status === 'draft' && typeof showMessage === 'function') showMessage('下書き保存しました。');
          this.$emit('submitted', this.formData);
          this.close();
        }
      } catch (e) {
        if (typeof showMessage === 'function') showMessage(this.mode === 'edit' ? '編集に失敗しました。' : '申請に失敗しました。', true);
      }
      this.submitting = false;
    },
    close() {
      this.$emit('close');
    },
    downloadUrl() {
      if (!this.formData.attachment) return '#';
      const requestId = this.mode === 'edit' && this.defaultData && this.defaultData.id ? this.defaultData.id : '';
      if (!requestId) return '#';
      return 'download.php?file=' + encodeURIComponent(this.formData.attachment) + '&request_id=' + encodeURIComponent(requestId);
    }
  },
  template: `
    <div>
      <div class="modal-header">
        <h5 class="modal-title">{{ modalTitle }}</h5>
        <button type="button" class="btn-close" @click="close"></button>
      </div>
      <div class="modal-body">
        <form @submit.prevent="submit('pending')">
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">交通費精算書 <span class="text-danger">*</span></label>
            <div class="col-sm-9">
              <div v-if="!formData.attachment" class="d-flex flex-column gap-2">
                <input type="file" class="form-control" :ref="fileInputRef" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" @change="onFileSelect" :disabled="uploading">
                <div v-if="uploading" class="progress" style="height: 6px;">
                  <div class="progress-bar" role="progressbar" :style="{ width: uploadProgress + '%' }"></div>
                </div>
              </div>
              <div v-else class="d-flex align-items-center gap-2 flex-wrap">
                <a v-if="mode==='edit' && defaultData && defaultData.id" :href="downloadUrl()" target="_blank" class="btn btn-sm btn-outline-primary">
                  <i class="fa fa-download me-1"></i>{{ formData.attachment_original || formData.attachment }}
                </a>
                <span v-else class="me-2">{{ formData.attachment_original || formData.attachment }}</span>
                <button type="button" class="btn btn-sm btn-outline-danger" @click="clearFile">削除</button>
              </div>
              <div class="text-danger small mt-1" v-if="errors.attachment">{{ errors.attachment }}</div>
              <small v-if="mode==='add'" class="text-muted">20MB以下。</small>
              <small v-if="mode==='add'" class="text-muted">この<a href="https://kanri.guis.co.jp/storage/view.php?id=616" target="_blank">フォーム</a>をダウンロードして、記載してください。</small>
            </div>
          </div>
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">請求書・領収書等</label>
            <div class="col-sm-9">
              <input type="file" class="form-control mb-2" :ref="receiptsInputRef" accept=".pdf,.jpg,.jpeg,.png,.gif" multiple @change="onReceiptsSelect" :disabled="uploadingReceipts">
              <div v-if="uploadingReceipts" class="text-muted small">アップロード中...</div>
              <ul v-if="formData.receipts && formData.receipts.length" class="list-group list-group-flush mt-2">
                <li v-for="(item, index) in formData.receipts" :key="index" class="list-group-item d-flex align-items-center justify-content-between py-2">
                  <a v-if="mode==='edit' && defaultData && defaultData.id" :href="receiptDownloadUrl(item)" target="_blank" class="btn btn-sm btn-link p-0 text-start text-truncate">
                    <i class="fa fa-download me-1"></i>{{ item.original_name || item.filename }}
                  </a>
                  <span v-else class="text-truncate">{{ item.original_name || item.filename }}</span>
                  <button type="button" class="btn btn-sm btn-outline-danger ms-2" @click="removeReceipt(index)">削除</button>
                </li>
              </ul>
              <small v-if="mode==='add'" class="text-muted">画像・PDF。複数可。各20MB以下。</small>
            </div>
          </div>
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">備考</label>
            <div class="col-sm-9">
              <textarea class="form-control" v-model="formData.note" rows="2"></textarea>
            </div>
          </div>
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">承認者 <span class="text-danger">*</span></label>
            <div class="col-sm-9">
              <select class="form-select" v-model="formData.approver_user_id" @change="validateField('approver_user_id')" @blur="validateField('approver_user_id')">
                <option value="">指定なし</option>
                <option v-for="user in approvers" :key="user.userid" :value="user.userid">
                  {{ user.realname }} ({{ user.userid }})
                </option>
              </select>
              <div class="text-danger small" v-if="errors.approver_user_id">{{ errors.approver_user_id }}</div>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" @click="close">キャンセル</button>
        <button v-if="mode==='add'" type="button" class="btn btn-outline-secondary" :disabled="submitting" @click="submit('draft')">下書き保存</button>
        <button v-if="mode==='add'" type="button" class="btn btn-primary" :disabled="submitting" @click="submit('pending')">申請</button>
        <button v-if="mode==='edit'" type="button" class="btn btn-primary" :disabled="submitting" @click="submit()">保存</button>
      </div>
      <div class="text-muted small" v-if="mode==='add'">
          <ul>
            <li>できる限り領収証の添付をお願いします。税込み3万円以上の運賃の場合は必ず領収証を添付してください。</li>
          </ul>
        </div>
    </div>
  `
};
