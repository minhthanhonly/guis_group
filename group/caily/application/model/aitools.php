<?php
/**
 * The fixed list of things the AI assistant can do.
 *
 * Read tools run automatically, always as the logged-in user and only through existing model methods.
 * Terminal tools (open_page, show_task_form, propose_actions) are never executed here: the chat loop
 * turns them into a navigation, a form, or a confirmation card for the user.
 * Nothing in this class accepts SQL, file paths or URLs.
 */
class AiTools {
    const READ_TOOLS = ['search_projects', 'search_buildings', 'get_project_tasks', 'get_statistics', 'get_availability', 'get_customer_info', 'list_departments_teams', 'get_project_notes', 'search_customers', 'get_building_projects', 'get_my_day', 'get_unstaffed_projects', 'get_building_statistics', 'get_requests', 'get_my_attendance', 'get_absences', 'get_work_hours', 'get_team_workload'];
    /** show_projects deadline => value of the list's 残り時間 filter (compares the project's end date). */
    const DEADLINE_FILTERS = ['overdue' => 'overdue', 'within_7_days' => '7', 'within_30_days' => '30'];
    const TERMINAL_TOOLS = ['open_page', 'show_task_form', 'propose_actions'];
    /** Tools that show their result on a page of the site: the browser goes there, Claude only gets the numbers. */
    const PAGE_TOOLS = ['show_projects', 'show_tasks', 'show_task_overview', 'show_requests', 'show_buildings'];
    /** Request kinds the building list page offers as a filter (it has no "3D" button). */
    const BUILDING_PAGE_REQUESTS = ['意匠', '設備', '3D設備', '省エネ', 'その他'];
    /** Pages opened for one record: page name => address without the id. */
    const ID_PAGES = ['building' => '/parent_project/detail.php?id=', 'building_files' => '/parent_project/attachment.php?id=', 'request' => '/form/detail.php?id='];
    /** Pages opened as they are. Each page applies its own access rules. */
    const STATIC_PAGES = [
        'building_list' => '/parent_project/index.php', 'task_overview' => '/project/task_overview.php',
        'project_gantt' => '/project/project_gantt.php', 'employee_statistics' => '/project/employee_statistics.php',
        'requests' => '/form/index.php', 'timecard' => '/timecard/index.php', 'schedule' => '/schedule/index.php',
        'customers' => '/customer/index.php', 'forum' => '/forum/index.php', 'storage' => '/storage/index.php',
        'members_online' => '/member/online.php',
    ];
    const REQUEST_TYPES = ['leave', 'outing', 'trip', 'holiday_work', 'overtime', 'attendance_correction', 'travel_expense', 'expense', 'trip_expense', 'commuting_allowance', 'purchase', 'it_support', 'call_recording'];
    const REQUEST_STATUSES = ['pending', 'approved', 'rejected', 'completed', 'draft'];
    /** Kinds of work a building asks for (ParentProject::list request_filter). */
    const BUILDING_REQUESTS = ['意匠', '設備', '3D設備', '省エネ', 'その他', '3D'];
    /** search_buildings sort => [order_column, order_dir] of ParentProject::list. */
    const BUILDING_SORTS = ['newest' => ['created_at', 'DESC'], 'nearest_deadline' => ['nearest_nouki', 'ASC'], 'most_projects' => ['child_project_count', 'DESC']];
    /** Date columns the project list can filter on (Project::appendProjectDateFieldRangeFilter). */
    const LIST_DATE_FIELDS = ['start_date', 'end_date', 'caily_nouki', 'guis_nouki', 'estimate_date', 'invoice_date'];
    /**
     * Every filter the project list reads from its URL. All of them are always sent, empty when unused:
     * the page merges the URL into the filters it remembers, so a missing key would keep an old filter.
     */
    const LIST_FILTER_DEFAULTS = [
        'department_id' => '', 'status' => '', 'filterKeyword' => '', 'filterProjectId' => '', 'filterTeam' => '',
        'filterCompany' => '', 'filterTantou' => '', 'filterAssignee' => '', 'filterPriority' => '', 'filterProgress' => '',
        'filterTimeLeft' => '', 'filterDateField' => '', 'filterDateFrom' => '', 'filterDateTo' => '',
        'filterProjectOrderType' => '', 'filterDeliveryStatus' => '', 'filterYoteiMonth' => '',
        'filterBusinessDocumentStatus' => '', 'filterNoDates' => '0', 'showInactive' => '0', 'my_projects' => '0', 'favorites_only' => '0',
    ];
    const MAX_DEPARTMENTS_COUNTED = 12;

    const DEFAULT_ROWS = 10;
    const MAX_ROWS = 20;
    const MAX_TEXT = 200;

    /** Same values as Project::updateStatusForAi accepts. */
    const PROJECT_STATUSES = ['draft', 'open', 'confirming', 'quotation', 'contract', 'waiting_documents', 'in_progress', 'calculation_completed', 'application_in_progress', 'waiting_invoice', 'completed', 'paused', 'cancelled'];
    /** Same values as the task screen stores (note the hyphen in in-progress). */
    const TASK_STATUSES = ['todo', 'in-progress', 'confirming', 'paused', 'completed', 'cancelled'];
    const PAGES = ['detail' => '/project/detail.php?id=', 'task' => '/project/task.php?project_id=', 'gantt' => '/project/gantt.php?project_id=', 'drawings' => '/project/drawings.php?project_id=', 'attachment' => '/project/attachment.php?project_id='];

    /** Columns always returned by search_projects: output name => source column. */
    const PROJECT_CORE = ['id' => 'id', 'name' => 'name', 'status' => 'status', 'construction_number' => 'parent_construction_number'];
    /** Columns returned only when asked for in `fields`. */
    const PROJECT_EXTRA = [
        'priority' => 'priority', 'progress' => 'progress', 'department_name' => 'department_name',
        'start_date' => 'start_date', 'end_date' => 'end_date', 'guis_nouki' => 'guis_nouki', 'caily_nouki' => 'caily_nouki',
        'tantou' => 'tantou', 'company_name' => 'parent_company_name', 'building_name' => 'parent_project_name',
        'branch_name' => 'parent_branch_name', 'contact_name' => 'parent_contact_name',
        'manager_names' => 'manager_names', 'member_names' => 'member_names', 'team_names' => 'team_names',
        // filled in by tool_search_projects (the project query does not select them)
        'building_id' => 'building_id', 'building_number' => 'building_number',
    ];

    /** Write actions the assistant may propose: type => allowed parameter names. Anything else is dropped. */
    const ACTION_PARAMS = [
        'parent_project_create' => ['company_name', 'project_name', 'branch_name', 'contact_name', 'customer_id', 'guis_receiver', 'request_date', 'construction_number', 'scale', 'type1', 'type2', 'requests', 'materials', 'structural_office', 'notes', 'status', 'construction_branch', 'city', 'type3', 'request_type'],
        'parent_project_update' => ['project_id', 'project_number', 'company_name', 'project_name', 'branch_name', 'contact_name', 'customer_id', 'guis_receiver', 'request_date', 'construction_number', 'scale', 'type1', 'type2', 'requests', 'materials', 'structural_office', 'notes', 'status', 'construction_branch', 'city', 'type3', 'request_type'],
        'project_create' => ['name', 'parent_project_id', 'project_number', 'description', 'status', 'priority', 'department_id', 'start_date', 'caily_nouki', 'guis_nouki', 'end_date', 'progress', 'teams', 'project_order_type', 'tantou'],
        'project_update' => ['name', 'parent_project_id', 'description', 'status', 'priority', 'department_id', 'start_date', 'caily_nouki', 'guis_nouki', 'end_date', 'progress', 'teams', 'project_order_type', 'tantou'],
        'update_project_status' => ['status'],
        'update_project_order_type' => ['project_order_type'],
        'task_update' => ['title', 'parent_id', 'description', 'status', 'priority', 'assigned_to', 'start_date', 'due_date', 'progress'],
        'project_add_manager' => ['project_id', 'user_id', 'user_name'],
        'project_add_member' => ['project_id', 'user_id', 'user_name'],
        'project_add_team_members' => ['project_id', 'team_id', 'team_name'],
        'project_remove_manager' => ['project_id', 'user_id', 'user_name'],
        'project_remove_member' => ['project_id', 'user_id', 'user_name'],
        'project_set_teams' => ['project_id', 'teams'],
        'project_add_team' => ['project_id', 'team_id', 'team_name'],
        'project_remove_team' => ['project_id', 'team_id', 'team_name'],
        'project_clear_members' => ['project_id'],
        'project_clear_managers' => ['project_id'],
        'project_clear_all_members' => ['project_id'],
        'project_clear_teams' => ['project_id'],
        'project_clear_all' => ['project_id'],
        // Group A: things an ordinary member does for themselves or on a project they take part in.
        'project_note_add' => ['project_id', 'content', 'label', 'visibility', 'is_important'],
        'project_note_toggle_pin' => [],
        'todo_add' => ['todo_title', 'todo_comment', 'todo_term'],
        'todo_complete' => [],
        'schedule_add' => ['title', 'start_date', 'start_time', 'end_date', 'end_time', 'all_day', 'comment', 'private'],
        'task_acknowledge' => [],
        'task_progress' => ['progress'],
        // The site lets the assignee, the creator and the project's managers change a task's status.
        'task_status' => ['status'],
        // A note on a building: id is the building.
        'building_note_add' => ['content', 'is_important'],
    ];
    const BUILDING_STATUSES = ['draft', 'under_contract', 'in_progress', 'completed', 'cancelled'];
    const DANGER_TYPES = ['project_clear_members', 'project_clear_managers', 'project_clear_all_members', 'project_clear_teams', 'project_clear_all'];
    const MAX_ACTIONS = 5;
    /** Actions on the user's own data (todo list, calendar): no project involved, any signed-in user. */
    const SELF_TYPES = ['todo_add', 'todo_complete', 'schedule_add'];
    /** Actions open to anyone who can see the project; the model methods apply their own finer rule. */
    const PARTICIPANT_TYPES = ['project_note_add', 'project_note_toggle_pin', 'task_acknowledge', 'task_progress', 'task_status'];
    /** Actions that change one existing record and therefore need its id. */
    const NEEDS_ID_TYPES = ['project_note_toggle_pin', 'todo_complete', 'task_acknowledge', 'task_progress', 'task_status', 'building_note_add'];
    /** Actions on a building that any user of the project module may ask for (the pages allow the same). */
    const BUILDING_OPEN_TYPES = ['building_note_add'];
    /** Actions whose project is looked up from the record (AI::normalizeAction), never taken from an id. */
    const RECORD_BOUND_TYPES = ['task_update', 'task_acknowledge', 'task_progress', 'task_status', 'project_note_toggle_pin', 'parent_project_update'];
    /** Actions that end in Project::update(), which refuses to save without the project's current version. */
    const VERSIONED_TYPES = ['project_update', 'update_project_status', 'update_project_order_type', 'project_set_teams', 'project_add_team', 'project_remove_team', 'project_clear_teams', 'project_clear_all'];

