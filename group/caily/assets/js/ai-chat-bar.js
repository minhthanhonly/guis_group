/**
 * AI assistant bar at the bottom of the screen.
 *
 * Everything the assistant or the server sends is shown either as plain text (textContent) or through
 * renderMarkdown(), which escapes all HTML first and only then builds bold, code, lists, tables and
 * same-site links. Nothing from the server is ever assigned to innerHTML unescaped.
 */
(function (root, factory) {
  var api = factory();
  if (typeof module === 'object' && module.exports) {
    module.exports = api;
  } else {
    root.AiChatBar = api;
  }
  if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', api.init);
    } else {
      api.init();
    }
  }
})(typeof self !== 'undefined' ? self : this, function () {
  'use strict';

  // ---- Pure helpers (also used by tests/ai/render.test.js) ----

  // Links and redirects may only go to these page addresses. A general "same site" rule is not enough:
  // /api/index.php?model=...&method=... changes data on a plain GET, so one click on a link written
  // by the model would act without the confirmation card.
  // Keep this list in step with AiTools::PAGES, ID_PAGES, STATIC_PAGES and the page tools (aitools.php).
  var QUERY = '\\?[A-Za-z0-9_=&%.,\\-]';
  var SAFE_URLS = [
    // one record, by id
    /^\/project\/detail\.php\?id=\d{1,9}$/,
    /^\/project\/(?:task|gantt|drawings|attachment)\.php\?project_id=\d{1,9}$/,
    /^\/parent_project\/(?:detail|attachment)\.php\?id=\d{1,9}$/,
    /^\/form\/detail\.php\?id=\d{1,9}$/,
    // list pages opened with filters in the query
    new RegExp('^/project/index\\.php' + QUERY + '{1,1500}$'),
    new RegExp('^/project/task_overview\\.php(?:' + QUERY + '{1,600})?$'),
    new RegExp('^/form/index\\.php(?:' + QUERY + '{1,200})?$'),
    new RegExp('^/parent_project/index\\.php' + QUERY + '{1,600}$'),
    // pages opened as they are
    /^\/project\/(?:project_gantt|employee_statistics)\.php$/,
    /^\/(?:parent_project|timecard|schedule|customer|forum|storage)\/index\.php$/,
    /^\/member\/online\.php$/
  ];

  function isSafeUrl(url) {
    if (typeof url !== 'string') {
      return false;
    }
    for (var i = 0; i < SAFE_URLS.length; i++) {
      if (SAFE_URLS[i].test(url)) {
        return true;
      }
    }
    return false;
  }

  function esc(text) {
    return String(text)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  /** Bold, inline code and same-site links inside one line. Everything else is escaped. */
  function inline(raw) {
    var re = /\[([^\]\n]{1,200})\]\(([^)\s]{1,1600})\)|\*\*([^*\n]+)\*\*|`([^`\n]+)`/g;
    var out = '';
    var last = 0;
    var m;
    while ((m = re.exec(raw)) !== null) {
      out += esc(raw.slice(last, m.index));
      if (m[1] !== undefined) {
        out += isSafeUrl(m[2]) ? '<a href="' + esc(m[2]) + '">' + esc(m[1]) + '</a>' : esc(m[0]);
      } else if (m[3] !== undefined) {
        out += '<strong>' + esc(m[3]) + '</strong>';
      } else {
        out += '<code>' + esc(m[4]) + '</code>';
      }
      last = re.lastIndex;
    }
    return out + esc(raw.slice(last));
  }

  function tableCells(line) {
    var t = line.trim().replace(/^\|/, '').replace(/\|$/, '');
    return t.split('|').map(function (c) {
      return c.trim();
    });
  }

  /** Markdown subset → HTML. Input is untrusted. */
  function renderMarkdown(text) {
    if (text === null || text === undefined || text === '') {
      return '';
    }
    var lines = String(text).replace(/\r\n?/g, '\n').split('\n');
    var parts = [];
    var i = 0;
    while (i < lines.length) {
      var line = lines[i];
      if (/^\s*\|.*\|\s*$/.test(line)) {
        var rows = [];
        while (i < lines.length && /^\s*\|.*\|\s*$/.test(lines[i])) {
          rows.push(lines[i]);
          i++;
        }
        var hasHead = rows.length > 1 && /^\s*\|[\s:|-]+\|\s*$/.test(rows[1]);
        var html = '<div class="ai-chat-table"><table class="table table-sm table-bordered mb-0">';
        rows.forEach(function (row, idx) {
          if (hasHead && idx === 1) {
            return;
          }
          var tag = hasHead && idx === 0 ? 'th' : 'td';
          html += '<tr>' + tableCells(row).map(function (c) {
            return '<' + tag + '>' + inline(c) + '</' + tag + '>';
          }).join('') + '</tr>';
        });
        parts.push({ block: true, html: html + '</table></div>' });
        continue;
      }
      var bullet = /^\s*[-*]\s+(.*)$/;
      var numbered = /^\s*\d+[.)]\s+(.*)$/;
      if (bullet.test(line) || numbered.test(line)) {
        var re = bullet.test(line) ? bullet : numbered;
        var tagName = re === bullet ? 'ul' : 'ol';
        var items = '';
        while (i < lines.length && re.test(lines[i])) {
          items += '<li>' + inline(re.exec(lines[i])[1]) + '</li>';
          i++;
        }
        parts.push({ block: true, html: '<' + tagName + '>' + items + '</' + tagName + '>' });
        continue;
      }
      var heading = /^\s*#{1,6}\s+(.*)$/.exec(line);
      parts.push({ block: false, html: heading ? '<strong>' + inline(heading[1]) + '</strong>' : inline(line) });
      i++;
    }
    var out = '';
    parts.forEach(function (p, idx) {
      if (idx > 0 && !p.block && !parts[idx - 1].block) {
        out += '<br>';
      }
      out += p.html;
    });
    return out;
  }

  /** Enter sends; Shift+Enter makes a new line; Enter that confirms an IME conversion does neither. */
  function shouldSend(event) {
    return event.key === 'Enter' && !event.shiftKey && !event.isComposing && event.keyCode !== 229;
  }

  /**
   * Should clicking the input open the conversation panel?
   * Yes when there is something to show: messages already on screen, the last answer above the input,
   * or a conversation the server still holds (a page that has just loaded has no messages on screen yet).
   */
  function shouldOpenOnFocus(s) {
    return s.messageCount > 0 || !!s.peekVisible || !!s.hasHistory;
  }

  /**
   * How far above the bottom edge the bar sits. Only things fixed near the bottom edge that the bar
   * (at its widest, 720px) would run into lift it; anything else, such as a banner at the top, is ignored.
   * @param {Array<{top:number,bottom:number,left:number,right:number}>} obstacles viewport rectangles
   */
  function bottomOffset(viewHeight, viewWidth, obstacles) {
    var bottom = 16;
    var width = Math.min(720, viewWidth - 32);
    var barLeft = (viewWidth - width) / 2;
    var barRight = (viewWidth + width) / 2;
    (obstacles || []).forEach(function (r) {
      var nearBottom = r.bottom > viewHeight - 120;
      var inTheWay = r.right > barLeft - 8 && r.left < barRight + 8;
      if (nearBottom && inTheWay) {
        bottom = Math.max(bottom, viewHeight - r.top + 8);
      }
    });
    return bottom;
  }

  /**
   * What the assistant can do, as example questions. `confirm` marks groups whose requests change
   * data and therefore show a confirmation card first.
   */
  var HELP = {
    ja: [
      { title: '案件を探す・見る', examples: ['進行中の案件を見せて', '私の案件を見せて', '来週納期の案件を見せて'] },
      { title: 'タスク', examples: ['私のタスクを見せて', 'この案件のタスクを見せて'] },
      { title: '建物（親案件）', examples: ['工事番号 0290926-01 の建物を探して', 'この建物の案件はどうなっていますか', '意匠の依頼がある建物を納期の近い順に見せて'] },
      { title: '統計・状況', examples: ['進行中の案件は何件ありますか', '今日は誰が休みですか', '来週空いているチームは？'] },
      { title: '顧客・申請・メモ', examples: ['大東建託の顧客を探して', '申請中の申請を見せて', 'この案件のメモを見せて'] },
      { title: '変更する（確認してから実行）', confirm: true, examples: ['この案件のステータスを見積に変更して', 'この案件にメモを追加：図面を確認', 'このタスクの進捗を50%にして'] },
      { title: '自分の予定・ToDo（確認してから実行）', confirm: true, examples: ['明日10時から11時に打ち合わせを予定に追加して', 'ToDoを追加：見積を送る（期限は金曜）'] },
      { title: '文章', examples: ['次の文章をベトナム語に翻訳して：', 'お客様への納期連絡メールを書いて'] }
    ],
    vi: [
      { title: 'Tìm và xem dự án', examples: ['Cho tôi xem các dự án đang tiến hành', 'Cho tôi xem các dự án của tôi', 'Dự án nào tới hạn trong tuần sau?'] },
      { title: 'Task', examples: ['Cho tôi xem các task của tôi', 'Dự án này có những task nào?'] },
      { title: 'Tòa nhà (công trình)', examples: ['Tìm công trình có số công trình 0290926-01', 'Tòa nhà này có những dự án nào, tình hình ra sao?', 'Cho tôi xem các tòa nhà có yêu cầu 意匠, ưu tiên hạn gần nhất'] },
      { title: 'Thống kê và tình hình', examples: ['Có bao nhiêu dự án đang tiến hành?', 'Hôm nay ai nghỉ?', 'Tuần sau team nào rảnh?'] },
      { title: 'Khách hàng, đơn từ, ghi chú', examples: ['Tìm khách hàng 大東建託', 'Cho tôi xem các đơn đang chờ duyệt', 'Dự án này có ghi chú gì?'] },
      { title: 'Thay đổi dữ liệu (xác nhận rồi mới thực hiện)', confirm: true, examples: ['Đổi trạng thái dự án này sang 見積', 'Thêm ghi chú cho dự án này: cần kiểm tra bản vẽ', 'Đặt tiến độ task này lên 50%'] },
      { title: 'Lịch và todo của tôi (xác nhận rồi mới thực hiện)', confirm: true, examples: ['Thêm vào lịch của tôi: họp ngày mai từ 10h đến 11h', 'Thêm todo: gửi báo giá, hạn thứ Sáu'] },
      { title: 'Văn bản', examples: ['Dịch đoạn sau sang tiếng Nhật:', 'Viết email báo hạn giao cho khách hàng'] }
    ]
  };

  function helpGroups(lang) {
    return HELP[lang === 'vi' ? 'vi' : 'ja'];
  }

  // ---- Browser part ----

  var els = {};
  var state = { busy: false, historyLoaded: false };

  /** Japanese text is the key; vi/en come from assets/json/locales when loaded. */
  /**
   * Where the bar sits while hidden: a round button just left of the leftmost shortcut button
   * (the tour button on the project list, otherwise the command palette button).
   * @param {Array} rects getBoundingClientRect of the visible shortcut buttons
   */
  function hiddenPlace(viewWidth, viewHeight, rects) {
    var leftmost = null;
    (rects || []).forEach(function (r) {
      if (!leftmost || r.left < leftmost.left) {
        leftmost = r;
      }
    });
    if (!leftmost) {
      return { right: 10, bottom: 10 };
    }
    return { right: Math.round(viewWidth - leftmost.left + 8), bottom: Math.round(viewHeight - leftmost.bottom) };
  }

  var HIDDEN_KEY = 'aiChatHidden';

  function isHidden() {
    return els.bar.classList.contains('is-hidden');
  }

  function setHidden(hidden) {
    if (hidden) {
      close();
      hidePeek();
      hideHelp();
    }
    els.bar.classList.toggle('is-hidden', hidden);
    try {
      localStorage.setItem(HIDDEN_KEY, hidden ? '1' : '0');
    } catch (e) {}
    place();
    if (!hidden) {
      els.input.focus();
    }
  }

  function tr(ja) {
    try {
      if (typeof i18next !== 'undefined' && i18next.isInitialized && i18next.exists(ja)) {
        return i18next.t(ja);
      }
    } catch (e) {}
    return ja;
  }

  function endpoint(method) {
    return (window.ROOT || '/') + 'ai/index.php?method=' + method;
  }

  function post(method, body) {
    return fetch(endpoint(method), {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body || {})
    }).then(function (r) {
      return r.json();
    });
  }

  var DEPT_KEY = 'aiChatDept';

  /**
   * The remembered default department, as { id, name }, or null.
   * @param stored what was saved ({ id, name })
   * @param list   the user's departments from the server, or null while they are not loaded yet;
   *               once loaded, a department that is no longer in the list is dropped.
   */
  function departmentChoice(stored, list) {
    var id = stored && /^\d{1,9}$/.test(String(stored.id)) ? parseInt(stored.id, 10) : 0;
    if (!id) {
      return null;
    }
    if (!Array.isArray(list)) {
      return { id: id, name: String(stored.name || '') };
    }
    for (var i = 0; i < list.length; i++) {
      if (list[i] && parseInt(list[i].id, 10) === id) {
        return { id: id, name: String(list[i].name || '') };
      }
    }
    return null;
  }

  function readDepartment() {
    try {
      return JSON.parse(localStorage.getItem(DEPT_KEY) || 'null');
    } catch (e) {
      return null;
    }
  }

  function saveDepartment(choice) {
    try {
      if (choice) {
        localStorage.setItem(DEPT_KEY, JSON.stringify(choice));
      } else {
        localStorage.removeItem(DEPT_KEY);
      }
    } catch (e) {}
  }

  /** Fills the picker. Before the list is loaded it shows only the remembered department. */
  function fillDepartments(list) {
    var choice = departmentChoice(readDepartment(), list);
    els.dept.textContent = '';
    var all = el('option', '', tr('すべての部署'));
    all.value = '';
    els.dept.appendChild(all);
    (Array.isArray(list) ? list : choice ? [choice] : []).forEach(function (d) {
      var option = el('option', '', String(d.name));
      option.value = String(parseInt(d.id, 10));
      els.dept.appendChild(option);
    });
    els.dept.value = choice ? String(choice.id) : '';
    els.dept.classList.toggle('is-set', !!choice);
    if (Array.isArray(list)) {
      saveDepartment(choice);
    }
  }

  function loadDepartments() {
    if (state.departmentsLoaded || !els.dept) {
      return;
    }
    state.departmentsLoaded = true;
    fetch(endpoint('get_departments'), { credentials: 'same-origin' })
      .then(function (r) {
        return r.json();
      })
      .then(function (data) {
        if (data && Array.isArray(data.departments)) {
          fillDepartments(data.departments);
        } else {
          state.departmentsLoaded = false;
        }
      })
      .catch(function () {
        state.departmentsLoaded = false;
      });
  }

  function pageContext() {
    var ctx = { page: location.pathname };
    var department = departmentChoice(readDepartment(), null);
    if (department) {
      // Checked again on the server, which then limits project and task lookups to it.
      ctx.department_id = department.id;
    }
    if (location.pathname.indexOf('/project/') === 0) {
      var q = new URLSearchParams(location.search);
      var id = q.get('project_id') || q.get('id') || '';
      if (/^\d{1,9}$/.test(id)) {
        ctx.project_id = parseInt(id, 10);
      }
    } else if (location.pathname.indexOf('/parent_project/') === 0) {
      // On a building page "this building" means the one that is open.
      var bid = new URLSearchParams(location.search).get('id') || '';
      if (/^\d{1,9}$/.test(bid)) {
        ctx.building_id = parseInt(bid, 10);
      }
    }
    return ctx;
  }

  function el(tag, className, text) {
    var node = document.createElement(tag);
    if (className) {
      node.className = className;
    }
    if (text !== undefined) {
      node.textContent = text;
    }
    return node;
  }

  function scrollDown() {
    els.messages.scrollTop = els.messages.scrollHeight;
  }

  /** @param {string} role user | assistant. Assistant text goes through renderMarkdown; user text is plain. */
  function addBubble(role, text, options) {
    var row = el('div', 'ai-chat-msg ai-chat-msg-' + role + (options && options.error ? ' is-error' : ''));
    var bubble = el('div', 'ai-chat-bubble');
    if (role === 'assistant') {
      bubble.innerHTML = renderMarkdown(text);
    } else {
      bubble.textContent = text;
    }
    row.appendChild(bubble);
    if (options && options.before) {
      els.messages.insertBefore(row, options.before);
    } else {
      els.messages.appendChild(row);
      scrollDown();
    }
    return row;
  }

  function showTyping() {
    var row = el('div', 'ai-chat-msg ai-chat-msg-assistant ai-chat-typing');
    var bubble = el('div', 'ai-chat-bubble');
    bubble.appendChild(el('span'));
    bubble.appendChild(el('span'));
    bubble.appendChild(el('span'));
    row.appendChild(bubble);
    els.messages.appendChild(row);
    scrollDown();
    return row;
  }

  function setBusy(busy) {
    state.busy = busy;
    els.input.disabled = busy;
    els.send.disabled = busy || els.input.value.trim() === '';
  }

  function autoGrow() {
    els.input.style.height = 'auto';
    els.input.style.height = Math.min(els.input.scrollHeight, 104) + 'px';
    els.send.disabled = state.busy || els.input.value.trim() === '';
  }

  var PEEK_KEY = 'aiChatPeek';

  /** After the assistant sent the browser to another page: its last answer, shown above the input. */
  function showPeek() {
    var text = null;
    try {
      text = sessionStorage.getItem(PEEK_KEY);
      sessionStorage.removeItem(PEEK_KEY);
    } catch (e) {}
    if (!text || !els.peek) {
      return;
    }
    els.peekText.innerHTML = renderMarkdown(text);
    els.peek.hidden = false;
    els.bar.classList.add('has-peek');
  }

  function hidePeek() {
    if (els.peek) {
      els.peek.hidden = true;
      els.bar.classList.remove('has-peek');
    }
  }

  function open() {
    hidePeek();
    hideHelp();
    if (!els.bar.classList.contains('is-open')) {
      els.bar.classList.add('is-open');
      els.panel.setAttribute('aria-hidden', 'false');
    }
    if (!state.historyLoaded) {
      state.historyLoaded = true;
      loadHistory();
    }
  }

  function close() {
    els.bar.classList.remove('is-open');
    els.panel.setAttribute('aria-hidden', 'true');
  }

  /** Earlier messages of this session, placed above anything already shown. */
  function loadHistory() {
    fetch(endpoint('get_chat_history'), { credentials: 'same-origin' })
      .then(function (r) {
        return r.json();
      })
      .then(function (data) {
        var first = els.messages.firstChild;
        (data && Array.isArray(data.history) ? data.history : []).forEach(function (item) {
          if (item && typeof item.text === 'string' && (item.role === 'user' || item.role === 'assistant')) {
            addBubble(item.role, item.text, { before: first });
          }
        });
        if (!first) {
          scrollDown();
        }
      })
      .catch(function () {});
  }

  var ERROR_TEXT = 'エラーが発生しました。もう一度お試しください。';

  function showReply(data) {
    if (!data || typeof data.reply !== 'string') {
      addBubble('assistant', tr(ERROR_TEXT), { error: true });
      return;
    }
    addBubble('assistant', data.reply, { error: !!data.error });
    if (data.pending && typeof data.pending.token === 'string') {
      addConfirmCard(data.pending);
    }
    if (data.task_form && data.task_form.project_id) {
      addTaskForm(data.task_form);
    }
    if (data.navigate && isSafeUrl(data.navigate.url)) {
      // The result is shown on a page of the site. The answer travels with the user: it is shown
      // above the input on the next page, and the whole conversation is restored when the panel opens.
      try {
        sessionStorage.setItem(PEEK_KEY, data.reply);
      } catch (e) {}
      setTimeout(function () {
        location.href = data.navigate.url;
      }, 700);
    }
  }

  function submit() {
    var text = els.input.value.trim();
    if (!text || state.busy) {
      return;
    }
    open();
    addBubble('user', text);
    els.input.value = '';
    autoGrow();
    setBusy(true);
    var typing = showTyping();
    post('chat', { message: text, context: pageContext() })
      .then(showReply)
      .catch(function () {
        addBubble('assistant', tr(ERROR_TEXT), { error: true });
      })
      .then(function () {
        typing.remove();
        setBusy(false);
        els.input.focus();
        scrollDown();
      });
  }

  /** Card for changes waiting for the user's confirmation. Nothing runs until 確認 is pressed. */
  function addConfirmCard(pending) {
    var card = el('div', 'ai-chat-card' + (pending.danger ? ' is-danger' : ''));
    card.appendChild(el('div', 'ai-chat-card-title', pending.summary || tr('次の変更を実行します')));
    var list = el('ul', 'ai-chat-card-list');
    (Array.isArray(pending.actions) ? pending.actions : []).forEach(function (a) {
      list.appendChild(el('li', '', a && a.label ? String(a.label) : ''));
    });
    card.appendChild(list);
    if (pending.danger) {
      card.appendChild(el('div', 'ai-chat-card-warning', tr('この操作は複数のデータを削除します。')));
    }
    // Things the server wants the user to know before confirming, e.g. a building that already exists.
    (Array.isArray(pending.warnings) ? pending.warnings : []).forEach(function (w) {
      card.appendChild(el('div', 'ai-chat-card-warning', '⚠ ' + String(w)));
    });
    var actions = el('div', 'ai-chat-card-actions');
    var confirm = el('button', 'btn btn-sm ' + (pending.danger ? 'btn-danger' : 'btn-primary'), tr('確認'));
    var cancel = el('button', 'btn btn-sm btn-label-secondary', tr('キャンセル'));
    confirm.type = 'button';
    cancel.type = 'button';
    actions.appendChild(confirm);
    actions.appendChild(cancel);
    card.appendChild(actions);
    els.messages.appendChild(card);
    scrollDown();

    cancel.addEventListener('click', function () {
      card.remove();
    });
    confirm.addEventListener('click', function () {
      confirm.disabled = true;
      cancel.disabled = true;
      post('execute_action', { token: pending.token })
        .then(function (res) {
          actions.remove();
          var ok = res && res.status === 'success';
          var message;
          if (ok) {
            message = '✓ ' + tr('実行しました。');
          } else if (res && res.error === 'expired') {
            message = '✗ ' + tr('確認の有効期限が切れました。もう一度依頼してください。');
          } else if (res && res.error === 'Forbidden') {
            message = '✗ ' + tr('この操作を行う権限がありません。');
          } else {
            message = '✗ ' + String((res && (res.error || res.message)) || tr(ERROR_TEXT));
          }
          card.appendChild(el('div', 'ai-chat-card-result' + (ok ? ' is-ok' : ' is-error'), message));
          (res && Array.isArray(res.links) ? res.links : []).forEach(function (link) {
            if (link && isSafeUrl(link.url)) {
              var a = el('a', 'ai-chat-card-link', String(link.label || link.url));
              a.href = link.url;
              card.appendChild(a);
            }
          });
          scrollDown();
        })
        .catch(function () {
          actions.remove();
          card.appendChild(el('div', 'ai-chat-card-result is-error', tr(ERROR_TEXT)));
        });
    });
  }

  function toDateTimeLocal(value) {
    var s = String(value || '').trim();
    if (/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}/.test(s)) {
      return s.replace(' ', 'T').substring(0, 16);
    }
    return /^\d{4}-\d{2}-\d{2}$/.test(s) ? s + 'T09:00' : '';
  }

  /** Add-task form the assistant asked for. Submitting it creates the task as the logged-in user. */
  function addTaskForm(form) {
    var initial = form.initial && typeof form.initial === 'object' ? form.initial : {};
    var card = el('form', 'ai-chat-card ai-chat-taskform');
    card.appendChild(el('div', 'ai-chat-card-title', tr('タスクを追加') + ' (#' + parseInt(form.project_id, 10) + ')'));

    function field(labelText, input) {
      var wrap = el('label', 'ai-chat-field');
      wrap.appendChild(el('span', '', labelText));
      input.classList.add(input.tagName === 'SELECT' ? 'form-select' : 'form-control');
      input.classList.add(input.tagName === 'SELECT' ? 'form-select-sm' : 'form-control-sm');
      wrap.appendChild(input);
      card.appendChild(wrap);
      return input;
    }
    function input(type, value) {
      var node = document.createElement('input');
      node.type = type;
      node.value = value || '';
      return node;
    }
    var title = field(tr('タイトル'), input('text', initial.title));
    title.required = true;
    title.maxLength = 255;
    // The description is written by the assistant: it is shown so the user saves only text they have seen.
    var description = document.createElement('textarea');
    description.rows = 2;
    description.value = typeof initial.description === 'string' ? initial.description : '';
    field(tr('説明'), description);
    var assignee = field(tr('担当者（氏名、カンマ区切り）'), input('text', initial.assigned_to_name));
    var start = field(tr('開始'), input('datetime-local', toDateTimeLocal(initial.start_date)));
    var due = field(tr('期限'), input('datetime-local', toDateTimeLocal(initial.due_date)));
    var priority = document.createElement('select');
    [['', '—'], ['low', tr('低')], ['medium', tr('中')], ['high', tr('高')], ['urgent', tr('緊急')]].forEach(function (o) {
      var opt = el('option', '', o[1]);
      opt.value = o[0];
      priority.appendChild(opt);
    });
    field(tr('優先度'), priority);
    var initialPriority = String(initial.priority || '').toLowerCase();
    if (['low', 'medium', 'high', 'urgent'].indexOf(initialPriority) !== -1) {
      priority.value = initialPriority;
    }
    var meWrap = el('label', 'ai-chat-check');
    var me = input('checkbox');
    me.className = 'form-check-input';
    meWrap.appendChild(me);
    meWrap.appendChild(el('span', '', tr('自分を担当者にする')));
    card.appendChild(meWrap);

    var actions = el('div', 'ai-chat-card-actions');
    var save = el('button', 'btn btn-sm btn-primary', tr('追加'));
    save.type = 'submit';
    var cancel = el('button', 'btn btn-sm btn-label-secondary', tr('キャンセル'));
    cancel.type = 'button';
    actions.appendChild(save);
    actions.appendChild(cancel);
    card.appendChild(actions);
    els.messages.appendChild(card);
    scrollDown();
    title.focus();

    cancel.addEventListener('click', function () {
      card.remove();
    });
    card.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!title.value.trim()) {
        title.focus();
        return;
      }
      save.disabled = true;
      cancel.disabled = true;
      post('chat', {
        task_form_data: {
          project_id: parseInt(form.project_id, 10),
          title: title.value.trim(),
          description: description.value.trim(),
          assigned_to: assignee.value.trim(),
          start_date: start.value,
          due_date: due.value,
          priority: priority.value,
          primary_assignee: me.checked
        }
      })
        .then(function (res) {
          card.remove();
          showReply(res);
        })
        .catch(function () {
          card.remove();
          addBubble('assistant', tr(ERROR_TEXT), { error: true });
        });
    });
  }

  /**
   * Keeps the bar clear of other things fixed to the bottom edge: the test-environment banner
   * (bottom centre) and the shortcut buttons (bottom right) when the window is too narrow for both.
   */
  function place() {
    if (isHidden()) {
      var rects = [];
      ['projectListTourBtn', 'command-palette-toggle'].forEach(function (id) {
        var node = document.getElementById(id);
        if (node && node.offsetHeight > 0) {
          rects.push(node.getBoundingClientRect());
        }
      });
      var spot = hiddenPlace(document.documentElement.clientWidth, window.innerHeight, rects); // clientWidth: without the scrollbar, as CSS right is measured
      els.bar.style.right = spot.right + 'px';
      els.bar.style.bottom = spot.bottom + 'px';
      return;
    }
    els.bar.style.right = '';
    var obstacles = [];
    [document.querySelector('.env-test-banner'), document.getElementById('fab-bar')].forEach(function (node) {
      if (node && node.offsetHeight > 0) {
        obstacles.push(node.getBoundingClientRect());
      }
    });
    els.bar.style.bottom = bottomOffset(window.innerHeight, window.innerWidth, obstacles) + 'px';
  }

  /** The help box: groups of example questions. Clicking one puts it in the input, ready to edit or send. */
  function buildHelp() {
    var lang = 'ja';
    try {
      if (typeof i18next !== 'undefined' && i18next.language) {
        lang = String(i18next.language).slice(0, 2);
      }
    } catch (e) {}
    els.helpbox.textContent = '';
    els.helpbox.appendChild(el('div', 'ai-chat-help-title', lang === 'vi' ? 'Trợ lý AI làm được gì? Bấm vào một câu để thử.' : 'AIアシスタントにできること（例文をクリックして試せます）'));
    helpGroups(lang).forEach(function (group) {
      var box = el('div', 'ai-chat-help-group' + (group.confirm ? ' is-confirm' : ''));
      box.appendChild(el('div', 'ai-chat-help-group-title', group.title));
      group.examples.forEach(function (example) {
        var chip = el('button', 'ai-chat-help-example', example);
        chip.type = 'button';
        chip.addEventListener('click', function () {
          hideHelp();
          els.input.value = example;
          autoGrow();
          els.input.focus();
          els.input.setSelectionRange(example.length, example.length);
        });
        box.appendChild(chip);
      });
      els.helpbox.appendChild(box);
    });
  }

  function showHelp() {
    close();
    hidePeek();
    buildHelp();
    els.helpbox.hidden = false;
    els.bar.classList.add('has-help');
    els.help.setAttribute('aria-expanded', 'true');
  }

  function hideHelp() {
    if (!els.helpbox || els.helpbox.hidden) {
      return;
    }
    els.helpbox.hidden = true;
    els.bar.classList.remove('has-help');
    els.help.setAttribute('aria-expanded', 'false');
  }

  function init() {
    els.bar = document.getElementById('ai-chat-bar');
    if (!els.bar || els.bar.dataset.ready) {
      return;
    }
    els.bar.dataset.ready = '1';
    els.panel = document.getElementById('ai-chat-panel');
    els.messages = document.getElementById('ai-chat-messages');
    els.form = document.getElementById('ai-chat-form');
    els.input = document.getElementById('ai-chat-input');
    els.send = document.getElementById('ai-chat-send');

    els.peek = document.getElementById('ai-chat-peek');
    els.peekText = document.getElementById('ai-chat-peek-text');
    if (els.peek) {
      els.peekText.addEventListener('click', function (e) {
        if (!e.target.closest('a')) {
          open();
        }
      });
      document.getElementById('ai-chat-peek-close').addEventListener('click', hidePeek);
      showPeek();
    }
    els.help = document.getElementById('ai-chat-help');
    els.helpbox = document.getElementById('ai-chat-helpbox');
    if (els.help && els.helpbox) {
      els.help.addEventListener('click', function () {
        if (els.helpbox.hidden) {
          showHelp();
        } else {
          hideHelp();
        }
      });
    }
    els.dept = document.getElementById('ai-chat-dept');
    if (els.dept) {
      els.dept.title = tr('既定の部署');
      els.dept.setAttribute('aria-label', els.dept.title);
      fillDepartments(null);
      els.dept.addEventListener('change', function () {
        var option = els.dept.options[els.dept.selectedIndex];
        var choice = els.dept.value ? { id: parseInt(els.dept.value, 10), name: option ? option.textContent : '' } : null;
        saveDepartment(choice);
        els.dept.classList.toggle('is-set', !!choice);
      });
      // The list is fetched the first time the bar is used on a page.
      els.bar.addEventListener('focusin', loadDepartments);
    }
    els.input.placeholder = tr('AIに質問…');
    var hideBtn = document.getElementById('ai-chat-hide');
    var showBtn = document.getElementById('ai-chat-show');
    if (hideBtn && showBtn) {
      hideBtn.title = tr('右に隠す');
      hideBtn.setAttribute('aria-label', hideBtn.title);
      showBtn.title = tr('AIアシスタントを表示');
      showBtn.setAttribute('aria-label', showBtn.title);
      hideBtn.addEventListener('click', function () {
        setHidden(true);
      });
      showBtn.addEventListener('click', function () {
        setHidden(false);
      });
    }
    document.getElementById('ai-chat-new').title = tr('これまでの会話を消して、最初から始めます');
    place();
    // Shortcut buttons can appear after this script runs (the tour button is shown by its page).
    window.addEventListener('load', place);
    setTimeout(place, 800);    var resizeTimer = null;
    window.addEventListener('resize', function () {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(place, 150);
    });

    els.input.addEventListener('input', autoGrow);
    els.input.addEventListener('keydown', function (e) {
      if (shouldSend(e)) {
        e.preventDefault();
        submit();
      } else if (e.key === 'Escape' && !e.isComposing) {
        close();
        els.input.blur();
      }
    });
    function openIfThereIsSomethingToShow() {
      var show = shouldOpenOnFocus({
        messageCount: els.messages.children.length,
        peekVisible: !!(els.peek && !els.peek.hidden),
        hasHistory: els.bar.dataset.hasHistory === '1'
      });
      if (show) {
        open();
      }
    }
    // focus covers keyboard and first click; click covers a click while the input already has focus.
    els.input.addEventListener('focus', openIfThereIsSomethingToShow);
    els.input.addEventListener('click', openIfThereIsSomethingToShow);
    els.form.addEventListener('submit', function (e) {
      e.preventDefault();
      submit();
    });
    document.getElementById('ai-chat-close').addEventListener('click', close);
    document.getElementById('ai-chat-new').addEventListener('click', function () {
      if (state.busy) {
        return;
      }
      post('clear_chat', {}).catch(function () {});
      els.messages.textContent = '';
      els.messages.appendChild(el('div', 'ai-chat-notice', tr('新しい会話を始めました。これまでの内容はAIに送られません。')));
      els.bar.dataset.hasHistory = '0';
      state.historyLoaded = true;
      els.input.focus();
    });
    document.addEventListener('mousedown', function (e) {
      if (!els.bar.contains(e.target) && !isHidden()) {
        if (els.bar.classList.contains('is-open')) {
          close();
        }
        hideHelp();
      }
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && els.bar.contains(document.activeElement)) {
        if (els.bar.classList.contains('is-open')) {
          close();
        }
        hideHelp();
      }
    });
  }

  return { renderMarkdown: renderMarkdown, shouldSend: shouldSend, isSafeUrl: isSafeUrl, shouldOpenOnFocus: shouldOpenOnFocus, helpGroups: helpGroups, bottomOffset: bottomOffset, hiddenPlace: hiddenPlace, departmentChoice: departmentChoice, init: init };
});
