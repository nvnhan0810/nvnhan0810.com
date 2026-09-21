export type TodoProject = {
  id: number;
  name: string;
  git_repo_url: string | null;
  domain: string | null;
  todos_count?: number;
  created_at?: string;
  updated_at?: string;
};

export type TodoStatus = "backlog" | "todo" | "in_progress" | "done" | "rejected";
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
  created_at?: string;
  updated_at?: string;
};