    /** Tools that list or count projects and tasks by department: limited to the user's default department when one is chosen. */
    const DEPARTMENT_TOOLS = ['search_projects', 'get_statistics', 'get_availability', 'get_unstaffed_projects', 'get_team_workload', 'show_projects', 'show_task_overview'];

    private $ai;
    /** @var int|null the default department the user chose in the chat bar (already checked by AI::chosenDepartment) */
    private $department = null;

    public function __construct(AI $ai) {
        $this->ai = $ai;
    }

    public function setDepartment(?int $departmentId): void {
        $this->department = $departmentId !== null && $departmentId > 0 ? $departmentId : null;
    }

    /**
     * With a default department, the department of a lookup is not Claude's to choose: it is replaced.
     * A lookup of projects named by id is left alone, so "this project" works on any project page.
     */
    private function withDepartment(string $name, array $input): array {
        if ($this->department === null || !in_array($name, self::DEPARTMENT_TOOLS, true)) {
            return $input;
        }
        if (!empty($input['project_ids']) || !empty($input['project_id'])) {
            return $input;
        }
        $input['department_id'] = $this->department;
        return $input;
    }

    /** Tool definitions sent to Claude. Order and content are fixed so the prompt cache prefix stays stable. */
    public function definitions(): array {
        $str = ['type' => 'string'];
        $int = ['type' => 'integer'];
        $date = ['type' => 'string', 'description' => 'YYYY-MM-DD'];
        $tool = function ($name, $description, array $properties, array $required = []) {
            return [
                'name' => $name,
                'description' => $description,
                'inputSchema' => ['type' => 'object', 'properties' => (object) $properties, 'required' => $required, 'additionalProperties' => false],
            ];
        };
        $actionHelp = [];
        foreach (self::ACTION_PARAMS as $type => $params) {
            $actionHelp[] = $type . '(' . implode(',', $params) . ')';
        }
        return [
            $tool('search_projects', 'Search projects (案件 / dự án) visible to the current user, newest first. Returns id, name, status, construction_number; ask for more columns with `fields` only when the question needs them. A list is never a count: it stops at 20 rows, so use get_statistics for "how many".', [
                'project_ids' => ['type' => 'array', 'items' => $int, 'description' => 'Project IDs ("dự án 23", "案件23"). Not the construction number.'],
                'project_name' => ['type' => 'string', 'description' => 'Project or building name, partial match'],
                'construction_number' => ['type' => 'string', 'description' => '工事番号 / số công trình, partial match'],
                'company_name' => $str,
                'branch_name' => $str,
                'contact_name' => $str,
                'team_name' => $str,
                'person_name' => ['type' => 'string', 'description' => 'Real name of a manager or member'],
                'status' => ['type' => 'string', 'enum' => self::PROJECT_STATUSES],
                'department_id' => $int,
                'tantou' => ['type' => 'string', 'enum' => ['CAILY', 'GUIS']],
                'overdue' => ['type' => 'boolean'],
                'date_from' => $date,
                'date_to' => ['type' => 'string', 'description' => 'YYYY-MM-DD. With date_from: projects whose period overlaps the range.'],
                'fields' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => array_keys(self::PROJECT_EXTRA)]],
                'limit' => ['type' => 'integer', 'description' => '1-20, default 10'],
            ]),
            $tool('search_buildings', 'Look up buildings (親案件 / tòa nhà / công trình). The building a project belongs to: give project_id. One building by id or by number such as P000008. A single building comes with its details (customer, branch, contact, type, scale, city, notes); with no parameter the most recent buildings are listed. The attribute filters (scale, city, type1, house_type, structural_office, feature) match part of the recorded value and can be combined.', [
                'project_id' => ['type' => 'integer', 'description' => 'Find the building of this project.'],
                'id' => $int,
                'project_number' => $str,
                'keyword' => ['type' => 'string', 'description' => 'Owner name, customer company, branch, contact, construction number or building number.'],
                'request' => ['type' => 'string', 'enum' => self::BUILDING_REQUESTS, 'description' => 'Kind of work requested.'],
                'scale' => ['type' => 'string', 'description' => '建物規模, a code such as W2F3J (structure W/S/RC, floors F, dwellings J); a part such as W2F matches too.'],
                'city' => ['type' => 'string', 'description' => '市区町村, e.g. 横浜市'],
                'type1' => ['type' => 'string', 'description' => '種類1, the product type, e.g. スターテラスⅡ'],
                'house_type' => ['type' => 'string', 'description' => '住宅種類, e.g. 長屋, 共同, 戸建'],
                'structural_office' => ['type' => 'string', 'description' => '構造事務所'],
                'feature' => ['type' => 'string', 'description' => '特徴, one feature, e.g. 補助金案件'],
                'sort' => ['type' => 'string', 'enum' => array_keys(self::BUILDING_SORTS), 'description' => 'nearest_deadline = earliest deadline among its unfinished projects.'],
                'limit' => $int,
            ]),
            $tool('get_project_tasks', 'Tasks of one project.', [
                'project_id' => $int,
                'include_subtasks' => ['type' => 'boolean'],
                'limit' => $int,
            ], ['project_id']),
            $tool('get_statistics', 'Counts, already aggregated. project_count is the number of projects matching the filter that was applied (echoed in `filter`). Task counts cover all projects of the department, whatever the status filter. Use the numbers as given.', [
                'department_id' => $int,
                'status' => ['type' => 'string', 'enum' => self::PROJECT_STATUSES],
            ]),
            $tool('get_availability', 'Teams and members with no project in a period.', [
                'date_from' => $date,
                'date_to' => $date,
                'department_id' => $int,
            ], ['date_from', 'date_to']),
            $tool('get_customer_info', 'Customer contact details of the building a project belongs to.', [
                'project_id' => $int,
                'project_number' => ['type' => 'string', 'description' => 'Building number, e.g. P000008'],
            ]),
            $tool('list_departments_teams', "The current user's departments and their teams (ids and names).", []),
            $tool('get_project_notes', 'Notes (memo board) of one project that the user may read, newest first, text shortened.', [
                'project_id' => $int,
                'limit' => $int,
            ], ['project_id']),
            $tool('search_customers', 'Find customers by company, branch, contact name or e-mail (at most 10).', [
                'keyword' => $str,
            ], ['keyword']),
            $tool('get_building_projects', 'The projects of one building, with their status. A building has no useful status of its own: its progress is the status of these projects.', [
                'building_id' => $int,
                'project_number' => ['type' => 'string', 'description' => 'Building number, e.g. P000008'],
            ]),
            $tool('get_my_day', "The current user's day: their open tasks (overdue, due that day, due in the next days), their open todo list, and the schedule of that day (meetings, who is off). Answers 'what do I have today', 'what is late', 'who is off today'.", [
                'date' => $date,
                'days' => ['type' => 'integer', 'description' => 'How many days ahead count as upcoming, 1-14, default 7.'],
            ]),
            $tool('get_unstaffed_projects', 'Active projects that nobody is in charge of: counts of projects without a manager and without a team, and a list of one of the two. For administrators and project managers.', [
                'department_id' => $int,
                'missing' => ['type' => 'string', 'enum' => ['manager', 'team'], 'description' => 'Which projects to list, default manager.'],
                'limit' => $int,
            ]),
            $tool('get_building_statistics', 'Counts of buildings: total, by kind of work requested, by house type, top customers, created per month, and buildings without a project.', []),
            $tool('get_requests', "Applications (申請: leave, overtime, expenses …). scope mine = the current user's own applications with their status; scope to_approve = applications waiting for the current user's approval. Only type, status, applicant and dates are returned, not the form contents.", [
                'scope' => ['type' => 'string', 'enum' => ['mine', 'to_approve']],
                'status' => ['type' => 'string', 'enum' => self::REQUEST_STATUSES],
                'type' => ['type' => 'string', 'enum' => self::REQUEST_TYPES],
                'limit' => $int,
            ]),
            $tool('get_my_attendance', "The current user's own time card for one pay period: check-in and check-out today, days recorded, working time, overtime, holiday work, days with no check-out, and how many leave applications were approved this year.", [
                'month' => ['type' => 'string', 'description' => 'YYYY-MM, default the current pay period'],
            ]),
            $tool('get_absences', 'Who is on leave, out of the office or on a business trip (approved applications only). For one day: the people and the time when given. For a month or a date range: one row per person with the number of leave days, the number of leave applications and the dates.', [
                'date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, one day, default today'],
                'month' => ['type' => 'string', 'description' => 'YYYY-MM, a whole month'],
                'date_from' => ['type' => 'string', 'description' => 'YYYY-MM-DD, with date_to: a range of at most 62 days'],
                'date_to' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
            ]),
            $tool('get_work_hours', 'Hours logged on tasks in one week (Monday to Sunday): total and the tasks with the most hours. For the current user; project managers may name another person.', [
                'week_start' => ['type' => 'string', 'description' => 'YYYY-MM-DD, any day of the week wanted, default this week'],
                'person_name' => $str,
            ]),
            $tool('get_team_workload', 'Workload per person of a department or team: open tasks, overdue tasks, hours on those tasks, hours logged this week. For administrators and project managers.', [
                'department_id' => $int,
                'team_id' => $int,
                'limit' => $int,
            ]),
            $tool('show_projects', 'Show projects to the user: opens the project list page with these filters filled in. Returns only numbers (count, and counts per department when no department was given). The list shows one department at a time; without department_id the department with the most matches is shown.', [
                'department_id' => $int,
                'status' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => self::PROJECT_STATUSES], 'description' => 'Empty = active projects only.'],
                'keyword' => ['type' => 'string', 'description' => 'Project name, construction number, customer. When given, the other filters are ignored by the list.'],
                'project_id' => $int,
                'team_name' => $str,
                'company_name' => $str,
                'person_name' => ['type' => 'string', 'description' => 'Real name of a manager or member'],
                'tantou' => ['type' => 'string', 'enum' => ['CAILY', 'GUIS']],
                'priority' => ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'urgent']],
                'date_field' => ['type' => 'string', 'enum' => self::LIST_DATE_FIELDS],
                'date_from' => $date,
                'date_to' => $date,
                'deadline' => ['type' => 'string', 'enum' => array_keys(self::DEADLINE_FILTERS), 'description' => 'By end date: overdue = past it and not finished.'],
                'my_projects' => ['type' => 'boolean', 'description' => 'Only projects the user is a member or manager of.'],
                'include_inactive' => ['type' => 'boolean', 'description' => 'Also completed, paused and cancelled projects.'],
            ]),
            $tool('show_tasks', 'Show the tasks of one project to the user: opens its task page. Returns only the task counts by status.', [
                'project_id' => $int,
            ], ['project_id']),
            $tool('show_task_overview', 'Show tasks across projects (a person\'s, a team\'s, a department\'s, or the user\'s own): opens the task overview page with these filters. Returns only the count and the counts by status.', [
                'department_id' => $int,
                'team_name' => $str,
                'person_name' => ['type' => 'string', 'description' => 'Real name of the assignee'],
                'my_tasks' => ['type' => 'boolean', 'description' => 'Tasks assigned to the current user.'],
                'created_month' => ['type' => 'string', 'description' => 'YYYY-MM'],
                'include_completed' => ['type' => 'boolean'],
                'unassigned_tasks' => ['type' => 'boolean', 'description' => 'Tasks that nobody is assigned to.'],
                'people_without_tasks' => ['type' => 'boolean', 'description' => 'People who hold no open task.'],
            ]),
            $tool('show_requests', 'Show applications/requests (申請: leave, overtime, expenses …) the user may see: opens the request list filtered. Returns only the count.', [
                'status' => ['type' => 'string', 'enum' => self::REQUEST_STATUSES],
                'type' => ['type' => 'string', 'enum' => self::REQUEST_TYPES],
            ]),
            $tool('show_buildings', 'Show buildings to the user: opens the building list page with these filters filled in. Returns only the count. The page has one search box: the attribute values are added to it as words that must all be found.', [
                'keyword' => ['type' => 'string', 'description' => 'Owner name, customer company, branch, contact, construction number or building number.'],
                'request' => ['type' => 'string', 'enum' => self::BUILDING_PAGE_REQUESTS],
                'scale' => ['type' => 'string', 'description' => '建物規模, e.g. W2F3J or a part such as W2F'],
                'city' => ['type' => 'string', 'description' => '市区町村'],
                'type1' => ['type' => 'string', 'description' => '種類1'],
                'house_type' => ['type' => 'string', 'description' => '住宅種類'],
                'structural_office' => ['type' => 'string', 'description' => '構造事務所'],
                'feature' => ['type' => 'string', 'description' => '特徴'],
                'sort' => ['type' => 'string', 'enum' => array_keys(self::BUILDING_SORTS)],
            ]),
            $tool('open_page', "Open a page in the user's browser. Project pages (detail, task, gantt, drawings, attachment) need project_id; building, building_files and request need id; the others need nothing.", [
                'page' => ['type' => 'string', 'enum' => array_merge(array_keys(self::PAGES), array_keys(self::ID_PAGES), array_keys(self::STATIC_PAGES))],
                'project_id' => $int,
                'id' => $int,
            ], ['page']),
            $tool('show_task_form', 'Show the add-task form for a project, pre-filled. Use this for every request to create a task.', [
                'project_id' => $int,
                'title' => $str,
                'description' => $str,
                'assigned_to_name' => $str,
                'start_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD HH:mm'],
                'due_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD HH:mm'],
                'priority' => $str,
            ], ['project_id']),
            $tool('propose_actions', 'Propose data changes. Nothing is changed until the user presses Confirm on the card this shows. Dates are YYYY-MM-DD or YYYY-MM-DD HH:mm. Project status: ' . implode('|', self::PROJECT_STATUSES) . '. Task status: ' . implode('|', self::TASK_STATUSES) . '. Types and their params: ' . implode('; ', $actionHelp) . '. project_note_add: visibility is all, members or self. todo_add, todo_complete and schedule_add act on the current user\'s own todo list and calendar (private=true hides the event from others; times are HH:MM). task_progress is 0-100. building_note_add adds a note to a building: id is the building id. parent_project_create is checked for an existing building with the same construction number or owner name; the user sees a warning on the card.', [
                'summary' => ['type' => 'string', 'description' => "One sentence in the user's language saying exactly what will change, with the concrete values."],
                'actions' => ['type' => 'array', 'items' => [
                    'type' => 'object',
                    'properties' => [
                        'type' => ['type' => 'string', 'enum' => array_keys(self::ACTION_PARAMS)],
                        'id' => ['type' => 'integer', 'description' => 'Id of the record to update (project, building or task).'],
                        'params' => ['type' => 'object'],
                    ],
                    'required' => ['type'],
                ]],
            ], ['summary', 'actions']),
        ];
    }

    /**
     * Checks proposed write actions against the whitelist.
     * Unknown types fail the whole proposal; unknown or non-scalar parameters are dropped.
     * @return array ['ok' => bool, 'actions' => array, 'danger' => bool, 'error' => ?string]
     */
    public function validateActions(array $actions): array {
        $fail = function ($error) {
            return ['ok' => false, 'actions' => [], 'danger' => false, 'error' => $error];
        };
        if (empty($actions)) {
            return $fail('No action given.');
        }
        if (count($actions) > self::MAX_ACTIONS) {
            return $fail('At most ' . self::MAX_ACTIONS . ' actions at a time.');
        }
        $clean = [];
        $danger = false;
        foreach ($actions as $action) {
            $type = is_array($action) && isset($action['type']) && is_string($action['type']) ? $action['type'] : '';
            if (!isset(self::ACTION_PARAMS[$type])) {
                return $fail('Action type not allowed: ' . mb_substr($type, 0, 40));
            }
            $params = [];
            $given = isset($action['params']) && is_array($action['params']) ? $action['params'] : [];
            foreach (self::ACTION_PARAMS[$type] as $name) {
                if (array_key_exists($name, $given) && (is_scalar($given[$name]) || $given[$name] === null)) {
                    $params[$name] = is_string($given[$name]) ? mb_substr($given[$name], 0, 5000) : $given[$name];
                }
            }
            if (isset($params['status'])) {
                // "deleted" and any other value outside the lists is refused: the assistant never deletes.
                $allowed = self::PROJECT_STATUSES;
                if ($type === 'task_update' || $type === 'task_status') {
                    $allowed = self::TASK_STATUSES;
                } elseif ($type === 'parent_project_create' || $type === 'parent_project_update') {
                    $allowed = self::BUILDING_STATUSES;
                }
                if (!in_array($params['status'], $allowed, true)) {
                    return $fail('Status not allowed: ' . mb_substr((string) $params['status'], 0, 40));
                }
            }
            if (isset($params['assigned_to']) && !preg_match('/^\d+(,\d+)*$/', (string) $params['assigned_to'])) {
                unset($params['assigned_to']);
            }
            $problem = self::checkActionValues($type, $params, isset($action['id']) ? (int) $action['id'] : 0);
            if ($problem !== null) {
                return $fail($problem);
            }
            $item = ['type' => $type, 'params' => $params];
            if (isset($action['id']) && (int) $action['id'] > 0) {
                $item['id'] = (int) $action['id'];
            }
            // Set by the server when the change is proposed (AI::applyTerminalTool); kept for stored actions.
            if (isset($action['version']) && is_int($action['version']) && $action['version'] > 0) {
                $item['version'] = $action['version'];
            }
            if (in_array($type, self::DANGER_TYPES, true)) {
                $danger = true;
            }
            $clean[] = $item;
        }
        return ['ok' => true, 'actions' => $clean, 'danger' => $danger, 'error' => null];
    }

    /** Value rules of the group A actions. @return string|null what is wrong, or null */
    private static function checkActionValues(string $type, array &$params, int $id): ?string {
        $isDate = function ($v) {
            return is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) === 1;
        };
        $isTime = function ($v) {
            return is_string($v) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v) === 1;
        };
        $text = function ($key) use ($params) {
            return isset($params[$key]) ? trim(strip_tags((string) $params[$key])) : '';
        };
        if (in_array($type, self::NEEDS_ID_TYPES, true) && $id <= 0) {
            return 'id is required for ' . $type;
        }
        if ($type === 'project_note_add') {
            if (empty($params['project_id']) || (int) $params['project_id'] <= 0 || $text('content') === '') {
                return 'project_note_add needs project_id and content.';
            }
            if (isset($params['visibility']) && !in_array($params['visibility'], ['all', 'members', 'self'], true)) {
                return 'visibility must be all, members or self.';
            }
            if (isset($params['is_important'])) {
                $params['is_important'] = empty($params['is_important']) ? 0 : 1;
            }
        } elseif ($type === 'todo_add') {
            if ($text('todo_title') === '') {
                return 'todo_add needs todo_title.';
            }
            if (isset($params['todo_term']) && !$isDate($params['todo_term'])) {
                return 'todo_term must be YYYY-MM-DD.';
            }
        } elseif ($type === 'schedule_add') {
            if ($text('title') === '' || !isset($params['start_date']) || !$isDate($params['start_date'])) {
                return 'schedule_add needs title and start_date (YYYY-MM-DD).';
            }
            if (isset($params['end_date']) && !$isDate($params['end_date'])) {
                return 'end_date must be YYYY-MM-DD.';
            }
            foreach (['start_time', 'end_time'] as $key) {
                if (isset($params[$key]) && !$isTime($params[$key])) {
                    return $key . ' must be HH:MM.';
                }
            }
        } elseif ($type === 'building_note_add') {
            if ($text('content') === '') {
                return 'building_note_add needs content.';
            }
            if (isset($params['is_important'])) {
                $params['is_important'] = empty($params['is_important']) ? 0 : 1;
            }
        } elseif ($type === 'task_status') {
            if (!isset($params['status']) || !in_array($params['status'], self::TASK_STATUSES, true)) {
                return 'task_status needs status: ' . implode('|', self::TASK_STATUSES) . '.';
            }
        } elseif ($type === 'task_progress') {
            if (!isset($params['progress']) || !preg_match('/^\d{1,3}$/', (string) $params['progress']) || (int) $params['progress'] > 100) {
                return 'progress must be a number from 0 to 100.';
            }
            $params['progress'] = (int) $params['progress'];
        }
        return null;
    }

    /** The project an action changes, or null when it is not tied to one (creates, building edits by number, the user's own data). */
    public static function actionProjectId(array $action): ?int {
        $type = isset($action['type']) ? $action['type'] : '';
        if (in_array($type, self::SELF_TYPES, true) || in_array($type, self::BUILDING_OPEN_TYPES, true)) {
            return null;
        }
        $params = isset($action['params']) && is_array($action['params']) ? $action['params'] : [];
        $id = isset($action['id']) ? (int) $action['id'] : 0;
        if (in_array($type, ['project_update', 'update_project_status', 'update_project_order_type'], true)) {
            return $id > 0 ? $id : null;
        }
        if (in_array($type, ['parent_project_create', 'project_create'], true)) {
            return null;
        }
        if ($type === 'parent_project_update' && $id > 0) {
            // The building is named directly: only administrators and department project managers.
            return null;
        }
        $pid = isset($params['project_id']) ? (int) $params['project_id'] : 0;
        if ($pid <= 0 && !in_array($type, self::RECORD_BOUND_TYPES, true)) {
            $pid = $id;
        }
        return $pid > 0 ? $pid : null;
    }

    const ACTION_LABELS = [
        'parent_project_create' => '建物を登録', 'parent_project_update' => '建物を更新',
        'project_create' => '案件を登録', 'project_update' => '案件を更新',
        'update_project_status' => 'ステータスを変更', 'update_project_order_type' => '受注形態を変更',
        'task_update' => 'タスクを更新',
        'project_add_manager' => 'マネージャーを追加', 'project_add_member' => 'メンバーを追加',
        'project_add_team_members' => 'チームのメンバーを追加',
        'project_remove_manager' => 'マネージャーを削除', 'project_remove_member' => 'メンバーを削除',
        'project_set_teams' => 'チームを設定', 'project_add_team' => 'チームを追加', 'project_remove_team' => 'チームを削除',
        'project_clear_members' => 'メンバーを全員削除', 'project_clear_managers' => 'マネージャーを全員削除',
        'project_clear_all_members' => 'メンバーとマネージャーを全員削除', 'project_clear_teams' => 'チームをすべて削除',
        'project_clear_all' => 'チーム・メンバー・マネージャーをすべて削除',
        'project_note_add' => 'メモを追加', 'project_note_toggle_pin' => 'メモのピンを切り替え',
        'todo_add' => 'ToDoを追加', 'todo_complete' => 'ToDoを完了にする', 'schedule_add' => '予定を追加',
        'task_acknowledge' => 'タスクを確認済みにする', 'task_progress' => 'タスクの進捗を更新', 'task_status' => 'タスクのステータスを変更',
        'building_note_add' => '建物メモを追加',
    ];

    /**
     * One line for the confirmation card, made from the validated action (not from Claude's wording),
     * so the user sees exactly what the server will run.
     * @return array ['type' => string, 'label' => string]
     */
    public static function describeAction(array $action): array {
        $type = $action['type'];
        $label = isset(self::ACTION_LABELS[$type]) ? self::ACTION_LABELS[$type] : $type;
        if (!empty($action['id'])) {
            $label .= ' #' . (int) $action['id'];
        }
        $parts = [];
        foreach (isset($action['params']) ? $action['params'] : [] as $key => $value) {
            $text = (string) $value;
            $parts[] = $key . '=' . (mb_strlen($text) > 200 ? mb_substr($text, 0, 200) . '…' : $text);
        }
        if (!empty($parts)) {
            $label .= ': ' . implode(', ', $parts);
        }
        return ['type' => $type, 'label' => $label];
    }

    public function isReadTool(string $name): bool {
        return in_array($name, self::READ_TOOLS, true);
    }

    /** Keeps only the given columns, at most $limit rows (never more than 20), strings cut to $maxLen. */
    public static function compact(array $rows, array $columns, int $limit = self::DEFAULT_ROWS, int $maxLen = self::MAX_TEXT): array {
        $limit = max(1, min(self::MAX_ROWS, $limit));
        $out = [];
        foreach (array_slice(array_values($rows), 0, $limit) as $row) {
            $item = [];
            foreach ($columns as $outName => $source) {
                if (is_int($outName)) {
                    $outName = $source;
                }
                $value = is_array($row) && array_key_exists($source, $row) ? $row[$source] : null;
                $item[$outName] = self::shorten($value, $maxLen);
            }
            $out[] = $item;
        }
        return $out;
    }

    private static function shorten($value, int $maxLen) {
        if (is_string($value) && mb_strlen($value) > $maxLen) {
            return mb_substr($value, 0, $maxLen);
        }
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = self::shorten($v, $maxLen);
            }
        }
        return $value;
    }

    /**
     * Runs a read tool as the logged-in user.
     * @return array data, or ['error' => string] for an unknown tool or invalid input. Never throws.
     */
    public function runRead(string $name, array $input): array {
        if (!$this->isReadTool($name)) {
            return ['error' => 'Unknown tool.'];
        }
        if (empty($_SESSION['show_project'])) {
            // Same rule as the project pages: no access to the project module, no project data.
            return ['error' => 'The user has no access to project data.'];
        }
        $error = $this->checkInput($name, $input);
        if ($error !== null) {
            return ['error' => $error];
        }
        try {
            return $this->{'tool_' . $name}($this->withDepartment($name, $input));
        } catch (\Throwable $e) {
            error_log('AiTools ' . $name . ': ' . $e->getMessage());
            return ['error' => 'The lookup failed.'];
        }
    }

    /** Validates input against the tool's own schema: no unknown keys, required keys present, simple types, enums, dates. */
    private function checkInput(string $name, array $input): ?string {
        $schema = null;
        foreach ($this->definitions() as $def) {
            if ($def['name'] === $name) {
                $schema = $def['inputSchema'];
            }
        }
        $props = (array) $schema['properties'];
        foreach ($input as $key => $value) {
            if (!isset($props[$key])) {
                return 'Unknown parameter: ' . mb_substr((string) $key, 0, 40);
            }
            $p = $props[$key];
            $ok = true;
            if ($p['type'] === 'integer') {
                $ok = is_int($value) || (is_string($value) && preg_match('/^\d+$/', $value));
            } elseif ($p['type'] === 'string') {
                $ok = is_string($value) || is_int($value);
                if ($ok && isset($p['enum'])) {
                    $ok = in_array((string) $value, $p['enum'], true);
                }
                if ($ok && isset($p['description']) && strpos($p['description'], 'YYYY-MM') === 0 && strpos($p['description'], 'YYYY-MM-DD') !== 0) {
                    $ok = (bool) preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $value);
                }
                if ($ok && isset($p['description']) && strpos($p['description'], 'YYYY-MM-DD') === 0 && strpos($p['description'], 'HH:mm') === false) {
                    $ok = (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value);
                }
            } elseif ($p['type'] === 'boolean') {
                $ok = is_bool($value);
            } elseif ($p['type'] === 'array') {
                $ok = is_array($value);
                if ($ok && isset($p['items']['enum'])) {
                    $ok = count(array_diff($value, $p['items']['enum'])) === 0;
                }
                if ($ok && $p['items']['type'] === 'integer') {
                    $ok = count(array_filter($value, function ($v) { return !is_int($v) && !(is_string($v) && preg_match('/^\d+$/', $v)); })) === 0;
                }
            }
            if (!$ok) {
                return 'Invalid value for ' . $key . '.';
            }
        }
        foreach ($schema['required'] as $key) {
            if (!array_key_exists($key, $input) || $input[$key] === '' || $input[$key] === null) {
                return 'Missing parameter: ' . $key;
            }
        }
        return null;
    }

    /**
     * Runs a page tool as the logged-in user.
     * @return array ['data' => array for Claude (numbers, or ['error' => string]), 'url' => string|null page to open]
     */
    public function runPage(string $name, array $input): array {
        $fail = function ($error) {
            return ['data' => ['error' => $error], 'url' => null];
        };
        if (!in_array($name, self::PAGE_TOOLS, true)) {
            return $fail('Unknown tool.');
        }
        if (empty($_SESSION['show_project'])) {
            return $fail('The user has no access to project data.');
        }
        $error = $this->checkInput($name, $input);
        if ($error !== null) {
            return $fail($error);
        }
        try {
            $out = $this->{'page_' . $name}($this->withDepartment($name, $input));
            return isset($out['error']) ? $fail($out['error']) : $out;
        } catch (\Throwable $e) {
            error_log('AiTools ' . $name . ': ' . $e->getMessage());
            return $fail('The page could not be prepared.');
        }
    }

    private function page_show_projects(array $in): array {
        $departments = $this->ai->getUserDepartments();
        $names = [];
        foreach ($departments as $d) {
            $names[(int) $d['id']] = $d['name'];
        }
        $departmentId = !empty($in['department_id']) ? (int) $in['department_id'] : 0;
        if ($departmentId > 0 && !isset($names[$departmentId])) {
            return ['error' => 'That department is not available to the user. Use list_departments_teams.'];
        }

        $query = self::LIST_FILTER_DEFAULTS;
        if (!empty($in['status'])) {
            $query['status'] = implode(',', $in['status']);
        }
        if (isset($in['keyword']) && trim((string) $in['keyword']) !== '') {
            $query['filterKeyword'] = mb_substr(trim((string) $in['keyword']), 0, 100);
        }
        if (!empty($in['project_id'])) {
            $query['filterProjectId'] = (string) (int) $in['project_id'];
        }
        if (isset($in['team_name']) && trim((string) $in['team_name']) !== '') {
            $team = $this->ai->findTeamForList(trim((string) $in['team_name']), $departmentId > 0 ? $departmentId : null);
            if ($team === null || !isset($names[$team['department_id']])) {
                return ['error' => 'Team not found: ' . mb_substr((string) $in['team_name'], 0, 60)];
            }
            $query['filterTeam'] = (string) $team['id'];
            $departmentId = $team['department_id'];
        }
        if (isset($in['company_name']) && trim((string) $in['company_name']) !== '') {
            $query['filterCompany'] = mb_substr(trim(str_replace(',', ' ', (string) $in['company_name'])), 0, 100);
        }
        if (isset($in['person_name']) && trim((string) $in['person_name']) !== '') {
            $userId = $this->ai->findUserIdByName(trim((string) $in['person_name']));
            if ($userId === null) {
                return ['error' => 'Person not found: ' . mb_substr((string) $in['person_name'], 0, 60)];
            }
            $query['filterAssignee'] = (string) $userId;
        }
        if (!empty($in['tantou'])) {
            $query['filterTantou'] = (string) $in['tantou'];
        }
        if (!empty($in['priority'])) {
            $query['filterPriority'] = (string) $in['priority'];
        }
        if (!empty($in['date_from']) || !empty($in['date_to'])) {
            $query['filterDateField'] = !empty($in['date_field']) ? (string) $in['date_field'] : 'end_date';
            $query['filterDateFrom'] = !empty($in['date_from']) ? $in['date_from'] : $in['date_to'];
            $query['filterDateTo'] = !empty($in['date_to']) ? $in['date_to'] : $in['date_from'];
        }
        if (!empty($in['deadline'])) {
            $query['filterTimeLeft'] = self::DEADLINE_FILTERS[$in['deadline']];
        }
        $query['my_projects'] = !empty($in['my_projects']) ? '1' : '0';
        $query['showInactive'] = !empty($in['include_inactive']) ? '1' : '0';

        $data = ['shown_on_page' => true];
        if ($departmentId <= 0 && count($names) === 1) {
            $departmentId = (int) array_keys($names)[0];
        }
        if ($departmentId <= 0) {
            // The list shows one department at a time: count them all, show the one with the most matches.
            if (empty($names)) {
                return ['error' => 'The user belongs to no department.'];
            }
            $byDepartment = [];
            foreach (array_slice($names, 0, self::MAX_DEPARTMENTS_COUNTED, true) as $id => $name) {
                $query['department_id'] = (string) $id;
                $byDepartment[] = ['id' => $id, 'name' => mb_substr($name, 0, 60), 'count' => $this->ai->countProjectsForList($query)];
            }
            usort($byDepartment, function ($a, $b) {
                return $b['count'] <=> $a['count'];
            });
            $departmentId = $byDepartment[0]['id'];
            $data['count'] = $byDepartment[0]['count'];
            $data['by_department'] = $byDepartment;
            $data['total_all_departments'] = array_sum(array_column($byDepartment, 'count'));
            $query['department_id'] = (string) $departmentId;
        } else {
            $query['department_id'] = (string) $departmentId;
            $data['count'] = $this->ai->countProjectsForList($query);
        }
        $data['department'] = ['id' => $departmentId, 'name' => mb_substr($names[$departmentId], 0, 60)];
        // ai=1 tells project-list.js to apply these filters (applyAssistantFiltersFromUrl); it is not a filter.
        $url = '/project/index.php?ai=1&' . str_replace('~', '%7E', http_build_query($query, '', '&', PHP_QUERY_RFC3986));
        return ['data' => $data, 'url' => $url];
    }

    /** The address open_page goes to, or null when the page is unknown or its id is missing. */
    public static function pageUrl(string $page, int $projectId, int $id): ?string {
        if (isset(self::PAGES[$page])) {
            return $projectId > 0 ? self::PAGES[$page] . $projectId : null;
        }
        if (isset(self::ID_PAGES[$page])) {
            return $id > 0 ? self::ID_PAGES[$page] . $id : null;
        }
        return isset(self::STATIC_PAGES[$page]) ? self::STATIC_PAGES[$page] : null;
    }

    private function page_show_task_overview(array $in): array {
        $departmentId = !empty($in['department_id']) ? (int) $in['department_id'] : 0;
        $teamId = 0;
        if (isset($in['team_name']) && trim((string) $in['team_name']) !== '') {
            $team = $this->ai->findTeamForList(trim((string) $in['team_name']), $departmentId > 0 ? $departmentId : null);
            if ($team === null) {
                return ['error' => 'Team not found: ' . mb_substr((string) $in['team_name'], 0, 60)];
            }
            $teamId = $team['id'];
            $departmentId = $team['department_id'];
        }
        $userId = 0;
        $mine = !empty($in['my_tasks']);
        if ($mine) {
            $userId = isset($_SESSION['id']) ? (int) $_SESSION['id'] : 0;
        } elseif (isset($in['person_name']) && trim((string) $in['person_name']) !== '') {
            $userId = (int) $this->ai->findUserIdByName(trim((string) $in['person_name']));
            if ($userId <= 0) {
                return ['error' => 'Person not found: ' . mb_substr((string) $in['person_name'], 0, 60)];
            }
        }
        $excludeCompleted = empty($in['include_completed']);
        $month = isset($in['created_month']) ? (string) $in['created_month'] : '';
        $unassignedOnly = !empty($in['unassigned_tasks']);
        $peopleOnly = !empty($in['people_without_tasks']);
        $counts = $this->ai->taskOverviewCounts([
            'department_id' => $departmentId, 'team_id' => $teamId, 'user_id' => $userId,
            'exclude_completed' => $excludeCompleted ? 1 : 0, 'created_month' => $month,
        ], $unassignedOnly);
        // Every filter is sent, empty when unused, so the page does not keep one it remembers.
        $query = [
            'tab' => $peopleOnly ? 'unassigned' : 'tasks',
            'show_unassigned' => $unassignedOnly ? '1' : '0',
            'department_id' => $departmentId > 0 ? (string) $departmentId : '',
            'team_id' => $teamId > 0 ? (string) $teamId : '',
            'user_id' => $userId > 0 ? (string) $userId : '',
            'created_month' => $month,
            'exclude_completed' => $excludeCompleted ? '1' : '0',
            'my_task' => $mine ? '1' : '0',
        ];
        $data = $peopleOnly
            ? ['shown_on_page' => true, 'people_without_tasks' => $counts['people_without_tasks']]
            : ['shown_on_page' => true, 'count' => $counts['count'], 'by_status' => $counts['by_status']];
        return [
            'data' => $data,
            'url' => self::STATIC_PAGES['task_overview'] . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986),
        ];
    }

    private function page_show_requests(array $in): array {
        $query = [];
        if (!empty($in['status'])) {
            $query['status'] = (string) $in['status'];
        }
        $count = $this->ai->countRequestsForAi($query + (!empty($in['type']) ? ['type' => (string) $in['type']] : []));
        if (!empty($in['type'])) {
            $query['tab'] = (string) $in['type'];
        }
        return [
            'data' => ['shown_on_page' => true, 'count' => $count],
            'url' => self::STATIC_PAGES['requests'] . (empty($query) ? '' : '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986)),
        ];
    }

    private function tool_get_project_notes(array $in): array {
        $rows = $this->ai->projectNotesForAi((int) $in['project_id']);
        if ($rows === null) {
            return ['error' => 'The project does not exist or the user cannot see it.'];
        }
        $limit = $this->limitOf($in);
        $notes = [];
        foreach (array_slice($rows, 0, $limit) as $row) {
            // Notes are rich text: only the words go to the model.
            $text = trim(preg_replace('/\s+/u', ' ', strip_tags(html_entity_decode((string) ($row['content'] ?? ''), ENT_QUOTES, 'UTF-8'))));
            $notes[] = [
                'id' => (int) $row['id'],
                'label' => mb_substr((string) ($row['label'] ?? ''), 0, 40),
                'content' => mb_substr(str_replace(['<', '>'], ['‹', '›'], $text), 0, self::MAX_TEXT),
                'important' => !empty($row['is_important']),
                'author' => mb_substr((string) ($row['realname'] ?? ($row['user_id'] ?? '')), 0, 60),
                'updated_at' => (string) ($row['updated_at'] ?? ''),
            ];
        }
        return ['notes' => $notes, 'more' => count($rows) > $limit];
    }

    private function tool_search_customers(array $in): array {
        $keyword = mb_substr(trim((string) $in['keyword']), 0, 100);
        if ($keyword === '') {
            return ['error' => 'Missing parameter: keyword'];
        }
        return ['customers' => self::compact($this->ai->searchCustomersForAi($keyword), ['id' => 'id', 'company_name' => 'company_name', 'branch' => 'branch', 'contact_name' => 'name', 'category' => 'category_name'], self::DEFAULT_ROWS, 80)];
    }

    private function page_show_buildings(array $in): array {
        $sort = self::BUILDING_SORTS[!empty($in['sort']) ? $in['sort'] : 'newest'];
        // Every key is sent, empty when unused: the page lays them over the filters it remembers.
        $query = [
            'search' => isset($in['keyword']) ? mb_substr(trim((string) $in['keyword']), 0, 100) : '',
            'request_filter' => !empty($in['request']) ? (string) $in['request'] : '',
            'order_column' => $sort[0],
            'order_dir' => $sort[1],
        ];
        // Attributes (建物情報): the page has one search box, which also searches them; every word must be found.
        $words = [$query['search']];
        foreach (['scale', 'city', 'type1', 'house_type', 'structural_office', 'feature'] as $param) {
            $words[] = isset($in[$param]) ? trim((string) $in[$param]) : '';
        }
        $query['search'] = mb_substr(implode(' ', array_filter($words, 'strlen')), 0, 100);
        $found = $this->ai->searchBuildingsForAi($query, 1);
        return [
            'data' => ['shown_on_page' => true, 'count' => $found['total']],
            // ai=1 tells parent-project-index.js to apply these filters (applyAssistantFiltersFromUrl).
            'url' => self::STATIC_PAGES['building_list'] . '?ai=1&' . str_replace('~', '%7E', http_build_query($query, '', '&', PHP_QUERY_RFC3986)),
        ];
    }

    private function page_show_tasks(array $in): array {
        $projectId = (int) $in['project_id'];
        $counts = $this->ai->taskCountsByStatus($projectId);
        if ($counts === null) {
            return ['error' => 'The user cannot open the tasks of this project, or it does not exist.'];
        }
        return [
            'data' => ['shown_on_page' => true, 'project_id' => $projectId, 'count' => array_sum($counts), 'by_status' => $counts],
            'url' => self::PAGES['task'] . $projectId,
        ];
    }

    private function limitOf(array $input): int {
        return isset($input['limit']) ? max(1, min(self::MAX_ROWS, (int) $input['limit'])) : self::DEFAULT_ROWS;
    }

    private function tool_search_projects(array $in): array {
        $filters = [];
        foreach (['project_name', 'construction_number', 'company_name', 'branch_name', 'contact_name', 'team_name', 'person_name', 'tantou'] as $key) {
            if (isset($in[$key]) && trim((string) $in[$key]) !== '') {
                $filters[$key] = trim((string) $in[$key]);
            }
        }
        if (!empty($in['overdue'])) {
            $filters['overdue'] = true;
        }
        if (!empty($in['date_from']) || !empty($in['date_to'])) {
            $filters['date_start'] = !empty($in['date_from']) ? $in['date_from'] : $in['date_to'];
            $filters['date_end'] = !empty($in['date_to']) ? $in['date_to'] : $in['date_from'];
        }
        if (!empty($in['project_ids'])) {
            $filters['project_ids'] = array_slice(array_map('intval', $in['project_ids']), 0, self::MAX_ROWS);
        }
        $departmentId = !empty($in['department_id']) ? (int) $in['department_id'] : null;
        $status = !empty($in['status']) ? (string) $in['status'] : null;
        $data = $this->ai->fetchContext(['projects'], $departmentId, $filters, $status);
        $rows = isset($data['projects']) && is_array($data['projects']) ? $data['projects'] : [];
        $columns = self::PROJECT_CORE;
        foreach (isset($in['fields']) ? $in['fields'] : [] as $field) {
            $columns[$field] = self::PROJECT_EXTRA[$field];
        }
        if (isset($columns['building_id']) || isset($columns['building_number'])) {
            $refs = $this->ai->buildingRefsOfProjects(array_column($rows, 'id'));
            foreach ($rows as $i => $row) {
                $ref = isset($refs[(int) $row['id']]) ? $refs[(int) $row['id']] : ['building_id' => null, 'building_number' => null];
                $rows[$i]['building_id'] = $ref['building_id'];
                $rows[$i]['building_number'] = $ref['building_number'];
            }
        }
        $limit = $this->limitOf($in);
        // The source returns at most MAX_ROWS rows, so a full page means there may be more.
        return ['projects' => self::compact($rows, $columns, $limit), 'more' => count($rows) > $limit || count($rows) >= self::MAX_ROWS];
    }

    private function tool_search_buildings(array $in): array {
        $limit = $this->limitOf($in);
        $id = null;
        $extra = [];
        if (!empty($in['project_id'])) {
            // "The building of this project": follow the project's own link.
            $projectId = (int) $in['project_id'];
            $id = $this->ai->buildingIdOfProject($projectId);
            if ($id === null) {
                return ['buildings' => []];
            }
            if ($id === 0) {
                return ['buildings' => [], 'note' => 'This project is not linked to a building.'];
            }
            $extra = ['project_id' => $projectId];
        } elseif (!empty($in['id'])) {
            $id = (int) $in['id'];
        } elseif (isset($in['project_number']) && trim((string) $in['project_number']) !== '') {
            $id = $this->ai->findParentProjectIdByNumber(trim((string) $in['project_number']));
            if ($id === null) {
                return ['buildings' => []];
            }
        }
        if ($id !== null) {
            $detail = $this->ai->getBuildingDetailForAi($id);
            if ($detail === null) {
                return ['buildings' => []] + $extra;
            }
            $building = self::compact([$detail], array_keys($detail), 1)[0];
            $building['customers'] = self::compact($this->ai->buildingCustomersForAi($id), ['company_name' => 'company_name', 'branch' => 'branch', 'contact_name' => 'name', 'primary' => 'primary'], self::MAX_ROWS, 80);
            $building['note_entries'] = self::compact($this->ai->buildingNoteEntriesForAi($id), ['content', 'important', 'updated_at'], 5);
            return ['buildings' => [$building]] + $extra;
        }
        // A list: the page's own search (ParentProject::list). The building status is never passed on:
        // it is not maintained, and list() puts it into SQL unescaped.
        $sort = self::BUILDING_SORTS[!empty($in['sort']) ? $in['sort'] : 'newest'];
        // Attributes recorded under 建物情報: tool parameter => filter of ParentProject::list.
        $attributes = [];
        foreach (['scale' => 'scale', 'city' => 'city', 'type1' => 'type1', 'house_type' => 'type2', 'structural_office' => 'structural_office', 'feature' => 'features'] as $param => $filter) {
            if (isset($in[$param]) && trim((string) $in[$param]) !== '') {
                $attributes[$filter] = mb_substr(trim((string) $in[$param]), 0, 100);
            }
        }
        $found = $this->ai->searchBuildingsForAi([
            'search' => isset($in['keyword']) ? mb_substr(trim((string) $in['keyword']), 0, 100) : '',
            'request_filter' => !empty($in['request']) ? (string) $in['request'] : '',
            'order_column' => $sort[0],
            'order_dir' => $sort[1],
        ] + $attributes, $limit);
        $columns = ['id' => 'id', 'project_number' => 'project_number', 'project_name' => 'project_name', 'construction_number' => 'construction_number', 'company_name' => 'company_name', 'requests' => 'requests', 'child_projects' => 'child_project_count', 'nearest_deadline' => 'nearest_nouki'];
        if (!empty($attributes)) {
            // Searching by attributes: show them, so the answer can say why each building matched.
            $columns += ['scale' => 'scale', 'city' => 'city', 'type1' => 'type1', 'house_type' => 'type2', 'structural_office' => 'structural_office', 'features' => 'features'];
        }
        $out = ['buildings' => self::compact($found['rows'], $columns, $limit, 80), 'total' => $found['total'], 'more' => $found['total'] > min($limit, count($found['rows']))];
        if ($found['total'] === 0 && count($attributes) > 1) {
            // Nothing has all of them: say how many have each, so the answer can explain it at once.
            $names = ['scale' => 'scale', 'city' => 'city', 'type1' => 'type1', 'type2' => 'house_type', 'structural_office' => 'structural_office', 'features' => 'feature'];
            $out['matches_per_filter'] = [];
            foreach ($attributes as $filter => $value) {
                $out['matches_per_filter'][$names[$filter]] = $this->ai->searchBuildingsForAi([$filter => $value], 1)['total'];
            }
            $out['note'] = 'No building has all of these together. Many buildings have some of these fields not filled in. Report the counts per filter; do not search again with other spellings.';
        }
        return $out;
    }

    private function tool_get_building_projects(array $in): array {
        $id = !empty($in['building_id']) ? (int) $in['building_id'] : 0;
        if ($id <= 0 && isset($in['project_number']) && trim((string) $in['project_number']) !== '') {
            $id = (int) $this->ai->findParentProjectIdByNumber(trim((string) $in['project_number']));
        }
        if ($id <= 0) {
            return ['error' => 'Give building_id or project_number of an existing building.'];
        }
        if ($this->ai->getBuildingDetailForAi($id) === null) {
            return ['error' => 'Building not found.'];
        }
        $rows = $this->ai->buildingProjectsForAi($id);
        $byStatus = [];
        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            $byStatus[$status] = isset($byStatus[$status]) ? $byStatus[$status] + 1 : 1;
        }
        $columns = ['id' => 'id', 'name' => 'name', 'status' => 'status', 'department' => 'department_name', 'start_date' => 'start_date', 'end_date' => 'end_date', 'progress' => 'progress'];
        return ['building_id' => $id, 'total' => count($rows), 'by_status' => $byStatus, 'projects' => self::compact($rows, $columns, self::MAX_ROWS, 80)];
    }

    private function tool_get_my_day(array $in): array {
        $date = !empty($in['date']) ? (string) $in['date'] : date('Y-m-d');
        $days = isset($in['days']) ? (int) $in['days'] : 7;
        if ($days < 1 || $days > 14) {
            return ['error' => 'days must be from 1 to 14.'];
        }
        $until = date('Y-m-d', strtotime($date . ' +' . $days . ' day'));
        $groups = ['overdue' => [], 'due_today' => [], 'upcoming' => []];
        $noDueDate = 0;
        $tasks = $this->ai->myOpenTasksForAi();
        foreach ($tasks as $task) {
            $due = !empty($task['due_date']) ? substr((string) $task['due_date'], 0, 10) : '';
            if ($due === '') {
                $noDueDate++;
            } elseif ($due < $date) {
                $groups['overdue'][] = $task;
            } elseif ($due === $date) {
                $groups['due_today'][] = $task;
            } elseif ($due <= $until) {
                $groups['upcoming'][] = $task;
            }
        }
        $columns = ['id' => 'id', 'title' => 'title', 'project_id' => 'project_id', 'project' => 'project_name', 'due_date' => 'due_date', 'status' => 'status', 'progress' => 'progress'];
        $out = ['total_open' => count($tasks), 'no_due_date' => $noDueDate];
        foreach ($groups as $name => $rows) {
            usort($rows, function ($a, $b) {
                return strcmp((string) $a['due_date'], (string) $b['due_date']);
            });
            $out[$name . '_count'] = count($rows);
            $out[$name] = self::compact($rows, $columns, self::DEFAULT_ROWS, 60);
        }
        $events = [];
        foreach ($this->ai->eventsForAi($date) as $event) {
            $props = isset($event['extendedProps']) && is_array($event['extendedProps']) ? $event['extendedProps'] : [];
            $events[] = [
                'title' => mb_substr((string) ($event['title'] ?? ''), 0, 80),
                'start' => (string) ($event['start'] ?? ''),
                'end' => (string) ($event['end'] ?? ''),
                'all_day' => !empty($event['allDay']),
                'calendar' => mb_substr((string) ($props['calendar'] ?? ''), 0, 30),
                'mine' => isset($props['owner'], $_SESSION['userid']) && $props['owner'] === $_SESSION['userid'],
            ];
        }
        return [
            'date' => $date,
            'tasks' => $out,
            'todos' => self::compact($this->ai->myTodosForAi(), ['id' => 'id', 'title' => 'todo_title', 'due' => 'todo_term'], self::MAX_ROWS, 80),
            'events' => array_slice($events, 0, self::MAX_ROWS),
        ];
    }

    private function tool_get_unstaffed_projects(array $in): array {
        if (!$this->ai->canProposeWrite(null)) {
            return ['error' => 'Only administrators and project managers can see the staffing overview.'];
        }
        $missing = !empty($in['missing']) ? (string) $in['missing'] : 'manager';
        $limit = $this->limitOf($in);
        $found = $this->ai->unstaffedProjectsForAi(!empty($in['department_id']) ? (int) $in['department_id'] : 0, $missing, self::MAX_ROWS);
        $total = $missing === 'team' ? $found['without_team'] : $found['without_manager'];
        return [
            'without_manager' => $found['without_manager'],
            'without_team' => $found['without_team'],
            'listed' => $missing,
            'projects' => self::compact($found['rows'], ['id' => 'id', 'name' => 'name', 'status' => 'status', 'department' => 'department_name', 'end_date' => 'end_date'], $limit, 80),
            'more' => $total > $limit,
        ];
    }

    private function tool_get_requests(array $in): array {
        $scope = !empty($in['scope']) ? (string) $in['scope'] : 'mine';
        $limit = $this->limitOf($in);
        $query = [];
        if (!empty($in['status'])) {
            $query['status'] = (string) $in['status'];
        }
        if (!empty($in['type'])) {
            $query['type'] = (string) $in['type'];
        }
        $found = $this->ai->requestsForAi($scope, $query, $limit);
        $columns = ['id' => 'id', 'type' => 'type', 'status' => 'status', 'applicant' => 'user_realname', 'created' => 'created_at', 'start_date' => 'start_date', 'end_date' => 'end_date'];
        return ['scope' => $scope, 'total' => $found['total'], 'requests' => self::compact($found['rows'], $columns, $limit, 60), 'more' => $found['total'] > $limit];
    }

    private function tool_get_my_attendance(array $in): array {
        $found = $this->ai->myAttendanceForAi(!empty($in['month']) ? (string) $in['month'] : null);
        if ($found === null) {
            return ['error' => 'The time card could not be read.'];
        }
        $today = date('Y-m-d');
        $todayRow = null;
        $missing = [];
        foreach ($found['rows'] as $row) {
            $date = substr((string) ($row['timecard_date'] ?? ''), 0, 10);
            $open = trim((string) ($row['timecard_open'] ?? ''));
            $close = trim((string) ($row['timecard_close'] ?? ''));
            if ($date === $today) {
                $todayRow = ['check_in' => $open, 'check_out' => $close];
            } elseif ($open !== '' && $close === '' && $date < $today) {
                $missing[] = $date;
            }
        }
        $year = date('Y');
        $leave = $this->ai->requestsForAi('mine', ['type' => 'leave', 'status' => 'approved,completed', 'from_date' => $year . '-01-01', 'to_date' => $year . '-12-31'], 1);
        return [
            'period' => ['from' => $found['from'], 'to' => $found['to']],
            'today' => $todayRow,
            'days_recorded' => count($found['rows']),
            'work_time' => $found['totals']['timecard_time'],
            'overtime' => $found['totals']['timecard_timeover'],
            'holiday_work' => $found['totals']['timecard_timeholiday'],
            'days_without_check_out' => array_slice($missing, -5),
            'leave_requests_this_year' => $leave['total'],
            'note' => 'Times are hours:minutes. The system keeps no balance of remaining leave days.',
        ];
    }

    private function tool_get_absences(array $in): array {
        if (!empty($in['month']) || !empty($in['date_from']) || !empty($in['date_to'])) {
            return $this->absencesOfPeriod($in);
        }
        $date = !empty($in['date']) ? (string) $in['date'] : date('Y-m-d');
        $rows = $this->ai->absencesForAi($date);
        return [
            'date' => $date,
            'total' => count($rows),
            'people' => self::compact($rows, ['name' => 'name', 'type' => 'type', 'start_date' => 'start_date', 'end_date' => 'end_date', 'time' => 'time'], self::MAX_ROWS, 40),
            'more' => count($rows) > self::MAX_ROWS,
        ];
    }

    /** get_absences for a month or a date range: per person, leave days as written on the applications. */
    private function absencesOfPeriod(array $in): array {
        if (!empty($in['month'])) {
            $from = $in['month'] . '-01';
            $to = date('Y-m-t', strtotime($from));
        } else {
            if (empty($in['date_from']) || empty($in['date_to'])) {
                return ['error' => 'Give both date_from and date_to, or month.'];
            }
            $from = (string) $in['date_from'];
            $to = (string) $in['date_to'];
        }
        $days = (int) round((strtotime($to) - strtotime($from)) / 86400) + 1;
        if ($days < 1 || $days > 62) {
            return ['error' => 'The period must run from date_from to date_to and cover at most 62 days.'];
        }
        $people = [];
        $applications = 0;
        $total = 0.0;
        foreach ($this->ai->absencesInPeriodForAi($from, $to) as $row) {
            $key = $row['userid'];
            if (!isset($people[$key])) {
                $people[$key] = ['name' => $row['name'], 'leave_days' => 0.0, 'leave_applications' => 0, 'leave_dates' => [], 'outings' => 0, 'trips' => 0];
            }
            if ($row['type'] === 'outing') {
                $people[$key]['outings']++;
            } elseif ($row['type'] === 'trip') {
                $people[$key]['trips']++;
            } else {
                $people[$key]['leave_days'] += $row['days'];
                $people[$key]['leave_applications']++;
                $span = substr($row['start_date'], 5) . ($row['end_date'] !== $row['start_date'] ? '〜' . substr($row['end_date'], 5) : '');
                $people[$key]['leave_dates'][] = $span . ($row['kind'] !== '' ? ' (' . $row['kind'] . ')' : '');
                $applications++;
                $total += $row['days'];
            }
        }
        $onLeave = 0;
        foreach ($people as $key => $person) {
            if ($person['leave_applications'] > 0) {
                $onLeave++;
            }
            sort($person['leave_dates']);
            $people[$key]['leave_dates'] = implode(', ', array_slice($person['leave_dates'], 0, 8)) . (count($person['leave_dates']) > 8 ? ' …' : '');
            $people[$key]['leave_days'] = round($person['leave_days'], 2);
        }
        // The list is of people who took leave; those who were only out or on a trip are counted.
        $withoutLeave = count($people) - $onLeave;
        $people = array_values(array_filter($people, function ($person) {
            return $person['leave_applications'] > 0;
        }));
        usort($people, function ($a, $b) {
            return [$b['leave_days'], $a['name']] <=> [$a['leave_days'], $b['name']];
        });
        return [
            'period' => ['from' => $from, 'to' => $to],
            'people_on_leave' => $onLeave,
            'leave_applications' => $applications,
            'leave_days_total' => round($total, 2),
            'people_only_out_or_on_trip' => $withoutLeave,
            'people' => self::compact($people, ['name' => 'name', 'leave_days' => 'leave_days', 'leave_applications' => 'leave_applications', 'leave_dates' => 'leave_dates', 'outings' => 'outings', 'trips' => 'trips'], self::MAX_ROWS, 160),
            'more' => count($people) > self::MAX_ROWS,
            'note' => 'Approved applications only; days are those written on each application (0.5 = half a day). An application that reaches into the period is counted whole.',
        ];
    }

    private function tool_get_work_hours(array $in): array {
        $userId = 0;
        if (isset($in['person_name']) && trim((string) $in['person_name']) !== '') {
            if (empty($_SESSION['isProjectManager'])) {
                return ['error' => "Only project managers can see another person's hours."];
            }
            $userId = (int) $this->ai->findUserIdByName(trim((string) $in['person_name']));
            if ($userId <= 0) {
                return ['error' => 'Person not found: ' . mb_substr((string) $in['person_name'], 0, 40)];
            }
        }
        $week = $this->ai->workHoursForAi($userId, !empty($in['week_start']) ? (string) $in['week_start'] : '');
        if ($week === null) {
            return ['error' => 'The hours could not be read.'];
        }
        $tasks = array_values(array_filter($week['tasks'], function ($row) {
            return (float) ($row['week_hours'] ?? 0) > 0;
        }));
        usort($tasks, function ($a, $b) {
            return $b['week_hours'] <=> $a['week_hours'];
        });
        $total = 0.0;
        foreach ($tasks as $row) {
            $total += (float) $row['week_hours'];
        }
        return [
            'person' => $week['person'],
            'week' => ['from' => $week['from'], 'to' => $week['to']],
            'total_hours' => round($total, 2),
            'tasks_with_hours' => count($tasks),
            'tasks' => self::compact($tasks, ['id' => 'id', 'title' => 'title', 'project' => 'project_name', 'status' => 'status', 'week_hours' => 'week_hours'], self::DEFAULT_ROWS, 60),
        ];
    }

    private function tool_get_team_workload(array $in): array {
        if (!$this->ai->canProposeWrite(null)) {
            return ['error' => 'Only administrators and project managers can see the workload of a team.'];
        }
        $found = $this->ai->teamWorkloadForAi(!empty($in['department_id']) ? (int) $in['department_id'] : 0, !empty($in['team_id']) ? (int) $in['team_id'] : 0);
        $limit = $this->limitOf($in);
        return [
            'people_with_tasks' => count($found['people']),
            'people_without_tasks' => $found['people_without_tasks'],
            'people' => self::compact($found['people'], ['name' => 'name', 'open_tasks' => 'open_tasks', 'overdue' => 'overdue', 'hours_on_open_tasks' => 'hours_on_open_tasks', 'hours_this_week' => 'hours_this_week'], $limit, 40),
            'more' => count($found['people']) > $limit,
        ];
    }

    private function tool_get_building_statistics(array $in): array {
        return $this->ai->buildingStatisticsForAi();
    }

    private function tool_get_project_tasks(array $in): array {
        $limit = $this->limitOf($in);
        // Top-level tasks are stored with parent_id 0, while Task::getForAiContext only treats NULL as
        // top-level; so everything is fetched and subtasks are filtered out here.
        $rows = $this->ai->getTasksForContext((int) $in['project_id'], ['limit' => 100, 'include_subtasks' => true]);
        $rows = is_array($rows) ? $rows : [];
        if (empty($in['include_subtasks'])) {
            $rows = array_values(array_filter($rows, function ($row) {
                return empty($row['parent_id']);
            }));
        }
        $columns = ['id', 'title', 'status', 'assigned_to_name', 'due_date', 'progress', 'parent_id'];
        return ['tasks' => self::compact($rows, $columns, $limit), 'more' => count($rows) > $limit];
    }

    private function tool_get_statistics(array $in): array {
        $departmentId = !empty($in['department_id']) ? (int) $in['department_id'] : null;
        $status = !empty($in['status']) ? (string) $in['status'] : null;
        $data = $this->ai->fetchContext(['statistics'], $departmentId, [], $status);
        $stats = isset($data['statistics']) && is_array($data['statistics']) ? $data['statistics'] : [];
        $overview = isset($stats['projects']['overview']) ? $stats['projects']['overview'] : [];
        // Flat and self-describing: the filter is echoed so a filtered count is not mistaken for the grand total.
        $out = [
            'filter' => ['status' => $status !== null ? $status : 'any', 'department_id' => $departmentId !== null ? $departmentId : 'any'],
            'project_count' => isset($overview['total']) ? (int) $overview['total'] : 0,
        ];
        if ($status === null) {
            $out['projects_active'] = isset($overview['active']) ? (int) $overview['active'] : 0;
            $out['projects_completed'] = isset($overview['completed']) ? (int) $overview['completed'] : 0;
        }
        $out['projects_by_department'] = self::compact(isset($stats['projects']['by_department']) ? $stats['projects']['by_department'] : [], ['department_id', 'department_name', 'count'], self::MAX_ROWS, 60);
        $out['tasks_by_status'] = self::compact(isset($stats['tasks']['by_status']) ? $stats['tasks']['by_status'] : [], ['status', 'count'], self::MAX_ROWS, 40);
        $out['tasks_overdue'] = isset($stats['tasks']['overdue_count']) ? (int) $stats['tasks']['overdue_count'] : 0;
        return $out;
    }

    private function tool_get_availability(array $in): array {
        $departments = $this->ai->getUserDepartments();
        $departmentId = !empty($in['department_id']) ? (int) $in['department_id'] : (count($departments) === 1 ? (int) $departments[0]['id'] : 0);
        if ($departmentId <= 0) {
            return ['error' => 'department_id is required. Ask the user which department.', 'departments' => self::compact($departments, ['id', 'name'], self::MAX_ROWS)];
        }
        $data = $this->ai->fetchContext(['availability'], $departmentId, ['date_start' => $in['date_from'], 'date_end' => $in['date_to']], null);
        $a = isset($data['availability']) && is_array($data['availability']) ? $data['availability'] : [];
        return [
            'free_teams' => self::compact(isset($a['free_teams']) ? $a['free_teams'] : [], ['id', 'name'], self::MAX_ROWS),
            'free_members' => self::compact(isset($a['free_members']) ? $a['free_members'] : [], ['id', 'realname'], self::MAX_ROWS),
            'period' => ['date_start' => $in['date_from'], 'date_end' => $in['date_to']],
        ];
    }

    private function tool_get_customer_info(array $in): array {
        $filters = [];
        if (!empty($in['project_id'])) {
            $filters['project_id'] = (string) (int) $in['project_id'];
        } elseif (isset($in['project_number']) && trim((string) $in['project_number']) !== '') {
            $filters['parent_project_number'] = trim((string) $in['project_number']);
        } else {
            return ['error' => 'Give project_id or project_number.'];
        }
        $data = $this->ai->fetchContext(['customer'], null, $filters, null);
        $customer = isset($data['customer']) && is_array($data['customer']) ? $data['customer'] : null;
        if ($customer !== null) {
            $customer = array_filter(self::shorten($customer, self::MAX_TEXT), function ($v) { return $v !== '' && $v !== null; });
        }
        return ['customer' => $customer];
    }

    private function tool_list_departments_teams(array $in): array {
        $departments = $this->ai->getUserDepartments();
        return [
            'departments' => self::compact($departments, ['id', 'name'], self::MAX_ROWS),
            'teams' => self::compact($this->ai->getTeamsOfDepartments(array_column($departments, 'id')), ['id', 'name', 'department_id'], self::MAX_ROWS, 60),
        ];
    }
}
