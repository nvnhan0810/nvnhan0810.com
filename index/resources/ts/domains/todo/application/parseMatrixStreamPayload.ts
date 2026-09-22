import type { MatrixQuadrantKey, MatrixQuadrants, TodoItem, TodoPriority, TodoStatus } from "../types";

const STATUSES: readonly TodoStatus[] = [
  "backlog",
  "todo",
  "in_progress",
  "done",
  "rejected",
];

const PRIORITIES: readonly TodoPriority[] = ["low", "medium", "high", "urgent"];

const QUADRANT_KEYS: readonly MatrixQuadrantKey[] = [
  "do",
  "schedule",
  "delegate",
  "eliminate",
];

const isRecord = (value: unknown): value is Record<string, unknown> =>
  typeof value === "object" && value !== null && !Array.isArray(value);

const isTodoStatus = (value: unknown): value is TodoStatus =>
  typeof value === "string" && (STATUSES as readonly string[]).includes(value);

const isTodoPriority = (value: unknown): value is TodoPriority =>
  typeof value === "string" && (PRIORITIES as readonly string[]).includes(value);

const parseProject = (
  value: unknown,
): Pick<{ id: number; name: string }, "id" | "name"> | null | undefined => {
  if (value === null || value === undefined) {
    return value;
  }
  if (!isRecord(value)) {
    return undefined;
  }
  if (typeof value.id !== "number" || typeof value.name !== "string") {
    return undefined;
  }
  return { id: value.id, name: value.name };
};

const parseTodoItem = (value: unknown): TodoItem | null => {
  if (!isRecord(value)) {
    return null;
  }
  if (typeof value.id !== "number" || typeof value.title !== "string") {
    return null;
  }
  if (!isTodoStatus(value.status) || !isTodoPriority(value.priority)) {
    return null;
  }
  if (typeof value.is_urgent !== "boolean" || typeof value.is_important !== "boolean") {
    return null;
  }

  return {
    id: value.id,
    project_id: typeof value.project_id === "number" ? value.project_id : null,
    title: value.title,
    description: typeof value.description === "string" ? value.description : null,
    status: value.status,
    priority: value.priority,
    started_at: typeof value.started_at === "string" ? value.started_at : null,
    due_at: typeof value.due_at === "string" ? value.due_at : null,
    closed_at: typeof value.closed_at === "string" ? value.closed_at : null,
    is_urgent: value.is_urgent,
    is_important: value.is_important,
    project: parseProject(value.project) ?? null,
  };
};

export type MatrixStreamPayload = {
  version: number;
  quadrants: MatrixQuadrants;
};

export const parseMatrixStreamPayload = (value: unknown): MatrixStreamPayload => {
  if (!isRecord(value)) {
    throw new Error("Invalid matrix SSE payload");
  }
  if (typeof value.version !== "number" || !isRecord(value.quadrants)) {
    throw new Error("Invalid matrix SSE payload shape");
  }

  const quadrants = {} as MatrixQuadrants;
  for (const key of QUADRANT_KEYS) {
    const bucket = value.quadrants[key];
    if (!Array.isArray(bucket)) {
      throw new Error(`Invalid matrix quadrant: ${key}`);
    }
    const items: TodoItem[] = [];
    for (const item of bucket) {
      const parsed = parseTodoItem(item);
      if (parsed === null) {
        throw new Error(`Invalid todo in quadrant: ${key}`);
      }
      items.push(parsed);
    }
    quadrants[key] = items;
  }

  return { version: value.version, quadrants };
};
