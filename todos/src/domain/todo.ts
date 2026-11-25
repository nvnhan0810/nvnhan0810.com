export type TodoProject = {
  id: number;
  name: string;
  git_repo_url: string | null;
  domain: string | null;
  /** Agent workspace (= Agent project) this Todo project maps to. */
  agent_workspace_id?: string | null;
  agent_workspace_name?: string | null;
  /** Prefix for task identifiers (PREFIX-N). Server default: DEF. */
  identifier_prefix?: string;
  todos_count?: number;
  created_at?: string;
  updated_at?: string;
};

export type TodoStatus =
  "backlog" | "todo" | "in_progress" | "in_review" | "done" | "rejected";
export type TodoDependency = {
  id: number;
  title: string;
  status: TodoStatus;
};
/** A task the "waits for" picker may offer (same project is filtered by the editor). */
export type DependencyCandidate = TodoDependency & { project_id: number };
export type TodoPriority = "low" | "medium" | "high" | "urgent";

export type TodoItem = {
  id: number;
  /** Human-facing key, e.g. DEF-12. Assigned once at creation. */
  identifier?: string;
  project_id: number | null;
  title: string;
  description: string | null;
  status: TodoStatus;
  priority: TodoPriority;
  started_at: string | null;
  due_at: string | null;
  closed_at: string | null;
  is_urgent: boolean;
  is_important: boolean;
  project?: Pick<TodoProject, "id" | "name"> | null;
  depends_on?: TodoDependency[];
  /** Agent repo this task runs in, and whether the Agent picks it up automatically. */
  agent_repo_id?: string | null;
  agent_repo_name?: string | null;
  agent_auto?: boolean;
  created_at?: string;
  updated_at?: string;
} & AgentRunFields;

export type MatrixQuadrantKey = "do" | "schedule" | "delegate" | "eliminate";

export type MatrixQuadrants = Record<MatrixQuadrantKey, TodoItem[]>;

/** Tasks this one still waits for (everything that is not `done`). */
export function pendingDependencies(todo: Pick<TodoItem, "depends_on">) {
  return (todo.depends_on ?? []).filter((d) => d.status !== "done");
}

/** Agent run state of a task (Phase 4). `idle` = never claimed, or released. */
export type AgentState =
  "idle" | "claimed" | "running" | "succeeded" | "failed" | "cancelled";

export type AgentEvent = {
  id?: number;
  kind: string;
  message: string | null;
  created_at: string;
};

export type AgentRunFields = {
  agent_state?: AgentState;
  agent_attempts?: number;
  agent_chat_url?: string | null;
  agent_branch?: string | null;
  agent_pr_url?: string | null;
  agent_pr_has_conflict?: boolean;
  agent_result_md?: string | null;
  agent_events?: AgentEvent[];
};

/** Which badge a task shows on the matrix, or `null` when the Agent has not touched it. */
export function agentBadgeState(
  todo: Pick<TodoItem, "agent_state">,
): Exclude<AgentState, "idle"> | null {
  switch (todo.agent_state) {
    case "claimed":
    case "running":
      return "running";
    case "succeeded":
    case "failed":
    case "cancelled":
      return todo.agent_state;
    default:
      return null;
  }
}

/** Only http(s) links are rendered as anchors, whatever the server sent. */
export function safeHttpUrl(raw: string | null | undefined): string | null {
  if (!raw) return null;
  try {
    const url = new URL(raw);
    return url.protocol === "https:" || url.protocol === "http:"
      ? url.href
      : null;
  } catch {
    return null;
  }
}

/** Shown when a task with a PR is moved to Done, because dependents may need the merged code. */
export function needsMergeBeforeDone(
  todo: Pick<TodoItem, "agent_pr_url">,
  nextStatus: string,
): boolean {
  return nextStatus === "done" && Boolean(todo.agent_pr_url);
}
