/**
 * App Chat
 */
'use strict';

document.addEventListener('DOMContentLoaded', () => {
  const t = (typeof i18next !== 'undefined' && i18next.isInitialized && i18next.t) ? i18next.t.bind(i18next) : (k) => k;

  // DOM Elements
  const elements = {
    chatContactsBody: document.querySelector('.app-chat-contacts .sidebar-body'),
    chatHistoryBody: document.querySelector('.chat-history-body'),
    chatSidebarLeftBody: document.querySelector('.app-chat-sidebar-left .sidebar-body'),
    chatSidebarRightBody: document.querySelector('.app-chat-sidebar-right .sidebar-body'),
    chatUserStatus: [...document.querySelectorAll(".form-check-input[name='chat-user-status']")],
    chatSidebarLeftUserAbout: document.getElementById('chat-sidebar-left-user-about'),
    formSendMessage: document.querySelector('.form-send-message'),
    messageInput: document.querySelector('.message-input'),
    searchInput: document.querySelector('.chat-search-input'),
    chatContactListItems: [...document.querySelectorAll('.chat-contact-list-item:not(.chat-contact-list-item-title)')],
    textareaInfo: document.getElementById('textarea-maxlength-info'),
    conversationButton: document.getElementById('app-chat-conversation-btn'),
    chatHistoryHeader: document.querySelector(".chat-history-header [data-target='#app-chat-contacts']"),
    speechToText: $('.speech-to-text'),
    appChatConversation: document.getElementById('app-chat-conversation'),
    appChatHistory: document.getElementById('app-chat-history'),
    chatHistory: document.getElementById('chat-history'),
    userAvatar: document.querySelector('#user_avatar img')
  };

  const userStatusClasses = {
    active: 'avatar-online',
    offline: 'avatar-offline',
    away: 'avatar-away',
    busy: 'avatar-busy'
  };

  /** Perfect Scrollbar instance for .chat-history-body (used in scrollToBottom). */
  let chatHistoryPS = null;
  let isUserScrolling = false;
  let scrollToBottomTimeout = null;
  let lastScrollTop = 0;

  /**
   * Initialize Perfect Scrollbar on provided elements. chat-history-body gets its instance stored for scrollToBottom.
   */
  const initPerfectScrollbar = (elementList) => {
    elementList.forEach(el => {
      if (!el) return;
      const isChatHistoryBody = el.classList && el.classList.contains('chat-history-body');
      const ps = new PerfectScrollbar(el, {
        wheelPropagation: false,
        suppressScrollX: true
      });
      if (isChatHistoryBody) {
        chatHistoryPS = ps;
        // Detect user scrolling để tránh auto-scroll khi user đang scroll
        el.addEventListener('scroll', () => {
          const currentScrollTop = el.scrollTop;
          const sh = el.scrollHeight;
          const ch = el.clientHeight;
          const maxScroll = sh - ch;
          // Nếu user scroll lên trên (không phải ở bottom), đánh dấu là user đang scroll
          if (currentScrollTop < maxScroll - 10) {
            isUserScrolling = true;
            clearTimeout(scrollToBottomTimeout);
            scrollToBottomTimeout = setTimeout(() => {
              isUserScrolling = false;
            }, 1000);
          } else {
            isUserScrolling = false;
          }
          lastScrollTop = currentScrollTop;
        });
      }
    });
  };

  /**
   * Scroll chat-history-body to the bottom. When container is hidden, scrollHeight/clientHeight are 0 → retry when visible.
   * Throttled để tránh gọi quá nhiều lần gây giật.
   */
  const scrollToBottom = (retryCount = 0, force = false) => {
    // Không scroll nếu user đang scroll (trừ khi force)
    if (!force && isUserScrolling) {
      return;
    }
    
    const container = elements.chatHistoryBody;
    if (!container) return;
    const sh = container.scrollHeight;
    const ch = container.clientHeight;
    if (sh === 0 || ch === 0) {
      if (retryCount < 20) {
        requestAnimationFrame(() => scrollToBottom(retryCount + 1, force));
      }
      return;
    }
    const maxScroll = sh - ch;
    // Chỉ scroll nếu chưa ở bottom hoặc force
    if (force || Math.abs(container.scrollTop - maxScroll) > 5) {
      container.scrollTop = maxScroll;
      // Update Perfect Scrollbar sau khi scroll, nhưng debounce để tránh giật
      if (chatHistoryPS && typeof chatHistoryPS.update === 'function') {
        requestAnimationFrame(() => {
          chatHistoryPS.update();
        });
      }
    }
  };

  /**
   * Update user status avatar classes.
   * @param {string} status - Status key from userStatusClasses.
   */
  const updateUserStatus = status => {
    const leftSidebarAvatar = document.querySelector('.chat-sidebar-left-user .avatar');
    const contactsAvatar = document.querySelector('.app-chat-contacts .avatar');

    [leftSidebarAvatar, contactsAvatar].forEach(avatar => {
      if (avatar) avatar.className = avatar.className.replace(/avatar-\w+/, userStatusClasses[status]);
    });
  };

  // Handle textarea max length count.
  function handleMaxLengthCount(inputElement, infoElement, maxLength) {
    const currentLength = inputElement.value.length;
    const remaining = maxLength - currentLength;

    infoElement.className = 'maxLength label-success';

    if (remaining >= 0) {
      infoElement.textContent = `${currentLength}/${maxLength}`;
    }
    if (remaining <= 0) {
      infoElement.textContent = `${currentLength}/${maxLength}`;
      infoElement.classList.remove('label-success');
      infoElement.classList.add('label-danger');
    }
  }

  /**
   * Switch to chat conversation view.
   */
  const switchToChatConversation = () => {
    elements.appChatConversation.classList.replace('d-flex', 'd-none');
    elements.appChatHistory.classList.replace('d-none', 'd-block');
    requestAnimationFrame(() => {
      scrollToBottom(0, true);
    });
  };

  /**
   * Filter chat contacts by search input.
   * @param {string} selector - CSS selector for chat/contact list items.
   * @param {string} searchValue - Search input value.
   * @param {string} placeholderSelector - Selector for placeholder element.
   */
  const filterChatContacts = (selector, searchValue, placeholderSelector) => {
    const items = document.querySelectorAll(`${selector}:not(.chat-contact-list-item-title)`);
    let visibleCount = 0;

    items.forEach(item => {
      const isVisible = item.textContent.toLowerCase().includes(searchValue);
      item.classList.toggle('d-flex', isVisible);
      item.classList.toggle('d-none', !isVisible);
      if (isVisible) visibleCount++;
    });

    document.querySelector(placeholderSelector)?.classList.toggle('d-none', visibleCount > 0);
  };

  /**
   * Initialize speech-to-text functionality.
   */
  const initSpeechToText = () => {
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SpeechRecognition || elements.speechToText.length === 0) return;

    const recognition = new SpeechRecognition();
    let listening = false;

    elements.speechToText.on('click', function () {
      if (!listening) recognition.start();
      recognition.onspeechstart = () => (listening = true);
      recognition.onresult = event => {
        $(this).closest('.form-send-message').find('.message-input').val(event.results[0][0].transcript);
      };
      recognition.onspeechend = () => (listening = false);
      recognition.onerror = () => (listening = false);
    });
  };

  // Initialize PerfectScrollbar
  initPerfectScrollbar([
    elements.chatContactsBody,
    elements.chatHistoryBody,
    elements.chatSidebarLeftBody,
    elements.chatSidebarRightBody
  ]);

  // Chỉ scroll xuống cuối khi khung chat vừa chuyển từ ẩn (height=0) sang hiện (có size), không ép scroll mỗi lần resize
  if (elements.chatHistoryBody && typeof ResizeObserver !== 'undefined') {
    let lastHeight = 0;
    let resizeTimeout = null;
    const ro = new ResizeObserver(() => {
      const ch = elements.chatHistoryBody.clientHeight;
      // Debounce resize để tránh gọi quá nhiều
      clearTimeout(resizeTimeout);
      resizeTimeout = setTimeout(() => {
        if (lastHeight === 0 && ch > 0) {
          scrollToBottom(0, true);
        }
        lastHeight = ch;
      }, 100);
    });
    ro.observe(elements.chatHistoryBody);
  }

  scrollToBottom(0, true);

  // Attach user status change event
  elements.chatUserStatus.forEach(statusInput => {
    statusInput.addEventListener('click', () => updateUserStatus(statusInput.value));
  });

  // Handle max length for textarea
  // const maxLength = parseInt(elements.chatSidebarLeftUserAbout.getAttribute('maxlength'), 10);
  // handleMaxLengthCount(elements.chatSidebarLeftUserAbout, elements.textareaInfo, maxLength);

  // elements.chatSidebarLeftUserAbout.addEventListener('input', () => {
  //   handleMaxLengthCount(elements.chatSidebarLeftUserAbout, elements.textareaInfo, maxLength);
  // });

  // Attach chat conversation switch event
  elements.conversationButton?.addEventListener('click', switchToChatConversation);

  // Attach chat contact selection event
  elements.chatContactListItems.forEach(item => {
    item.addEventListener('click', () => {
      elements.chatContactListItems.forEach(contact => contact.classList.remove('active'));
      item.classList.add('active');
      switchToChatConversation();
    });
  });

  // Attach chat search filter event
  elements.searchInput?.addEventListener(
    'keyup',
    debounce(e => {
      const searchValue = e.target.value.toLowerCase();
      filterChatContacts('#chat-list li', searchValue, '.chat-list-item-0');
      filterChatContacts('#contact-list li', searchValue, '.contact-list-item-0');
    }, 300)
  );

  // Attach message send event
  elements.formSendMessage?.addEventListener('submit', e => {
    e.preventDefault();
    const message = elements.messageInput.value.trim();
    if (message) {
      sendMessage(message);
    }
  });

  // Ô nhập nhiều dòng: Enter = gửi, Shift+Enter = xuống dòng
  elements.messageInput?.addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      if (elements.formSendMessage?.requestSubmit) {
        elements.formSendMessage.requestSubmit();
      } else {
        const msg = elements.messageInput.value.trim();
        if (msg) sendMessage(msg);
      }
    }
  });

  // Tự động tăng chiều cao textarea khi nhập (giới hạn max-height)
  elements.messageInput?.addEventListener('input', function autoResizeTextarea() {
    const el = this;
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 120) + 'px';
  });

  function sendMessage(message) {
    let html = generateMessage(message, true);
    elements.chatHistory.innerHTML += html;
    elements.messageInput.value = '';
    if (elements.messageInput.style) {
      elements.messageInput.style.height = 'auto';
    }
    scrollToBottom(0, true);
    aiChat(message);
  }

  function aiChat(message, projectsData = null, departmentId = null) {
    if (!message) return;
    var loadingElement = elements.chatHistory.querySelector('.loading-message');
    if (loadingElement) {
      loadingElement.remove();
    }
    let loadingHtml = generateLoadingMessage();
    elements.chatHistory.innerHTML += loadingHtml;
    scrollToBottom(0, true);
    // Page context (e.g. project_id from current URL) – set by project/detail.php, task.php, etc.
    const pageContext = (typeof window.__chatPageContext === 'object' && window.__chatPageContext !== null)
      ? { ...window.__chatPageContext }
      : {};
    // Chỉ gửi dữ liệu hiện tại của trang (page_projects, page_project, page_tasks) khi câu hỏi có "trong trang", "trên trang", "ở trang này"...
    var msgNorm = (message || '').toLowerCase().replace(/\s+/g, ' ').trim();
    var askAboutCurrentPage = /\b(trong trang|trên trang|tren trang|ở trang|o trang|trang này|trang nay|hiện tại trên trang|hien tai tren trang|đang hiển thị|dang hien thi|trên màn hình|tren man hinh|このページ|ページに|今表示)/.test(msgNorm);
    if (askAboutCurrentPage) {
      // Trên trang project list: lấy lại dữ liệu bảng hiện tại ngay trước khi gửi
      if (pageContext.page === 'project_list') {
        try {
          var isDT = typeof $ !== 'undefined' && $ && $.fn && $.fn.DataTable && $.fn.DataTable.isDataTable && $('#projectTable').length && $.fn.DataTable.isDataTable('#projectTable');
          if (typeof window.projectTable !== 'undefined' && window.projectTable && isDT) {
            var rows = window.projectTable.rows({ search: 'applied' }).data();
            pageContext.page_projects = Array.isArray(rows) ? Array.from(rows) : (rows ? Array.from(rows) : []);
          }
        } catch (e) { /* ignore */ }
      }
      // page_project / page_tasks giữ nguyên từ __chatPageContext (đã set bởi project-detail.js, task-manager.js)
    } else {
      delete pageContext.page_projects;
      delete pageContext.page_project;
      delete pageContext.page_tasks;
    }
    // Nếu có projectsData từ project_search, thêm vào context
    const requestBody = { message, context: pageContext };
    if (projectsData && Array.isArray(projectsData) && projectsData.length > 0) {
      requestBody.projects = projectsData;
    }
    if (departmentId != null && departmentId !== '') {
      requestBody.department_id = parseInt(departmentId, 10);
    }

    // Send message to server
    fetch('/ai/index.php?method=chat', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(requestBody),
    })
        .then(function (response) {
          const ct = (response.headers.get('Content-Type') || '').toLowerCase();
          if (!ct.includes('application/json')) {
            return response.text().then(function (text) {
              throw new Error('Server returned non-JSON (status ' + response.status + ')');
            });
          }
          return response.json();
        })
        .then(data => {
          // Display AI response
          let aiMessage = '';

          // Check if candidates exist in the response
          if (data.candidates && data.candidates.length > 0) {
              const parts = data.candidates[0].content.parts; // Access the parts array
              //format the response
              
              aiMessage = parts.map(part => {
                const hasTable = (part.text && part.text.includes && part.text.includes('<table'));
                let formattedText = part.text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
                formattedText = formattedText.replace(/\n/g, '<br>');
                if (hasTable) {
                  return '<div class="table-responsive">' + formattedText + '</div>';
                }
                return `<p class="mb-0">${formattedText}</p>`;
              }).join(''); // Display all parts

              // Bảng có thể nằm ngoài table-responsive (ví dụ từ history hoặc part có text trước table)
              if (aiMessage.indexOf('<table') !== -1 && aiMessage.indexOf('table-responsive') === -1) {
                aiMessage = '<div class="table-responsive">' + aiMessage + '</div>';
              }
              //remove <p> with empty text
              aiMessage = aiMessage.replace(/<p class="mb-0"><\/p>/g, '');
              
              // Fix HTML structure to prevent layout breaking
              aiMessage = fixHtmlStructure(aiMessage);
          } else {
              aiMessage = 'エラーが発生しました';
          }
          let html = generateMessage(aiMessage, false);
          elements.chatHistory.innerHTML += html;
          
          // Khi backend yêu cầu chọn phòng ban (department_id null), hiển thị nút chọn; sau khi chọn gửi lại message kèm department_id (không hỏi lại)
          if (data.ask_department && data.departments && Array.isArray(data.departments) && data.departments.length > 0) {
            const li = elements.chatHistory.querySelector('li:last-child');
            const wrapper = li ? li.querySelector('.chat-message-wrapper') : null;
            if (wrapper) {
              const deptBlock = document.createElement('div');
              deptBlock.className = 'mt-2 pt-2 border-top';
              const label = document.createElement('small');
              label.className = 'text-muted d-block mb-1';
              label.textContent = t('Chọn phòng ban') || 'Chọn phòng ban';
              deptBlock.appendChild(label);
              const btnGroup = document.createElement('div');
              btnGroup.className = 'd-flex flex-wrap gap-1';
              data.departments.forEach(function (dept) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn btn-sm btn-outline-primary btn-chat-department';
                btn.textContent = dept.name || ('ID ' + dept.id);
                btn.dataset.departmentId = String(dept.id);
                btn.addEventListener('click', function () {
                  const deptId = this.dataset.departmentId;
                  deptBlock.remove();
                  aiChat(message, null, deptId);
                });
                btnGroup.appendChild(btn);
              });
              deptBlock.appendChild(btnGroup);
              wrapper.appendChild(deptBlock);
            }
          }

          // Khi backend trả ask_task_form: hiển thị form tạo task (giống UI confirm)
          if (data.ask_task_form && data.project_id) {
            const li = elements.chatHistory.querySelector('li:last-child');
            const wrapper = li ? li.querySelector('.chat-message-wrapper') : null;
            if (wrapper) {
              const formBlock = document.createElement('div');
              formBlock.className = 'mt-2 p-2 border rounded bg-light task-form-block';
              formBlock.dataset.projectId = String(data.project_id);
              const lblId = t('ID') || 'ID';
              const lblTitle = t('Tiêu đề') || 'Tiêu đề';
              const lblStart = t('Thời gian bắt đầu') || 'Thời gian bắt đầu';
              const lblEnd = t('Thời gian kết thúc') || 'Thời gian kết thúc';
              const lblAssign = t('Phân công cho') || 'Phân công cho';
              const lblPrimaryMe = t('Phân công chính (tôi)') || 'Phân công chính (tôi)';
              const lblPriority = t('Mức ưu tiên') || 'Mức ưu tiên';
              const btnCreate = t('Tạo task') || 'Tạo task';
              const btnCancel = t('Hủy') || 'Hủy';
              const projectId = parseInt(formBlock.dataset.projectId, 10);
              formBlock.innerHTML =
                '<div class="mb-2"><label class="form-label small mb-0">' + lblId + '</label><div class="form-control form-control-sm bg-light">' + projectId + '</div></div>' +
                '<div class="mb-2"><label class="form-label small mb-0">' + lblTitle + ' <span class="text-danger">*</span></label><input type="text" class="form-control form-control-sm task-form-title" placeholder="' + lblTitle + '" required></div>' +
                '<div class="mb-2"><label class="form-label small mb-0">' + lblStart + '</label><input type="datetime-local" class="form-control form-control-sm task-form-start" step="60"></div>' +
                '<div class="mb-2"><label class="form-label small mb-0">' + lblEnd + '</label><input type="datetime-local" class="form-control form-control-sm task-form-due" step="60"></div>' +
                '<div class="mb-2"><label class="form-label small mb-0">' + lblAssign + '</label><input type="text" class="form-control form-control-sm task-form-assign" placeholder="Tên người (vd: Thom)"></div>' +
                '<div class="mb-2"><div class="form-check"><input type="checkbox" class="form-check-input task-form-primary-me" id="task-form-primary-me-' + projectId + '"><label class="form-check-label small" for="task-form-primary-me-' + projectId + '">' + lblPrimaryMe + '</label></div></div>' +
                '<div class="mb-2"><label class="form-label small mb-0">' + lblPriority + '</label><select class="form-select form-select-sm task-form-priority"><option value="">—</option><option value="low">' + (t('Thấp') || 'Thấp') + '</option><option value="medium" selected>' + (t('Trung bình') || 'Trung bình') + '</option><option value="high">' + (t('Cao') || 'Cao') + '</option><option value="urgent">' + (t('Khẩn cấp') || 'Khẩn cấp') + '</option></select></div>' +
                '<div class="d-flex gap-1"><button type="button" class="btn btn-sm btn-primary btn-task-form-submit">' + btnCreate + '</button><button type="button" class="btn btn-sm btn-outline-secondary btn-task-form-cancel">' + btnCancel + '</button></div>';
              wrapper.appendChild(formBlock);

              // Điền sẵn giá trị từ Gemini (initial_values); datetime-local cần format YYYY-MM-DDTHH:mm
              const iv = data.initial_values && typeof data.initial_values === 'object' ? data.initial_values : {};
              const toDateTimeLocal = function (val, defaultTime) {
                if (val == null || val === '') return '';
                var s = String(val).trim();
                if (s.indexOf('T') !== -1) return s.substring(0, 16);
                if (s.indexOf(' ') !== -1) return s.replace(' ', 'T').substring(0, 16);
                if (s.length >= 10) return s.substring(0, 10) + (defaultTime ? 'T' + defaultTime : '');
                return s;
              };
              if (iv.title != null && iv.title !== '') formBlock.querySelector('.task-form-title').value = String(iv.title).trim();
              var startVal = toDateTimeLocal(iv.start_date, '09:00');
              if (startVal) formBlock.querySelector('.task-form-start').value = startVal;
              var dueVal = toDateTimeLocal(iv.due_date, '17:00');
              if (dueVal) formBlock.querySelector('.task-form-due').value = dueVal;
              if (iv.assigned_to != null && iv.assigned_to !== '') formBlock.querySelector('.task-form-assign').value = String(iv.assigned_to).trim();
              if (iv.primary_assignee === true || iv.primary_assignee === 'true') formBlock.querySelector('.task-form-primary-me').checked = true;
              var pri = (iv.priority && String(iv.priority).toLowerCase()) || '';
              if (['low', 'medium', 'high', 'urgent'].indexOf(pri) !== -1) formBlock.querySelector('.task-form-priority').value = pri;

              formBlock.querySelector('.btn-task-form-submit').addEventListener('click', function () {
                const title = (formBlock.querySelector('.task-form-title').value || '').trim();
                if (!title) {
                  formBlock.querySelector('.task-form-title').focus();
                  return;
                }
                const startDate = (formBlock.querySelector('.task-form-start').value || '').trim();
                const dueDate = (formBlock.querySelector('.task-form-due').value || '').trim();
                const assignedTo = (formBlock.querySelector('.task-form-assign').value || '').trim();
                const primaryAssignMe = formBlock.querySelector('.task-form-primary-me') && formBlock.querySelector('.task-form-primary-me').checked;
                const priority = (formBlock.querySelector('.task-form-priority').value || '').trim();
                const projectId = parseInt(formBlock.dataset.projectId, 10);
                const pageContext = (typeof window.__chatPageContext === 'object' && window.__chatPageContext !== null)
                  ? { ...window.__chatPageContext }
                  : {};
                pageContext.project_id = projectId;
                formBlock.remove();
                const loadingHtml = generateLoadingMessage();
                elements.chatHistory.innerHTML += loadingHtml;
                scrollToBottom(0, true);
                const requestBody = {
                  message: '',
                  context: pageContext,
                  task_form_data: {
                    project_id: projectId,
                    title: title,
                    start_date: startDate,
                    due_date: dueDate,
                    assigned_to: assignedTo,
                    primary_assignee: primaryAssignMe,
                    priority: priority
                  }
                };
                fetch('/ai/index.php?method=chat', {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify(requestBody)
                })
                  .then(function (r) {
                    const ct = (r.headers.get('Content-Type') || '').toLowerCase();
                    if (!ct.includes('application/json')) {
                      return r.text().then(function () { throw new Error('Server returned non-JSON'); });
                    }
                    return r.json();
                  })
                  .then(function (res) {
                    const loadingEl = elements.chatHistory.querySelector('.loading-message');
                    if (loadingEl) loadingEl.remove();
                    let resultText = '';
                    if (res.candidates && res.candidates.length > 0 && res.candidates[0].content && res.candidates[0].content.parts && res.candidates[0].content.parts[0]) {
                      resultText = res.candidates[0].content.parts[0].text || '';
                    }
                    if (res.error) {
                      resultText = (resultText || '') + (resultText ? ' ' : '') + (res.error || '');
                    }
                    if (!resultText) {
                      resultText = res.task_created ? (t('Đã tạo task.') || 'Đã tạo task.') : (t('エラーが発生しました') || 'エラーが発生しました');
                    }
                    const resultHtml = generateMessage(resultText.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>').replace(/\n/g, '<br>'), false);
                    elements.chatHistory.innerHTML += resultHtml;
                    scrollToBottom(0, true);
                  })
                  .catch(function (err) {
                    const loadingEl = elements.chatHistory.querySelector('.loading-message');
                    if (loadingEl) loadingEl.remove();
                    const resultHtml = generateMessage('✗ ' + (t('接続エラー') || '接続エラー'), false);
                    elements.chatHistory.innerHTML += resultHtml;
                    scrollToBottom(0, true);
                    console.error('task form submit error:', err);
                  });
              });

              formBlock.querySelector('.btn-task-form-cancel').addEventListener('click', function () {
                formBlock.remove();
                const cancelledText = t('Đã hủy') || 'Đã hủy';
                const cancelledHtml = generateMessage(cancelledText, false);
                li.insertAdjacentHTML('afterend', cancelledHtml);
                scrollToBottom(0, true);
              });
            }
          }
          
          // Support both single action (backward compatible) and multiple actions
          const pendingActions = (data.pending_actions && Array.isArray(data.pending_actions)) 
            ? data.pending_actions 
            : (data.pending_action && typeof data.pending_action === 'object' ? [data.pending_action] : []);
          
          if (pendingActions.length > 0) {
            const pendingAction = pendingActions[0]; // Keep for backward compatibility in event handlers
            const allScript = pendingActions.every(function (a) { return a && a.type === 'script'; });
            const li = elements.chatHistory.querySelector('li:last-child');
            const wrapper = li ? li.querySelector('.chat-message-wrapper') : null;
            if (wrapper) {
              const confirmBlock = document.createElement('div');
              confirmBlock.className = 'mt-2 p-2 border rounded bg-light';
              const actionCountText = allScript
                ? (pendingActions.length > 1 ? t('{count}件のページへ移動しますか？').replace('{count}', pendingActions.length) : t('このページへ移動しますか？'))
                : (pendingActions.length > 1 ? t('{count}件の変更を実行しますか？').replace('{count}', pendingActions.length) : t('変更を実行しますか？'));
              confirmBlock.innerHTML = '<small class="text-muted">' + actionCountText + '</small><br class="mb-1"/><button type="button" class="btn btn-sm btn-primary me-1 btn-confirm-action">' + t('確認') + '</button><button type="button" class="btn btn-sm btn-outline-secondary btn-cancel-action">' + t('キャンセル') + '</button>';
              wrapper.appendChild(confirmBlock);
            }
            if (li) {
              li.querySelector('.btn-confirm-action')?.addEventListener('click', function () {
                if (allScript && pendingActions.length > 0) {
                  var act = pendingActions[0];
                  var params = act.params || {};
                  var url = params.url;
                  if (!url && params.project_id != null) {
                    var page = (params.page || '').toLowerCase();
                    var base = window.location.origin + '/project/';
                    if (page === 'task') {
                      url = base + 'task.php?project_id=' + params.project_id;
                    } else if (page === 'gantt') {
                      url = base + 'gantt.php?project_id=' + params.project_id;
                    } else if (page === 'drawings') {
                      url = base + 'drawings.php?project_id=' + params.project_id;
                    } else if (page === 'attachment') {
                      url = base + 'attachment.php?project_id=' + params.project_id;
                    } else {
                      url = base + 'detail.php?id=' + params.project_id;
                    }
                  } else if (url && url.indexOf('http') !== 0) {
                    url = window.location.origin + (url.indexOf('/') === 0 ? url : '/' + url);
                  }
                  if (url) {
                    var block = li.querySelector('.bg-light');
                    if (block) block.remove();
                    window.location.href = url;
                    return;
                  }
                }
                // Send single action or multiple actions based on count
                const requestBody = pendingActions.length === 1
                  ? { action: pendingActions[0] }
                  : { actions: pendingActions };
                
                fetch('/ai/index.php?method=execute_action', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(requestBody) })
                  .then(function (r) {
                    const ct = (r.headers.get('Content-Type') || '').toLowerCase();
                    if (!ct.includes('application/json')) {
                      return r.text().then(function () {
                        throw new Error('Server returned non-JSON');
                      });
                    }
                    return r.json();
                  })
                  .then(res => {
                    const block = li.querySelector('.bg-light');
                    if (block) block.remove();
                    const doneLabel = t('実行しました。');
                    const errorLabel = t('エラー');
                    let resultText = '';
                    if (res.status === 'success') {
                      resultText = '✓ ' + (res.message || doneLabel);
                    } else if (res.status === 'partial') {
                      resultText = '⚠ ' + (res.message || t('一部の操作が失敗しました'));
                    } else {
                      resultText = '✗ ' + (res.error || res.message || errorLabel);
                    }
                    if (res.links && Array.isArray(res.links) && res.links.length > 0) {
                      var taskLinks = [];
                      var projectLinks = [];
                      res.links.forEach(function (l) {
                        var url = (l.url && l.url.indexOf('/') === 0) ? (window.location.origin + l.url) : (l.url || '#');
                        var a = '<a href="' + url + '" target="_blank" rel="noopener">' + (l.label || l.url) + '</a>';
                        if (l.type === 'task') taskLinks.push(a);
                        else projectLinks.push(a);
                      });
                      var parts = [];
                      if (taskLinks.length) parts.push((taskLinks.length > 1 ? 'Task: ' : '') + taskLinks.join(' · '));
                      if (projectLinks.length) parts.push((projectLinks.length > 1 ? 'Dự án: ' : '') + projectLinks.join(' · '));
                      resultText += '<br><span class="text-muted small">' + parts.join(' | ') + '</span>';
                    }
                    const resultHtml = generateMessage(resultText.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>').replace(/\n/g, '<br>'), false);
                    li.insertAdjacentHTML('afterend', resultHtml);
                    scrollToBottom(0, true);
                    if (res.status === 'success' || res.status === 'partial') {
                      window.dispatchEvent(new CustomEvent('ai-action-success', { detail: { actions: pendingActions, response: res } }));
                      
                      // Nếu có project_search action và có projects, tự động gửi lại request với projects để Gemini trả lời về tiến độ/thông tin
                      const searchAction = pendingActions.find(a => a.type === 'project_search');
                      if (searchAction && res.projects && Array.isArray(res.projects) && res.projects.length > 0) {
                        // Tìm message gốc của user (message trước message AI hiện tại)
                        let originalMessage = '';
                        const allMessages = elements.chatHistory.querySelectorAll('li.chat-message');
                        for (let i = allMessages.length - 1; i >= 0; i--) {
                          if (allMessages[i].classList.contains('chat-message-right')) {
                            const msgText = allMessages[i].querySelector('.chat-message-text');
                            if (msgText) {
                              originalMessage = msgText.textContent.trim();
                              break;
                            }
                          }
                        }
                        // Nếu không tìm thấy, dùng message mặc định
                        if (!originalMessage) {
                          originalMessage = 'これらの案件の進捗状況を教えてください';
                        }
                        // Gửi lại request với projects data
                        setTimeout(() => {
                          aiChat(originalMessage, res.projects);
                        }, 500);
                      }
                    }
                  })
                  .catch(function (err) {
                    const b = li.querySelector('.bg-light');
                    if (b) b.remove();
                    const connErr = t('接続エラー');
                    const resultHtml = generateMessage('✗ ' + connErr, false);
                    li.insertAdjacentHTML('afterend', resultHtml);
                    scrollToBottom(0, true);
                    console.error('execute_action error:', err);
                  });
              });
              li.querySelector('.btn-cancel-action')?.addEventListener('click', function () {
                const block = li.querySelector('.bg-light');
                if (block) block.remove();
                const cancelledText = t('変更をキャンセルしました');
                const cancelledHtml = generateMessage(cancelledText, false);
                li.insertAdjacentHTML('afterend', cancelledHtml);
                scrollToBottom(0, true);
              });
            }
          }
          scrollToBottom(0, true);
          var loadingElement = elements.chatHistory.querySelector('.loading-message');
          if (loadingElement) {
            loadingElement.remove();
          }
        })
        .catch(function (error) {
            console.error('Chat error:', error);
            let html = generateMessage('エラーが発生しました', false);
            elements.chatHistory.innerHTML += html;
            scrollToBottom(0, true);
            var loadingElement = elements.chatHistory.querySelector('.loading-message');
            if (loadingElement) {
              loadingElement.remove();
            }
        });
  }

  function generateMessage(message, isUser = false) {
    if (!message || (typeof message === 'string' && message.trim() === '')) {
      return ''; // Không render message rỗng
    }
    let time = new Date().toLocaleTimeString();
    // Tin nhắn user: escape HTML và xuống dòng \n → <br>. Tin nhắn AI: giữ HTML từ Gemini
    let displayContent = message;
    if (isUser && typeof message === 'string') {
      const div = document.createElement('div');
      div.textContent = message;
      displayContent = div.innerHTML.replace(/\n/g, '<br>');
    }
    let html = `<li class="chat-message ${isUser ? 'chat-message-right' : ''}">
        <div class="d-flex overflow-hidden gap-4 ${isUser ? 'flex-row-reverse' : ''}">
          <div class="user-avatar flex-shrink-0">
            <div class="avatar avatar-sm">
              <img src="${isUser ? (elements.userAvatar?.src || '') : '/assets/img/avatars/ai.png'}" alt="Avatar" class="rounded-circle" />
            </div>
          </div>
          <div class="chat-message-wrapper flex-grow-1">
            <div class="chat-message-text">
              <div class="mb-0">${displayContent}</div>
            </div>
            <div class="text-body-secondary mt-1">
              <small>${time}</small>
            </div>
          </div>
        </div>
      </li>`;

    return html;
  }

  function generateLoadingMessage() {
    let html = `<li class="chat-message loading-message">
      <div class="d-flex overflow-hidden">
        <div class="spinner-border spinner-border-sm text-primary" role="status">
          <span class="visually-hidden">Loading...</span>
        </div>
      </div>
    </li>`;


    return html;
  }
  

  /**
   * Load chat history from server and render (on page load / reload).
   */
  function loadChatHistory() {
    if (!elements.chatHistory) return;
    fetch('/ai/index.php?method=get_chat_history')
      .then(r => {
        if (!r.ok) {
          console.error('Failed to load chat history:', r.status);
          return { history: [] };
        }
        return r.json();
      })
      .then(data => {
        const history = data.history || [];
        if (history.length === 0) {
          console.log('No chat history found');
          return;
        }
        console.log('Loading chat history:', history.length, 'items');
        elements.chatHistory.innerHTML = '';
        history.forEach(item => {
          const isUser = item.role === 'user';
          let message = '';
          
          // Parse user message
          if (isUser) {
            if (item.content && typeof item.content === 'object') {
              if (item.content.text) {
                message = String(item.content.text);
              } else if (Array.isArray(item.content) && item.content.length > 0 && item.content[0].text) {
                message = String(item.content[0].text);
              }
            } else if (typeof item.content === 'string') {
              message = item.content;
            }
          } 
          // Parse model message
          else {
            if (item.content && typeof item.content === 'object') {
              // Check for parts array
              if (item.content.parts && Array.isArray(item.content.parts)) {
                message = item.content.parts.map(p => {
                  if (typeof p === 'object' && p.text) {
                    return String(p.text);
                  } else if (typeof p === 'string') {
                    return p;
                  }
                  return '';
                }).join('');
              } 
              // Check if content is array directly
              else if (Array.isArray(item.content)) {
                message = item.content.map(p => {
                  if (typeof p === 'object' && p.text) {
                    return String(p.text);
                  } else if (typeof p === 'string') {
                    return p;
                  }
                  return '';
                }).join('');
              }
              // Check for text field directly
              else if (item.content.text) {
                message = String(item.content.text);
              }
            } else if (typeof item.content === 'string') {
              message = item.content;
            }
            
            // Format message (only if not empty)
            if (message) {
              message = message.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>').replace(/\n/g, '<br>');
              message = message.replace(/<p class="mb-0"><\/p>/g, '');
              // Fix HTML structure to prevent layout breaking
              message = fixHtmlStructure(message);
              if (message.indexOf('<table') !== -1 && message.indexOf('table-responsive') === -1) {
                message = '<div class="table-responsive">' + message + '</div>';
              }
            }
          }
          
          // Only render if message is not empty
          if (message && message.trim() !== '') {
            const html = generateMessage(message, isUser);
            elements.chatHistory.insertAdjacentHTML('beforeend', html);
          }
        });
        requestAnimationFrame(() => {
          scrollToBottom(0, true);
        });
      })
      .catch(() => {});
  }

  // Restore chat history when page loads (e.g. after reload)
  loadChatHistory();

  // Fix overlay issue for chat sidebar
  elements.chatHistoryHeader?.addEventListener('click', () => {
    document.querySelector('.app-chat-sidebar-left .close-sidebar')?.removeAttribute('data-overlay');
  });

  // Initialize speech-to-text
  initSpeechToText();
});

