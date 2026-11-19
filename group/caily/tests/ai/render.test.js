// Run: node tests/ai/render.test.js
const { renderMarkdown, shouldSend, isSafeUrl, shouldOpenOnFocus, helpGroups, bottomOffset, hiddenPlace, departmentChoice } = require('../../assets/js/ai-chat-bar.js');
let failed = 0, count = 0;
function ok(cond, name) { count++; if (!cond) failed++; console.log((cond ? 'PASS' : 'FAIL') + '  ' + name); }
function eq(a, b, name) { ok(a === b, name + (a === b ? '' : '\n      got: ' + JSON.stringify(a))); }

eq(renderMarkdown('<script>alert(1)</script>'), '&lt;script&gt;alert(1)&lt;/script&gt;', 'script tag is escaped');
ok(!renderMarkdown('<img src=x onerror=alert(1)>').includes('<img'), 'img tag is escaped');
ok(!renderMarkdown('"><svg onload=alert(1)>').includes('<svg'), 'attribute break-out is escaped');
ok(renderMarkdown('**a**').includes('<strong>a</strong>'), 'bold');
ok(renderMarkdown('**<b>x</b>**').includes('<strong>&lt;b&gt;x&lt;/b&gt;</strong>'), 'bold content is escaped');
ok(renderMarkdown('[x](/project/task.php?project_id=2)').includes('<a href="/project/task.php?project_id=2">x</a>'), 'internal link');
ok(!renderMarkdown('[a](/project/task.php?project_id=2&x=1)').includes('href'), 'link with extra query parameters refused');
ok(!renderMarkdown('[開く](/api/index.php?model=project&method=addMemberApi&project_id=23&user_id=99)').includes('href'), 'link to a write endpoint refused');
ok(!renderMarkdown('[x](javascript:alert(1))').includes('href'), 'javascript: link refused');
ok(!renderMarkdown('[x](//evil.example)').includes('href'), 'protocol-relative link refused');
ok(!renderMarkdown('[x](https://evil.example)').includes('href'), 'external link refused');
ok(!renderMarkdown('[x](/a"onclick="alert(1))').includes('onclick="'), 'quote in link refused');
ok(renderMarkdown('[<i>x</i>](/project/detail.php?id=3)').includes('&lt;i&gt;x&lt;/i&gt;</a>'), 'link text is escaped');
const table = renderMarkdown('| ID | 案件名 |\n|---|---|\n| 1 | <b>A</b> |');
ok(table.includes('<th>ID</th>') && table.includes('<td>&lt;b&gt;A&lt;/b&gt;</td>') && !table.includes('---'), 'table with escaped cells');
const list = renderMarkdown('- one\n- **two**\n\nafter');
ok(list.includes('<ul><li>one</li><li><strong>two</strong></li></ul>') && list.includes('after'), 'bullet list');
ok(renderMarkdown('1. a\n2. b').includes('<ol><li>a</li><li>b</li></ol>'), 'numbered list');
eq(renderMarkdown('a\nb'), 'a<br>b', 'line break');
eq(renderMarkdown(''), '', 'empty text');
eq(renderMarkdown(null), '', 'null is empty');
ok(renderMarkdown('`<x>`').includes('<code>&lt;x&gt;</code>'), 'inline code is escaped');

eq(shouldSend({ key: 'Enter', shiftKey: false, isComposing: false, keyCode: 13 }), true, 'Enter sends');
eq(shouldSend({ key: 'Enter', shiftKey: false, isComposing: true, keyCode: 229 }), false, 'Enter during IME composition does not send');
eq(shouldSend({ key: 'Enter', shiftKey: false, isComposing: false, keyCode: 229 }), false, 'keyCode 229 does not send');
eq(shouldSend({ key: 'Enter', shiftKey: true, isComposing: false, keyCode: 13 }), false, 'Shift+Enter does not send');
eq(shouldSend({ key: 'a', shiftKey: false, isComposing: false, keyCode: 65 }), false, 'other keys do not send');

