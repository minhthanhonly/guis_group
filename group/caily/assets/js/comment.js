// Vue options cho phần comment (dùng cho modal comment)
window.projectDetailVueOptions = {
    data() {
        return {
            projectId: PROJECT_ID,
            comments: [],
            newComment: '',
            commentsPage: 1,
            commentsPerPage: 20,
            loadingOlderComments: false,
            hasMoreComments: true,
            showLoadMoreButton: false,
            managers: [],
            members: [],
            USER_ID: typeof USER_ID !== 'undefined' ? USER_ID : null,
        };
    },
    computed: {
        displayedComments() {
            // Sort comments by date ascending (oldest first)
            const sortedComments = [...this.comments].sort((a, b) => 
                new Date(a.created_at) - new Date(b.created_at)
            );
            return sortedComments;
        },
    },
    methods: {
        async loadComments() {
            console.log('loadComments');
            try {
                const response = await axios.get(`/api/index.php?model=project&method=getComments&project_id=${this.projectId}&page=${this.commentsPage}&per_page=${this.commentsPerPage}`);
                const newComments = response.data || [];
                if (this.commentsPage === 1) {
                    this.comments = newComments;
                    this.$nextTick(() => {
                        setTimeout(() => {
                            const element = document.querySelector('#chat-history-project');
                            if (element && element.scrollHeight > 0) {
                                element.scrollTop = element.scrollHeight;
                            }
                        }, 100);
                    });
                } else {
                    const element = document.querySelector('#chat-history-project');
                    const scrollHeightBefore = element ? element.scrollHeight : 0;
                    this.comments = [...newComments, ...this.comments];
                    this.$nextTick(() => {
                        if (element) {
                            const scrollHeightAfter = element.scrollHeight;
                            const scrollDifference = scrollHeightAfter - scrollHeightBefore;
                            element.scrollTop = scrollDifference;
                        }
                    });
                }
                this.hasMoreComments = newComments.length === this.commentsPerPage;
                this.loadingOlderComments = false;
            } catch (error) {
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
            } finally {
                this.loadingOlderComments = false;
            }
        },
        handleScroll(event) {
            const element = event.target;
            const isAtTop = element.scrollTop === 0;
            this.showLoadMoreButton = isAtTop && this.hasMoreComments && !this.loadingOlderComments;
            if (isAtTop && this.hasMoreComments && !this.loadingOlderComments) {
                this.loadMoreComments();
            }
        },
        async addComment() {
            if (!this.hasCommentContent()) return;
            const commentHtml = this.getCommentText().trim();
            if (!commentHtml) return;
            try {
                const formData = new FormData();
                formData.append('project_id', this.projectId);
                formData.append('content', commentHtml);
                formData.append('user_id', USER_ID);
                await axios.post('/api/index.php?model=project&method=addComment', formData);
                const contenteditableDiv = document.querySelector('[contenteditable="true"][data-mention]');
                if (contenteditableDiv) {
                    contenteditableDiv.innerHTML = '';
                }
                this.commentsPage = 1;
                this.hasMoreComments = true;
                this.showLoadMoreButton = false;
                await this.loadComments();
                this.$nextTick(() => {
                    setTimeout(() => {
                        const element = document.querySelector('#chat-history-project');
                        if (element && element.scrollHeight > 0) {
                            element.scrollTop = element.scrollHeight;
                        }
                    }, 100);
                });
            } catch (error) {}
        },
        getCommentText() {
            const contenteditableDiv = document.querySelector('[contenteditable="true"][data-mention]');
            if (contenteditableDiv) {
                return contenteditableDiv.innerHTML || '';
            }
            return '';
        },
        hasCommentContent() {
            const contenteditableDiv = document.querySelector('[contenteditable="true"][data-mention]');
            if (contenteditableDiv) {
                const textContent = contenteditableDiv.textContent || contenteditableDiv.innerText || '';
                return textContent.trim().length > 0;
            }
            return false;
        },
        onCommentInput() {
            this.$forceUpdate();
        },
        renderMentions(content) {
            if (!content) return content;
            if (content.includes('mention-highlight')) {
                let displayContent = content;
                displayContent = this.decodeHtmlEntities(displayContent);
                return displayContent;
            }
            return MentionManager.renderMentions(content);
        },
        decodeHtmlEntities(str) {
            const txt = document.createElement('textarea');
            txt.innerHTML = str;
            return txt.value;
        },
        getAvatarSrc(member) {
            return '/assets/upload/avatar/' + member.user_image || '';
        },
        handleAvatarError(member) {
            member.avatarError = true;
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
        },
        async loadMembers() {
            try {
                const response = await axios.get(`/api/index.php?model=project&method=getMembers&project_id=${this.projectId}`);
                const all = response.data || [];
                this.managers = all.filter(m => m && m.role === 'manager');
                const managerIds = this.managers.map(m => m.user_id);
                this.members = all.filter(m => m && m.role === 'member' && !managerIds.includes(m.user_id));
            } catch (error) {
                this.managers = [];
                this.members = [];
            }
        },
    },
    async mounted() {
        await this.loadMembers();
        await this.loadComments();
    }
}; 