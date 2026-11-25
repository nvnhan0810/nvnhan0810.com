export type TodoProject = {
  id: number;
  name: string;
  git_repo_url: string | null;
  domain: string | null;
  /** Agent workspace (= Agent project) this Todo project maps to. */
  agent_workspace_id?: string | null;
  agent_workspace_name?: string | null;
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
};

export type MatrixQuadrantKey = "do" | "schedule" | "delegate" | "eliminate";

export type MatrixQuadrants = Record<MatrixQuadrantKey, TodoItem[]>;

/** Tasks this one still waits for (everything that is not `done`). */
export function pendingDependencies(todo: Pick<TodoItem, "depends_on">) {
  return (todo.depends_on ?? []).filter((d) => d.status !== "done");
}
