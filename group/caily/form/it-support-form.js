export default {
  props: {
    defaultData: { type: Object, default: () => ({}) },
    mode: { type: String, default: 'add' }
  },
  data() {
    return {
      categoryOptions: ['ハードウェア', 'ソフトウェア', 'ネットワーク', 'アカウント/権限', 'その他'],
      priorityOptions: ['高', '中', '低'],
      formData: {
        category: '',
        subject: '',
        description: '',
        priority: '',
        attachments: [],
        note: '',
        approver_user_id: ''
      },
      errors: {},
      modalTitle: this.mode === 'edit' ? 'ITサポート 編集' : 'ITサポート',
      submitting: false,
      uploadingAttachments: false,
      approvers: [],
      originalData: null
    };
  },
  created() {
    if (this.defaultData && Object.keys(this.defaultData).length > 0) {
      const raw = Object.assign({
        category: '',
        subject: '',
        description: '',
        priority: '',
        attachments: [],
        note: '',
        approver_user_id: ''
      }, this.defaultData);
      raw.category = this.categoryOptions.includes(raw.category) ? raw.category : '';
      raw.priority = this.priorityOptions.includes(raw.priority) ? raw.priority : '';
      raw.attachments = Array.isArray(raw.attachments) ? raw.attachments : [];
      this.formData = raw;
      this.originalData = JSON.parse(JSON.stringify(this.formData));
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
            category: '',
            subject: '',
            description: '',
            priority: '',
            attachments: [],
            note: '',
            approver_user_id: ''
          }, newVal);
          raw.category = this.categoryOptions.includes(raw.category) ? raw.category : '';
          raw.priority = this.priorityOptions.includes(raw.priority) ? raw.priority : '';
          raw.attachments = Array.isArray(raw.attachments) ? raw.attachments : [];
          this.formData = raw;
          this.originalData = JSON.parse(JSON.stringify(this.formData));
        }
      },
      immediate: true,
      deep: true
    }
  },
  computed: {
    isDirty() {
      if (this.mode !== 'edit') return true;
      if (!this.originalData) return false;
      return JSON.stringify(this.formData) !== JSON.stringify(this.originalData);
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
    validate() {
      this.errors = {};
      let valid = true;
      if (!this.formData.category || !this.categoryOptions.includes(this.formData.category)) {
        this.errors.category = '区分を選択してください。';
        valid = false;
      }
      if (!this.formData.subject || !String(this.formData.subject).trim()) {
        this.errors.subject = '件名を入力してください。';
        valid = false;
      }
      if (!this.formData.description || !String(this.formData.description).trim()) {
        this.errors.description = '内容・詳細を入力してください。';
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
      if (field === 'category') {
        if (!this.formData.category || !this.categoryOptions.includes(this.formData.category)) err.category = '区分を選択してください。';
        else { delete err.category; }
      } else if (field === 'subject') {
        if (!this.formData.subject || !String(this.formData.subject).trim()) err.subject = '件名を入力してください。';
        else { delete err.subject; }
      } else if (field === 'description') {
        if (!this.formData.description || !String(this.formData.description).trim()) err.description = '内容・詳細を入力してください。';
        else { delete err.description; }
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
          const payload = Object.assign({ type: 'it_support', status }, payloadBase);
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
    async onAttachmentsSelect(event) {
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
      this.uploadingAttachments = true;
      for (const file of toUpload) {
        await this.uploadAttachmentFile(file);
      }
      this.uploadingAttachments = false;
    },
    async uploadAttachmentFile(file) {
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
          if (!Array.isArray(this.formData.attachments)) this.formData.attachments = [];
          this.formData.attachments.push({
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
    removeAttachment(index) {
      this.formData.attachments.splice(index, 1);
    },
    attachmentDownloadUrl(item) {
      if (!item || !item.filename) return '#';
      const requestId = this.mode === 'edit' && this.defaultData && this.defaultData.id ? this.defaultData.id : '';
      if (!requestId) return '#';
      return 'download.php?file=' + encodeURIComponent(item.filename) + '&request_id=' + encodeURIComponent(requestId);
    }
  },
  template: `
    <div class="position-relative">
      <div v-if="submitting" class="position-absolute top-0 start-0 end-0 bottom-0 d-flex align-items-center justify-content-center bg-white bg-opacity-75 rounded" style="z-index: 1050;">
        <div class="text-center">
          <div class="spinner-border text-primary mb-2" role="status" style="width: 2rem; height: 2rem;"></div>
          <div class="text-muted small">保存中...</div>
        </div>
      </div>
      <div class="modal-header">
        <h5 class="modal-title">{{ modalTitle }}</h5>
        <button type="button" class="btn-close" @click="close"></button>
      </div>
      <div class="modal-body">
        <form @submit.prevent="submit('pending')">
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">区分 <span class="text-danger">*</span></label>
            <div class="col-sm-9">
              <select class="form-select" v-model="formData.category" @change="validateField('category')">
                <option value="">選択してください</option>
                <option v-for="opt in categoryOptions" :key="opt" :value="opt">{{ opt }}</option>
              </select>
              <div class="text-danger small" v-if="errors.category">{{ errors.category }}</div>
            </div>
          </div>
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">件名 <span class="text-danger">*</span></label>
            <div class="col-sm-9">
              <input type="text" class="form-control" v-model="formData.subject" maxlength="255" @blur="validateField('subject')">
              <div class="text-danger small" v-if="errors.subject">{{ errors.subject }}</div>
            </div>
          </div>
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">内容・詳細 <span class="text-danger">*</span></label>
            <div class="col-sm-9">
              <textarea class="form-control" v-model="formData.description" rows="4" @blur="validateField('description')" placeholder="不具合や依頼内容を詳しく記入してください"></textarea>
              <div class="text-danger small" v-if="errors.description">{{ errors.description }}</div>
            </div>
          </div>
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">添付資料（画像・書類）</label>
            <div class="col-sm-9">
              <input type="file" class="form-control mb-2" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif" multiple @change="onAttachmentsSelect" :disabled="uploadingAttachments">
              <div v-if="uploadingAttachments" class="text-muted small">アップロード中...</div>
              <ul v-if="formData.attachments && formData.attachments.length" class="list-group list-group-flush mt-2">
                <li v-for="(item, index) in formData.attachments" :key="index" class="list-group-item d-flex align-items-center justify-content-between py-2">
                  <a v-if="mode==='edit' && defaultData && defaultData.id" :href="attachmentDownloadUrl(item)" target="_blank" class="btn btn-sm btn-link p-0 text-start text-truncate">
                    <i class="fa fa-download me-1"></i>{{ item.original_name || item.filename }}
                  </a>
                  <span v-else class="text-truncate">{{ item.original_name || item.filename }}</span>
                  <button type="button" class="btn btn-sm btn-outline-danger ms-2" @click="removeAttachment(index)">削除</button>
                </li>
              </ul>
              <small class="text-muted">原因特定のため画像・書類を添付できます。複数可。各20MB以下。</small>
            </div>
          </div>
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">緊急度</label>
            <div class="col-sm-9">
              <select class="form-select" v-model="formData.priority">
                <option value="">選択してください（任意）</option>
                <option v-for="opt in priorityOptions" :key="opt" :value="opt">{{ opt }}</option>
              </select>
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
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">注記</label>
            <div class="col-sm-9">
              <textarea class="form-control" v-model="formData.note" rows="2"></textarea>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" @click="close">キャンセル</button>
        <button v-if="mode==='add'" type="button" class="btn btn-outline-secondary" :disabled="submitting" @click="submit('draft')">下書き保存</button>
        <button v-if="mode==='add'" type="button" class="btn btn-primary" :disabled="submitting" @click="submit('pending')">申請</button>
        <button v-if="mode==='edit'" type="button" class="btn btn-primary" :disabled="submitting || !isDirty" @click="submit()">保存</button>
      </div>
    </div>
  `
};
