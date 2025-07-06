// Vue Comment Component - Có thể tái sử dụng cho project, task, etc.
window.CommentComponent = {
    name: 'CommentComponent',
    props: {
        entityType: {
            type: String,
            required: true,
            default: 'project'
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
            <div class="comments-list mb-4" ref="commentsList">
                <!-- Load More Button -->
                <div v-if="hasMoreComments && showLoadMore" class="text-center py-3 mb-3">
                    <button class="btn btn-outline-primary" @click="loadMoreComments" :disabled="loadingOlderComments">
                        <i v-if="loadingOlderComments" class="fa fa-spinner fa-spin me-2"></i>
                        <i v-else class="fa fa-chevron-up me-2"></i>
                        {{ loadingOlderComments ? '読み込み中...' : '過去のコメントを読み込む' }}
                    </button>
                </div>

                <!-- Comment Items -->
                <div v-for="comment in displayedComments" :key="comment.id" class="comment-item mb-3">
                    <div class="d-flex">
                        <div class="flex-shrink-0 me-3">
                            <div class="avatar avatar-sm">
                                <img v-if="!comment.avatarError" class="rounded-circle" :src="getAvatarSrc(comment)" :alt="comment.user_name" @error="handleAvatarError(comment)">
                                <span v-else class="avatar-initial rounded-circle bg-label-primary">{{ getInitials(comment.user_name) }}</span>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <div class="comment-header d-flex justify-content-between align-items-center">
                                <div class="comment-author fw-semibold">{{ comment.user_name }}</div>
                                <small class="text-muted">{{ formatDateTime(comment.created_at) }}</small>
                            </div>
                            <div class="comment-content mt-2" v-html="renderMentions(comment.content)"></div>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="comments.length === 0 && !loadingComments" class="text-center text-muted py-4">
                    <i class="fa fa-comments fa-2x mb-2"></i>
                    <p>コメントはまだありません</p>
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
                            <span v-else class="avatar-initial rounded-circle bg-label-primary">{{ getInitials(currentUser.realname || 'User') }}</span>
                        </div>
                    </div>
                    <div class="flex-grow-1">
                        <!-- Mention Input Section -->
                        <div class="mention-input-section mb-3">
                            <label class="form-label small text-muted">メンション (オプション)</label>
                            <div class="position-relative">
                                <div
                                    ref="mentionInput"
                                    class="form-control form-control-sm allow-mention mention-input-editable"
                                    data-mention="true"
                                    data-html-mention="true"
                                    contenteditable="true"
                                    data-placeholder="@でメンションするユーザーを入力..."
                                ></div>
                            </div>
                            
                            <!-- Selected Mentions Tags -->
                            <div v-if="selectedMentions.length > 0" class="selected-mentions mt-2">
                                <small class="text-muted d-block mb-1">選択されたメンション:</small>
                                <div class="d-flex flex-wrap gap-1">
                                    <span 
                                        v-for="mention in selectedMentions" 
                                        :key="mention.userid"
                                        class="mention-tag"
                                    >
                                        <div class="avatar avatar-sm me-1">
                                            <img v-if="mention.user_image" :src="'/assets/upload/avatar/' + mention.user_image" :alt="mention.realname" class="rounded-circle">
                                            <span v-else class="avatar-initial rounded-circle bg-label-primary">{{ getInitials(mention.realname) }}</span>
                                        </div>
                                        {{ mention.realname }}
                                        <button 
                                            type="button" 
                                            class="btn-close btn-close-sm ms-1"
                                            @click="removeMention(mention)"
                                            aria-label="Remove mention"
                                        ></button>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Quill Editor -->
                        <div class="comment-editor-container">
                            <div ref="quillEditor" class="comment-editor" style="min-height: 100px;"></div>
                        </div>
                        <div class="d-flex justify-content-end mt-2">
                            <button 
                                class="btn btn-primary btn-sm"
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
            commentsPerPage: 20,
            loadingComments: false,
            loadingOlderComments: false,
            submittingComment: false,
            hasMoreComments: true,
            quillInstance: null,
            maxDisplayComments: 20,
            editorHasContent: false,
            selectedMentions: [],
            mentionManager: null,
        };
    },
    computed: {
        displayedComments() {
            const sortedComments = [...this.comments].sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
            // Hiển thị tối đa 20 comments mới nhất, nhưng load more sẽ thêm comments cũ hơn vào đầu
            return sortedComments;
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
                
                const response = await axios.get(`${this.apiEndpoints.getComments}&${params}`);
                const newComments = response.data || [];
                
                if (this.commentsPage === 1) {
                    this.comments = newComments;
                    this.$nextTick(() => {
                        this.scrollToBottom();
                    });
                } else {
                    // Prepend older comments to the beginning
                    this.comments = [...newComments, ...this.comments];
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
                
                await axios.post(this.apiEndpoints.addComment, formData);
                
                // Clear Quill editor and mentions
                if (this.quillInstance) {
                    this.quillInstance.setContents([]);
                    this.editorHasContent = false;
                }
                this.clearMentions();
                
                await this.loadComments(true);
                this.$emit('comment-added', { content: finalContent });
                
            } catch (error) {
                console.error('Error adding comment:', error);
                this.$emit('error', { type: 'add', message: 'コメントの追加に失敗しました' });
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
                // Simple Quill configuration without mentions
                const modules = {
                    toolbar: [
                        ['bold', 'italic', 'underline'],
                        ['link'],
                        [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                        ['clean']
                    ]
                };

                this.quillInstance = new Quill(this.$refs.quillEditor, {
                    theme: 'snow',
                    placeholder: 'コメントを入力してください...',
                    modules: modules,
                    formats: ['bold', 'italic', 'underline', 'link', 'list']
                });
                
                // Add text change listener to update button state
                this.quillInstance.on('text-change', () => {
                    this.updateEditorContent();
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
                    // mention.js handles insertion, we just track selected mentions
                    this.trackSelectedMention(user);
                    // Update button state
                    this.$nextTick(() => {
                        this.hasCommentContent();
                    });
                }
            });
            
            // Add input listener for button state updates
            this.$nextTick(() => {
                if (this.$refs.mentionInput) {
                    this.$refs.mentionInput.addEventListener('input', () => {
                        this.hasCommentContent();
                    });
                }
            });
        },
        
        trackSelectedMention(user) {
            // Just track for display in tags (mention.js handles the input)
            const userId = user.userid || user.id;
            const alreadySelected = this.selectedMentions.some(m => m.userid === userId);
            
            if (!alreadySelected) {
                this.selectedMentions.push({
                    userid: userId,
                    realname: user.user_name || user.realname,
                    user_image: user.user_image || user.avatar
                });
            }
        },
        
        removeMention(mention) {
            this.selectedMentions = this.selectedMentions.filter(m => m.userid !== mention.userid);
            
            // Also remove from mention input if exists
            if (this.$refs.mentionInput) {
                const mentionSpans = this.$refs.mentionInput.querySelectorAll(`[data-user-id="${mention.userid}"]`);
                mentionSpans.forEach(span => span.remove());
            }
        },
        
        clearMentions() {
            this.selectedMentions = [];
            
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
            
            // If content already contains mention-highlight spans, just decode and return
            if (content.includes('mention-highlight')) {
                return this.decodeHtmlEntities(content);
            }
            
            // Convert plain @mentions to HTML
            const mentionRegex = /@([^\s<>]+)/g;
            const renderedContent = content.replace(mentionRegex, (match, username) => {
                return `<span class="mention-highlight" data-user-name="${username}" data-mention="true">${match}</span>`;
            });
            
            return this.decodeHtmlEntities(renderedContent);
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
            if (!name) return '?';
            const hasJapanese = /[\u3040-\u309f\u30a0-\u30ff\u4e00-\u9faf]/.test(name);
            if (hasJapanese) {
                return name.substring(0, 2);
            } else {
                const initials = name.split(' ').map(n => n.charAt(0)).join('');
                return initials.toUpperCase();
            }
        },
        
        formatDateTime(datetime) {
            if (!datetime) return '-';
            return moment(datetime).format('YYYY/MM/DD HH:mm');
        }
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
        });
    },
    
    beforeUnmount() {
        this.destroyQuillEditor();
    }
}; 