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
            currentCursorPosition: 0,
            savedMentionSelection: null,
            isProcessingMention: false,
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
            if (!this.editorHasContent || this.submittingComment) return;
            
            const commentHtml = this.getCommentText().trim();
            if (!commentHtml) return;
            
            this.submittingComment = true;
            
            try {
                const formData = new FormData();
                formData.append(`${this.entityType}_id`, this.entityId);
                formData.append('content', commentHtml);
                formData.append('user_id', this.currentUser.userid);
                
                await axios.post(this.apiEndpoints.addComment, formData);
                
                // Clear Quill editor
                if (this.quillInstance) {
                    this.quillInstance.setContents([]);
                    this.editorHasContent = false;
                }
                
                await this.loadComments(true);
                this.$emit('comment-added', { content: commentHtml });
                
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
            if (this.quillInstance) {
                const text = this.quillInstance.getText().trim();
                this.editorHasContent = text.length > 0;
                return this.editorHasContent;
            }
            return false;
        },
        
        updateEditorContent() {
            if (this.quillInstance) {
                const text = this.quillInstance.getText().trim();
                this.editorHasContent = text.length > 0;
            }
        },
        
        initQuillEditor() {
            if (this.$refs.quillEditor && !this.quillInstance) {
                // Register custom mention format
                this.registerMentionFormat();
                
                // Quill modules configuration
                const modules = {
                    toolbar: [
                        ['bold', 'italic', 'underline'],
                        ['link'],
                        [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                        ['clean']
                    ]
                };

                // Add mention module if available
                if (window.QuillMention) {
                    modules.mention = {
                        allowedChars: /^[A-Za-z\sÀ-ÿ]*$/,
                        mentionDenotationChars: ["@"],
                        source: this.getMentionUsers,
                        selectKeys: [13, 9], // Enter and Tab
                        onSelect: (item, insertItem) => {
                            insertItem(item);
                        },
                        renderItem: (item, searchTerm) => {
                            return `<div class="mention-item">
                                <img src="/assets/upload/avatar/${item.user_image || 'default.png'}" alt="${item.realname}" class="mention-avatar">
                                <span class="mention-name">${item.realname}</span>
                            </div>`;
                        }
                    };
                }

                this.quillInstance = new Quill(this.$refs.quillEditor, {
                    theme: 'snow',
                    placeholder: 'コメントを入力... @でメンション',
                    modules: modules,
                    formats: ['bold', 'italic', 'underline', 'link', 'list', 'mention']
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

                // Setup mention support using mention.js
                this.$nextTick(() => {
                    this.setupBasicMentions();
                });
            }
        },
        
        registerMentionFormat() {
            // Create custom mention format for Quill
            const Inline = Quill.import('blots/inline');
            
            class MentionBlot extends Inline {
                static create(value) {
                    const node = super.create();
                    node.setAttribute('class', 'mention-highlight');
                    node.setAttribute('data-user-id', value.userId || value['data-user-id'] || '');
                    node.setAttribute('data-user-name', value.userName || value['data-user-name'] || '');
                    node.setAttribute('data-mention', 'true');
                    node.setAttribute('contenteditable', 'false');
                    return node;
                }
                
                static formats(node) {
                    return {
                        userId: node.getAttribute('data-user-id'),
                        userName: node.getAttribute('data-user-name'),
                        mention: true
                    };
                }
                
                static value(node) {
                    return {
                        userId: node.getAttribute('data-user-id'),
                        userName: node.getAttribute('data-user-name'),
                        mention: true
                    };
                }
            }
            
            MentionBlot.blotName = 'mention';
            MentionBlot.tagName = 'span';
            
            // Register the custom format
            Quill.register(MentionBlot);
        },
        
        async getMentionUsers(searchTerm, renderList) {
            // Function for QuillMention module
            try {
                const response = await axios.get(`/api/index.php?model=user&method=searchUsers&search=${searchTerm}`);
                const users = response.data || [];
                const formattedUsers = users.map(user => ({
                    id: user.userid,
                    value: user.realname,
                    realname: user.realname,
                    user_image: user.user_image
                }));
                renderList(formattedUsers, searchTerm);
            } catch (error) {
                console.error('Error loading mention users:', error);
                renderList([], searchTerm);
            }
        },
        
        setupBasicMentions() {
            // Setup mention functionality using mention.js
            if (this.quillInstance) {
                const editor = this.quillInstance.root;
                
                // Add required attributes for mention.js
                editor.setAttribute('data-mention', 'true');
                editor.setAttribute('data-html-mention', 'true');
                editor.classList.add('allow-mention');
                
                // Initialize mention manager if not already initialized
                if (typeof window.mentionManager === 'undefined' && typeof MentionManager !== 'undefined') {
                    window.mentionManager = new MentionManager({
                        inputSelector: '[data-mention]',
                        apiEndpoint: '/api/index.php?model=user&method=getMentionUsers',
                        onMentionSelect: (user, input) => {
                            console.log('Mention selected:', user);
                            // Prevent default mention insertion behavior
                            // We handle this in overrideMentionSelection
                        }
                    });
                }
                
                // Store reference to saved selection
                this.savedMentionSelection = null;
                
                // Store current cursor position
                this.currentCursorPosition = 0;
                
                // Track cursor position changes
                this.quillInstance.on('selection-change', (range, oldRange, source) => {
                    if (range && range.index !== undefined) {
                        this.currentCursorPosition = range.index;
                    }
                });
                
                // Add custom event listener for @ detection using delta
                this.quillInstance.on('text-change', (delta, oldDelta, source) => {
                    if (source === 'user') {
                        // First check for @ character insertion
                        this.handleAtCharacterDetectionWithDelta(delta);
                        
                        // Then update mention selection if user is typing after @
                        setTimeout(() => {
                            this.updateMentionSelectionOnType();
                        }, 20);
                    }
                });
                
                // Override the selectMention method completely
                if (window.mentionManager) {
                    this.overrideMentionSelection(window.mentionManager, editor);
                }
                
                // If mention manager already exists, make sure it binds to this editor
                if (window.mentionManager) {
                    try {
                        // Force rebind to ensure proper integration
                        window.mentionManager.bindToInput(editor);
                    } catch (error) {
                        console.log('Could not bind mention manager to Quill:', error);
                        // Fallback: manually bind events
                        this.bindMentionEvents(editor);
                    }
                }
            }
        },
        
        handleAtCharacterDetectionWithDelta(delta) {
            // Handle @ character detection using delta and cursor position
            try {
                // Skip if already processing a mention
                if (this.isProcessingMention) return;
                
                // Check if @ character was inserted
                let insertedAt = false;
                let insertPosition = -1;
                const ops = delta.ops || [];
                
                // Calculate position where @ was inserted
                let currentPos = 0;
                for (const op of ops) {
                    if (op.retain) {
                        currentPos += op.retain;
                    } else if (op.insert && typeof op.insert === 'string') {
                        if (op.insert.includes('@')) {
                            insertedAt = true;
                            insertPosition = currentPos + op.insert.indexOf('@');
                            break;
                        }
                        currentPos += op.insert.length;
                    }
                }
                
                if (!insertedAt) return;
                
                const text = this.quillInstance.getText();
                
                // Use insert position if available, otherwise use cursor position
                let atIndex = insertPosition >= 0 ? insertPosition : this.currentCursorPosition - 1;
                
                // Validate @ character is actually at this position
                if (atIndex >= 0 && atIndex < text.length && text[atIndex] === '@') {
                    console.log('@ confirmed at position:', atIndex);
                } else {
                    // Fallback: search for @ near cursor position
                    const searchStart = Math.max(0, this.currentCursorPosition - 5);
                    const searchEnd = Math.min(text.length, this.currentCursorPosition + 1);
                    const searchText = text.substring(searchStart, searchEnd);
                    const relativeAtIndex = searchText.lastIndexOf('@');
                    
                    if (relativeAtIndex !== -1) {
                        atIndex = searchStart + relativeAtIndex;
                        console.log('@ found via fallback search at position:', atIndex);
                    } else {
                        console.warn('Could not locate @ character');
                        return;
                    }
                }
                
                console.log('@ detected at position:', atIndex, 'cursor:', this.currentCursorPosition);
                
                if (atIndex >= 0 && (atIndex === 0 || /\s/.test(text[atIndex - 1]))) {
                    // Found @ at word boundary
                    let endIndex = atIndex + 1;
                    
                    // Find end of text after @ (until space or end)
                    while (endIndex < text.length && !/\s/.test(text[endIndex])) {
                        endIndex++;
                    }
                    
                    const mentionLength = endIndex - atIndex;
                    console.log('Setting selection for mention:', atIndex, 'length:', mentionLength);
                    
                    // Save the selection for later use (don't call setSelection as it causes errors)
                    this.savedMentionSelection = {
                        index: atIndex,
                        length: mentionLength,
                        text: text.substring(atIndex, endIndex)
                    };
                    
                    console.log('Saved mention selection:', this.savedMentionSelection);
                }
            } catch (error) {
                console.error('Error in handleAtCharacterDetectionWithDelta:', error);
            }
        },
        
        updateMentionSelectionOnType() {
            // Update saved selection when user continues typing after @
            try {
                if (!this.savedMentionSelection) return;
                
                const text = this.quillInstance.getText();
                const atIndex = this.savedMentionSelection.index;
                
                // Find new end position
                let endIndex = atIndex + 1;
                while (endIndex < text.length && !/\s/.test(text[endIndex])) {
                    endIndex++;
                }
                
                // Update saved selection
                const newLength = endIndex - atIndex;
                if (newLength !== this.savedMentionSelection.length) {
                    this.savedMentionSelection.length = newLength;
                    this.savedMentionSelection.text = text.substring(atIndex, endIndex);
                    
                    console.log('Updated mention selection:', this.savedMentionSelection);
                    
                    // Update selection in editor
                    setTimeout(() => {
                        try {
                            this.quillInstance.setSelection(atIndex, newLength, 'silent');
                        } catch (error) {
                            console.error('Error updating selection:', error);
                        }
                    }, 10);
                }
            } catch (error) {
                console.error('Error in updateMentionSelectionOnType:', error);
            }
        },
        
        ensureMentionFormat(user, input) {
            // Ensure the mention has the correct format
            // This is called after mention selection to validate format
            setTimeout(() => {
                const mentions = input.querySelectorAll('.mention-highlight');
                mentions.forEach(mention => {
                    if (!mention.dataset.userId || !mention.dataset.userName) {
                        mention.setAttribute('data-user-id', user.userid || user.id);
                        mention.setAttribute('data-user-name', user.user_name);
                        mention.setAttribute('data-mention', 'true');
                        mention.className = 'mention-highlight';
                    }
                });
            }, 100);
        },
        
        bindMentionEvents(editor) {
            // Fallback manual binding if automatic binding fails
            let mentionStartPos = -1;
            let mentionSearchTerm = '';
            
            editor.addEventListener('input', (event) => {
                const text = editor.textContent;
                const selection = window.getSelection();
                
                if (selection.rangeCount > 0) {
                    const range = selection.getRangeAt(0);
                    const cursorPos = this.getCursorPosition(editor, range);
                    const beforeCursor = text.substring(0, cursorPos);
                    const atIndex = beforeCursor.lastIndexOf('@');
                    
                    if (atIndex !== -1 && (atIndex === 0 || /\s/.test(text[atIndex - 1]))) {
                        mentionStartPos = atIndex;
                        mentionSearchTerm = beforeCursor.substring(atIndex + 1);
                        
                        // Trigger mention dropdown manually
                        if (window.mentionManager) {
                            window.mentionManager.currentInput = editor;
                            window.mentionManager.mentionStartPosition = mentionStartPos;
                            window.mentionManager.mentionSearchTerm = mentionSearchTerm;
                            window.mentionManager.showDropdown();
                        }
                    }
                }
            });
        },
        
        getCursorPosition(element, range) {
            // Get cursor position in contenteditable element
            const tempRange = range.cloneRange();
            tempRange.selectNodeContents(element);
            tempRange.setEnd(range.endContainer, range.endOffset);
            return tempRange.toString().length;
        },
        
        overrideMentionSelection(mentionManager, editor) {
            // Store the original selectMention method
            const originalSelectMention = mentionManager.selectMention.bind(mentionManager);
            
            // Override selectMention for this editor
            mentionManager.selectMention = (user) => {
                if (mentionManager.currentInput === editor) {
                    // Set processing flag to prevent duplicate @ detection
                    this.isProcessingMention = true;
                    
                    // Prevent the original selectMention from running
                    // Only use our custom insertion
                    this.insertMentionIntoQuill(user, editor);
                    
                    // Hide dropdown first
                    mentionManager.hideDropdown();
                    
                    // Call original callback after our insertion
                    if (mentionManager.options.onMentionSelect) {
                        mentionManager.options.onMentionSelect(user, editor);
                    }
                    
                    // Clear processing flag after a delay
                    setTimeout(() => {
                        this.isProcessingMention = false;
                    }, 1000);
                } else {
                    // Use original method for other inputs
                    originalSelectMention(user);
                }
            };
        },
        
        insertMentionIntoQuill(user, editor) {
            if (!this.quillInstance) return;
            
            try {
                // Make sure editor is focused and ready
                editor.focus();
                
                // Small delay to ensure focus is set
                setTimeout(() => {
                    try {
                        // Use saved mention selection if available
                        let atIndex, deleteLength;
                        
                        if (this.savedMentionSelection) {
                            // Use saved selection data
                            atIndex = this.savedMentionSelection.index;
                            deleteLength = this.savedMentionSelection.length;
                            console.log('Using saved selection - atIndex:', atIndex, 'deleteLength:', deleteLength, 'text:', this.savedMentionSelection.text);
                        } else {
                            // Fallback: find @ position in text
                            const text = this.quillInstance.getText();
                            atIndex = text.lastIndexOf('@');
                            
                            if (atIndex === -1) {
                                console.warn('Could not find @ character, falling back');
                                this.fallbackMentionInsertion(user, editor);
                                return;
                            }
                            
                            // Find the end of the search term
                            let searchEndIndex = atIndex + 1;
                            while (searchEndIndex < text.length && !/\s/.test(text[searchEndIndex])) {
                                searchEndIndex++;
                            }
                            
                            deleteLength = searchEndIndex - atIndex;
                            console.log('Using fallback method - atIndex:', atIndex, 'deleteLength:', deleteLength);
                        }
                        
                        // Validate positions
                        if (atIndex < 0 || deleteLength <= 0) {
                            console.warn('Invalid position data, using fallback');
                            this.fallbackMentionInsertion(user, editor);
                            return;
                        }
                        
                        // Create mention content 
                        const mentionText = `@${user.user_name}`;
                        
                        // Use safer approach: individual API calls instead of updateContents
                        try {
                            // Delete the @ and search term
                            this.quillInstance.deleteText(atIndex, deleteLength, 'silent');
                            
                            // Insert mention using custom format
                            this.quillInstance.insertText(atIndex, mentionText, {
                                'mention': {
                                    userId: user.userid || user.id,
                                    userName: user.user_name,
                                    'data-user-id': user.userid || user.id,
                                    'data-user-name': user.user_name
                                }
                            }, 'silent');
                            
                            // Add space after mention
                            this.quillInstance.insertText(atIndex + mentionText.length, ' ', 'silent');
                            
                        } catch (apiError) {
                            console.error('Quill API error, using DOM fallback:', apiError);
                            this.fallbackMentionInsertion(user, editor);
                            return;
                        }
                        
                        // Clear saved selection
                        this.savedMentionSelection = null;
                        
                        // Clear MentionManager state
                        if (window.mentionManager) {
                            window.mentionManager.mentionStartPosition = -1;
                            window.mentionManager.mentionSearchTerm = '';
                        }
                        
                        // Clean up any duplicate @ characters after the operation
                        setTimeout(() => {
                            this.cleanupDuplicateAtCharacters();
                            
                            // Update cursor position without using setSelection
                            const newCursorPos = atIndex + mentionText.length + 1;
                            this.currentCursorPosition = newCursorPos;
                            console.log('Cursor updated to position:', newCursorPos);
                        }, 50);
                        
                        // Update editor content state
                        this.updateEditorContent();
                        
                    } catch (innerError) {
                        console.error('Error in mention insertion:', innerError);
                        this.fallbackMentionInsertion(user, editor);
                    }
                }, 50);
                
            } catch (error) {
                console.error('Error focusing editor:', error);
                this.fallbackMentionInsertion(user, editor);
            }
        },
        
        cleanupDuplicateAtCharacters() {
            // Clean up duplicate @ characters that might appear after mention insertion
            try {
                const html = this.quillInstance.root.innerHTML;
                
                // Look for patterns like: </span>@ or mention followed by standalone @
                const cleanedHtml = html.replace(/(<\/span>)\s*@(?!\w)/g, '$1 ');
                
                if (html !== cleanedHtml) {
                    console.log('Cleaning up duplicate @ characters');
                    this.quillInstance.root.innerHTML = cleanedHtml;
                    
                    // Trigger text change to update Quill's internal state
                    this.quillInstance.update();
                }
            } catch (error) {
                console.error('Error cleaning up duplicate @ characters:', error);
            }
        },
        
        fallbackMentionInsertion(user, editor) {
            // Fallback method using simple text insertion at cursor
            try {
                console.log('Using fallback mention insertion');
                
                // Get current cursor position
                const cursorPos = this.currentCursorPosition;
                const text = this.quillInstance.getText();
                
                // Simple approach: just insert mention at current position
                const mentionText = `@${user.user_name}`;
                
                // If there's a saved mention selection, use it
                if (this.savedMentionSelection && this.savedMentionSelection.index >= 0) {
                    const atIndex = this.savedMentionSelection.index;
                    const deleteLength = this.savedMentionSelection.length;
                    
                    console.log('Fallback using saved selection:', atIndex, deleteLength);
                    
                    // Try simple text operations
                    try {
                        this.quillInstance.deleteText(atIndex, deleteLength, 'silent');
                        this.quillInstance.insertText(atIndex, mentionText + ' ', 'silent');
                        this.currentCursorPosition = atIndex + mentionText.length + 1;
                    } catch (textError) {
                        // Last resort: DOM manipulation
                        this.domMentionInsertion(user, editor, atIndex, deleteLength);
                    }
                } else {
                    // Search for @ near cursor
                    const searchStart = Math.max(0, cursorPos - 10);
                    const searchText = text.substring(searchStart, cursorPos + 1);
                    const atIndex = searchText.lastIndexOf('@');
                    
                    if (atIndex !== -1) {
                        const absoluteAtIndex = searchStart + atIndex;
                        console.log('Fallback found @ at:', absoluteAtIndex);
                        
                        try {
                            this.quillInstance.deleteText(absoluteAtIndex, 1, 'silent');
                            this.quillInstance.insertText(absoluteAtIndex, mentionText + ' ', 'silent');
                            this.currentCursorPosition = absoluteAtIndex + mentionText.length + 1;
                        } catch (textError) {
                            this.domMentionInsertion(user, editor, absoluteAtIndex, 1);
                        }
                    } else {
                        // Just insert at cursor position
                        console.log('Fallback: inserting at cursor position');
                        try {
                            this.quillInstance.insertText(cursorPos, mentionText + ' ', 'silent');
                            this.currentCursorPosition = cursorPos + mentionText.length + 1;
                        } catch (textError) {
                            console.error('All fallback methods failed:', textError);
                        }
                    }
                }
                
                // Clear saved selection
                this.savedMentionSelection = null;
                
                // Update editor content state
                this.updateEditorContent();
                
                // Clear processing flag
                this.isProcessingMention = false;
                
            } catch (error) {
                console.error('Fallback mention insertion failed:', error);
            }
        },
        
        domMentionInsertion(user, editor, atIndex, deleteLength) {
            // Last resort DOM manipulation
            try {
                const mentionHtml = `<span class="mention-highlight" data-user-id="${user.userid || user.id}" data-user-name="${user.user_name}" data-mention="true">@${user.user_name}</span> `;
                
                // Get current selection
                const selection = window.getSelection();
                if (selection.rangeCount > 0) {
                    const range = selection.getRangeAt(0);
                    
                    // Simple insertion at cursor
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = mentionHtml;
                    const mentionElement = tempDiv.firstElementChild;
                    
                    range.insertNode(mentionElement);
                    range.setStartAfter(mentionElement);
                    range.collapse(true);
                    selection.removeAllRanges();
                    selection.addRange(range);
                }
                
                console.log('DOM mention insertion completed');
                
            } catch (error) {
                console.error('DOM mention insertion failed:', error);
            }
        },
        

        
        destroyQuillEditor() {
            if (this.quillInstance) {
                this.quillInstance = null;
            }
            // Clear saved mention selection and cursor position
            this.savedMentionSelection = null;
            this.currentCursorPosition = 0;
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