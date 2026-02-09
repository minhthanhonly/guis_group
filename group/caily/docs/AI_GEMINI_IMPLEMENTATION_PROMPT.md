# Implementation Prompt: Gemini AI for Parent Project, Project, and Task Management

**Implementation status:** Phase 1–5 implemented. See `application/model/ai.php`, `application/model/project.php`, `application/model/task.php`, `application/model/parentproject.php`, `application/controller.php`, `application/library/helper.php`.

**Purpose:** This document is a coding prompt / technical specification. Use it to implement Gemini support for managing parent_project, project, and task: operations, statistics, scheduling, team/member assignment, and customer add/edit. Implement **one feature at a time**; do not implement everything in one go.

**Language:** All implementation instructions and acceptance criteria are in English.

---

## 1. Permission Rules (Must Enforce Everywhere)

Before implementing any write operation (create/update/delete) for projects, tasks, or customer links:

- **A user may edit a project only if at least one of the following is true:**
  1. `$_SESSION['authority'] === 'administrator'`, or  
  2. The user is a **manager of that project**: exists in `project_members` for that `project_id` with `role = 'manager'`, or  
  3. The user has **project_manager** (or project_director) for the project’s department: in `user_department` for that `department_id` with `project_manager = 1` (or equivalent `project_director` if used).

- Reuse or mirror the permission logic already used in:
  - `application/model/task.php` → `getPermission()` (checks admin, project manager, department project_manager).
  - Frontend: `canEditProject` / `can_manage_project` (project detail, list, Gantt).

- Any API or backend action that creates/updates/deletes projects, project members, teams, tasks, or customer-related project data **must** perform this permission check server-side before executing. Do not rely only on frontend or AI context.

---

## 2. Scope of “Gemini Support”

- **Parent project (parent_project):** List, filter, view; create/update/delete only where product already allows it and permission is checked.
- **Project (project):** List, filter, view; create/update/delete; assign teams and members (manager/member); respect permission rules above.
- **Task:** List by project, filter, view; create/update/delete; assign assignee; respect project permission (user can edit task only if they can edit the project, per rules above).
- **Statistics:** Provide or aggregate data for projects/tasks (e.g. by department, status, overdue) for display or for sending to Gemini as context. No write operations.
- **Scheduling / planning:** Suggest timelines, milestones, workload; implementation may only suggest (e.g. return text or structured suggestions). Actual date/assignment changes must go through existing project/task APIs with permission checks.
- **Team / member assignment:** Add, remove, or change project members (manager/member) and teams via existing APIs; every call must enforce the permission rules above.
- **Customer:** Add, edit, search customer information; link or use customer data in projects where the product already supports it. Any project-bound customer update must pass project edit permission.

---

## 3. Implementation Plan (Feature-by-Feature)

Implement in the following order. Each item is one unit of work. Do not combine multiple items into a single change unless explicitly stated.

### Phase 1: Context and permission for AI

1. **1.1 – User context for Gemini**  
   - **Goal:** When sending a request to Gemini, include minimal user context so the model can answer in a permission-aware way.  
   - **Requirements:**  
     - Add to the payload (or system/user message) sent to Gemini: current user identifier, display name, and whether they are an administrator, and optionally whether they are a project manager for a given project or have project_manager in a given department (if that context is available in the request).  
     - Do not send passwords or sensitive tokens.  
   - **Acceptance:** Each Gemini request includes a short, structured “user context” (e.g. `user_id`, `realname`, `is_administrator`, optional `is_project_manager_for_project_id`, optional `has_department_project_manager`). Document the format.

2. **1.2 – Permission helper for backend**  
   - **Goal:** Single place to check “can this user edit this project?” for use in AI-triggered or AI-related APIs.  
   - **Requirements:**  
     - Implement or refactor a function (e.g. `can_user_edit_project($user_id, $project_id)`) that returns true only if: session user is administrator, or is project manager of that project, or has project_manager for the project’s department.  
     - Use existing DB structures: `user`, `project_members`, `user_department`, `projects` (for `department_id`).  
   - **Acceptance:** All new or modified APIs that perform project/task/member writes use this helper (or the same logic) before proceeding.

### Phase 2: Read-only and statistics

3. **2.1 – Parent project list/read for AI context**  
   - **Goal:** Allow the backend to return a minimal list or single parent project for use as context sent to Gemini (e.g. when the user asks “what are my parent projects?” or “summary of parent project X”).  
   - **Requirements:**  
     - Reuse or add an internal API or method that returns parent projects (e.g. id, name, construction_number, status, department_id) with existing permission/filters applied (e.g. by department, status).  
     - No new write operations.  
   - **Acceptance:** Backend can return a safe, filtered list or one parent project by id; this can be passed into the AI flow as context when needed.

4. **2.2 – Project list/read for AI context**  
   - **Goal:** Same as 2.1 but for **projects** (child projects): minimal list or single project (id, name, status, department_id, manager_ids, member count, etc.) with existing list/detail permission applied.  
   - **Acceptance:** Backend can return filtered project(s) for the current user; usable as context for Gemini.

5. **2.3 – Task list/read for AI context**  
   - **Goal:** Return tasks for a project (or for current user) for use as context in Gemini (e.g. “tasks of project X”, “my assigned tasks”).  
   - **Requirements:** Reuse or expose existing task list API with permission check: only if the user can view the project.  
   - **Acceptance:** Backend can return task list for a given project when the user is allowed to see that project.

