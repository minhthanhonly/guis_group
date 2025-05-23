const { createApp } = Vue;

createApp({
    data() {
        return {
            flatTasks: [], // Store tasks in flat array
            users: [],
            selectedTask: null,
            editingTask: null,
            taskForm: {
                name: '',
                description: '',
                start_date: '',
                due_date: '',
                assignees: [],
                priority: 'medium',
                parent_id: null,
                progress: 0
            },
            expandedTasks: new Set(),
            taskModal: null
        }
    },
    computed: {
        // Build hierarchical structure from flat tasks
        hierarchicalTasks() {
            // Get root tasks (tasks with no parent)
            const rootTasks = this.flatTasks.filter(task => task.parent_id === null);
            
            // Build hierarchy by adding subtasks property to each task
            const buildHierarchy = (task) => {
                const subtasks = this.getChildTasks(task.id);
                return {
                    ...task,
                    subtasks: subtasks.map(buildHierarchy)
                };
            };

            return rootTasks.map(buildHierarchy);
        },

        displayTasks() {
            const renderTasks = (tasks, level = 0) => {
                return tasks.map(task => {
                    const hasSubtasks = this.getChildTasks(task.id).length > 0;
                    const isExpanded = this.expandedTasks.has(task.id);
                    const result = [{
                        ...task,
                        level,
                        hasSubtasks,
                        isExpanded
                    }];
                    
                    if (hasSubtasks && isExpanded) {
                        const children = this.getChildTasks(task.id);
                        result.push(...renderTasks(children, level + 1));
                    }
                    return result;
                }).flat();
            };
            return renderTasks(this.hierarchicalTasks);
        },

        availableParentTasks() {
            const currentTaskId = this.editingTask ? this.editingTask.id : null;
            // A task cannot be its own parent or a child of itself
            return this.flatTasks.filter(task => {
                if (task.id === currentTaskId) return false;
                if (!currentTaskId) return true;
                
                // Check if task is a child of current task
                let parentId = task.parent_id;
                while (parentId) {
                    if (parentId === currentTaskId) return false;
                    const parent = this.flatTasks.find(t => t.id === parentId);
                    parentId = parent ? parent.parent_id : null;
                }
                return true;
            });
        }
    },
    methods: {
        // Get all child tasks for a given task
        getChildTasks(taskId) {
            return this.flatTasks.filter(task => task.parent_id === taskId);
        },

        // Calculate progress for a task based on its direct children
        calculateTaskProgress(taskId) {
            const children = this.getChildTasks(taskId);
            if (children.length === 0) return null; // No children, use own progress

            const totalProgress = children.reduce((sum, child) => sum + child.progress, 0);
            return Math.round(totalProgress / children.length);
        },

        // Update progress for a task and all its ancestors
        updateTaskAndAncestorsProgress(taskId) {
            let currentId = taskId;
            while (currentId) {
                const task = this.flatTasks.find(t => t.id === currentId);
                if (!task) break;

                const calculatedProgress = this.calculateTaskProgress(task.id);
                if (calculatedProgress !== null) {
                    task.progress = calculatedProgress;
                }

                currentId = task.parent_id;
            }
        },

        async loadData() {
            try {
                const response = await fetch('./assets/js/sample-tasks.json');
                const data = await response.json();
                this.flatTasks = data.tasks;
                this.users = data.users;
                
                // Update progress for all tasks
                this.flatTasks.forEach(task => {
                    if (this.getChildTasks(task.id).length > 0) {
                        this.updateTaskAndAncestorsProgress(task.id);
                    }
                });
            } catch (error) {
                console.error('Error loading data:', error);
                alert('データの読み込みに失敗しました。');
            }
        },
        toggleTask(taskId) {
            if (this.expandedTasks.has(taskId)) {
                this.expandedTasks.delete(taskId);
            } else {
                this.expandedTasks.add(taskId);
            }
        },
        hasSubtasks(task) {
            return this.getChildTasks(task.id).length > 0;
        },
        openNewTaskModal() {
            this.editingTask = null;
            this.taskForm = {
                name: '',
                description: '',
                start_date: '',
                due_date: '',
                assignees: [],
                priority: 'medium',
                parent_id: null,
                progress: 0
            };
            const modal = new bootstrap.Modal(document.getElementById('taskModal'));
            modal.show();
        },
        viewTask(taskId) {
            const task = this.findTask(taskId);
            if (task) {
                this.selectedTask = task;
            }
        },
        editTask(taskId) {
            const task = this.findTask(taskId);
            if (task) {
                this.editingTask = task;
                this.taskForm = { ...task };
                const modal = new bootstrap.Modal(document.getElementById('taskModal'));
                modal.show();
            }
        },

        // Find a task and its parent chain
        findTaskAndParents(taskId) {
            const task = this.flatTasks.find(t => t.id === taskId);
            if (!task) return null;

            const parents = [];
            let currentId = task.parent_id;
            while (currentId) {
                const parent = this.flatTasks.find(t => t.id === currentId);
                if (!parent) break;
                parents.push(parent);
                currentId = parent.parent_id;
            }

            return { task, parents };
        },

        findTask(taskId) {
            return this.flatTasks.find(task => task.id === taskId);
        },
        saveTask() {
            if (this.editingTask) {
                // Update existing task
                const taskIndex = this.flatTasks.findIndex(t => t.id === this.editingTask.id);
                if (taskIndex !== -1) {
                    const oldParentId = this.flatTasks[taskIndex].parent_id;
                    Object.assign(this.flatTasks[taskIndex], this.taskForm);
                    
                    // If parent changed, update progress for both old and new parent chains
                    if (oldParentId !== this.taskForm.parent_id) {
                        if (oldParentId) this.updateTaskAndAncestorsProgress(oldParentId);
                        if (this.taskForm.parent_id) this.updateTaskAndAncestorsProgress(this.taskForm.parent_id);
                    } else if (this.taskForm.parent_id) {
                        // If parent didn't change but exists, update its chain
                        this.updateTaskAndAncestorsProgress(this.taskForm.parent_id);
                    }
                }
            } else {
                // Add new task
                const newTask = {
                    ...this.taskForm,
                    id: Date.now()
                };
                
                this.flatTasks.push(newTask);
                
                // Update parent's progress if this task has a parent
                if (newTask.parent_id) {
                    this.updateTaskAndAncestorsProgress(newTask.parent_id);
                }
            }
            
            const modal = bootstrap.Modal.getInstance(document.getElementById('taskModal'));
            modal.hide();
        },
        deleteTask(taskId) {
            if (!confirm('このタスクを削除してもよろしいですか？')) {
                return;
            }
            
            const taskToDelete = this.findTask(taskId);
            if (taskToDelete) {
                const parentId = taskToDelete.parent_id;
                
                // Remove all child tasks recursively
                const removeChildren = (parentId) => {
                    const children = this.flatTasks.filter(t => t.parent_id === parentId);
                    children.forEach(child => {
                        removeChildren(child.id);
                        const index = this.flatTasks.findIndex(t => t.id === child.id);
                        if (index !== -1) this.flatTasks.splice(index, 1);
                    });
                };
                
                removeChildren(taskId);
                
                // Remove the task itself
                const index = this.flatTasks.findIndex(t => t.id === taskId);
                if (index !== -1) {
                    this.flatTasks.splice(index, 1);
                }

                // Update parent's progress if task had a parent
                if (parentId) {
                    this.updateTaskAndAncestorsProgress(parentId);
                }

                if (this.selectedTask && this.selectedTask.id === taskId) {
                    this.selectedTask = null;
                }
            }
        },
        updateTaskStatus() {
            const task = this.findTask(this.selectedTask.id);
            if (task) {
                task.status = this.selectedTask.status;
            }
        },
        updateTaskProgress() {
            const task = this.findTask(this.selectedTask.id);
            if (task) {
                task.progress = parseInt(this.selectedTask.progress);
                
                // If task has a parent, update the parent chain
                if (task.parent_id) {
                    this.updateTaskAndAncestorsProgress(task.parent_id);
                }
            }
        },
        formatDate(date) {
            if (!date) return '';
            return new Date(date).toLocaleDateString('ja-JP');
        }
    },
    mounted() {
        this.loadData();
        window.app = this;
    }
}).mount('#app');
