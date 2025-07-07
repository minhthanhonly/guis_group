/**
 * Mention Functionality - Reusable Component
 * Provides @mention functionality for input fields across the website
 */
class MentionManager {
    constructor(options = {}) {
        
        this.options = {
            inputSelector: '[data-mention]',
            dropdownClass: 'mention-dropdown',
            apiEndpoint: '/api/index.php?model=user&method=getMentionUsers',
            departmentId: null,
            onMentionSelect: null,
            onMentionRender: null,
            ...options
        };
        
        this.mentionUsers = [];
        this.showMentionDropdown = false;
        this.selectedMentionIndex = 0;
        this.mentionStartPosition = -1;
        this.mentionSearchTerm = '';
        this.currentInput = null;
        this.dropdownElement = null;
        this.savedRange = null;
        
        
        // Load users
        this.loadMentionUsers();
        
    }
    
    init() {
        // Bind event listeners
        document.addEventListener('input', this.handleInput.bind(this));
        document.addEventListener('keydown', this.handleKeydownEvent.bind(this));
        document.addEventListener('click', this.handleGlobalClick.bind(this));
        document.addEventListener('keydown', this.preventMentionEdit.bind(this));
    }
    
    async loadMentionUsers() {
        try {
            const params = new URLSearchParams();
            if (this.options.departmentId) {
                params.append('department_id', this.options.departmentId);
            }
            
            const response = await fetch(`${this.options.apiEndpoint}&${params.toString()}`);
            
            
            // Check if response is ok
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            // Check content type
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                // If not JSON, get the text to see what's being returned
                const text = await response.text();
                console.error('API returned non-JSON response:', text);
                throw new Error('API returned non-JSON response');
            }
            
            const data = await response.json();
            this.mentionUsers = data || []; 
            
            // Initialize after loading users
            this.init();
        } catch (error) {
            console.error('loadMentionUsers - error:', error);
            this.mentionUsers = [];
            
            // Initialize even if loading fails
            this.init();
        }
    }
    
    loadFallbackUsers() {
        // Fallback data for testing when API is not available
        this.mentionUsers = [
            {
                id: 1,
                userid: 'admin',
                user_name: '管理者',
                role: 'administrator',
                authority: 'administrator'
            }
           
        ];
    }
    
    bindEvents() {
        // Global click handler to close dropdown
        document.addEventListener('click', (event) => {
            this.handleGlobalClick(event);
        });
        
        // Bind to all inputs with data-mention attribute
        this.bindToInputs();
        
        // Watch for dynamically added inputs
        this.observeDOM();
    }
    
    bindToInputs() {
        const inputs = document.querySelectorAll(this.options.inputSelector);
        inputs.forEach(input => {
            this.bindToInput(input);
        });
    }
    
    bindToInput(input) {
        if(!input.classList.contains('allow-mention')) return;
        if (input.dataset.mentionBound) return; // Already bound
        
        input.dataset.mentionBound = 'true';
        
        input.addEventListener('keyup', (event) => {
         
            this.handleInput(event);
        });
        
        input.addEventListener('keydown', (event) => {
            this.handleKeydownEvent(event);
        });
        
        input.addEventListener('focus', () => {
            this.currentInput = input;
        });
    }
    
    observeDOM() {
        // Watch for new inputs being added to the DOM
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType === Node.ELEMENT_NODE) {
                        const inputs = node.querySelectorAll ? 
                            node.querySelectorAll(this.options.inputSelector) : 
                            (node.matches && node.matches(this.options.inputSelector) ? [node] : []);
                        
                        inputs.forEach(input => {
                            this.bindToInput(input);
                        });
                    }
                });
            });
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }
    
    handleInput(event) {
        const input = event.target;
        if(!input.classList.contains('allow-mention')) return;
        
        // Check if this is a contenteditable element
        const isContentEditable = input.contentEditable === 'true' || 
                                 input.hasAttribute('data-html-mention');
        
        
        
        if (isContentEditable) {
            // For contenteditable elements, work with HTML content
            const currentHtml = input.innerHTML;
           
            
            // Get cursor position for contenteditable
            const selection = window.getSelection();
            if (selection.rangeCount > 0) {
                const range = selection.getRangeAt(0);
                
                // Get the text content of the entire input
                const fullText = input.textContent || input.innerText;
               
                
                // Calculate cursor position in text
                const tempRange = range.cloneRange();
                tempRange.selectNodeContents(input);
                tempRange.setEnd(range.endContainer, range.endOffset);
                const cursorPosition = tempRange.toString().length;
               
                
                // Get text before cursor
                let beforeCursorText = fullText.substring(0, cursorPosition);
               
                
                // Special case: just typed @, cursor at position 1
                let atIndex = beforeCursorText.lastIndexOf('@');
                if (atIndex === -1 && cursorPosition === 1 && fullText[0] === '@') {
                    atIndex = 0;
                    beforeCursorText = '@';
                }
               
                
                if (atIndex !== -1) {
                    // Always define beforeAt
                    let beforeAt = beforeCursorText.substring(0, atIndex);
                    // Check if @ is at the beginning or after a space/word boundary or after a mention span
                    let isAtWordBoundary = false;
                    if (atIndex === 0) {
                        isAtWordBoundary = true;
                    } else {
                        // Check if previous character is space
                        isAtWordBoundary = /\s$/.test(beforeAt);
                        if (!isAtWordBoundary) {
                            // Check if previous node is a mention span
                            let nodeBefore = null;
                            if (selection.rangeCount > 0) {
                                const range = selection.getRangeAt(0);
                                nodeBefore = range.startContainer;
                                let offset = range.startOffset;
                                if (nodeBefore.nodeType === Node.TEXT_NODE && offset > 0) {
                                    // If in text node, check char before offset
                                    isAtWordBoundary = /\s/.test(nodeBefore.textContent[offset - 1]);
                                } else if (nodeBefore.nodeType === Node.ELEMENT_NODE && offset > 0) {
                                    // If in element node, check previous sibling
                                    const prev = nodeBefore.childNodes[offset - 1];
                                    if (prev && prev.nodeType === Node.ELEMENT_NODE && prev.classList.contains('mention-highlight')) {
                                        isAtWordBoundary = true;
                                    }
                                }
                            }
                        }
                    }
                   
                    
                    if (isAtWordBoundary) {
                        // We found @ at word boundary
                        const searchTerm = beforeCursorText.substring(atIndex + 1);
                       
                        this.mentionStartPosition = atIndex;
                        this.mentionSearchTerm = searchTerm;
                        this.currentInput = input;
                       
                        this.showDropdown();
                    } else {
                       
                        this.hideDropdown();
                    }
                } else {
                   
                    this.hideDropdown();
                }
            }
        } else {
            // For regular input elements
            const value = input.value;
            const cursorPosition = input.selectionStart;
            

            
            // Check if we're typing @
            const beforeCursor = value.substring(0, cursorPosition);
            const atIndex = beforeCursor.lastIndexOf('@');
            
            
            if (atIndex !== -1 && (atIndex === 0 || /\s/.test(value[atIndex - 1]))) {
                // We found @ at the beginning or after a space
                const searchTerm = beforeCursor.substring(atIndex + 1);
                this.mentionStartPosition = atIndex;
                this.mentionSearchTerm = searchTerm;
                this.currentInput = input;
                this.showDropdown();
            } else {
                this.hideDropdown();
            }
        }
    }
    
    handleKeydownEvent(event) {
        if (!this.showMentionDropdown) return;
        const key = event.key;
        // Handle dropdown navigation
        if (key === 'ArrowDown') {
            event.preventDefault();
            this.selectedMentionIndex = Math.min(this.selectedMentionIndex + 1, this.getFilteredUsers().length - 1);
            this.updateDropdownSelection();
        } else if (key === 'ArrowUp') {
            event.preventDefault();
            this.selectedMentionIndex = Math.max(this.selectedMentionIndex - 1, 0);
            this.updateDropdownSelection();
        } else if (key === 'Enter') {
            event.preventDefault();
            event.stopPropagation(); // Ngăn không bubble lên
            const filteredUsers = this.getFilteredUsers();
            if (filteredUsers[this.selectedMentionIndex]) {
                this.selectMention(filteredUsers[this.selectedMentionIndex]);
            }
            return false; // Ngăn không bubble lên
        } else if (key === 'Escape') {
            event.preventDefault();
            this.hideDropdown();
        }
    }
    
    handleGlobalClick(event) {
        // Check if click is outside dropdown
        if (this.dropdownElement && !this.dropdownElement.contains(event.target)) {
            this.hideDropdown();
        }
    }
    
    getFilteredUsers() {
        if (!this.mentionSearchTerm) {
            return this.mentionUsers;
        }
        
        const filtered = this.mentionUsers.filter(user => 
            user.user_name.toLowerCase().includes(this.mentionSearchTerm.toLowerCase())
        );
        
        return filtered;
    }
    
    showDropdown() {
        if (!this.currentInput) {
            return;
        }
        
        // Lưu lại selection range hiện tại
        const selection = window.getSelection();
        if (selection.rangeCount > 0) {
            this.savedRange = selection.getRangeAt(0).cloneRange();
        }
        
        const filteredUsers = this.getFilteredUsers();
        
        if (filteredUsers.length === 0) {
            this.hideDropdown();
            return;
        }
        
        this.selectedMentionIndex = 0;
        this.createDropdown();
        this.positionDropdown();
        
        // Show the dropdown
        if (this.dropdownElement) {
            this.dropdownElement.style.display = 'block';
        }
        
        this.showMentionDropdown = true;
    }
    
    createDropdown() {
        if (this.dropdownElement) {
            this.dropdownElement.remove();
        }
        
        this.dropdownElement = document.createElement('div');
        this.dropdownElement.className = this.options.dropdownClass;
        
        const filteredUsers = this.getFilteredUsers();
        
        this.dropdownElement.innerHTML = `
            <div class="mention-list">
                ${filteredUsers.map((user, index) => `
                    <div class="mention-item ${index === 0 ? 'active' : ''}" data-index="${index}">
                        <div class="d-flex align-items-center">
                            <div class="mention-avatar me-2">
                                ${this.getUserAvatar(user)}
                            </div>
                            <div class="mention-info">
                                <div class="mention-name">${user.user_name}</div>
                            </div>
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
        
        // Add click handlers
        this.dropdownElement.addEventListener('click', (event) => {
            const mentionItem = event.target.closest('.mention-item');
            if (mentionItem) {
                const index = parseInt(mentionItem.dataset.index);
                this.selectMention(filteredUsers[index]);
            }
        });
        
        document.body.appendChild(this.dropdownElement);
    }
    
    positionDropdown() {
        if (!this.currentInput || !this.dropdownElement) return;
        
        const inputRect = this.currentInput.getBoundingClientRect();
        const dropdownRect = this.dropdownElement.getBoundingClientRect();
        
        // Position below the input
        this.dropdownElement.style.position = 'absolute';
        this.dropdownElement.style.top = `${inputRect.bottom + window.scrollY}px`;
        this.dropdownElement.style.left = `${inputRect.left + window.scrollX}px`;
        this.dropdownElement.style.width = `${inputRect.width}px`;
        this.dropdownElement.style.zIndex = '99999';
    }
    
    updateDropdownSelection() {
        if (!this.dropdownElement) return;
        
        const items = this.dropdownElement.querySelectorAll('.mention-item');
        items.forEach((item, index) => {
            item.classList.toggle('active', index === this.selectedMentionIndex);
        });
    }
    
    selectMention(user) {
        if (!user || !this.currentInput) return;
        // Check if this is a contenteditable element
        const isContentEditable = this.currentInput.contentEditable === 'true' || 
                                 this.currentInput.hasAttribute('data-html-mention');
        if (isContentEditable) {
            // Focus lại input và restore selection nếu có
            this.currentInput.focus();
            const selection = window.getSelection();
            if (this.savedRange) {
                selection.removeAllRanges();
                selection.addRange(this.savedRange);
            }
            // Get current selection
            const currentSelection = window.getSelection();
            if (currentSelection.rangeCount > 0) {
                let range = currentSelection.getRangeAt(0);
                // Tìm vị trí @ trong text node trước con trỏ
                let node = range.startContainer;
                let offset = range.startOffset;
                let found = false;
                let atNode = null, atOffset = null;
                if (node.nodeType === Node.TEXT_NODE) {
                    // Tìm @ trong text node
                    const text = node.textContent;
                    const upToCursor = text.substring(0, offset);
                    const atPos = upToCursor.lastIndexOf('@');
                    if (atPos !== -1) {
                        atNode = node;
                        atOffset = atPos;
                        found = true;
                    }
                }
                if (!found) {
                    // Nếu không phải text node, thử tìm node trước
                    if (node.nodeType === Node.ELEMENT_NODE && offset > 0) {
                        const prev = node.childNodes[offset - 1];
                        if (prev && prev.nodeType === Node.TEXT_NODE) {
                            const text = prev.textContent;
                            const atPos = text.lastIndexOf('@');
                            if (atPos !== -1) {
                                atNode = prev;
                                atOffset = atPos;
                                offset = prev.length;
                                found = true;
                            }
                        }
                    }
                }
                if (found) {
                    // Tạo range từ @ đến vị trí con trỏ
                    const delRange = document.createRange();
                    delRange.setStart(atNode, atOffset);
                    delRange.setEnd(range.startContainer, range.startOffset);
                    delRange.deleteContents();
                    // Sau khi xóa, range sẽ tự động cập nhật
                }
                // Chèn mention tại vị trí con trỏ hiện tại
                const mentionHtml = `<span class="mention-highlight" contenteditable="false" data-user-id="${user.userid || user.id}" data-user-name="${user.user_name}" data-mention="true">@${user.user_name}</span>`;
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = mentionHtml;
                const mentionElement = tempDiv.firstElementChild;
                
                // Lấy range hiện tại sau khi đã xóa
                const currentRange = currentSelection.getRangeAt(0);
                currentRange.insertNode(mentionElement);
                
                // Sau khi chèn mentionElement
                currentRange.setStartAfter(mentionElement);
                currentRange.collapse(true);
                
                // Kiểm tra node tiếp theo của mentionElement
                let nextNode = mentionElement.nextSibling;
                if (!nextNode || nextNode.nodeType !== Node.TEXT_NODE || !nextNode.textContent.startsWith(' ')) {
                    // Nếu chưa có space, thì chèn vào
                    const spaceNode = document.createTextNode('\u00A0');
                    mentionElement.parentNode.insertBefore(spaceNode, nextNode);
                    nextNode = spaceNode;
                }
                
                // Đặt con trỏ vào sau space
                requestAnimationFrame(() => {
                    const range = document.createRange();
                    range.setStart(nextNode, 1); // sau space
                    range.collapse(true);
                    const sel = window.getSelection();
                    sel.removeAllRanges();
                    sel.addRange(range);
                    this.currentInput.focus();
                });
            }
        } else {
            // For regular inputs, use the original logic
            const value = this.currentInput.value;
            const beforeMention = value.substring(0, this.mentionStartPosition);
            const afterMention = value.substring(this.mentionStartPosition + this.mentionSearchTerm.length + 1);
            const mentionText = `@${user.user_name}`;
            const newValue = beforeMention + mentionText + ' ' + afterMention;
            this.currentInput.value = newValue;
            // Set cursor position after the mention
            const newPosition = beforeMention.length + mentionText.length + 1;
            this.currentInput.setSelectionRange(newPosition, newPosition);
            this.currentInput.focus();
        }
        
        // Trigger input event for Vue reactivity
        this.currentInput.dispatchEvent(new Event('input', { bubbles: true }));
        // Call custom callback if provided
        if (this.options.onMentionSelect) {
            this.options.onMentionSelect(user, this.currentInput);
        }
        this.hideDropdown();
    }
    
    setCursorPosition(position) {
        if (this.currentInput.tagName === 'TEXTAREA') {
            this.currentInput.setSelectionRange(position, position);
        } else {
            // For contenteditable elements
            const range = document.createRange();
            const selection = window.getSelection();
            
            // For contenteditable, we need to handle HTML content
            if (this.currentInput.contentEditable === 'true' || this.currentInput.hasAttribute('data-html-mention')) {
                // Find the text node and set position
                let currentPos = 0;
                const walker = document.createTreeWalker(
                    this.currentInput,
                    NodeFilter.SHOW_TEXT,
                    null,
                );
                
                let node;
                while (node = walker.nextNode()) {
                    if (currentPos + node.length >= position) {
                        range.setStart(node, position - currentPos);
                        range.setEnd(node, position - currentPos);
                        selection.removeAllRanges();
                        selection.addRange(range);
                        return;
                    }
                    currentPos += node.length;
                }
                
                // If we couldn't find the exact position, set to end
                if (this.currentInput.lastChild) {
                    range.selectNodeContents(this.currentInput);
                    range.collapse(false);
                    selection.removeAllRanges();
                    selection.addRange(range);
                }
            } else {
                // Fallback for other elements
                this.currentInput.setSelectionRange(position, position);
            }
        }
    }
    
    hideDropdown() {
        this.showMentionDropdown = false;
        this.selectedMentionIndex = 0;
        this.mentionSearchTerm = '';
        this.mentionStartPosition = -1;
        
        if (this.dropdownElement) {
            this.dropdownElement.remove();
            this.dropdownElement = null;
        }
    }
    
    getUserAvatar(user) {
        if (user.avatar && !user.avatarError) {
            return `<img class="rounded-circle" src="${user.avatar}" alt="${user.user_name}" width="24" height="24">`;
        } else {
            const initials = this.getInitials(user.user_name);
            return `<span class="avatar-initial rounded-circle bg-label-primary" style="width:24px;height:24px;display:inline-flex;align-items:center;justify-content:center;font-size:10px;">${initials}</span>`;
        }
    }
    
    getInitials(name) {
        if (!name) return '?';
        return name.split(' ').map(n => n.charAt(0)).join('').toUpperCase().substring(0, 2);
    }
    
    getRoleLabel(role) {
        const roles = {
            'administrator': '管理者',
            'manager': 'マネージャー',
            'member': 'メンバー',
            'viewer': '閲覧者'
        };
        return roles[role] || role;
    }
    
    // Static method to render mentions in content
    static renderMentions(content) {
        if (!content) return this.decodeHTML(content);
        // Updated regex to handle Unicode characters including Japanese
        return content.replace(/@([^\s]+)/g, '<span class="mention-highlight">@$1</span>');
    }

    decodeHTML(html) {
        if (!html) return '';
        const txt = document.createElement('textarea');
        txt.innerHTML = html;
        return txt.value;
      }
    
    // Method to set department ID dynamically
    setDepartmentId(departmentId) {
        this.options.departmentId = departmentId;
        this.loadMentionUsers();
    }
    
    // Method to destroy the instance
    destroy() {
        this.hideDropdown();
        // Remove event listeners and cleanup
        if (this.dropdownElement) {
            this.dropdownElement.remove();
        }
    }
    
    // New method to prevent editing inside mention highlights
    preventMentionEdit(event) {
        const target = event.target;
        
        // Check if we're inside a mention highlight
        if (target.classList.contains('mention-highlight') || 
            target.closest('.mention-highlight')) {
            
            // Allow navigation keys
            const allowedKeys = ['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End'];
            if (allowedKeys.includes(event.key)) {
                return; // Allow navigation
            }
            
            // Allow selection (Ctrl+A, Ctrl+C, etc.)
            if (event.ctrlKey || event.metaKey) {
                return; // Allow shortcuts
            }
            
            // Prevent editing
            if (event.key.length === 1 || event.key === 'Backspace' || event.key === 'Delete') {
                event.preventDefault();
                return false;
            }
        }
    }
}

// Auto-initialize for inputs with data-mention attribute
document.addEventListener('DOMContentLoaded', () => {
    if (typeof window !== 'undefined' && window.MentionManager) {
        // Đã khai báo, không khai báo lại
    } else {
        window.mentionManager = new MentionManager();
        window.MentionManager = MentionManager;
    }
});

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = MentionManager;
} 