6. **2.4 – Statistics aggregation for AI**  
   - **Goal:** Provide aggregated statistics (e.g. project count by department/status, task count by status, overdue tasks) that can be sent to Gemini for summarization or suggestions.  
   - **Requirements:** Reuse or add an API that returns such aggregates; respect existing permission (e.g. only departments/projects the user can see).  
   - **Acceptance:** One or more endpoints return statistics in a structured form (e.g. JSON) that the AI flow can include in the prompt or in a separate message to Gemini.

### Phase 3: Scheduling and suggestions (no direct writes)

7. **3.1 – Scheduling suggestion flow**  
   - **Goal:** When the user asks for schedule or timeline suggestions, send relevant context (e.g. project dates, task list, assignees) to Gemini and return the model’s text (or structured) suggestion only. Do not change project/task dates or assignments in this step.  
   - **Requirements:**  
     - Build or reuse a flow that: (a) gathers project/task context (using 2.2, 2.3), (b) sends it to Gemini with a prompt asking for schedule/workload suggestions, (c) returns the answer to the client.  
     - In the prompt, state that the user must apply changes in the app and that only users with edit permission can do so.  
   - **Acceptance:** User can ask for schedule suggestions and receive a text (or structured) response without any DB writes.

8. **3.2 – Team/member assignment suggestion flow**  
   - **Goal:** Same as 3.1 but for “who should be assigned to this project/task?”. Gemini returns suggestions only; no assignment is performed.  
   - **Requirements:** Send project and optionally team/member list (or department users) as context; prompt Gemini to suggest assignments; return suggestions only.  
   - **Acceptance:** User receives assignment suggestions; actual assignment is done later via existing APIs with permission check.

### Phase 4: Writes (projects, tasks, members, customer)

9. **4.1 – Project create/update/delete via API (AI-triggered)**  
   - **Goal:** If the product supports “AI-suggested actions” that trigger project create/update/delete, implement or expose APIs that perform these operations and call the permission helper (1.2) before executing.  
   - **Requirements:**  
     - Any endpoint that creates/updates/deletes a project must call `can_user_edit_project` (or equivalent) for the target project (for update/delete) or for the department (for create, if applicable).  
     - Return clear error (e.g. 403) when the user does not have permission.  
   - **Acceptance:** Project create/update/delete triggered from an AI flow only succeed when the user is administrator, project manager of that project, or department project_manager; otherwise 403.

10. **4.2 – Task create/update/delete via API (AI-triggered)**  
    - **Goal:** Same as 4.1 for tasks. User can edit task only if they can edit the project (use same permission rule).  
    - **Requirements:** Before task create/update/delete, check project edit permission (1.2).  
    - **Acceptance:** Task writes succeed only when the user has project edit permission; otherwise 403.

11. **4.3 – Project member/team assignment via API (AI-triggered)**  
    - **Goal:** Add/remove project members (manager/member) or teams via API when triggered from an AI flow; enforce permission (1.2).  
    - **Requirements:** Endpoints that add/remove members or teams must call the project edit permission check first.  
    - **Acceptance:** Member/team changes succeed only when the user has project edit permission; otherwise 403.

12. **4.4 – Customer add/edit and link to project**  
    - **Goal:** Allow customer create/update (or link to project) from an AI-related flow; if the operation is scoped to a project, enforce project edit permission.  
    - **Requirements:**  
      - If the API updates project-related customer info (e.g. customer_info on project), check project edit permission.  
      - If the API only creates/updates a global customer record, follow existing product rules (e.g. role or permission for customer management).  
    - **Acceptance:** Customer updates that affect a project require project edit permission; others follow existing customer permission rules.

### Phase 5: Integration and safety

13. **5.1 – Chat UI and action confirmation**  
    - **Goal:** When the user confirms an “action” suggested by Gemini (e.g. “create task”, “assign member”), the frontend calls the corresponding API; the backend performs permission check and returns success or 403.  
    - **Requirements:** No automatic execution of destructive or write actions without user confirmation; display clear error when permission is denied.  
    - **Acceptance:** Every write action from the chat goes through an explicit user confirmation and then the secured API.

14. **5.2 – Logging and audit**  
    - **Goal:** Log AI-triggered write operations (who, what, when) for audit.  
    - **Requirements:** For each successful project/task/member/customer write triggered from the AI flow, log at least: user_id, action type, target id(s), timestamp.  
    - **Acceptance:** Audit log or log entries exist for AI-triggered writes.

---

## 4. Out of Scope for This Prompt

- Changing Gemini model parameters (temperature, etc.) unless required by a specific feature above.  
- General chat UX (themes, layout) unless it blocks the above flows.  
- Redesigning existing permission model; only reuse and enforce it.  
- Implementing features not listed (e.g. file upload, custom fields) unless they are necessary for one of the items above.

---

## 5. Definition of Done for Each Item

- Code implements only the described item (or a clearly documented subset).  
- Permission check (1.2) is used for all project/task/member writes introduced or touched.  
- No new write operation is possible without passing the permission rule (administrator OR project manager of that project OR department project_manager).  
- Brief documentation or comments describe how the new code fits into the AI flow and where permission is checked.

Use this document as the **single source of truth** when implementing Gemini support for parent_project, project, task, statistics, scheduling, team/member assignment, and customer add/edit, step by step.
