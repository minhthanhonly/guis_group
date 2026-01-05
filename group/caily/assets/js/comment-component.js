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
        },
        enableThreads: {
            type: Boolean,
            default: false
        }
    },
    template: `
        <div class="comment-component">
            <div class="row g-3" v-if="enableThreads">
                <!-- Column 1: Thread List -->
                <div class="col-md-4 col-lg-3">
                    <div class="thread-sidebar">
                        <!-- Thread Header -->
                        <div class="thread-header mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="mb-0">
                                    <i class="fa fa-comments me-2"></i>スレッド
                                </h6>
                                <button class="btn btn-sm btn-primary" @click="showCreateThreadModal = true">
                                    <i class="fa fa-plus me-1"></i>新規
                                </button>
                            </div>
                            
                            <!-- Search Bar -->
                            <div class="input-group mb-2">
                                <input 
                                    type="text" 
                                    class="form-control form-control-sm" 
                                    placeholder="コメントを検索..." 
                                    v-model="searchTerm"
                                    @input="onSearchInput"
                                    @keyup.enter="performSearch"
                                >
                                <button class="btn btn-outline-secondary btn-sm" @click="performSearch" :disabled="searching">
                                    <i v-if="searching" class="fa fa-spinner fa-spin"></i>
                                    <i v-else class="fa fa-search"></i>
                                </button>
                                <button v-if="searchTerm" class="btn btn-outline-secondary btn-sm" @click="clearSearch">
                                    <i class="fa fa-times"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Thread List -->
                        <div class="thread-list" v-if="!searchMode">
                            <div 
                                v-for="thread in threads" 
                                :key="thread.id"
                                class="thread-item p-2 mb-2 border rounded cursor-pointer"
                                :class="{ 
                                    'bg-primary text-white': selectedThreadId === thread.id,
                                    'thread-unread': thread.unread_count > 0 && selectedThreadId !== thread.id
                                }"
                                @click="selectThread(thread.id)"
                            >
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <div class="fw-bold small flex-grow-1">{{ thread.title }}</div>
                                            <span v-if="thread.unread_count > 0 && selectedThreadId !== thread.id" 
                                                  class="badge bg-danger rounded-pill">
                                                {{ thread.unread_count }}
                                            </span>
                                        </div>
                                        
                                        <!-- Last comment preview -->
                                        <div v-if="thread.last_comment_preview" 
                                             class="thread-preview small mb-1"
                                             :class="{ 
                                                 'fw-bold': thread.unread_count > 0 && selectedThreadId !== thread.id,
                                                 'text-white-50': selectedThreadId === thread.id,
                                                 'text-muted': selectedThreadId !== thread.id && thread.unread_count === 0,
                                                 'text-dark': selectedThreadId !== thread.id && thread.unread_count > 0
                                             }">
                                            {{ thread.last_comment_preview }}
                                        </div>
                                        <div v-else class="text-muted small mb-1" :class="{ 'text-white-50': selectedThreadId === thread.id }">
                                            コメントがありません
                                        </div>
                                        
                                        <div class="d-flex align-items-center gap-2">
                                            <small :class="{ 
                                                'text-white-50': selectedThreadId === thread.id, 
                                                'text-muted': selectedThreadId !== thread.id 
                                            }">
                                                {{ thread.last_comment_user_name || thread.creator_name }}
                                            </small>
                                            <small v-if="thread.last_comment_at" 
                                                   :class="{ 
                                                       'text-white-50': selectedThreadId === thread.id, 
                                                       'text-muted': selectedThreadId !== thread.id 
                                                   }">
                                                {{ formatShortDateTime(thread.last_comment_at) }}
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-if="threads.length === 0 && !loadingThreads" class="text-center text-muted py-3">
                                <p class="small mb-2">スレッドがありません</p>
                                <button class="btn btn-sm btn-outline-primary" @click="showCreateThreadModal = true">
                                    最初のスレッドを作成
                                </button>
                            </div>
                            <div v-if="loadingThreads" class="text-center py-2">
                                <div class="spinner-border spinner-border-sm" role="status"></div>
                            </div>
                        </div>
                        
                        <!-- Search Results -->
                        <div v-if="searchMode && searchResults.length > 0" class="search-results">
                            <div class="alert alert-info alert-sm mb-2">
                                <i class="fa fa-search me-2"></i>{{ searchResults.length }}件
                            </div>
                            <div 
                                v-for="result in searchResults" 
                                :key="'search-' + result.id"
                                class="search-result-item p-2 mb-2 border rounded cursor-pointer"
                                @click="navigateToSearchResult(result)"
                            >
                                <div class="d-flex align-items-start">
                                    <div class="flex-grow-1">
                                        <div class="fw-bold text-primary small mb-1" v-if="result.thread_title">
                                            <i class="fa fa-comments me-1"></i>{{ result.thread_title }}
                                        </div>
                                        <div class="comment-preview small" v-html="highlightSearchTerm(result.content)"></div>
                                        <small class="text-muted d-block">
                                            {{ result.user_name }} - {{ formatShortDateTime(result.created_at) }}
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-if="searchMode && searchResults.length === 0 && !searching" class="text-center text-muted py-3">
                            <p class="small">検索結果が見つかりませんでした</p>
                        </div>
                    </div>
                </div>
                
                <!-- Column 2: Comments Content -->
                <div class="col-md-8 col-lg-9">
                    <div class="comments-content">
                        <!-- Current Thread Title -->
                        <div v-if="selectedThread && !searchMode" class="current-thread-title mb-3 p-2 bg-light rounded">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0">{{ selectedThread.title }}</h6>
                                    <small class="text-muted">{{ selectedThread.comment_count || 0 }} コメント</small>
                                </div>
                                <button class="btn btn-sm btn-outline-secondary" @click="selectThread(null)">
                                    <i class="fa fa-times"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Empty State when no thread selected -->
                        <div v-if="!selectedThread && !searchMode && threads.length > 0" class="empty-thread-state text-center py-5">
                            <i class="fa fa-comments fa-3x mb-3 text-muted"></i>
                            <p class="text-muted">スレッドを選択してください</p>
                        </div>
                        
                        <!-- Comments List -->
                        <div class="comments-list" ref="commentsList" v-if="(selectedThread || !enableThreads) && !searchMode">
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
            <div class="comment-input-section" v-if="(selectedThread || !enableThreads) && !searchMode">
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
            </div>
            
            <!-- Non-threaded layout (when threads disabled) -->
            <div v-if="!enableThreads">
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
            
            <!-- Create Thread Modal -->
            <div v-if="showCreateThreadModal" class="modal fade show" style="display: block;" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">新規スレッドを作成</h5>
                            <button type="button" class="btn-close" @click="showCreateThreadModal = false"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">スレッドタイトル <span class="text-danger">*</span></label>
                                <input 
                                    type="text" 
                                    class="form-control" 
                                    v-model="newThreadTitle"
                                    placeholder="スレッドのタイトルを入力..."
                                    @keyup.enter="createThread"
                                    ref="threadTitleInput"
                                >
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" @click="showCreateThreadModal = false">キャンセル</button>
                            <button type="button" class="btn btn-primary" @click="createThread" :disabled="!newThreadTitle.trim()">
                                作成
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div v-if="showCreateThreadModal" class="modal-backdrop fade show"></div>
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
            initializingQuill: false, // Flag to prevent multiple initializations
            maxDisplayComments: 10,
            editorHasContent: false,
            mentionManager: null,
            initializingMention: false,
            navigatingToComment: false, // Flag to prevent auto-scroll when navigating to specific comment
            originalPageTitle: '', // Store original page title
            newCommentCount: 0, // Track new comment count for title
            // Thêm trạng thái sửa comment
            editingCommentId: null,
            editContent: '',
            // Thread management
            threads: [],
            selectedThreadId: null,
            selectedThread: null,
            loadingThreads: false,
            showCreateThreadModal: false,
            newThreadTitle: '',
            // Search
            searchTerm: '',
            searchMode: false,
            searchResults: [],
            searching: false,
            // Polling for realtime updates (without Firebase)
            pollingInterval: null,
            lastCommentId: null,
            lastCheckTime: null,
            pollingEnabled: true,
            pollingIntervalMs: 3000, // Check every 3 seconds
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
                
                // Add thread_id if thread is selected
                if (this.enableThreads && this.selectedThreadId) {
                    params.append('thread_id', this.selectedThreadId);
                }
                
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
                
                // Mark thread as read after loading comments (only on first page load)
                if (this.commentsPage === 1 && this.enableThreads && this.selectedThreadId) {
                    // Get the latest comment ID - comments are sorted DESC, so first is latest
                    // If no comments, mark thread as read with 0 or use thread's last_comment_id
                    let lastCommentId = 0;
                    if (this.comments.length > 0) {
                        const latestComment = this.comments[0];
                        if (latestComment && latestComment.id) {
                            lastCommentId = latestComment.id;
                        }
                    } else if (this.selectedThread && this.selectedThread.last_comment_id) {
                        // No comments loaded, but thread has last_comment_id
                        lastCommentId = this.selectedThread.last_comment_id;
                    }
                    
                    if (lastCommentId > 0) {
                        await this.markThreadAsRead(this.selectedThreadId, lastCommentId);
                    }
                }
                
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
            
            // If threads enabled, require thread selection (but allow if no threads exist yet)
            if (this.enableThreads && this.threads.length > 0 && !this.selectedThreadId) {
                this.$emit('error', { type: 'thread', message: 'スレッドを選択してください' });
                return;
            }
            
            this.submittingComment = true;
            
            try {
                // Combine mentions HTML at the start of the message
                const finalContent = mentionHtml + commentHtml;
                
                const formData = new FormData();
                formData.append(`${this.entityType}_id`, this.entityId);
                formData.append('content', finalContent);
                formData.append('user_id', this.currentUser.userid);
                
                // Add thread_id if thread is selected
                if (this.enableThreads && this.selectedThreadId) {
                    formData.append('thread_id', this.selectedThreadId);
                }
                
                const response = await axios.post(this.computedApiEndpoints.addComment, formData);
                if(response.data && response.data.success){
                   // this.$emit('error', { type: 'info', message: 'コメントを追加しました' });
                    this.clearMentions();
                    
                    // Update last comment ID for polling
                    if (this.enableThreads && response.data.comment_id) {
                        this.lastCommentId = response.data.comment_id;
                        this.lastCheckTime = Date.now();
                    }
                    
                    await this.loadComments(true);
                    this.scrollToCommentBottom();
                    
                    // Reload thread list to update counts
                    if (this.enableThreads) {
                        await this.loadThreads();
                    }

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
            // If editor already exists or is being initialized, don't reinitialize
            if (this.quillInstance || this.initializingQuill) {
                return;
            }
            
            // Check if element exists and is ready for initialization
            if (this.$refs.quillEditor) {
                // Check parent container for any existing Quill structure
                const parentContainer = this.$refs.quillEditor.closest('.comment-editor-container');
                
                // Check if Quill is already initialized - look in both element and parent
                const hasQuillToolbar = parentContainer && parentContainer.querySelector('.ql-toolbar');
                const hasQuillContainer = this.$refs.quillEditor.classList.contains('ql-container') || 
                                         this.$refs.quillEditor.querySelector('.ql-container') ||
                                         this.$refs.quillEditor.querySelector('.ql-editor');
                
                if (hasQuillToolbar || hasQuillContainer) {
                    // Quill already initialized, don't reinitialize
                    console.warn('Quill editor already initialized, skipping');
                    return;
                }
                
                // Clean up any leftover Quill elements in parent container
                if (parentContainer) {
                    const leftoverQuill = parentContainer.querySelectorAll('.ql-toolbar, .ql-container, .ql-editor, .ql-snow');
                    leftoverQuill.forEach(el => el.remove());
                }
                
                // Ensure element is clean and ready
                this.$refs.quillEditor.innerHTML = '';
                this.$refs.quillEditor.className = 'comment-editor';
                
                // Remove any Quill-related attributes
                this.$refs.quillEditor.removeAttribute('data-placeholder');
                this.$refs.quillEditor.removeAttribute('data-theme');
                
                this.initializingQuill = true;
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

                try {
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
                } catch (error) {
                    console.error('Error initializing Quill editor:', error);
                } finally {
                    this.initializingQuill = false;
                }
            } else {
                this.initializingQuill = false;
            }
        },
        
        // Mention functionality using mention.js
        initMentionManager() {
            // Prevent multiple initializations
            if (this.initializingMention || this.mentionManager) {
                return;
            }
            
            if (!this.$refs.mentionInput) return;
            
            this.initializingMention = true;
            
            try {
                // Destroy existing mention manager if any
                if (this.mentionManager && this.mentionManager.destroy) {
                    this.mentionManager.destroy();
                    this.mentionManager = null;
                }
                
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
            } catch (error) {
                console.error('Error initializing mention manager:', error);
            } finally {
                this.initializingMention = false;
            }
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
            // Set flag to prevent new initialization during destroy
            this.initializingQuill = true;
            
            if (this.quillInstance) {
                // Get the editor element before destroying instance
                const editorElement = this.$refs.quillEditor;
                
                // Clear the Quill instance first
                this.quillInstance = null;
                
                if (editorElement) {
                    // Find parent container (comment-editor-container)
                    const parentContainer = editorElement.closest('.comment-editor-container');
                    
                    if (parentContainer) {
                        // IMPORTANT: Quill transforms the element structure
                        // The original element becomes ql-container, and Quill adds:
                        // - .ql-toolbar (before the container)
                        // - .ql-container (the transformed original element)
                        // - .ql-editor (inside the container)
                        
                        // Remove toolbar (it's a sibling, not inside our element)
                        const toolbar = parentContainer.querySelector('.ql-toolbar');
                        if (toolbar && toolbar.parentNode === parentContainer) {
                            toolbar.remove();
                        }
                        
                        // Check if our element was transformed into ql-container
                        if (editorElement.classList.contains('ql-container')) {
                            // Quill transformed our element - we need to restore it
                            // Get all children (ql-editor, etc.) and remove them
                            while (editorElement.firstChild) {
                                editorElement.removeChild(editorElement.firstChild);
                            }
                            // Restore original class
                            editorElement.className = 'comment-editor';
                        } else {
                            // Element wasn't transformed, just clear it
                            editorElement.innerHTML = '';
                            editorElement.className = 'comment-editor';
                        }
                        
                        // Remove any Quill-related attributes
                        editorElement.removeAttribute('data-placeholder');
                        editorElement.removeAttribute('data-theme');
                        editorElement.removeAttribute('role');
                        
                        // Remove any Quill classes that might be on parent
                        parentContainer.classList.remove('ql-snow', 'ql-toolbar');
                    } else {
                        // Fallback: just clear the element
                        editorElement.innerHTML = '';
                        editorElement.className = 'comment-editor';
                    }
                    
                    // Force a reflow to ensure DOM is clean
                    editorElement.offsetHeight;
                }
            }
            
            // Clean up mention manager if needed
            if (this.mentionManager && this.mentionManager.destroy) {
                try {
                    this.mentionManager.destroy();
                } catch (error) {
                    console.error('Error destroying mention manager:', error);
                }
                this.mentionManager = null;
            }
            this.initializingMention = false;
            
            // Reset flag after a short delay to ensure cleanup is complete
            setTimeout(() => {
                this.initializingQuill = false;
            }, 150);
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
            // Set flag to prevent auto-scroll
            this.navigatingToComment = true;
            
            // First, try to get comment info to find its thread_id
            if (this.enableThreads && this.entityType === 'project') {
                try {
                    const params = new URLSearchParams({
                        comment_id: targetCommentId
                    });
                    const response = await axios.get(`/api/index.php?model=project&method=getCommentInfo&${params}`);
                    
                    if (response.data && response.data.success && response.data.comment) {
                        const comment = response.data.comment;
                        
                        // If comment belongs to a thread, select that thread first (skip auto-scroll)
                        if (comment.thread_id && comment.thread_id !== this.selectedThreadId) {
                            await this.selectThread(comment.thread_id, true);
                            // Wait a bit for thread to load
                            await new Promise(resolve => setTimeout(resolve, 300));
                        }
                    }
                } catch (error) {
                    console.error('Error getting comment info:', error);
                }
            }
            
            // Now search for the comment in the current thread or all comments
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
                    
                    // Add thread_id if thread is selected
                    if (this.enableThreads && this.selectedThreadId) {
                        params.append('thread_id', this.selectedThreadId);
                    }
                    
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
                        await this.$nextTick();
                        setTimeout(() => {
                            this.scrollToComment(targetCommentId);
                        }, 200);
                        
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
            } else {
                // Reset flag after navigation is complete
                setTimeout(() => {
                    this.navigatingToComment = false;
                }, 1000);
            }
        },
        
        // Check URL hash on component mount
        async checkUrlHash() {
            const hash = window.location.hash;
            if (hash && hash.startsWith('#comment-')) {
                const commentId = hash.replace('#comment-', '');
                if (commentId) {
                    // If threads are enabled, we need to find the thread containing this comment
                    if (this.enableThreads && this.entityType === 'project') {
                        await this.loadCommentAndThread(commentId);
                    } else {
                        // Wait for comments to be loaded first
                        this.waitForCommentsAndScroll(commentId);
                    }
                }
            } else {
                // No comment hash, scroll to bottom of comment component
                this.scrollToCommentBottom();
            }
        },
        
        // Load comment info and navigate to its thread
        async loadCommentAndThread(commentId) {
            try {
                // Set flag to prevent auto-scroll
                this.navigatingToComment = true;
                
                // Get comment info including thread_id
                const params = new URLSearchParams({
                    comment_id: commentId
                });
                const response = await axios.get(`/api/index.php?model=project&method=getCommentInfo&${params}`);
                
                if (response.data && response.data.success && response.data.comment) {
                    const comment = response.data.comment;
                    
                    // If comment has a thread_id, select that thread
                    if (comment.thread_id) {
                        // Load threads first if not loaded
                        if (this.threads.length === 0) {
                            await this.loadThreads();
                        }
                        
                        // Select the thread containing this comment (skip auto-scroll)
                        await this.selectThread(comment.thread_id, true);
                        
                        // Wait for comments to load, then scroll to the comment
                        await this.waitForCommentsAndScroll(commentId);
                    } else {
                        // Comment doesn't belong to a thread, just load it normally
                        await this.waitForCommentsAndScroll(commentId);
                    }
                } else {
                    console.warn('Comment not found:', commentId);
                    this.$emit('error', { type: 'navigation', message: 'コメントが見つかりませんでした' });
                }
            } catch (error) {
                console.error('Error loading comment info:', error);
                this.$emit('error', { type: 'navigation', message: 'コメントの読み込みに失敗しました' });
            } finally {
                // Reset flag after navigation is complete
                setTimeout(() => {
                    this.navigatingToComment = false;
                }, 1000);
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
        
        // Scroll to bottom of comment component (or top if comments are sorted DESC)
        scrollToCommentBottom() {
            this.$nextTick(() => {
                setTimeout(() => {
                    // Thử nhiều selector để tìm đúng vùng scroll
                    // Ưu tiên tìm trong comments-content (layout 2 cột)
                    let container = this.$el.querySelector('.comments-content .comments-list');
                    if (!container) {
                        container = this.$el.querySelector('.comments-list');
                    }
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
                        // Scroll to bottom (last position) to show the oldest comments
                        // This will scroll to the end of the list
                        container.scrollTop = container.scrollHeight;
                        
                        // Force a reflow to ensure scroll happens
                        container.offsetHeight;
                        
                        // Double check and scroll again after a brief moment to ensure we're at the bottom
                        setTimeout(() => {
                            if (container) {
                                const newScrollHeight = container.scrollHeight;
                                if (container.scrollTop < newScrollHeight - 10) {
                                    container.scrollTop = newScrollHeight;
                                }
                            }
                        }, 100);
                    } else {
                        // Debug: log nếu không tìm thấy vùng scroll
                        console.warn('scrollToCommentBottom: Không tìm thấy vùng scroll comment');
                    }
                }, 100); // Increase delay to ensure DOM is fully rendered
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
        
        // Thread management methods
        async loadThreads() {
            if (!this.enableThreads || this.entityType !== 'project') return;
            
            this.loadingThreads = true;
            try {
                const params = new URLSearchParams({
                    project_id: this.entityId
                });
                const response = await axios.get(`/api/index.php?model=project&method=getThreads&${params}`);
                this.threads = response.data || [];
                
                // Update selected thread info if it exists
                if (this.selectedThreadId) {
                    this.selectedThread = this.threads.find(t => t.id === this.selectedThreadId) || null;
                }
            } catch (error) {
                console.error('Error loading threads:', error);
            } finally {
                this.loadingThreads = false;
            }
        },
        
        async selectThread(threadId, skipAutoScroll = false) {
            this.selectedThreadId = threadId;
            this.selectedThread = threadId ? this.threads.find(t => t.id === threadId) : null;
            this.searchMode = false;
            this.searchTerm = '';
            this.searchResults = [];
            
            // Reset new comment count and title when selecting a thread
            this.newCommentCount = 0;
            this.updatePageTitle(0);
            
            // Destroy existing editor before loading comments
            if (this.quillInstance) {
                this.destroyQuillEditor();
                // Wait for destroy to complete
                await new Promise(resolve => setTimeout(resolve, 200));
            }
            
            await this.loadComments(true);
            
            // Mark thread as read immediately after loading comments
            // This ensures it's marked even if there are no comments
            if (this.enableThreads && threadId && this.selectedThread) {
                let lastCommentId = 0;
                if (this.comments.length > 0) {
                    // Comments are sorted DESC, so first is latest
                    const latestComment = this.comments[0];
                    if (latestComment && latestComment.id) {
                        lastCommentId = latestComment.id;
                    }
                } else if (this.selectedThread.last_comment_id) {
                    // No comments loaded, but thread has last_comment_id
                    lastCommentId = this.selectedThread.last_comment_id;
                }
                
                if (lastCommentId > 0) {
                    await this.markThreadAsRead(threadId, lastCommentId);
                }
            }
            
            // Re-initialize Quill editor after thread selection and DOM update
            // Use multiple nextTick to ensure DOM is fully rendered
            await this.$nextTick();
            await this.$nextTick();
            
            // Wait a bit more to ensure element is ready and destroy is complete
            await new Promise(resolve => setTimeout(resolve, 200));
            
            // Initialize editor once after DOM is ready and destroy is complete
            // Check multiple times to ensure element is available
            let retries = 0;
            const maxRetries = 5;
            const tryInit = () => {
                if (this.$refs.quillEditor && !this.quillInstance && !this.initializingQuill) {
                    const element = this.$refs.quillEditor;
                    const parentContainer = element.closest('.comment-editor-container');
                    
                    // Ensure element exists and is clean
                    if (element && parentContainer) {
                        // Check if there's any leftover Quill structure
                        const hasQuill = parentContainer.querySelector('.ql-toolbar') || 
                                        element.classList.contains('ql-container') ||
                                        element.querySelector('.ql-editor');
                        
                        if (!hasQuill) {
                            // Element is clean, safe to initialize
                            this.initQuillEditor();
                            // Initialize mention manager after a short delay (only once)
                            if (!this.mentionManager && !this.initializingMention) {
                                setTimeout(() => {
                                    this.initMentionManager();
                                }, 50);
                            }
                            
                            // Scroll after Quill editor is initialized (only if not navigating to specific comment)
                            if (!skipAutoScroll && !this.navigatingToComment) {
                                setTimeout(() => {
                                    this.scrollToCommentBottom();
                                }, 200);
                            }
                        } else {
                            // Still has Quill structure, retry
                            if (retries < maxRetries) {
                                retries++;
                                setTimeout(tryInit, 100);
                            }
                        }
                    } else if (retries < maxRetries) {
                        // Element not found yet, retry
                        retries++;
                        setTimeout(tryInit, 100);
                    }
                } else if (retries < maxRetries && (!this.$refs.quillEditor || this.initializingQuill)) {
                    // Wait a bit more if element not ready or still initializing
                    retries++;
                    setTimeout(tryInit, 100);
                } else if (retries >= maxRetries) {
                    // Max retries reached, scroll anyway (only if not navigating to specific comment)
                    if (!skipAutoScroll && !this.navigatingToComment) {
                        setTimeout(() => {
                            this.scrollToCommentBottom();
                        }, 300);
                    }
                }
            };
            
            tryInit();
            
            // Also scroll after a delay as fallback in case Quill init takes longer (only if not navigating to specific comment)
            if (!skipAutoScroll && !this.navigatingToComment) {
                setTimeout(() => {
                    this.scrollToCommentBottom();
                }, 500);
            }
        },
        
        async createThread() {
            if (!this.newThreadTitle.trim()) return;
            
            try {
                const formData = new FormData();
                formData.append('project_id', this.entityId);
                formData.append('title', this.newThreadTitle.trim());
                formData.append('user_id', this.currentUser.userid);
                
                const response = await axios.post('/api/index.php?model=project&method=createThread', formData);
                if (response.data.success) {
                    const newThreadId = response.data.id;
                    this.newThreadTitle = '';
                    this.showCreateThreadModal = false;
                    await this.loadThreads();
                    // Select the newly created thread
                    if (newThreadId) {
                        await this.selectThread(newThreadId);
                    }
                } else {
                    this.$emit('error', { type: 'thread', message: response.data.message || 'スレッドの作成に失敗しました' });
                }
            } catch (error) {
                console.error('Error creating thread:', error);
                this.$emit('error', { type: 'thread', message: 'スレッドの作成に失敗しました' });
            }
        },
        
        async performSearch() {
            if (!this.searchTerm.trim()) {
                this.clearSearch();
                return;
            }
            
            this.searching = true;
            this.searchMode = true;
            try {
                const params = new URLSearchParams({
                    project_id: this.entityId,
                    search: this.searchTerm.trim()
                });
                const response = await axios.get(`/api/index.php?model=project&method=searchComments&${params}`);
                this.searchResults = this.initializeLikeStatus(response.data || []);
            } catch (error) {
                console.error('Error searching comments:', error);
                this.$emit('error', { type: 'search', message: '検索に失敗しました' });
            } finally {
                this.searching = false;
            }
        },
        
        onSearchInput() {
            // Debounce search - perform search after user stops typing
            if (this.searchTimeout) {
                clearTimeout(this.searchTimeout);
            }
            this.searchTimeout = setTimeout(() => {
                if (this.searchTerm.trim()) {
                    this.performSearch();
                } else {
                    this.clearSearch();
                }
            }, 500);
        },
        
        clearSearch() {
            this.searchTerm = '';
            this.searchMode = false;
            this.searchResults = [];
            if (this.searchTimeout) {
                clearTimeout(this.searchTimeout);
            }
        },
        
        navigateToSearchResult(result) {
            if (result.thread_id) {
                this.selectThread(result.thread_id).then(() => {
                    this.$nextTick(() => {
                        setTimeout(() => {
                            this.scrollToComment(result.id);
                        }, 500);
                    });
                });
            }
        },
        
        highlightSearchTerm(content) {
            if (!this.searchTerm || !content) return content;
            const term = this.searchTerm.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            const regex = new RegExp(`(${term})`, 'gi');
            return content.replace(regex, '<mark>$1</mark>');
        },
        
        formatShortDateTime(datetime) {
            if (!datetime) return '-';
            return moment(datetime).format('MM/DD HH:mm');
        },
        
        // Mark thread as read
        async markThreadAsRead(threadId, lastCommentId) {
            try {
                const formData = new FormData();
                formData.append('thread_id', threadId);
                formData.append('last_comment_id', lastCommentId);
                formData.append('user_id', this.currentUser.userid);
                
                const response = await axios.post('/api/index.php?model=project&method=markThreadAsRead', formData);
                
                if (response.data && response.data.success) {
                    // Reload thread list to get updated unread count from server
                    if (this.enableThreads) {
                        await this.loadThreads();
                    }
                }
            } catch (error) {
                console.error('Error marking thread as read:', error);
            }
        },
        
        // Polling methods (replacement for Firebase)
        startPolling() {
            if (!this.pollingEnabled || this.pollingInterval) return;
            
            // Set initial last check time
            this.lastCheckTime = Date.now();
            
            // Start polling
            this.pollingInterval = setInterval(() => {
                this.checkForNewComments();
            }, this.pollingIntervalMs);
        },
        
        stopPolling() {
            if (this.pollingInterval) {
                clearInterval(this.pollingInterval);
                this.pollingInterval = null;
            }
        },
        
        async checkForNewComments() {
            if (!this.enableThreads || this.entityType !== 'project') return;
            
            try {
                const params = new URLSearchParams({
                    project_id: this.entityId
                });
                
                // Add since parameter if we have last comment ID or timestamp
                if (this.lastCommentId) {
                    params.append('since', this.lastCommentId);
                } else if (this.lastCheckTime) {
                    params.append('since', this.lastCheckTime);
                }
                
                const response = await axios.get(`/api/index.php?model=project&method=getLatestCommentInfo&${params}`);
                const data = response.data || {};
                
                if (data.latest_comment_id && data.latest_comment_id !== this.lastCommentId) {
                    // New comment detected
                    const newThreadId = data.thread_id;
                    
                    // Update last comment ID
                    this.lastCommentId = data.latest_comment_id;
                    this.lastCheckTime = Date.now();
                    
                    // Reload thread list to update counts and previews
                    await this.loadThreads();
                    
                    // Reload comments if viewing the thread that received the comment
                    if (newThreadId && newThreadId == this.selectedThreadId) {
                        // New comment in the thread we're viewing
                        await this.loadComments();
                        this.scrollToCommentBottom();
                        
                        // Check if user is viewing the page (tab is visible)
                        if (!document.hidden) {
                            // User is viewing, mark as read and reset title
                            if (data.latest_comment_id) {
                                await this.markThreadAsRead(newThreadId, data.latest_comment_id);
                            }
                            this.newCommentCount = 0;
                            this.updatePageTitle(0);
                        } else {
                            // User is not viewing (tab is hidden), update title to notify
                            this.newCommentCount++;
                            this.updatePageTitle(this.newCommentCount);
                        }
                    } else if (!this.selectedThreadId) {
                        // No thread selected, just reload comments
                        await this.loadComments();
                        this.scrollToCommentBottom();
                    }
                } else if (data.latest_comment_id) {
                    // Same comment, just update timestamp
                    this.lastCheckTime = Date.now();
                }
            } catch (error) {
                console.error('Error checking for new comments:', error);
                // Don't stop polling on error, just log it
            }
        },
        
        // Update page title to show new comment count
        updatePageTitle(newCount) {
            if (!this.originalPageTitle) {
                this.originalPageTitle = document.title;
            }
            
            if (newCount > 0 && this.selectedThreadId) {
                // Show notification in title
                document.title = `(${newCount}) ${this.originalPageTitle}`;
            } else {
                // Reset to original title
                document.title = this.originalPageTitle;
            }
        },
        
        // Initialize polling with current latest comment
        async initializePolling() {
            if (!this.enableThreads || this.entityType !== 'project') return;
            
            try {
                // Get the latest comment ID to start tracking from
                const params = new URLSearchParams({
                    project_id: this.entityId
                });
                const response = await axios.get(`/api/index.php?model=project&method=getLatestCommentInfo&${params}`);
                const data = response.data || {};
                
                if (data.latest_comment_id) {
                    this.lastCommentId = data.latest_comment_id;
                    this.lastCheckTime = Date.now();
                }
                
                // Start polling
                this.startPolling();
            } catch (error) {
                console.error('Error initializing polling:', error);
            }
        },
    },
    
    async mounted() {
        // Store original page title
        this.originalPageTitle = document.title;
        
        // Setup page visibility listener to reset title when user returns to tab
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && this.selectedThreadId) {
                // User returned to tab, reset title and comment count
                this.newCommentCount = 0;
                this.updatePageTitle(0);
            }
        });
        
        // Check URL hash first to see if we need to load a specific thread
        const hash = window.location.hash;
        const hasCommentHash = hash && hash.startsWith('#comment-');
        
        // Load threads if enabled
        if (this.enableThreads) {
            await this.loadThreads();
            
            // If there's a comment hash, checkUrlHash will handle loading the thread
            // Otherwise, auto-select first thread if no thread is selected
            if (!hasCommentHash && !this.selectedThreadId && this.threads.length > 0) {
                await this.selectThread(this.threads[0].id);
            } else if (!hasCommentHash && this.selectedThreadId) {
                // If thread is already selected, load its comments
                await this.loadComments();
            }
        } else {
            // Load comments if threads are not enabled
            await this.loadComments();
        }
        
        this.$nextTick(() => {
            this.initQuillEditor();
            this.initMentionManager();
            // Force initial check of content
            setTimeout(() => {
                this.updateEditorContent();
            }, 100);
            
            // Initialize Bootstrap tooltips
            this.initTooltips();
            
            // Focus thread title input when modal opens
            if (this.$refs.threadTitleInput && this.showCreateThreadModal) {
                this.$refs.threadTitleInput.focus();
            }
            
            // Check URL hash after everything is initialized
            // This will handle loading thread and scrolling to comment if hash exists
            if (hasCommentHash) {
                this.checkUrlHash();
            } else {
                // No comment hash, scroll to bottom
                this.scrollToCommentBottom();
            }
            this.addZoomToDescriptionImages();
            let inited = false;

            // Initialize polling for realtime updates (replaces Firebase)
            if (this.enableThreads && this.entityType === 'project' && this.entityId) {
                // Wait a bit before starting polling to avoid initial load conflicts
                setTimeout(() => {
                    this.initializePolling();
                }, 2000);
            }
            
            // Keep Firebase listener for backward compatibility (if needed)
            // But prefer polling for thread updates
            if (this.entityType === 'project' && this.entityId && window.notificationManager && !inited && !this.enableThreads) {
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
                if (this.entityType === 'project' && this.entityId && window.notificationManager && !inited && !this.enableThreads) {
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
        
        // Stop polling
        this.stopPolling();
        
        // Remove event listener for browser back/forward buttons
        window.removeEventListener('popstate', this.handlePopState);
    },
    watch: {
        comments() {
            this.addZoomToDescriptionImages();
        },
        // Removed watch for selectedThreadId to prevent duplicate initialization
        // selectThread() method already handles editor initialization
    }
}; 