(function () {
    const API = {
        getActive: '/api/index.php?model=task&method=getActiveTaskTimer',
        start: '/api/index.php?model=task&method=startTaskTimer',
        stop: '/api/index.php?model=task&method=stopTaskTimer',
    };
    const SERVER_TIMEZONE = 'Asia/Tokyo';

    function t(key) {
        if (typeof i18next !== 'undefined' && i18next.t) {
            const translated = i18next.t(key);
            if (translated && translated !== key) {
                return translated;
            }
        }
        return key;
    }

    function notify(message, isError) {
        if (typeof showMessage === 'function') {
            showMessage(message, isError);
            return;
        }
        if (isError) {
            console.error(message);
        } else {
            console.log(message);
        }
    }

    function pad2(n) {
        return String(n).padStart(2, '0');
    }

    function formatElapsed(seconds) {
        const total = Math.max(0, Math.floor(seconds));
        const h = Math.floor(total / 3600);
        const m = Math.floor((total % 3600) / 60);
        const s = total % 60;
        return `${pad2(h)}:${pad2(m)}:${pad2(s)}`;
    }

    function hoursToSeconds(hours) {
        const n = parseFloat(hours);
        if (Number.isNaN(n) || n < 0) {
            return 0;
        }
        return Math.round(n * 3600);
    }

    function parseServerWallClockAsDate(raw) {
        if (!raw) return null;
        const text = String(raw).trim();
        const match = text.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?$/);
        if (!match) return null;
        const iso = `${match[1]}-${match[2]}-${match[3]}T${match[4]}:${match[5]}:${match[6] || '00'}+09:00`;
        const date = new Date(iso);
        return Number.isNaN(date.getTime()) ? null : date;
    }

    function parseStartTime(startTime, startTimestamp) {
        const ts = Number(startTimestamp);
        if (Number.isFinite(ts) && ts > 0) {
            return new Date(ts * 1000);
        }

        if (!startTime) return null;

        const raw = String(startTime).trim();
        if (typeof moment !== 'undefined' && typeof moment.tz === 'function') {
            const formats = ['YYYY-MM-DD HH:mm:ss', 'YYYY-MM-DDTHH:mm:ss', moment.ISO_8601];
            for (let i = 0; i < formats.length; i++) {
                const m = moment.tz(raw, formats[i], SERVER_TIMEZONE);
                if (m.isValid()) {
                    return m.toDate();
                }
            }
        }

        return parseServerWallClockAsDate(raw);
    }

    function normalizeTaskIds(ids) {
        if (!Array.isArray(ids)) {
            return [];
        }
        return ids
            .map((id) => parseInt(id, 10))
            .filter((id) => !Number.isNaN(id) && id > 0);
    }

    function sameTaskIdSet(a, b) {
        if (a.length !== b.length) {
            return false;
        }
        const setB = new Set(b);
        return a.every((id) => setB.has(id));
    }

    const TaskTimer = {
        active: null,
        activeTaskIds: [],
        tickInterval: null,
        syncInterval: null,
        syncIntervalMs: 15000,
        busy: false,
        localVersion: 0,
        serverSkewSeconds: null,
        elapsedAtSync: null,
        syncedAtPerf: null,
        els: {},

        bindElements() {
            this.els.nav = document.getElementById('global-task-timer-nav');
            this.els.display = document.getElementById('global-task-timer-display');
            this.els.taskLink = document.getElementById('global-task-timer-task-link');
            this.els.stopBtn = document.getElementById('global-task-timer-stop-btn');
            this.els.label = document.getElementById('global-task-timer-label');
        },

        init() {
            this.bindElements();

            if (this.els.stopBtn && !this.els.stopBtn.dataset.timerBound) {
                this.els.stopBtn.dataset.timerBound = '1';
                this.els.stopBtn.addEventListener('click', (event) => {
                    event.preventDefault();
                    this.stop();
                });
            }

            this.refresh();
            this.startGlobalSync();
            this.bindSyncListeners();
        },

        bindSyncListeners() {
            if (this._syncListenersBound) return;
            this._syncListenersBound = true;

            window.addEventListener('focus', () => {
                this.refresh();
            });

            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) {
                    this.refresh();
                }
            });
        },

        dispatchChanged(detail) {
            document.dispatchEvent(new CustomEvent('task-timer-changed', { detail: detail || {} }));
        },

        updateActiveTaskIds(ids) {
            const next = normalizeTaskIds(ids);
            const changed = !sameTaskIdSet(this.activeTaskIds, next);
            this.activeTaskIds = next;
            if (changed) {
                this.dispatchChanged({ active_task_ids: next.slice() });
            }
        },

        removeActiveTaskId(taskId) {
            const id = parseInt(taskId, 10);
            if (Number.isNaN(id) || id <= 0) {
                return;
            }
            this.updateActiveTaskIds(this.activeTaskIds.filter((activeId) => activeId !== id));
        },

        applyActiveTaskIdsFromResponse(data) {
            if (data && Array.isArray(data.active_task_ids)) {
                this.updateActiveTaskIds(data.active_task_ids);
            }
        },

        hasActiveTimerForTask(taskId) {
            if (!taskId) {
                return false;
            }
            const id = parseInt(taskId, 10);
            if (Number.isNaN(id) || id <= 0) {
                return false;
            }
            return this.activeTaskIds.includes(id);
        },

        setActive(active, fromRemote) {
            if (!fromRemote) {
                this.localVersion += 1;
            }
            this.active = active || null;
            this.updateWidget();
            if (this.active) {
                this.startTick();
            } else {
                this.stopTick();
            }
            this.dispatchChanged({ active: this.active });
        },

        isActive(taskId) {
            if (!this.active || !taskId) return false;
            return parseInt(this.active.task_id, 10) === parseInt(taskId, 10);
        },

        applyServerTimeFromResponse(data, active) {
            if (!data) {
                return;
            }
            const serverNow = Number(data.server_now);
            if (Number.isFinite(serverNow) && serverNow > 0) {
                this.serverSkewSeconds = serverNow - Math.floor(Date.now() / 1000);
            }
            const payload = active || data.active || null;
            if (payload && payload.elapsed_seconds != null) {
                const elapsed = Number(payload.elapsed_seconds);
                if (Number.isFinite(elapsed) && elapsed >= 0) {
                    this.elapsedAtSync = elapsed;
                    this.syncedAtPerf = performance.now();
                }
            }
        },

        getServerNowSeconds() {
            if (Number.isFinite(this.serverSkewSeconds)) {
                return Math.floor(Date.now() / 1000) + this.serverSkewSeconds;
            }
            return Math.floor(Date.now() / 1000);
        },

        getElapsedSeconds() {
            if (!this.active) return 0;

            const startTs = Number(this.active.start_timestamp);
            if (Number.isFinite(startTs) && startTs > 0 && Number.isFinite(this.serverSkewSeconds)) {
                return Math.max(0, this.getServerNowSeconds() - startTs);
            }

            if (Number.isFinite(this.elapsedAtSync) && this.syncedAtPerf != null) {
                const driftSeconds = (performance.now() - this.syncedAtPerf) / 1000;
                return Math.max(0, Math.floor(this.elapsedAtSync + driftSeconds));
            }

            const start = parseStartTime(
                this.active.start_time,
                this.active.start_timestamp
            );
            if (!start) return 0;

            return Math.max(0, Math.floor((Date.now() - start.getTime()) / 1000));
        },

        getBaseSeconds() {
            if (!this.active) {
                return 0;
            }
            return hoursToSeconds(this.active.estimated_hours);
        },

        getDisplaySeconds() {
            return this.getBaseSeconds() + this.getElapsedSeconds();
        },

        startTick() {
            this.stopTick();
            this.updateWidget();
            this.tickInterval = window.setInterval(() => this.updateWidget(), 1000);
        },

        stopTick() {
            if (this.tickInterval) {
                window.clearInterval(this.tickInterval);
                this.tickInterval = null;
            }
        },

        startGlobalSync() {
            this.stopGlobalSync();
            this.syncInterval = window.setInterval(() => {
                this.refresh();
            }, this.syncIntervalMs);
        },

        stopGlobalSync() {
            if (this.syncInterval) {
                window.clearInterval(this.syncInterval);
                this.syncInterval = null;
            }
        },

        updateWidget() {
            if (!this.els.nav || !this.els.display) {
                this.bindElements();
            }
            if (!this.els.nav) return;

            if (!this.active) {
                this.els.nav.style.display = 'none';
                return;
            }

            this.els.nav.style.display = '';
            if (this.els.display) {
                this.els.display.textContent = formatElapsed(this.getDisplaySeconds());
            }
            if (this.els.taskLink) {
                const title = this.active.task_title || t('タスク');
                this.els.taskLink.textContent = title;
                this.els.taskLink.title = title;
                const projectId = this.active.project_id;
                const taskId = this.active.task_id;
                if (projectId) {
                    let href = `/project/task.php?project_id=${projectId}`;
                    if (taskId) {
                        href += `&task_id=${taskId}`;
                    }
                    this.els.taskLink.href = href;
                } else {
                    this.els.taskLink.href = 'javascript:void(0);';
                }
            }
            if (this.els.label && this.els.label.dataset.i18n) {
                this.els.label.textContent = t(this.els.label.dataset.i18n);
            }
            if (this.els.stopBtn) {
                const stopLabel = t(this.els.stopBtn.dataset.i18n || '作業時間を終了');
                this.els.stopBtn.title = stopLabel;
                this.els.stopBtn.setAttribute('aria-label', stopLabel);
            }
        },

        async refresh() {
            const versionAtStart = this.localVersion;
            const previousActive = this.active;
            try {
                const response = await axios.get(API.getActive);
                if (versionAtStart !== this.localVersion) {
                    return;
                }
                if (response.data && response.data.status === 'success') {
                    this.applyServerTimeFromResponse(response.data, response.data.active);
                    this.applyActiveTaskIdsFromResponse(response.data);
                    const remoteActive = response.data.active || null;
                    if (previousActive && !remoteActive) {
                        const stoppedTaskId = previousActive.task_id;
                        this.setActive(null, true);
                        this.removeActiveTaskId(stoppedTaskId);
                        this.dispatchChanged({
                            active: null,
                            task_id: stoppedTaskId,
                            stopped: true,
                            remote: true,
                        });
                        return;
                    }
                    this.setActive(remoteActive, true);
                }
            } catch (error) {
                console.error('Failed to load active task timer', error);
            }
        },

        async start(taskId, projectId, meta) {
            if (this.busy || !taskId) return false;
            this.busy = true;
            try {
                const formData = new FormData();
                formData.append('task_id', taskId);
                if (projectId) {
                    formData.append('project_id', projectId);
                }
                const response = await axios.post(API.start, formData);
                const data = response.data || {};

                if (data.status === 'success') {
                    this.applyActiveTaskIdsFromResponse(data);
                    const active = data.active || null;
                    if (active) {
                        this.applyServerTimeFromResponse(data, active);
                        if (!active.start_timestamp) {
                            const serverNow = Number(data.server_now);
                            active.start_timestamp = Number.isFinite(serverNow) && serverNow > 0
                                ? serverNow
                                : (() => {
                                    const parsed = parseStartTime(active.start_time);
                                    return parsed ? Math.floor(parsed.getTime() / 1000) : this.getServerNowSeconds();
                                })();
                        }
                        if (meta) {
                            if (meta.title && !active.task_title) active.task_title = meta.title;
                            if (meta.project_name && !active.project_name) active.project_name = meta.project_name;
                            if (meta.estimated_hours != null && (active.estimated_hours == null || active.estimated_hours === '')) {
                                active.estimated_hours = meta.estimated_hours;
                            }
                        }
                    }
                    if (data.stopped_previous_task) {
                        this.dispatchChanged({
                            stopped: true,
                            switched: true,
                            task_id: data.stopped_previous_task.task_id,
                            hours_added: data.stopped_previous_task.hours_added,
                            estimated_hours: data.stopped_previous_task.estimated_hours,
                        });
                    }
                    this.setActive(active);
                    return data;
                }

                if (data.active) {
                    this.applyServerTimeFromResponse(data, data.active);
                    this.setActive(data.active);
                }
                notify(data.message || t('作業計測の開始に失敗しました'), true);
                return false;
            } catch (error) {
                notify(t('作業計測の開始に失敗しました'), true);
                return false;
            } finally {
                this.busy = false;
            }
        },

        async stop(taskId) {
            if (this.busy) return false;
            this.busy = true;
            try {
                const formData = new FormData();
                if (taskId) {
                    formData.append('task_id', taskId);
                }
                const response = await axios.post(API.stop, formData);
                const data = response.data || {};

                if (data.status === 'success') {
                    if (Array.isArray(data.active_task_ids)) {
                        this.updateActiveTaskIds(data.active_task_ids);
                    } else if (data.task_id) {
                        this.removeActiveTaskId(data.task_id);
                    }
                    this.setActive(null);
                    this.dispatchChanged({
                        active: null,
                        task_id: data.task_id,
                        hours_added: data.hours_added,
                        estimated_hours: data.estimated_hours,
                        stopped: true,
                    });
                    notify(t('作業時間を計測しました'));
                    return data;
                }

                if (data.active) {
                    this.applyServerTimeFromResponse(data, data.active);
                    this.setActive(data.active);
                }
                notify(data.message || t('作業計測の終了に失敗しました'), true);
                return false;
            } catch (error) {
                notify(t('作業計測の終了に失敗しました'), true);
                return false;
            } finally {
                this.busy = false;
            }
        },

        async toggle(taskId, projectId, meta) {
            if (this.isActive(taskId)) {
                return this.stop(taskId);
            }
            const started = await this.start(taskId, projectId, meta);
            return started;
        },
    };

    window.TaskTimer = TaskTimer;

    function bootTaskTimer() {
        TaskTimer.init();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootTaskTimer);
    } else {
        bootTaskTimer();
    }
})();
