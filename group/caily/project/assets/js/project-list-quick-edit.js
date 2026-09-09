/**
 * Project list Quick Edit modal (lazy-loaded).
 * Classic script — uses helpers/globals from project-list.js.
 */
(function() {
    'use strict';

    window.installProjectListQuickEdit = function() {
        if (window.ProjectListQuickEditInstalled) return;
        window.ProjectListQuickEditInstalled = true;

        // Quick Edit Tagify instances (destroy on each open, re-init after load)
        let quickEditOrderTypeTagify = null, quickEditTeamTagify = null, quickEditManagerTagify = null, quickEditMembersTagify = null;
        let quickEditQuillInstance = null;
        let quickEditIsManagerOnly = false;
        let quickEditOriginalStatus = '';
        let quickEditOriginalCailyNoukiStatus = '';
        let quickEditOriginalGuisNoukiStatus = '';
        let quickEditShareContext = null;
        let quickEditEstimateStatus = '未発行';
        let quickEditInvoiceStatus = '未発行';

        function getQuickEditYoteiDraft() {
            return {
                from_month: ($('#quickEditYoteiFromMonth').val() || '').trim(),
                from_part: ($('#quickEditYoteiFromPart').val() || '').trim(),
                to_month: ($('#quickEditYoteiToMonth').val() || '').trim(),
                to_part: ($('#quickEditYoteiToPart').val() || '').trim()
            };
        }

        function destroyQuickEditYoteiMonthPickers() {
            if (typeof window.YoteiField === 'undefined') return;
            window.YoteiField.destroyMonthPicker(document.getElementById('quickEditYoteiFromMonth'));
            window.YoteiField.destroyMonthPicker(document.getElementById('quickEditYoteiToMonth'));
        }

        function initQuickEditYoteiMonthPickers() {
            if (typeof window.YoteiField === 'undefined') return;
            window.YoteiField.initMonthPicker(
                document.getElementById('quickEditYoteiFromMonth'),
                function() { return ($('#quickEditYoteiFromMonth').val() || '').trim(); },
                function(ym) { $('#quickEditYoteiFromMonth').val(ym || ''); refreshQuickEditYoteiPreview(); }
            );
            window.YoteiField.initMonthPicker(
                document.getElementById('quickEditYoteiToMonth'),
                function() { return ($('#quickEditYoteiToMonth').val() || '').trim(); },
                function(ym) {
                    $('#quickEditYoteiToMonth').val(ym || '');
                    if (!ym) $('#quickEditYoteiToPart').val('');
                    refreshQuickEditYoteiPreview();
                }
            );
        }

        function refreshQuickEditYoteiPreview() {
            var draft = getQuickEditYoteiDraft();
            var text = (typeof window.YoteiField !== 'undefined') ? window.YoteiField.buildDisplay(draft) : '';
            $('#quickEditYoteiPreview').text(text || '');
            var hasTo = !!draft.to_month;
            $('#quickEditYoteiToPart').prop('disabled', !hasTo);
            if (!hasTo) $('#quickEditYoteiToPart').val('');
        }

        function setQuickEditYoteiFromProject(yotei) {
            var parsed = (typeof window.YoteiField !== 'undefined')
                ? window.YoteiField.parse(yotei)
                : { from_month: '', from_part: '', to_month: '', to_part: '' };
            $('#quickEditYoteiFromMonth').val(parsed.from_month || '');
            $('#quickEditYoteiFromPart').val(parsed.from_part || '');
            $('#quickEditYoteiToMonth').val(parsed.to_month || '');
            $('#quickEditYoteiToPart').val(parsed.to_part || '');
            $('#quickEditYoteiError').text('');
            $('#quickEditYoteiFromMonth, #quickEditYoteiToMonth').removeClass('is-invalid');
            destroyQuickEditYoteiMonthPickers();
            initQuickEditYoteiMonthPickers();
            if (typeof window.YoteiField !== 'undefined') {
                window.YoteiField.setMonthPickerValue(document.getElementById('quickEditYoteiFromMonth'), parsed.from_month || '');
                window.YoteiField.setMonthPickerValue(document.getElementById('quickEditYoteiToMonth'), parsed.to_month || '');
            }
            refreshQuickEditYoteiPreview();
            if (typeof window.applyDataI18n === 'function') {
                var wrap = document.getElementById('quickEditProjectForm');
                if (wrap) window.applyDataI18n(wrap);
            }
        }

        function clearQuickEditYotei() {
            $('#quickEditYoteiFromMonth').val('');
            $('#quickEditYoteiFromPart').val('');
            $('#quickEditYoteiToMonth').val('');
            $('#quickEditYoteiToPart').val('');
            $('#quickEditYoteiError').text('');
            $('#quickEditYoteiFromMonth, #quickEditYoteiToMonth').removeClass('is-invalid');
            if (typeof window.YoteiField !== 'undefined') {
                window.YoteiField.setMonthPickerValue(document.getElementById('quickEditYoteiFromMonth'), '');
                window.YoteiField.setMonthPickerValue(document.getElementById('quickEditYoteiToMonth'), '');
            }
            refreshQuickEditYoteiPreview();
        }

        $(document)
            .off('click.quickEditYotei', '#quickEditYoteiClear')
            .on('click.quickEditYotei', '#quickEditYoteiClear', function() { clearQuickEditYotei(); })
            .off('input.quickEditYotei change.quickEditYotei', '#quickEditYoteiFromMonth, #quickEditYoteiFromPart, #quickEditYoteiToMonth, #quickEditYoteiToPart')
            .on('input.quickEditYotei change.quickEditYotei', '#quickEditYoteiFromMonth, #quickEditYoteiFromPart, #quickEditYoteiToMonth, #quickEditYoteiToPart', function() {
                refreshQuickEditYoteiPreview();
            });

        function destroyQuickEditQuill() {
            if (quickEditQuillInstance) {
                try {
                    if (typeof quickEditQuillInstance.setText === 'function') quickEditQuillInstance.setText('');
                    if (typeof quickEditQuillInstance.destroy === 'function') quickEditQuillInstance.destroy();
                } catch (e) {}
                quickEditQuillInstance = null;
            }
            var quillContainer = document.getElementById('quickEditQuillDescription');
            if (quillContainer) {
                var parent = quillContainer.parentElement;
                if (parent) {
                    var toolbar = parent.querySelector('.ql-toolbar');
                    if (toolbar) toolbar.remove();
                    parent.querySelectorAll('.ql-container, .ql-editor').forEach(function(el) {
                        if (el !== quillContainer) el.remove();
                    });
                }
                quillContainer.innerHTML = '';
                quillContainer.className = 'custom_editor_content';
                quillContainer.setAttribute('id', 'quickEditQuillDescription');
                quillContainer.removeAttribute('contenteditable');
                quillContainer.removeAttribute('data-gramm');
                quillContainer.removeAttribute('data-gramm_editor');
                quillContainer.removeAttribute('data-enable-grammarly');
            }
        }
        function destroyQuickEditTagify() {
            [quickEditOrderTypeTagify, quickEditTeamTagify, quickEditManagerTagify, quickEditMembersTagify].forEach(function(t) {
                if (t && typeof t.destroy === 'function') { try { t.destroy(); } catch (e) {} }
            });
            quickEditOrderTypeTagify = quickEditTeamTagify = quickEditManagerTagify = quickEditMembersTagify = null;
            // Clear value các input Tagify trước khi load dự án mới
            $('#quickEditProjectOrderType, #quickEditTeamTags, #quickEditManagerTags, #quickEditMembersTags').val('');
        }

        function getQuickEditOrderTypeValue() {
            if (quickEditOrderTypeTagify && Array.isArray(quickEditOrderTypeTagify.value)) {
                return quickEditOrderTypeTagify.value.map(function(t) { return t.value; }).join(',');
            }
            return ($('#quickEditProjectOrderType').val() || '').toString().trim();
        }

        function setQuickEditOrderTypeInvalid(isInvalid) {
            var $input = $('#quickEditProjectOrderType');
            var $tagify = $input.next('.tagify');
            if (!$tagify.length) $tagify = $input.parent().find('.tagify').first();
            $input.toggleClass('is-invalid', !!isInvalid);
            if ($tagify.length) $tagify.toggleClass('is-invalid', !!isInvalid);
        }

        // Quick Edit Project Modal: open and save (isManagerOnly = true: chỉ hiện ステータス, 進捗率, チーム, 管理, メンバー)
        window.__openQuickEditProjectModalImpl = function(projectId, isManagerOnly) {
            quickEditIsManagerOnly = !!isManagerOnly;
            var $form = $('#quickEditProjectForm');
            if (quickEditIsManagerOnly) $form.addClass('quick-edit-manager-only-mode'); else $form.removeClass('quick-edit-manager-only-mode');
            destroyQuickEditTagify();
            var modalEl = document.getElementById('quickEditProjectModal');
            var quickEditModal = bootstrap.Modal.getOrCreateInstance(modalEl);
            quickEditModal.show();
            $('#quickEditModalLoading').removeClass('d-none');

            var quillReady = ensureProjectListQuill().catch(function(err) {
                console.error('Failed to load Quill for quick edit:', err);
            });
            var flatpickrReady = (typeof window.ensureFlatpickr === 'function')
                ? window.ensureFlatpickr().catch(function(err) {
                    console.error('Failed to load Flatpickr for quick edit:', err);
                })
                : Promise.resolve();

            axios.get('/api/index.php?model=project&method=getById&id=' + projectId).then(function(res) {
                const p = res.data && res.data.data ? res.data.data : (res.data || {});
                return Promise.all([quillReady, flatpickrReady]).then(function() { return p; });
            }).then(function(p) {
                const effectiveId = p.id || projectId;
                $('#quickEditProjectId').val(effectiveId);
                $('#quickEditProjectIdBadge').text('#' + effectiveId);
                $('#quickEditProjectVersion').val(normalizeProjectVersion(p.version));
                $('#quickEditName').val(p.name || '');
                var datetimePlaceholder = getProjectDateTimePlaceholder();
                $('#quickEditStartDate, #quickEditEndDate, #quickEditCailyNouki, #quickEditGuisNouki')
                    .attr('placeholder', datetimePlaceholder);
                $('#quickEditStartDate').val(toProjectDateTimeInputValue(p.start_date));
                $('#quickEditEndDate').val(toProjectDateTimeInputValue(p.end_date));
                setQuickEditYoteiFromProject(p.yotei);
                quickEditOriginalStatus = p.status || 'draft';
                quickEditOriginalCailyNoukiStatus = p.caily_nouki_status || '';
                quickEditOriginalGuisNoukiStatus = p.guis_nouki_status || '';
                quickEditShareContext = {
                    energy_drawing_share_status: p.energy_drawing_share_status || '',
                    has_energy_sibling: !!(p.has_energy_sibling === 1 || p.has_energy_sibling === '1' || p.has_energy_sibling === true),
                    department_name: p.department_name || ''
                };
                quickEditEstimateStatus = p.estimate_status || '未発行';
                quickEditInvoiceStatus = p.invoice_status || '未発行';
                syncQuickEditStatusOptions(quickEditOriginalStatus);
                $('#quickEditStatus').val(quickEditOriginalStatus);
                var orderTypeVal = typeof p.project_order_type === 'string'
                    ? p.project_order_type
                    : (Array.isArray(p.project_order_type) ? (p.project_order_type || []).join(',') : '');
                // Clear trước khi init Tagify (tránh parse value cũ → trùng tag)
                $('#quickEditProjectOrderType').val('');
                $('input[name="tantou"]').prop('checked', false);
                if (p.tantou === 'CAILY') $('#quickEditTantouCaily').prop('checked', true);
                else if (p.tantou === 'GUIS') $('#quickEditTantouGuis').prop('checked', true);
                $('#quickEditTantouDisplayText').text(p.tantou || '—');
                $('#quickEditCailyNouki').val(toProjectDateTimeInputValue(p.caily_nouki));
                $('#quickEditGuisNouki').val(toProjectDateTimeInputValue(p.guis_nouki));
                $('#quickEditCailyNoukiStatus').prop('checked', !!(p.caily_nouki_status && String(p.caily_nouki_status).indexOf('納品済み') !== -1));
                $('#quickEditGuisNoukiStatus').prop('checked', !!(p.guis_nouki_status && String(p.guis_nouki_status).indexOf('納品済み') !== -1));
                $('#quickEditProgress').val(p.progress != null && p.progress !== '' ? parseInt(p.progress, 10) : 0);
                updateQuickEditNoukiRequiredIndicators();

                // 説明 (description): Quill editor like parent_project edit child project modal (destroy + DOM cleanup để không sinh nhiều instance)
                destroyQuickEditQuill();
                var quickEditDescEl = document.getElementById('quickEditQuillDescription');
                if (quickEditDescEl && window.Quill) {
                    var existingToolbar = quickEditDescEl.parentElement && quickEditDescEl.parentElement.querySelector('.ql-toolbar');
                    if (existingToolbar) existingToolbar.remove();
                    if (quickEditDescEl.classList.contains('ql-container')) {
                        quickEditDescEl.className = 'custom_editor_content';
                        quickEditDescEl.setAttribute('id', 'quickEditQuillDescription');
                    }
                    quickEditDescEl.innerHTML = '';
                    quickEditQuillInstance = new Quill(quickEditDescEl, {
                        bounds: quickEditDescEl,
                        placeholder: '説明を入力してください...',
                        modules: {
                            toolbar: [
                                ['bold', 'italic', 'underline', 'strike'],
                                [{ color: [] }, { background: [] }],
                                ['blockquote', 'code-block'],
                                [{ 'header': 1 }, { 'header': 2 }],
                                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                                [{ 'indent': '-1'}, { 'indent': '+1' }],
                                [{ 'align': [] }],
                                ['link'],
                                ['clean']
                            ]
                        },
                        theme: 'snow'
                    });
                    var descHtml = (p.description || '').toString().trim();
                    if (descHtml) {
                        descHtml = (typeof decodeHtmlEntities === 'function') ? decodeHtmlEntities(descHtml) : descHtml.replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&').replace(/&quot;/g, '"');
                        quickEditQuillInstance.root.innerHTML = descHtml;
                    }
                }

                var depId = p.department_id || '';
                var savedCustom = [];
                try {
                    var raw = p.custom_fields;
                    if (typeof raw === 'string' && raw.indexOf('&quot;') !== -1) raw = raw.replace(/&quot;/g, '"');
                    savedCustom = typeof raw === 'string' ? JSON.parse(raw || '[]') : (Array.isArray(raw) ? raw : []);
                } catch (e) { savedCustom = []; }
                var savedValueMap = {};
                savedCustom.forEach(function(f) { if (f && f.label) savedValueMap[String(f.label).trim()] = f.value || ''; });

                axios.get('/api/index.php?model=department&method=getCustomFields').then(function(cfRes) {
                    var sets = cfRes.data || [];
                    var mergedFields = [];
                    // Filter sets by department_id (use strict comparison)
                    sets.filter(function(s) { 
                        return s && s.department_id != null && String(s.department_id) === String(depId); 
                    }).forEach(function(s) {
                        if (s.fields && Array.isArray(s.fields)) {
                            s.fields.forEach(function(f) {
                                if (f && f.label && !mergedFields.some(function(ex) { 
                                    return ex.label && String(ex.label).trim() === String(f.label || '').trim(); 
                                })) {
                                    mergedFields.push({ 
                                        label: f.label || '', 
                                        type: f.type || 'text', 
                                        options: f.options || '',
                                        one_row: (f.one_row === 1 || f.one_row === '1' || f.one_row === true)
                                    });
                                }
                            });
                        }
                    });
                    var $wrap = $('#quickEditCustomFieldsWrap');
                    $wrap.empty();
                    if (mergedFields.length === 0) {
                        // No custom fields found, but don't hide the wrapper
                        return;
                    }
                    mergedFields.forEach(function(f, idx) {
                        var label = f.label;
                        var type = f.type;
                        // Ensure options is a string before calling trim()
                        var options = (f.options != null ? String(f.options) : '').trim();
                        var opts = options ? options.split(',').map(function(s) { return s.trim(); }).filter(Boolean) : [];
                        var val = savedValueMap[String(label).trim()] || '';
                        var safeLabel = String(label).replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                        var labelText = (typeof translateText === 'function' ? translateText(safeLabel) : safeLabel);
                        var isOneRow = (f.one_row === 1 || f.one_row === '1' || f.one_row === true);
                        var colClass = type === 'textarea' || isOneRow ? 'col-12' : 'col-md-6';
                        var html = '<div class="' + colClass + ' mb-3 quick-edit-custom-field" data-custom-label="' + safeLabel + '" data-custom-type="' + type + '">';
                
                        html += '<label class="form-label">' + labelText + '</label>';
                        if (type === 'textarea') {
                            html += '<textarea class="form-control quickEditCustomInput" data-custom-label="' + safeLabel + '" rows="3">' + (val ? String(val).replace(/</g, '&lt;').replace(/>/g, '&gt;') : '') + '</textarea>';
                        } else if (type === 'select') {
                            html += '<select class="form-select quickEditCustomInput" data-custom-label="' + safeLabel + '"><option value="">選択してください</option>';
                            opts.forEach(function(opt) { html += '<option value="' + String(opt).replace(/"/g, '&quot;') + '"' + (val === opt ? ' selected' : '') + '>' + String(opt).replace(/</g, '&lt;') + '</option>'; });
                            html += '</select>';
                        } else if (type === 'radio') {
                            opts.forEach(function(opt) {
                                html += '<div class="form-check"><input class="form-check-input quickEditCustomRadio" type="radio" name="quickEditCustomRadio_' + idx + '" data-custom-label="' + safeLabel + '" value="' + String(opt).replace(/"/g, '&quot;') + '"' + (val === opt ? ' checked' : '') + '><label class="form-check-label">' + String(opt).replace(/</g, '&lt;') + '</label></div>';
                            });
                        } else if (type === 'checkbox') {
                            var arr = val ? String(val).split(',').map(function(s) { return s.trim(); }).filter(Boolean) : [];
                            opts.forEach(function(opt) {
                                var checked = arr.indexOf(opt) !== -1;
                                var optText = (typeof translateText === 'function' ? translateText(String(opt).replace(/</g, '&lt;')) : String(opt).replace(/</g, '&lt;'));
                                html += '<div class="form-check"><input class="form-check-input quickEditCustomCheckbox" type="checkbox" data-custom-label="' + safeLabel + '" value="' + String(opt).replace(/"/g, '&quot;') + '"' + (checked ? ' checked' : '') + '><label class="form-check-label">' + optText + '</label></div>';
                            });
                        } else if (type === 'datetime') {
                            var datetimeVal = val ? toProjectDateTimeInputValue(val) : '';
                            html += '<input type="text" class="form-control quickEditCustomInput quickEditCustomDatetime" data-custom-label="' + safeLabel + '" value="' + (datetimeVal ? String(datetimeVal).replace(/"/g, '&quot;') : '') + '" placeholder="' + datetimePlaceholder.replace(/"/g, '&quot;') + '" autocomplete="off">';
                        } else {
                            html += '<input type="text" class="form-control quickEditCustomInput" data-custom-label="' + safeLabel + '" value="' + (val ? String(val).replace(/"/g, '&quot;') : '') + '">';
                        }
                        html += '</div>';
                        $wrap.append(html);
                    });
                    if (typeof $().flatpickr === 'function') {
                        $wrap.find('.quickEditCustomDatetime').each(function() {
                            initQuickEditFlatpickr(this, { defaultHour: getCustomFieldDefaultHour(), defaultMinute: 0 });
                        });
                    }
                }).catch(function(err) { 
                    console.error('Error loading custom fields:', err);
                    $('#quickEditCustomFieldsWrap').empty(); 
                });

                if (typeof $().flatpickr === 'function') {
                    var fpOnChangeNouki = function() { updateQuickEditNoukiRequiredIndicators(); };
                    initQuickEditFlatpickr('#quickEditStartDate', { defaultHour: getStartDateDefaultHour(), defaultMinute: 0 });
                    initQuickEditFlatpickr('#quickEditEndDate', {
                        defaultHour: getDeadlineDefaultHour(),
                        defaultMinute: 0,
                        onChange: fpOnChangeNouki
                    });
                    ['#quickEditCailyNouki', '#quickEditGuisNouki'].forEach(function(sel) {
                        initQuickEditFlatpickr(sel, {
                            defaultHour: getDeadlineDefaultHour(),
                            defaultMinute: 0,
                            onChange: fpOnChangeNouki
                        });
                    });
                    updateQuickEditNoukiRequiredIndicators();
                }

                // Load team list, project members, department users then init Tagify
                const teamIdsStr = (p.teams || '').toString().trim();
                Promise.all([
                    teamIdsStr ? axios.get('/api/index.php?model=team&method=listbyids&ids=' + teamIdsStr.split(',').map(function(id) { return id.trim(); }).filter(Boolean).join(',')) : Promise.resolve({ data: [] }),
                    axios.get('/api/index.php?model=project&method=getMembers&project_id=' + projectId).catch(function() { return { data: [] }; }),
                    depId ? axios.get('/api/index.php?model=department&method=get_users&department_id=' + depId).catch(function() { return { data: [] }; }) : Promise.resolve({ data: [] }),
                    axios.get('/api/index.php?model=team&method=list').catch(function() { return { data: [] }; })
                ]).then(function(results) {
                    const teamList = (results[0].data && Array.isArray(results[0].data)) ? results[0].data : [];
                    const membersRaw = results[1].data || [];
                    const managersRaw = membersRaw.filter(function(m) { return m && m.role === 'manager'; });
                    const managerIds = managersRaw.map(function(m) { return m.user_id; });
                    const membersOnly = membersRaw.filter(function(m) { return m && m.role === 'member' && managerIds.indexOf(m.user_id) === -1; });
                    const departmentUsers = (results[2].data && Array.isArray(results[2].data)) ? results[2].data : [];
                    const allTeams = (results[3].data && Array.isArray(results[3].data)) ? results[3].data : [];
                    const departmentTeams = depId ? allTeams.filter(function(t) { return String(t.department_id) === String(depId); }) : allTeams;

                    if (!window.Tagify) {
                        $('#quickEditProjectOrderType').val(orderTypeVal || '');
                        $('#quickEditModalLoading').addClass('d-none');
                        return;
                    }

                    // Clear trước khi gán tag mới, tránh giữ tag của dự án cũ
                    $('#quickEditProjectOrderType, #quickEditTeamTags, #quickEditManagerTags, #quickEditMembersTags').val('');

                    // 受注形態 Tagify (giống parent_project 案件依頼編集)
                    const orderTypeInput = document.getElementById('quickEditProjectOrderType');
                    if (orderTypeInput) {
                        if (orderTypeInput.tagify) {
                            try { orderTypeInput.tagify.destroy(); } catch (e) {}
                        }
                        orderTypeInput.value = '';
                        quickEditOrderTypeTagify = new window.Tagify(orderTypeInput, {
                            whitelist: ['新規', '修正', '新規修正', '変更', '免震', '耐震', '計画変更', '契約図', '実施図'],
                            maxTags: 5,
                            dropdown: {
                                maxItems: 20,
                                classname: 'tags-look-project-order-type',
                                enabled: 0,
                                closeOnSelect: true
                            }
                        });
                        if (orderTypeVal && String(orderTypeVal).trim() !== '') {
                            quickEditOrderTypeTagify.addTags(String(orderTypeVal).trim());
                        }
                    }

                    const teamInput = document.getElementById('quickEditTeamTags');
                    if (teamInput) {
                        teamInput.value = '';
                        quickEditTeamTagify = new window.Tagify(teamInput, {
                            whitelist: departmentTeams.map(function(t) { return { value: t.name, id: t.id }; }),
                            enforceWhitelist: false,
                            dropdown: { maxItems: 1000, enabled: 0, closeOnSelect: true }
                        });
                        quickEditTeamTagify.addTags(teamList.map(function(t) { return { value: t.name, id: t.id }; }));
                        // Tự động thêm/xóa members khi chọn/bỏ team (giống project/detail.php)
                        quickEditTeamTagify.on('remove', function(e) {
                            const removedTeamId = e.detail.data && e.detail.data.id;
                            if (!removedTeamId || !quickEditMembersTagify) return;
                            axios.get('/api/index.php?model=team&method=get&id=' + removedTeamId).then(function(res) {
                                if (res.data && Array.isArray(res.data.members)) {
                                    const teamMemberIds = res.data.members.map(function(m) { return String(m.user_id); });
                                    const remain = quickEditMembersTagify.value.filter(function(tag) { return teamMemberIds.indexOf(String(tag.id)) === -1; });
                                    quickEditMembersTagify.removeAllTags();
                                    quickEditMembersTagify.addTags(remain);
                                }
                            }).catch(function() {});
                        });
                        quickEditTeamTagify.on('add', function(e) {
                            const addedTeamId = e.detail.data && e.detail.data.id;
                            if (!addedTeamId) return;
                            axios.get('/api/index.php?model=team&method=get&id=' + addedTeamId).then(function(res) {
                                if (res.data && Array.isArray(res.data.members)) {
                                    if (quickEditMembersTagify) {
                                        const teamMembers = res.data.members.map(function(m) { return { id: m.user_id, value: m.user_name || '' }; });
                                        const currentIds = quickEditMembersTagify.value.map(function(tag) { return String(tag.id); });
                                        const toAdd = teamMembers.filter(function(m) { return currentIds.indexOf(String(m.id)) === -1; });
                                        quickEditMembersTagify.addTags(toAdd);
                                    }
                                    var leaders = res.data.members.filter(function(m) { return m.leader == 1 || m.leader === '1'; });
                                    if (leaders.length && quickEditManagerTagify) {
                                        var leaderTags = leaders.map(function(m) { return { id: m.user_id, value: m.user_name || '' }; });
                                        var managerCurrentIds = quickEditManagerTagify.value.map(function(tag) { return String(tag.id); });
                                        var leadersToAdd = leaderTags.filter(function(m) { return managerCurrentIds.indexOf(String(m.id)) === -1; });
                                        quickEditManagerTagify.addTags(leadersToAdd);
                                    }
                                }
                            }).catch(function() {});
                        });
                    }

                    const managerInput = document.getElementById('quickEditManagerTags');
                    if (managerInput) {
                        managerInput.value = '';
                        const allMembersForWhitelist = departmentUsers.map(function(u) { return { id: u.id || u.user_id, value: u.user_name || u.realname || '' }; });
                        quickEditManagerTagify = new window.Tagify(managerInput, {
                            whitelist: allMembersForWhitelist,
                            enforceWhitelist: false,
                            dropdown: { maxItems: 1000, enabled: 0, closeOnSelect: true }
                        });
                        quickEditManagerTagify.addTags(managersRaw.map(function(m) { return { id: m.user_id, value: m.user_name || '' }; }));
                    }

                    const membersInput = document.getElementById('quickEditMembersTags');
                    if (membersInput) {
                        membersInput.value = '';
                        const allMembersForWhitelist = departmentUsers.map(function(u) { return { id: u.id || u.user_id, value: u.user_name || u.realname || '' }; });
                        quickEditMembersTagify = new window.Tagify(membersInput, {
                            whitelist: allMembersForWhitelist,
                            enforceWhitelist: false,
                            dropdown: { maxItems: 1000, enabled: 0, closeOnSelect: true }
                        });
                        quickEditMembersTagify.addTags(membersOnly.map(function(m) { return { id: m.user_id, value: m.user_name || '' }; }));
                    }
                    $('#quickEditModalLoading').addClass('d-none');
                }).catch(function(err) {
                    console.error('Quick edit load team/members:', err);
                    $('#quickEditModalLoading').addClass('d-none');
                });
            }).catch(function(err) {
                console.error('Load project for quick edit:', err);
                $('#quickEditModalLoading').addClass('d-none');
                quickEditModal.hide();
                if (typeof alert === 'function') alert('プロジェクトの取得に失敗しました。');
            });
        };

        $('#quickEditProjectOrderTypeClear').on('click', function() { if (quickEditOrderTypeTagify) quickEditOrderTypeTagify.removeAllTags(); });
        $('#quickEditTeamTagsClear').on('click', function() { if (quickEditTeamTagify) quickEditTeamTagify.removeAllTags(); });
        $('#quickEditManagerTagsClear').on('click', function() { if (quickEditManagerTagify) quickEditManagerTagify.removeAllTags(); });
        $('#quickEditMembersTagsClear').on('click', function() { if (quickEditMembersTagify) quickEditMembersTagify.removeAllTags(); });

        var quickEditModalEl = document.getElementById('quickEditProjectModal');
        if (quickEditModalEl) {
            quickEditModalEl.addEventListener('hidden.bs.modal', function() {
                destroyQuickEditQuill();
            });
        }

        function isValidDateOrDateTime(str) {
            if (!str || typeof str !== 'string') return false;
            if (str.trim() === '') return false;
            return !!parseProjectDateTimeInDisplayTz(str);
        }

        function hasQuickEditDateValue(value) {
            return !!(value && String(value).trim() !== '');
        }

        function parseQuickEditDateTime(value) {
            if (!hasQuickEditDateValue(value)) return null;
            var parsed = parseProjectDateTimeInDisplayTz(value);
            return parsed ? parsed.toDate() : null;
        }

        function getQuickEditDateFieldValue(selector) {
            syncQuickEditDateFieldsFromPickers();
            return fromProjectDateTimeInputValue($(selector).val() || '');
        }

        function syncQuickEditDateFieldsFromPickers() {
            ['#quickEditStartDate', '#quickEditEndDate', '#quickEditCailyNouki', '#quickEditGuisNouki'].forEach(function(sel) {
                var $el = $(sel);
                if (!$el.length) return;
                var el = $el[0];
                var fp = $el.data('flatpickr') || (el && el._flatpickr);
                var displayVal = getFlatpickrVisibleValue(fp, el);
                if (!displayVal) {
                    if (fp && fp.selectedDates && fp.selectedDates.length) {
                        try { fp.clear(); } catch (e) {}
                    }
                    $el.val('');
                    if (el) el.value = '';
                    return;
                }
                if (fp && fp.selectedDates && fp.selectedDates.length > 0) {
                    $el.val(fp.formatDate(fp.selectedDates[0], PROJECT_DATETIME_FLATPICKR_FORMAT));
                } else {
                    $el.val(displayVal);
                }
            });
        }

        function updateQuickEditNoukiRequiredIndicators() {
            var showGuisFields = !isCailyBranchUser();
            var end = ($('#quickEditEndDate').val() || '').trim();
            var endFilled = showGuisFields && hasQuickEditDateValue(end);
            var tantou = ($('input[name="tantou"]:checked').val() || '').trim();
            $('#quickEditCailyNoukiRequired').toggleClass('d-none', !(endFilled && tantou === 'CAILY'));
            $('#quickEditGuisNoukiRequired').toggleClass('d-none', !(endFilled && tantou === 'GUIS'));
        }

        function validateQuickEditNoukiFields() {
            var errors = [];
            var showGuisFields = !isCailyBranchUser();
            var tantou = ($('input[name="tantou"]:checked').val() || '').trim();
            var caily = ($('#quickEditCailyNouki').val() || '').trim();
            var guis = ($('#quickEditGuisNouki').val() || '').trim();
            var end = ($('#quickEditEndDate').val() || '').trim();
            var endFilled = showGuisFields && hasQuickEditDateValue(end);

            if (endFilled) {
                if (tantou === 'CAILY' && !caily) {
                    errors.push({ field: 'caily', message: translateText('担当がCAILYの場合、CAILY納期は必須です') });
                }
                if (tantou === 'GUIS' && !guis) {
                    errors.push({ field: 'guis', message: translateText('担当がGUISの場合、GUIS納期は必須です') });
                }
            }

            if (hasQuickEditDateValue(caily) && hasQuickEditDateValue(guis)) {
                var cailyDate = parseQuickEditDateTime(caily);
                var guisDate = parseQuickEditDateTime(guis);
                if (cailyDate && guisDate && guisDate < cailyDate) {
                    var msg = translateText('GUIS納期はCAILY納期以降である必要があります');
                    if (showGuisFields) {
                        errors.push({ field: 'guis', message: msg });
                    } else {
                        errors.push({ field: 'caily', message: msg });
                    }
                }
            }

            return errors;
        }

        function revalidateQuickEditNoukiOnChange() {
            updateQuickEditNoukiRequiredIndicators();
            var $cailyNouki = $('#quickEditCailyNouki');
            var $guisNouki = $('#quickEditGuisNouki');
            $cailyNouki.removeClass('is-invalid');
            $guisNouki.removeClass('is-invalid');
            $('#quickEditCailyNoukiError').text('');
            $('#quickEditGuisNoukiError').text('');
            var noukiErrors = validateQuickEditNoukiFields();
            noukiErrors.forEach(function(err) {
                if (err.field === 'caily') {
                    $cailyNouki.addClass('is-invalid');
                    $('#quickEditCailyNoukiError').text(err.message);
                } else if (err.field === 'guis') {
                    $guisNouki.addClass('is-invalid');
                    $('#quickEditGuisNoukiError').text(err.message);
                }
            });
        }

        $(document).off('change.quickeditnouki').on('change.quickeditnouki', '#quickEditProjectForm input[name="tantou"]', revalidateQuickEditNoukiOnChange);
        $(document).off('change.quickeditnoukidate input.quickeditnoukidate').on('change.quickeditnoukidate input.quickeditnoukidate', '#quickEditEndDate, #quickEditCailyNouki, #quickEditGuisNouki', revalidateQuickEditNoukiOnChange);

        $('#quickEditProjectSaveBtnHeader').off('click.quickedit').on('click.quickedit', function() { $('#quickEditProjectSaveBtn').trigger('click.quickedit'); });

        $('#quickEditProjectSaveBtn').off('click.quickedit').on('click.quickedit', async function() {
            const id = $('#quickEditProjectId').val();
            if (!id) {
                if (typeof showMessage === 'function') showProjectListError(translateText('プロジェクトデータを読み込み中です。しばらくお待ちください。'));
                return;
            }
            var $name = $('#quickEditName');
            var $orderType = $('#quickEditProjectOrderType');
            var $progress = $('#quickEditProgress');
            var $startDate = $('#quickEditStartDate');
            var $endDate = $('#quickEditEndDate');
            var $cailyNouki = $('#quickEditCailyNouki');
            var $guisNouki = $('#quickEditGuisNouki');
            var $tantouWrap = $('#quickEditTantouWrap');
            var errorIds = ['quickEditNameError', 'quickEditProjectOrderTypeError', 'quickEditTantouError', 'quickEditStartDateError', 'quickEditEndDateError', 'quickEditCailyNoukiError', 'quickEditGuisNoukiError', 'quickEditProgressError', 'quickEditYoteiError'];
            errorIds.forEach(function(id) { $('#' + id).text(''); });
            $name.removeClass('is-invalid');
            setQuickEditOrderTypeInvalid(false);
            $progress.removeClass('is-invalid');
            $startDate.removeClass('is-invalid');
            $endDate.removeClass('is-invalid');
            $cailyNouki.removeClass('is-invalid');
            $guisNouki.removeClass('is-invalid');
            $tantouWrap.removeClass('is-invalid');
            $('#quickEditYoteiFromMonth, #quickEditYoteiToMonth').removeClass('is-invalid');
            var hasError = false;
            if (!quickEditIsManagerOnly) {
                if (!$name.val() || $name.val().toString().trim() === '') {
                    $name.addClass('is-invalid');
                    $('#quickEditNameError').text(translateText('案件名は必須です。'));
                    hasError = true;
                }
                if (!getQuickEditOrderTypeValue()) {
                    setQuickEditOrderTypeInvalid(true);
                    $('#quickEditProjectOrderTypeError').text(translateText('受注形態は必須です。'));
                    hasError = true;
                }
                if (!$('input[name="tantou"]:checked').length) {
                    $tantouWrap.addClass('is-invalid');
                    $('#quickEditTantouError').text(translateText('担当は必須です。'));
                    hasError = true;
                }
                if ($startDate.val() && $startDate.val().toString().trim() !== '' && !isValidDateOrDateTime($startDate.val())) {
                    $startDate.addClass('is-invalid');
                    $('#quickEditStartDateError').text(translateText('開始日の形式が正しくありません。（例: 2025-01-15 09:00）'));
                    hasError = true;
                }
                if (!isCailyBranchUser() && $endDate.val() && $endDate.val().toString().trim() !== '' && !isValidDateOrDateTime($endDate.val())) {
                    $endDate.addClass('is-invalid');
                    $('#quickEditEndDateError').text(translateText('期限日の形式が正しくありません。（例: 2025-02-28 18:00）'));
                    hasError = true;
                }
                if ($cailyNouki.val() && $cailyNouki.val().toString().trim() !== '' && !isValidDateOrDateTime($cailyNouki.val())) {
                    $cailyNouki.addClass('is-invalid');
                    $('#quickEditCailyNoukiError').text(translateText('CAILY納期の形式が正しくありません。（例: 2025-01-20 18:00）'));
                    hasError = true;
                }
                if ($guisNouki.length && $guisNouki.val() && $guisNouki.val().toString().trim() !== '' && !isValidDateOrDateTime($guisNouki.val())) {
                    $guisNouki.addClass('is-invalid');
                    $('#quickEditGuisNoukiError').text(translateText('GUIS納期の形式が正しくありません。（例: 2025-01-25 18:00）'));
                    hasError = true;
                }
                var startVal = ($startDate.val() || '').trim();
                var endVal = !isCailyBranchUser() ? ($endDate.val() || '').trim() : '';
                if (startVal && endVal) {
                    var startDt = parseQuickEditDateTime(startVal);
                    var endDt = parseQuickEditDateTime(endVal);
                    if (startDt && endDt && startDt >= endDt) {
                        $endDate.addClass('is-invalid');
                        $('#quickEditEndDateError').text(translateText('期限日は開始日より後である必要があります'));
                        hasError = true;
                    }
                }
                var noukiErrors = validateQuickEditNoukiFields();
                noukiErrors.forEach(function(err) {
                    if (err.field === 'caily') {
                        $cailyNouki.addClass('is-invalid');
                        $('#quickEditCailyNoukiError').text(err.message);
                        hasError = true;
                    } else if (err.field === 'guis') {
                        $guisNouki.addClass('is-invalid');
                        $('#quickEditGuisNoukiError').text(err.message);
                        hasError = true;
                    }
                });
            }
            var progressVal = $progress.val();
                if (progressVal !== '' && progressVal != null) {
                var p = parseInt(progressVal, 10);
                if (isNaN(p) || p < 0 || p > 100) {
                    $progress.addClass('is-invalid');
                    $('#quickEditProgressError').text(translateText('進捗率は0〜100の範囲で入力してください。'));
                    hasError = true;
                }
            }
            if (typeof window.YoteiField !== 'undefined') {
                var yoteiDraft = getQuickEditYoteiDraft();
                if (!window.YoteiField.isValid(yoteiDraft)) {
                    $('#quickEditYoteiFromMonth, #quickEditYoteiToMonth').addClass('is-invalid');
                    $('#quickEditYoteiError').text(translateText('予定工程の期間が正しくありません'));
                    hasError = true;
                }
            }
            if (hasError) {
                return;
            }
            const $btn = $('#quickEditProjectSaveBtn, #quickEditProjectSaveBtnHeader');
            const $spinner = $('#quickEditSaveSpinner, #quickEditSaveSpinnerHeader');
            $btn.prop('disabled', true);
            $spinner.removeClass('d-none');
            const formData = new FormData();
            formData.append('model', 'project');
            formData.append('method', 'update');
            formData.append('id', id);
            formData.append('name', $('#quickEditName').val() || '');
            formData.append('start_date', getQuickEditDateFieldValue('#quickEditStartDate'));
            formData.append('end_date', getQuickEditDateFieldValue('#quickEditEndDate'));
            var yoteiPayload = (typeof window.YoteiField !== 'undefined')
                ? window.YoteiField.toPayload(getQuickEditYoteiDraft())
                : null;
            formData.append('yotei', yoteiPayload ? JSON.stringify(yoteiPayload) : '');
            var quickEditStatus = $('#quickEditStatus').val() || 'draft';
            if (isCailyBranchUser() && !canViewEndDateColumn()
                && quickEditStatus === 'completed' && quickEditOriginalStatus !== 'completed') {
                if (typeof showMessage === 'function') {
                    showProjectListError(translateText('このステータスは選択できません。'));
                }
                $btn.prop('disabled', false);
                $spinner.addClass('d-none');
                return;
            }
            if (quickEditStatus === 'completed' && quickEditOriginalStatus !== 'completed') {
                var paymentOk = await confirmPaymentInfoBeforeComplete({
                    estimate_status: quickEditEstimateStatus,
                    invoice_status: quickEditInvoiceStatus
                });
                if (!paymentOk) {
                    $btn.prop('disabled', false);
                    $spinner.addClass('d-none');
                    return;
                }
            }
            var newCailyNouki = $('#quickEditCailyNoukiStatus').is(':checked') ? '納品済み' : '';
            var newGuisNouki = $('#quickEditGuisNoukiStatus').is(':checked') ? '納品済み' : '';
            var statusBecameCompleted = quickEditStatus === 'completed' && quickEditOriginalStatus !== 'completed';
            var noukiBecameDelivered =
                (newCailyNouki === '納品済み' && String(quickEditOriginalCailyNoukiStatus || '').indexOf('納品済み') === -1)
                || (newGuisNouki === '納品済み' && String(quickEditOriginalGuisNoukiStatus || '').indexOf('納品済み') === -1);
            var shareAnswer = null;
            if ((statusBecameCompleted || noukiBecameDelivered) && window.EnergyDrawingShare && quickEditShareContext) {
                var shareProject = {
                    id: $('#quickEditProjectId').val(),
                    energy_drawing_share_status: quickEditShareContext.energy_drawing_share_status,
                    department_name: quickEditShareContext.department_name,
                    has_energy_sibling: quickEditShareContext.has_energy_sibling
                };
                var shareResult = await window.EnergyDrawingShare.ensureEnergyDrawingShareAnswer(shareProject, {
                    departmentName: quickEditShareContext.department_name,
                    hasEnergySibling: quickEditShareContext.has_energy_sibling
                });
                if (shareResult === false) {
                    $btn.prop('disabled', false);
                    $spinner.addClass('d-none');
                    return;
                }
                shareAnswer = shareResult;
            }
            formData.append('status', quickEditStatus);
            formData.append('tantou', $('input[name="tantou"]:checked').val() || '');
            formData.append('caily_nouki', getQuickEditDateFieldValue('#quickEditCailyNouki'));
            formData.append('guis_nouki', getQuickEditDateFieldValue('#quickEditGuisNouki'));
            formData.append('caily_nouki_status', newCailyNouki);
            formData.append('guis_nouki_status', newGuisNouki);
            formData.append('progress', $('#quickEditProgress').val() !== '' ? parseInt($('#quickEditProgress').val(), 10) : 0);
            formData.append('project_order_type', getQuickEditOrderTypeValue());
            formData.append('teams', (quickEditTeamTagify && quickEditTeamTagify.value) ? quickEditTeamTagify.value.map(function(t) { return t.id; }).join(',') : '');
            formData.append('managers', (quickEditManagerTagify && quickEditManagerTagify.value) ? quickEditManagerTagify.value.map(function(t) { return t.id; }).join(',') : '');
            formData.append('members', (quickEditMembersTagify && quickEditMembersTagify.value) ? quickEditMembersTagify.value.map(function(t) { return t.id; }).join(',') : '');
            var descContent = (quickEditQuillInstance && typeof quickEditQuillInstance.getSemanticHTML === 'function') ? quickEditQuillInstance.getSemanticHTML() : ($('#quickEditQuillDescriptionTextarea').val() || '');
            formData.append('description', descContent);
            var customFieldsData = [];
            $('#quickEditCustomFieldsWrap .quick-edit-custom-field').each(function() {
                var $field = $(this);
                var label = $field.attr('data-custom-label');
                var type = $field.attr('data-custom-type');
                if (!label) return;
                var value = '';
                if (type === 'checkbox') {
                    var checked = $field.find('.quickEditCustomCheckbox:checked').map(function() { return $(this).val(); }).get();
                    value = checked.join(',');
                } else if (type === 'radio') {
                    var checkedEl = $field.find('.quickEditCustomRadio:checked');
                    value = checkedEl.length ? checkedEl.val() : '';
                } else {
                    var input = $field.find('.quickEditCustomInput');
                    value = input.length ? (input.val() || '').trim() : '';
                    if (type === 'datetime') {
                        var dtEl = input[0];
                        var dtFp = dtEl && (dtEl._flatpickr || $(dtEl).data('flatpickr'));
                        var dtDisplay = getFlatpickrVisibleValue(dtFp, dtEl);
                        if (!dtDisplay) {
                            if (dtFp && dtFp.selectedDates && dtFp.selectedDates.length) {
                                try { dtFp.clear(); } catch (e) {}
                            }
                            value = '';
                        } else if (dtFp && dtFp.selectedDates && dtFp.selectedDates.length > 0) {
                            value = fromProjectDateTimeInputValue(
                                dtFp.formatDate(dtFp.selectedDates[0], PROJECT_DATETIME_FLATPICKR_FORMAT)
                            );
                        } else {
                            value = fromProjectDateTimeInputValue(dtDisplay);
                        }
                    }
                }
                customFieldsData.push({ label: label, value: value });
            });
            if (customFieldsData.length) formData.append('custom_fields', JSON.stringify(customFieldsData));
            appendProjectVersionToFormData(formData, $('#quickEditProjectVersion').val());
            if (window.EnergyDrawingShare) {
                window.EnergyDrawingShare.appendEnergyDrawingShareToFormData(formData, shareAnswer);
            }
            axios.post('/api/index.php?model=project&method=update', formData, { headers: { 'Content-Type': 'multipart/form-data' } }).then(function(res) {
                var data = res && res.data ? res.data : {};
                if (data.status === 'success') {
                bootstrap.Modal.getInstance(document.getElementById('quickEditProjectModal')).hide();
                if (projectTable) reloadProjectTable(false);
                if (typeof showMessage === 'function') showMessage(translateText('プロジェクトを更新しました。'));
                    return;
                }
                if (handleProjectVersionConflict(data, function() {
                    var reloadId = $('#quickEditProjectId').val();
                    if (reloadId) {
                        window.__openQuickEditProjectModalImpl(reloadId, quickEditIsManagerOnly);
                    }
                })) {
                    return;
                }
                var errMsg = data.message || data.error || translateText('更新に失敗しました。');
                if (typeof showMessage === 'function') showProjectListError(errMsg, data);
                else if (typeof alert === 'function') alert(errMsg);
            }).catch(function(err) {
                console.error('Quick edit save:', err);
                var errData = err.response && err.response.data ? err.response.data : {};
                if (handleProjectVersionConflict(errData, function() {
                    var reloadId = $('#quickEditProjectId').val();
                    if (reloadId) {
                        window.__openQuickEditProjectModalImpl(reloadId, quickEditIsManagerOnly);
                    }
                })) {
                    return;
                }
                if (typeof showMessage === 'function') {
                    showProjectListError(errData.message || translateText('更新に失敗しました。'), errData);
                } else if (typeof alert === 'function') {
                    alert(errData.message ? errData.message : translateText('更新に失敗しました。'));
                }
            }).finally(function() {
                $btn.prop('disabled', false);
                $spinner.addClass('d-none');
            });
        });

        window.openQuickEditProjectModal = window.__openQuickEditProjectModalImpl;
    };

    window.installProjectListQuickEdit();
})();