eq(isSafeUrl('/project/detail.php?id=3'), true, 'internal url is safe');
eq(isSafeUrl('//evil.example'), false, 'protocol-relative url is unsafe');
eq(isSafeUrl('https://evil.example'), false, 'absolute url is unsafe');
eq(isSafeUrl('/project/detail.php?id=3' + String.fromCharCode(92) + 'x'), false, 'backslash url is unsafe');
['/project/task.php?project_id=3', '/project/gantt.php?project_id=3', '/project/drawings.php?project_id=3', '/project/attachment.php?project_id=3', '/parent_project/detail.php?id=5'].forEach(u => eq(isSafeUrl(u), true, 'page allowed: ' + u));
['/api/index.php?model=project&method=delete&id=3', '/ai/index.php?method=clear_chat', '/logout.php', '/', '/project/detail.php', '/project/detail.php?id=abc', '/project/detail.php?id=3&x=1', '/project/delete.php?id=3', '/member/index.php'].forEach(u => eq(isSafeUrl(u), false, 'not a page link: ' + u));
eq(isSafeUrl(null), false, 'null url is unsafe');

// Project list with filters (opened by show_projects).
['/project/index.php?department_id=7&status=in_progress&filterKeyword=&showInactive=0', '/project/index.php?department_id=5&status=in_progress%2Cquotation&filterKeyword=%E5%BA%9C%E4%B8%AD%20%EF%BC%B0%EF%BC%AA&filterDateFrom=2026-11-01'].forEach(u => eq(isSafeUrl(u), true, 'filtered list allowed: ' + u.slice(0, 60)));
['/project/index.php', '/project/index.php?x=<script>', '/project/index.php?a="onload=1', '/project/index.php?a=1#x', '/project/index.php?a=1;b', '/project/index.php?' + 'a'.repeat(1600), '/project/delete.php?department_id=7'].forEach(u => eq(isSafeUrl(u), false, 'filtered list refused: ' + u.slice(0, 40)));
ok(renderMarkdown('[一覧](/project/index.php?department_id=7&status=in_progress)').includes('href="/project/index.php?department_id=7&amp;status=in_progress"'), 'link to the filtered list, ampersand escaped');
// More pages the assistant may open (all read-only pages).
['/parent_project/detail.php?id=9', '/parent_project/attachment.php?id=9', '/form/detail.php?id=12', '/parent_project/index.php', '/project/task_overview.php', '/project/task_overview.php?view=list&department_id=5&user_id=3&exclude_completed=1&my_task=1&tab=tasks', '/project/employee_statistics.php', '/project/project_gantt.php', '/form/index.php', '/form/index.php?status=pending&tab=leave', '/timecard/index.php', '/schedule/index.php', '/customer/index.php', '/forum/index.php', '/storage/index.php', '/member/online.php'].forEach(u => eq(isSafeUrl(u), true, 'page allowed: ' + u.slice(0, 60)));
['/project/revenue_statistics.php?department_id=5&month=2026-11', '/timecard/csv.php?year=2026', '/forum/delete.php?id=3', '/member/change_password.php', '/setting/backup.php', '/setting/backup_download.php', '/storage/delete.php?id=1', '/form/download.php?id=1', '/logout.php', '/schedule/index.php?x=<b>', '/customer/import.php', '/project/employee_statistics.php?a=1'].forEach(u => eq(isSafeUrl(u), false, 'page refused: ' + u));

// Building list with filters (opened by show_buildings).
['/parent_project/index.php?ai=1&search=%E5%A4%A7%E6%9D%B1%20%E5%BB%BA%E8%A8%97&request_filter=%E6%84%8F%E5%8C%A0&order_column=nearest_nouki&order_dir=ASC', '/parent_project/index.php?ai=1&search=&request_filter=&order_column=created_at&order_dir=DESC'].forEach(u => eq(isSafeUrl(u), true, 'filtered building list allowed: ' + u.slice(0, 50)));
['/parent_project/index.php?search=<script>', '/parent_project/index.php?a=1#x', '/parent_project/delete.php?id=1', '/parent_project/index.php?' + 'a'.repeat(700)].forEach(u => eq(isSafeUrl(u), false, 'filtered building list refused: ' + u.slice(0, 45)));

