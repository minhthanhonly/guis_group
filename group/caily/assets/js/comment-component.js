// Vue Comment Component - Có thể tái sử dụng cho project, task, etc.
window.CommentComponent = {
    name: 'CommentComponent',
    props: {
        entityType: {
            type: String,
            required: true,
            default: 'project'
        },
        projectId: {
            type: [String, Number],
            required: false
        },
        entityId: {
            type: [String, Number],
            required: true
        },
        currentUser: {
            type: Object,
            default: () => ({
                userid: null,
                realname: 'User',
                user_image: null
            })
        },
        apiEndpoints: {
            type: Object,
            default: () => ({
                getComments: '/api/index.php?model=project&method=getComments',
                addComment: '/api/index.php?model=project&method=addComment'
            })
        },
        showLoadMore: {
            type: Boolean,
            default: true
        }
    },
    template: `
        <div class="comment-component">
            <!-- Comments List -->
            <div class="comments-list" ref="commentsList">
                <!-- Load More Button -->
                <div v-if="hasMoreComments && showLoadMore" class="text-center py-3 mb-3">
                    <button class="btn btn-outline-primary" @click="loadMoreComments" :disabled="loadingOlderComments">
                        <i v-if="loadingOlderComments" class="fa fa-spinner fa-spin me-2"></i>
                        <i v-else class="fa fa-chevron-up me-2"></i>
                        {{ loadingOlderComments ? '読み込み中...' : '過去のコメントを読み込む' }}
                    </button>
                </div>

                <!-- Comment Items -->
                <div v-for="comment in displayedComments" :key="comment.id" class="comment-item" :id="'comment-' + comment.id" @mouseenter="comment.hovered = true" @mouseleave="comment.hovered = false">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="d-flex flex-grow-1 position-relative" style="position: relative; padding-right: 50px;">
                            <div class="flex-shrink-0 me-3">
                                <div class="avatar">
                                    <img v-if="!comment.avatarError" class="rounded-circle" :src="getAvatarSrc(comment)" :alt="comment.user_name" @error="handleAvatarError(comment)">
                                    <span v-else class="avatar-initial rounded-circle">{{ getInitials(comment.user_name) }}</span>
                                </div>
                            </div>
                            <div class="flex-grow-1">
                                <div class="comment-header">
                                    <div class="comment-author text-primary">{{ comment.user_name }}</div>
                                    <small class="comment-timestamp" @click="navigateToComment(comment.id)" style="cursor: pointer;">{{ formatDateTime(comment.created_at) }}</small>
                                </div>
                                <div class="comment-content ql-editor" v-html="renderMentions(comment.content)"></div>
                            </div>
                            <!-- Nút sửa chỉ hiện khi hover và là comment của mình -->
                            <div class="ms-2 d-flex gap-1" style="position: absolute; right: 170px; top: -5px;">
                                <button v-if="comment.user_id == currentUser.userid && comment.hovered" class="btn btn-sm btn-outline-secondary mx-1" @click="startEditComment(comment)"><i class="fa fa-edit"></i> 編集</button>
                                <button v-if="comment.user_id == currentUser.userid && comment.hovered" class="btn btn-sm btn-outline-danger" @click="deleteComment(comment)"><i class="fa fa-trash"></i> 削除</button>
                                <button v-if="comment.hovered" class="btn btn-sm btn-outline-primary" @click="copyCommentContent(comment)"><i class="fa fa-copy"></i> コピー</button>
                            </div>
                            <div class="ms-2" style="position: absolute; right: 10px; top: -6px;">
                                <button 
                                    class="like-button"
                                    :class="{ 'liked': comment.isLiked }"
                                    @click="toggleLike(comment)"
                                    :title="comment.isLiked ? 'いいねを取り消す' : 'いいね'"
                                >
                                    <i class="fa-thumbs-up" :class="comment.isLiked ? 'fa-solid' : 'fa-regular'"></i>
                                    <span 
                                        v-if="comment.like_count > 0" 
                                        class="like-count"
                                        :title="getLikeTooltip(comment)"
                                        data-bs-toggle="tooltip"
                                        data-bs-placement="top"
                                    >
                                        {{ comment.like_count }}
                                    </span>
                                </button>
                            </div>
                        </div>
                        <!-- Like Button -->
                       
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="comments.length === 0 && !loadingComments" class="empty-state">
                    <i class="fa fa-comments fa-3x mb-3"></i>
                    <p>コメントはまだありません</p>
                    <p class="small">最初のコメントを投稿してみましょう</p>
                </div>

                <!-- Loading State -->
                <div v-if="loadingComments" class="text-center py-4">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            
            <!-- Comment Input -->
            <div class="comment-input-section">
                <div class="d-flex">
                    <div class="flex-shrink-0 me-3">
                        <div class="avatar avatar-sm">
                            <img v-if="currentUser.user_image" :src="'/assets/upload/avatar/' + currentUser.user_image" :alt="currentUser.realname" class="rounded-circle">
                            <span v-else class="avatar-initial rounded-circle">{{ getInitials(currentUser.realname || 'User') }}</span>
                        </div>
                    </div>
                    <div class="flex-grow-1">
                        <!-- Mention Input Section -->
                        <div class="mention-input-section mb-3">
                            <div class="position-relative">
                                <div
                                    ref="mentionInput"
                                    class="form-control allow-mention mention-input-editable"
                                    data-mention="true"
                                    data-html-mention="true"
                                    contenteditable="true"
                                    data-placeholder="@でメンションするユーザーを入力..."
                                ></div>
                            </div>
                        </div>
                        
                        <!-- Quill Editor -->
                        <div class="comment-editor-container">
                            <div ref="quillEditor" class="comment-editor"></div>
                        </div>
                        <div class="d-flex justify-content-end mt-3">
                            <template v-if="editingCommentId">
                                <button 
                                    class="btn btn-success me-2"
                                    @click="saveEditComment"
                                    :disabled="!editorHasContent || submittingComment">
                                    <i v-if="submittingComment" class="fa fa-spinner fa-spin me-2"></i>
                                    <i v-else class="fa fa-save me-2"></i>
                                    {{ submittingComment ? '保存中...' : '保存' }}
                                </button>
                                <button 
                                    class="btn btn-secondary"
                                    @click="cancelEditComment"
                                    :disabled="submittingComment">
                                    <i class="fa fa-times me-2"></i> キャンセル
                                </button>
                            </template>
                            <button 
                                v-else
                                class="btn btn-primary"
                                @click="addComment"
                                :disabled="!editorHasContent || submittingComment">
                                <i v-if="submittingComment" class="fa fa-spinner fa-spin me-2"></i>
                                <i v-else class="fa fa-paper-plane me-2"></i>
                                {{ submittingComment ? '送信中...' : 'コメント送信' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `,
    data() {
        return {
            comments: [],
            commentsPage: 1,
            commentsPerPage: 10,
            loadingComments: false,
            loadingOlderComments: false,
            submittingComment: false,
            hasMoreComments: true,
            quillInstance: null,
            maxDisplayComments: 10,
            editorHasContent: false,
            mentionManager: null,
            // Thêm trạng thái sửa comment
            editingCommentId: null,
            editContent: '',
        };
    },
    computed: {
        displayedComments() {
            const sortedComments = [...this.comments].sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
            // Hiển thị tối đa 20 comments mới nhất, nhưng load more sẽ thêm comments cũ hơn vào đầu
            return sortedComments;
        },
        
        // Auto-generate API endpoints based on entity type
        computedApiEndpoints() {
            const baseEndpoints = {
                project: {
                    getComments: '/api/index.php?model=project&method=getComments',
                    addComment: '/api/index.php?model=project&method=addComment',
                    toggleLike: '/api/index.php?model=project&method=toggleLike'
                },
                task: {
                    getComments: '/api/index.php?model=task&method=getComments',
                    addComment: '/api/index.php?model=task&method=addComment',
                    toggleLike: '/api/index.php?model=task&method=toggleLike'
                }
            };
            
            return baseEndpoints[this.entityType] || baseEndpoints.project;
        }
    },
    methods: {
        async loadComments(resetPagination = false) {
            if (resetPagination) {
                this.commentsPage = 1;
                this.hasMoreComments = true;
            }
            
            this.loadingComments = this.commentsPage === 1;
            
            try {
                const params = new URLSearchParams({
                    [`${this.entityType}_id`]: this.entityId,
                    page: this.commentsPage,
                    per_page: this.commentsPerPage
                });
                
                const response = await axios.get(`${this.computedApiEndpoints.getComments}&${params}`);
                const newComments = response.data || [];
                
                // Initialize like status for comments
                const commentsWithLikes = this.initializeLikeStatus(newComments);
                
                if (this.commentsPage === 1) {
                    this.comments = commentsWithLikes;
                    this.$nextTick(() => {
                        // this.scrollToBottom();
                        this.updateTooltips();
                    });
                } else {
                    // Prepend older comments to the beginning
                    this.comments = [...commentsWithLikes, ...this.comments];
                    this.$nextTick(() => {
                        this.updateTooltips();
                    });
                }
                
                this.hasMoreComments = newComments.length === this.commentsPerPage;
                
            } catch (error) {
                console.error('Error loading comments:', error);
                this.$emit('error', { type: 'load', message: 'コメントの読み込みに失敗しました' });
            } finally {
                this.loadingComments = false;
                this.loadingOlderComments = false;
            }
        },
        
        async loadMoreComments() {
            if (this.loadingOlderComments || !this.hasMoreComments) return;
            
            this.loadingOlderComments = true;
            this.showLoadMoreButton = false;
            
            try {
                this.commentsPage++;
                await this.loadComments();
            } catch (error) {
                this.commentsPage--;
            }
        },
        
        async addComment() {
            const commentHtml = this.getCommentText().trim();
            const mentionHtml = this.generateMentionHtml();
            
            // Need either comment content or mentions
            if (!commentHtml && !mentionHtml) return;
            if (this.submittingComment) return;
            
            this.submittingComment = true;
            
            try {
                // Combine mentions HTML at the start of the message
                const finalContent = mentionHtml + commentHtml;
                
                const formData = new FormData();
                formData.append(`${this.entityType}_id`, this.entityId);
                formData.append('content', finalContent);
                formData.append('user_id', this.currentUser.userid);
                
                const response = await axios.post(this.computedApiEndpoints.addComment, formData);
                if(response.data.success){
                   // this.$emit('error', { type: 'info', message: 'コメントを追加しました' });
                    this.clearMentions();
                    await this.loadComments(true);
                    this.scrollToCommentBottom();

                    // Clear Quill editor
                    try {
                        if (this.quillInstance) {
                            this.quillInstance.setContents([]);
                            this.editorHasContent = false;
                        }
                    } catch (error) {
                        //console.error('Error clearing Quill editor:', error);
                    }
                }else{
                    this.$emit('error', { type: 'error', message: 'コメントの追加に失敗しました' });
                }
                
                
                
            } catch (error) {
                console.error('Error adding comment:', error);
                //this.$emit('error', { type: 'add', message: 'コメントの追加に失敗しました' });
            } finally {
                this.submittingComment = false;
            }
        },
        
        scrollToBottom() {
            this.$nextTick(() => {
                setTimeout(() => {
                    window.scrollTo({
                        top: document.body.scrollHeight,
                        behavior: 'smooth'
                    });
                }, 100);
            });
        },
        
        getCommentText() {
            if (this.quillInstance) {
                // Use getSemanticHTML() for better HTML output according to Quill API
                try {
                    return this.quillInstance.getSemanticHTML();
                } catch (error) {
                    // Fallback to root innerHTML if getSemanticHTML is not available
                    return this.quillInstance.root.innerHTML;
                }
            }
            return '';
        },
        
        hasCommentContent() {
            let hasContent = false;
            
            // Check Quill content
            if (this.quillInstance) {
                const text = this.quillInstance.getText().trim();
                hasContent = text.length > 0;
            }
            
            // Check mention input content
            const hasMentions = this.generateMentionHtml().length > 0;
            
            this.editorHasContent = hasContent || hasMentions;
            return this.editorHasContent;
        },
        
        updateEditorContent() {
            if (this.quillInstance) {
                const text = this.quillInstance.getText().trim();
                this.editorHasContent = text.length > 0;
            }
        },
        
        initQuillEditor() {
            if (this.$refs.quillEditor && !this.quillInstance) {
                // Use the same toolbar configuration as project-detail.js
                const toolbarOptions = [
                    [
                        { font: [] },
                        { size: [] }
                    ],
                    ['bold', 'italic', 'underline', 'strike'],
                    [
                        { color: [] },
                        { background: [] }
                    ],
                    [
                        { script: 'super' },
                        { script: 'sub' }
                    ],
                    [
                        { header: '1' },
                        { header: '2' }, 'blockquote' ],
                    [
                        { list: 'ordered' },
                        { indent: '-1' },
                        { indent: '+1' }
                    ],
                    [{ direction: 'rtl' }, { align: [] }],
                    ['link', 'image', 'video', 'formula'],
                    ['clean']
                ];

                this.quillInstance = new Quill(this.$refs.quillEditor, {
                    theme: 'snow',
                    placeholder: 'コメントを入力してください...',
                    modules: {
                        // syntax: true,
                        toolbar: {
                            container: toolbarOptions,
                            handlers: {
                                image: () => this.imageHandler()
                            }
                        }
                    },
                    formats: ['bold', 'italic', 'underline', 'strike', 'color', 'background', 'script', 'header', 'blockquote', 'list', 'indent', 'direction', 'align', 'link', 'image', 'video', 'formula', 'clean', 'font', 'size']
                });
                
                // Add text change listener to update button state
                this.quillInstance.on('text-change', () => {
                    this.updateEditorContent();
                    this.addZoomToDescriptionImages();
                });
                
                // Add blur/focus listeners
                this.quillInstance.on('selection-change', (range) => {
                    if (range) {
                        // Editor focused
                        this.updateEditorContent();
                    }
                });
            }
        },
        
        // Mention functionality using mention.js
        initMentionManager() {
            if (!this.$refs.mentionInput) return;
            
            // Let mention.js handle everything
            this.mentionManager = new MentionManager({
                inputSelector: '[data-mention]',
                apiEndpoint: '/api/index.php?model=user&method=getMentionUsers',
                onMentionSelect: (user, input) => {
                    // mention.js handles insertion
                    // Update button state after a short delay to avoid cursor conflicts
                    setTimeout(() => {
                        this.hasCommentContent();
                    }, 10);
                }
            });
            
            // Add input listener for button state updates
            this.$nextTick(() => {
                if (this.$refs.mentionInput) {
                    this.$refs.mentionInput.addEventListener('input', (event) => {
                        // Only update if not triggered by mention selection
                        if (!event.target.classList.contains('mention-highlight')) {
                            this.hasCommentContent();
                        }
                    });
                }
            });
        },
        
        clearMentions() {
            // Clear the contenteditable input
            if (this.$refs.mentionInput) {
                this.$refs.mentionInput.innerHTML = '';
            }
        },
        
        generateMentionHtml() {
            // Get mentions directly from mention input (handled by mention.js)
            if (this.$refs.mentionInput) {
                const mentionHtml = this.$refs.mentionInput.innerHTML.trim();
                return mentionHtml ? mentionHtml + ' ' : '';
            }
            return '';
        },
        
        destroyQuillEditor() {
            if (this.quillInstance) {
                this.quillInstance = null;
            }
            
            // Clean up mention manager if needed
            if (this.mentionManager && this.mentionManager.destroy) {
                this.mentionManager.destroy();
                this.mentionManager = null;
            }
        },
        
        renderMentions(content) {
            if (!content) return content;
            // Nếu content đã có mention-highlight thì chỉ decode và thêm data-zoom cho img
            let html = content.includes('mention-highlight') ? this.decodeHtmlEntities(content) : this.decodeHtmlEntities(content.replace(/@([^\s<>]+)/g, '<span class="mention-highlight" data-user-name="$1" data-mention="true">@$1</span>'));
            // Thêm data-zoom cho mọi img
            html = html.replace(/<img /g, '<img data-zoom ');
            return html;
        },
        
        decodeHtmlEntities(str) {
            const txt = document.createElement('textarea');
            txt.innerHTML = str;
            return txt.value;
        },
        
        getAvatarSrc(user) {
            return user.user_image ? `/assets/upload/avatar/${user.user_image}` : '';
        },
        
        handleAvatarError(user) {
            user.avatarError = true;
        },
        
        getInitials(name) {
            return getAvatarName(name);
        },
        
        formatDateTime(datetime) {
            if (!datetime) return '-';
            return moment(datetime).format('YYYY/MM/DD HH:mm');
        },
        
        toggleLike(comment) {
            // Toggle like state immediately for better UX
            comment.isLiked = !comment.isLiked;
            
            // Call API to update like status
            this.updateCommentLike(comment);
        },
        
        async updateCommentLike(comment) {
            try {
                const formData = new FormData();
                formData.append('comment_id', comment.id);
                formData.append('user_id', this.currentUser.userid);
                formData.append('action', comment.isLiked ? 'like' : 'unlike');
                formData.append('name', this.currentUser.realname);
                
                // Use the appropriate model API endpoint
                const response = await axios.post(this.computedApiEndpoints.toggleLike, formData);
                
                if (response.data.success) {
                    // Update like count if returned from API
                    if (response.data.like_count !== undefined) {
                        comment.like_count = response.data.like_count;
                    }
                    
                    // Update liked_by_names if the API returns updated data
                    if (response.data.liked_by_names !== undefined) {
                        comment.liked_by_names = response.data.liked_by_names;
                    }
                    
                    // Update tooltips after like status change
                    this.$nextTick(() => {
                        this.updateTooltips();
                    });
                    
                    this.$emit('comment-liked', { comment, isLiked: comment.isLiked });
                } else {
                    // Revert if API call failed
                    comment.isLiked = !comment.isLiked;
                    console.error('Failed to update like status');
                }
            } catch (error) {
                // Revert on error
                comment.isLiked = !comment.isLiked;
                console.error('Error updating like status:', error);
                this.$emit('error', { type: 'like', message: 'いいねの更新に失敗しました' });
            }
        },
        
        // Initialize like status for comments
        initializeLikeStatus(comments) {
            return comments.map(comment => ({
                ...comment,
                isLiked: comment.liked_by && comment.liked_by.includes(this.currentUser.userid),
                like_count: comment.like_count || 0
            }));
        },
        
        getLikeTooltip(comment) {
            if (!comment.liked_by_names || comment.liked_by_names.length === 0) {
                return '';
            }
            
            const names = comment.liked_by_names;
            return names.join(', ');
        },
        
        initTooltips() {
            // Initialize Bootstrap tooltips for like count badges
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl, {
                    trigger: 'hover',
                    html: true
                });
            });
        },
        
        updateTooltips() {
            // Update tooltips after comments are loaded or updated
            this.$nextTick(() => {
                // Update existing tooltips or create new ones
                const tooltipElements = document.querySelectorAll('[data-bs-toggle="tooltip"]');
                tooltipElements.forEach(el => {
                    const tooltip = bootstrap.Tooltip.getInstance(el);
                    if (tooltip) {
                        // Update the tooltip content
                        const newTitle = el.getAttribute('title') || el.getAttribute('data-bs-original-title') || '';
                        tooltip.setContent({ '.tooltip-inner': newTitle });
                    } else {
                        // Create new tooltip if it doesn't exist
                        new bootstrap.Tooltip(el, {
                            trigger: 'hover',
                            html: true
                        });
                    }
                });
            });
        },
        
        // Image handler for Quill editor
        imageHandler() {
            const input = document.createElement('input');
            input.setAttribute('type', 'file');
            input.setAttribute('accept', 'image/*');
            input.click();
            
            input.onchange = async () => {
                const file = input.files[0];
                if (file) {
                    try {
                        // Check file size (max 5MB)
                        if (file.size > 5 * 1024 * 1024) {
                            this.$emit('error', { type: 'image', message: 'ファイルサイズは5MB以下にしてください。' });
                            return;
                        }
                        
                        // Upload image
                        const uploadUrl = '/api/quill-image-upload.php';
                        let response;
                        
                        // Use entity type and ID for upload context
                        const uploadData = {
                            [`${this.entityType}_id`]: this.entityId
                        };
                        if(this.entityType != 'project'){
                            uploadData.project_id = this.projectId;
                        }
                        
                        if (window.swManager && window.swManager.swRegistration) {
                            // Use Service Worker with entity context
                            response = await window.swManager.uploadFile(file, uploadUrl, uploadData);
                        } else {
                            // Fallback to regular upload
                            const formData = new FormData();
                            formData.append('image', file);
                            formData.append(`${this.entityType}_id`, this.entityId);
                            const uploadResponse = await axios.post(uploadUrl, formData, {
                                headers: {
                                    'Content-Type': 'multipart/form-data'
                                }
                            });
                            response = uploadResponse.data;
                        }
                        
                        if (response.success) {
                            // Insert image at current cursor position
                            requestAnimationFrame(() => {
                                try {
                                    if (this.quillInstance && this.quillInstance.root) {
                                        // Lấy độ dài hiện tại của nội dung
                                        const length = this.quillInstance.getLength();
                                        
                                        // Chèn ảnh ở cuối
                                        this.quillInstance.insertEmbed(length - 1, 'image', response.url);
                                        this.quillInstance.insertText(length, '\n');
                                        
                                        // Focus vào editor
                                        this.quillInstance.focus();
                                        
                                        // Scroll xuống cuối
                                        if (this.quillInstance.scrollingContainer) {
                                            this.quillInstance.scrollingContainer.scrollTop = this.quillInstance.scrollingContainer.scrollHeight;
                                        }
                                    }
                                } catch (error) {
                                    console.error('Error inserting image:', error);
                                    // Fallback: append trực tiếp vào HTML
                                    if (this.quillInstance && this.quillInstance.root) {
                                        const imageHtml = `<p><img src="${response.url}" alt="Uploaded image" style="max-width: 100%; height: auto;"></p>`;
                                        this.quillInstance.root.innerHTML += imageHtml;
                                    }
                                }
                            });
                        } else {
                            this.$emit('error', { type: 'image', message: '画像のアップロードに失敗しました: ' + (response.error || 'Unknown error') });
                        }
                    } catch (error) {
                        console.error('Error uploading image:', error);
                        this.$emit('error', { type: 'image', message: '画像のアップロードに失敗しました。' + error});
                    }
                }
            };
        },
        
        // Navigate to specific comment and update URL
        navigateToComment(commentId) {
            // Update URL with comment hash
            const newUrl = `${window.location.pathname}${window.location.search}#comment-${commentId}`;
            window.history.pushState({ commentId }, '', newUrl);
            
            // Scroll to comment
            this.scrollToComment(commentId);
        },
        
        // Scroll to specific comment
        scrollToComment(commentId) {
            this.$nextTick(() => {
                const commentElement = document.getElementById(`comment-${commentId}`);
                if (commentElement) {
                    // First scroll to the comment component container
                    const commentComponent = this.$el;
                    if (commentComponent) {
                        commentComponent.scrollIntoView({ 
                            behavior: 'smooth', 
                            block: 'start' 
                        });
                    }
                    
                    // Then scroll to the specific comment with a small delay
                    setTimeout(() => {
                        commentElement.scrollIntoView({ 
                            behavior: 'smooth', 
                            block: 'center' 
                        });
                        
                        // Add highlight effect
                        commentElement.classList.add('comment-highlight');
                        setTimeout(() => {
                            commentElement.classList.remove('comment-highlight');
                        }, 2000);
                    }, 300);
                } else {
                    console.warn(`Comment element with ID comment-${commentId} not found`);
                }
            });
        },
        
        // Load specific comment by ID (loads older comments if needed)
        async loadCommentById(targetCommentId) {
            let found = false;
            let currentPage = 1;
            let allLoadedComments = [];
            
            while (!found) {
                try {
                    const params = new URLSearchParams({
                        [`${this.entityType}_id`]: this.entityId,
                        page: currentPage,
                        per_page: this.commentsPerPage
                    });
                    
                    const response = await axios.get(`${this.computedApiEndpoints.getComments}&${params}`);
                    const newComments = response.data || [];
                    
                    if (newComments.length === 0) {
                        // No more comments to load
                        break;
                    }
                    
                    // Check if target comment is in this batch
                    const targetComment = newComments.find(c => c.id == targetCommentId);
                    if (targetComment) {
                        found = true;
                        
                        // Initialize like status for all comments
                        const commentsWithLikes = this.initializeLikeStatus([...allLoadedComments, ...newComments]);
                        
                        // Replace all comments with the complete list
                        this.comments = commentsWithLikes;
                        
                        // Update pagination
                        this.commentsPage = currentPage;
                        this.hasMoreComments = newComments.length === this.commentsPerPage;
                        
                        // Scroll to comment after loading
                        this.$nextTick(() => {
                            this.scrollToComment(targetCommentId);
                        });
                        
                        break;
                    }
                    
                    // Add comments to temporary list and continue loading
                    allLoadedComments = [...allLoadedComments, ...newComments];
                    currentPage++;
                    
                    // Safety check to prevent infinite loop
                    if (currentPage > 100) {
                        console.error('Comment not found after loading 100 pages');
                        break;
                    }
                    
                } catch (error) {
                    console.error('Error loading comment by ID:', error);
                    break;
                }
            }
            
            if (!found) {
                this.$emit('error', { type: 'navigation', message: 'コメントが見つかりませんでした' });
            }
        },
        
        // Check URL hash on component mount
        checkUrlHash() {
            const hash = window.location.hash;
            if (hash && hash.startsWith('#comment-')) {
                const commentId = hash.replace('#comment-', '');
                if (commentId) {
                    // Wait for comments to be loaded first
                    this.waitForCommentsAndScroll(commentId);
                }
            } else {
                // No comment hash, scroll to bottom of comment component
                this.scrollToCommentBottom();
            }
        },
        
        // Wait for comments to be loaded then scroll to specific comment
        async waitForCommentsAndScroll(commentId) {
            // Wait for initial comments to load
            let attempts = 0;
            const maxAttempts = 50; // 5 seconds max wait
            
            while (attempts < maxAttempts) {
                // Check if comments are loaded
                if (this.comments.length > 0) {
                    // Check if target comment exists in loaded comments
                    const commentExists = this.comments.some(c => c.id == commentId);
                    
                    if (commentExists) {
                        // Comment found, scroll to it
                        this.scrollToComment(commentId);
                        return;
                    } else {
                        // Comment not in current batch, try to load it
                        await this.loadCommentById(commentId);
                        return;
                    }
                }
                
                // Wait 100ms before next attempt
                await new Promise(resolve => setTimeout(resolve, 100));
                attempts++;
            }
            
            // If we reach here, comments didn't load in time
            console.warn('Comments did not load in time for hash navigation');
        },
        
        // Scroll to bottom of comment component
        scrollToCommentBottom() {
            this.$nextTick(() => {
                setTimeout(() => {
                    // Thử nhiều selector để tìm đúng vùng scroll trong modal
                    let container = this.$el.querySelector('.comments-list');
                    if (!container) {
                        container = this.$el.querySelector('.modal .comments-list');
                    }
                    if (!container) {
                        container = this.$el.querySelector('.modal .comment-component');
                    }
                    if (!container) {
                        container = this.$el.querySelector('.comment-component');
                    }
                    if (container) {
                        container.scrollTop = container.scrollHeight;
                    } else {
                        // Debug: log nếu không tìm thấy vùng scroll
                        console.warn('scrollToCommentBottom: Không tìm thấy vùng scroll comment');
                    }
                }, 200); // tăng delay lên 200ms
            });
        },
        
        // Handle browser back/forward buttons
        handlePopState(event) {
            if (event.state && event.state.commentId) {
                this.scrollToComment(event.state.commentId);
            } else {
                // Check hash from URL
                this.checkUrlHash();
            }
        },
        startEditComment(comment) {
            this.editingCommentId = comment.id;
            this.editContent = comment.content;
            // Đưa nội dung vào editor
            this.$nextTick(() => {
                if (this.quillInstance) {
                    try {
                        this.quillInstance.root.innerHTML = decodeHtmlEntities(this.editContent);
                        this.editorHasContent = this.quillInstance.getText().trim().length > 0;
                    } catch (e) {}
                }
            });
            // Focus editor
            setTimeout(() => {
                if (this.quillInstance) this.quillInstance.focus();
            }, 100);
        },
        async saveEditComment() {
            if (!this.editingCommentId) return;
            const commentHtml = this.getCommentText().trim();
            const mentionHtml = this.generateMentionHtml();
            const finalContent = mentionHtml + commentHtml;
            if (!finalContent) return;
            this.submittingComment = true;
            try {
                const formData = new FormData();
                formData.append('comment_id', this.editingCommentId);
                formData.append('content', finalContent);
                formData.append('user_id', this.currentUser.userid);
                // Gọi API update comment (dùng endpoint updateComment)
                const updateUrl = this.apiEndpoints.updateComment || `/api/index.php?model=${this.entityType}&method=updateComment`;
                const response = await axios.post(updateUrl, formData);
                if (response.data && response.data.success == '1') {
                    this.$emit('message', { type: 'info', message: 'コメントの編集に成功しました。' });
                    this.editingCommentId = null;
                    this.editContent = '';
                    // Xóa editor
                    try{
                        if (this.quillInstance) this.quillInstance.setContents([]);
                    } catch (e) {}
                    this.editorHasContent = false;
                    await this.loadComments(true);
                    this.scrollToCommentBottom();
                } else {
                    this.$emit('error', { type: 'edit', message: 'コメントの編集に失敗しました。' + response.data.message });
                }
            } catch (error) {
                this.$emit('error', { type: 'edit', message: 'コメントの編集に失敗しました。' + error });
            } finally {
                this.submittingComment = false;
            }
        },
        async deleteComment(comment) {
            if (!comment || comment.user_id != this.currentUser.userid) return;
            // Sử dụng Swal để xác nhận xóa
            const swal = await Swal.fire({
                title: '本当にこのコメントを削除しますか？',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: '削除',
                cancelButtonText: 'キャンセル',
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6'
            });
            if (!swal.isConfirmed) return;
            try {
                const formData = new FormData();
                formData.append('comment_id', comment.id);
                formData.append('user_id', this.currentUser.userid);
                // Gọi API xóa comment
                const deleteUrl = this.apiEndpoints.deleteComment || `/api/index.php?model=${this.entityType}&method=deleteComment`;
                const response = await axios.post(deleteUrl, formData);
                if (response.data && response.data.success) {
                    this.$emit('message', { type: 'info', message: 'コメントの削除に成功しました。' });
                    await this.loadComments(true);
                    this.scrollToCommentBottom();
                } else {
                    this.$emit('error', { type: 'delete', message: 'コメントの削除に失敗しました。' + (response.data && response.data.message ? response.data.message : '') });
                }
            } catch (error) {
                this.$emit('error', { type: 'delete', message: 'コメントの削除に失敗しました。' + error });
            }
        },
        cancelEditComment() {
            this.editingCommentId = null;
            this.editContent = '';
            if (this.quillInstance) this.quillInstance.setContents([]);
            this.editorHasContent = false;
        },
        async copyCommentContent(comment) {
            try {
                // Lấy plain text của phần tử .comment-content
                const commentEl = document.getElementById('comment-' + comment.id)?.querySelector('.comment-content');
                let text = '';
                if (commentEl) {
                    text = commentEl.innerText || commentEl.textContent || '';
                } else {
                    // fallback: lấy text từ content nếu không tìm thấy element
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = comment.content;
                    text = tempDiv.innerText || tempDiv.textContent || '';
                }
                
                // Check if Clipboard API is available
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    await navigator.clipboard.writeText(text);
                } else {
                    // Fallback for browsers without Clipboard API or insecure contexts
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
                        document.body.removeChild(textArea);
                    } catch (err) {
                        document.body.removeChild(textArea);
                        throw new Error('Copy fallback failed');
                    }
                }
                
                Swal.fire({
                    icon: 'success',
                    title: 'コピーしました',
                    text: 'コメント内容がクリップボードにコピーされました',
                    timer: 1200,
                    showConfirmButton: false
                });
            } catch (e) {
                console.error('Error copying comment content:', e);
                Swal.fire({
                    icon: 'error',
                    title: 'コピー失敗',
                    text: 'コピーできませんでした',
                    timer: 1200,
                    showConfirmButton: false
                });
            }
        },

        addZoomToDescriptionImages() {
            this.$nextTick(() => {
                // View mode
                const descEls = document.querySelectorAll('.project-description, #project-description, .ql-editor');
                descEls.forEach(el => {
                    el.querySelectorAll('img:not([data-zoom])').forEach(img => {
                        img.setAttribute('data-zoom', '');
                    });
                });
            });
        },
    },
    
    async mounted() {
        await this.loadComments();
        
        this.$nextTick(() => {
            this.initQuillEditor();
            this.initMentionManager();
            // Force initial check of content
            setTimeout(() => {
                this.updateEditorContent();
            }, 100);
            
            // Initialize Bootstrap tooltips
            this.initTooltips();
            
            // Check URL hash after everything is initialized
            this.checkUrlHash();
            this.addZoomToDescriptionImages();
            let inited = false;

            if (this.entityType === 'project' && this.entityId && window.notificationManager && !inited) {
                inited = true;
                window.notificationManager.listenProjectCommentRealtime(this.entityId, () => {
                    this.loadComments();
                    this.scrollToCommentBottom();
                });
            }

            if (this.entityType === 'task' && this.entityId && window.notificationManager && !inited) {
                inited = true;
                window.notificationManager.listenTaskCommentRealtime(this.entityId, () => {
                    this.loadComments();
                    this.scrollToCommentBottom();
                });
            }

            document.addEventListener('notificationManagerReady', () => {
                if (this.entityType === 'project' && this.entityId && window.notificationManager && !inited) {
                    inited = true;
                    window.notificationManager.listenProjectCommentRealtime(this.entityId, () => {
                        this.loadComments();
                        this.scrollToCommentBottom();
                    });
                }
                if (this.entityType === 'task' && this.entityId && window.notificationManager && !inited) {
                    inited = true;
                    window.notificationManager.listenTaskCommentRealtime(this.entityId, () => {
                        this.loadComments();
                        this.scrollToCommentBottom();
                    });
                }
            });
        });
        
        // Lắng nghe realtime comment nếu là project
        
      
        // Handle browser back/forward buttons
        window.addEventListener('popstate', this.handlePopState);
    },
    
    beforeUnmount() {
        this.destroyQuillEditor();
        
        // Remove event listener for browser back/forward buttons
        window.removeEventListener('popstate', this.handlePopState);
    },
    watch: {
        comments() {
            this.addZoomToDescriptionImages();
        }
    }
}; 