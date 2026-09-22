import type { TodoPriority, TodoStatus } from "../types";

export const TODO_STATUS_LABEL: Record<TodoStatus, string> = {
  backlog: "Backlog",
  todo: "Todo",
  in_progress: "In progress",
  done: "Done",
  rejected: "Rejected",
};

export const TODO_PRIORITY_LABEL: Record<TodoPriority, string> = {
  low: "Low",
  medium: "Medium",
  high: "High",
  urgent: "Urgent",
};