// Clicking the input reopens the conversation, also on a page that has just loaded.
eq(shouldOpenOnFocus({ messageCount: 0, peekVisible: false, hasHistory: true }), true, 'opens on a new page when the server holds a conversation');
eq(shouldOpenOnFocus({ messageCount: 2, peekVisible: false, hasHistory: false }), true, 'opens when messages are on screen');
eq(shouldOpenOnFocus({ messageCount: 0, peekVisible: true, hasHistory: false }), true, 'opens when the last answer is shown above the input');
eq(shouldOpenOnFocus({ messageCount: 0, peekVisible: false, hasHistory: false }), false, 'stays compact when there is nothing to show');

// Help: what the assistant can do, as example questions in the user's language.
const ja = helpGroups('ja'), vi = helpGroups('vi');
ok(ja.length >= 6 && ja.length === vi.length, 'help has the same groups in Japanese and Vietnamese');
ok(ja.concat(vi).every(g => typeof g.title === 'string' && g.title && Array.isArray(g.examples) && g.examples.length >= 1 && g.examples.every(e => typeof e === 'string' && e.trim().length > 3)), 'every help group has a title and examples');
ok(/[ぁ-んァ-ン]/.test(JSON.stringify(ja)) && /[ăâêôơưđ]/i.test(JSON.stringify(vi)), 'each language set is written in that language');
eq(helpGroups('en').length, ja.length, 'other languages fall back to Japanese');
ok(ja.some(g => g.confirm === true) && ja.some(g => !g.confirm), 'groups that change data are marked');

// The bar is lifted only by things fixed at the bottom edge (the test banner now sits at the top).
eq(bottomOffset(800, 1280, []), 16, 'nothing in the way: 16px from the bottom');
eq(bottomOffset(800, 1280, [{ top: 0, bottom: 32, left: 494, right: 786 }]), 16, 'a banner at the top edge does not move the bar');
eq(bottomOffset(800, 1280, [{ top: 768, bottom: 800, left: 494, right: 786 }]), 40, 'a banner at the bottom edge lifts the bar above it');
eq(bottomOffset(800, 1280, [{ top: 738, bottom: 790, left: 1087, right: 1255 }]), 16, 'buttons at the side do not move the bar on a wide window');
eq(bottomOffset(800, 1000, [{ top: 738, bottom: 790, left: 807, right: 975 }]), 70, 'buttons the wide bar would reach lift it');

// Hidden: the bar becomes a round button just left of the leftmost shortcut button it is given.
const tour = { top: 754, bottom: 790, left: 1058, right: 1094 };
const palette = { top: 754, bottom: 790, left: 1102, right: 1138 };
eq(JSON.stringify(hiddenPlace(1280, 800, [palette])), JSON.stringify({ right: 186, bottom: 10 }), 'hidden: left of the command palette button');
eq(JSON.stringify(hiddenPlace(1280, 800, [palette, tour])), JSON.stringify({ right: 230, bottom: 10 }), 'hidden: left of the tour button when the page has one');
eq(JSON.stringify(hiddenPlace(1280, 800, [])), JSON.stringify({ right: 10, bottom: 10 }), 'hidden: bottom right corner when there are no shortcut buttons');

// Default department: remembered as { id, name }; dropped once the server's list no longer has it.
eq(JSON.stringify(departmentChoice({ id: 5, name: '意匠設計' }, null)), JSON.stringify({ id: 5, name: '意匠設計' }), 'department: the remembered one is used before the list is loaded');
eq(JSON.stringify(departmentChoice({ id: '5', name: 'old name' }, [{ id: 3, name: 'A' }, { id: 5, name: '意匠設計' }])), JSON.stringify({ id: 5, name: '意匠設計' }), 'department: the name comes from the server list');
eq(departmentChoice({ id: 9, name: 'gone' }, [{ id: 3, name: 'A' }]), null, 'department: one that is not in the list is dropped');
eq(departmentChoice(null, [{ id: 3, name: 'A' }]), null, 'department: nothing remembered means all departments');
eq(departmentChoice({ id: '5 OR 1=1', name: 'x' }, null), null, 'department: only a number is accepted');

console.log('\n' + (count - failed) + '/' + count + ' passed');
process.exit(failed ? 1 : 0);