/**
 * Fix HTML structure by ensuring tags are properly closed.
 * Uses DOM parser to automatically fix structural issues, then validates common tags.
 * @param {string} html - HTML string to fix.
 * @returns {string} - Fixed HTML string.
 */
function fixHtmlStructure(html) {
  if (!html || typeof html !== 'string') return html;
  
  try {
    // Use a temporary DOM element to parse and fix HTML
    // The browser's HTML parser will automatically fix many structural issues
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = html;
    
    // Get the fixed HTML from the parsed DOM
    let fixedHtml = tempDiv.innerHTML;
    
    // Additional fix: Remove orphaned closing tags at the end
    // This handles cases where Gemini returns extra closing tags
    // We'll check for common tags that might be orphaned
    const commonTags = ['div', 'p', 'span'];
    
    commonTags.forEach(tag => {
      const openRegex = new RegExp(`<${tag}[^>]*>`, 'gi');
      const closeRegex = new RegExp(`</${tag}>`, 'gi');
      const openMatches = html.match(openRegex) || [];
      const closeMatches = html.match(closeRegex) || [];
      
      // If there are more closing tags than opening tags, remove excess from the end
      if (closeMatches.length > openMatches.length) {
        const excess = closeMatches.length - openMatches.length;
        // Remove excess closing tags from the end
        for (let i = 0; i < excess; i++) {
          const lastIndex = fixedHtml.lastIndexOf(`</${tag}>`);
          if (lastIndex !== -1) {
            fixedHtml = fixedHtml.substring(0, lastIndex) + fixedHtml.substring(lastIndex + `</${tag}>`.length);
          } else {
            break;
          }
        }
      }
    });
    
    return fixedHtml;
  } catch (e) {
    // If parsing fails, return original HTML
    console.warn('Failed to fix HTML structure:', e);
    return html;
  }
}

/**
 * Debounce utility function.
 * @param {Function} func - Function to debounce.
 * @param {number} wait - Delay in milliseconds.
 */
function debounce(func, wait) {
  let timeout;
  return (...args) => {
    clearTimeout(timeout);
    timeout = setTimeout(() => func.apply(this, args), wait);
  };
}


