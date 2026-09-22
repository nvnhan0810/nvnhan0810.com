import type { MatrixQuadrantKey, MatrixQuadrants, TodoItem, TodoPriority } from "../types";

const PRIORITY_RANK: Record<TodoPriority, number> = {
  urgent: 0,
  high: 1,
  medium: 2,
  low: 3,
};

/** Eisenhower pick order: Làm ngay → Lên lịch → Giao việc → Loại bỏ */
export const MATRIX_PICK_ORDER: readonly MatrixQuadrantKey[] = [
  "do",
  "schedule",
  "delegate",
  "eliminate",
];

const dueTimestamp = (dueAt: string | null | undefined): number | null => {
  if (dueAt === null || dueAt === undefined || dueAt === "") {
    return null;
  }
  const ms = Date.parse(dueAt);
  return Number.isFinite(ms) ? ms : null;
};

const createdTimestamp = (createdAt: string | null | undefined): number => {
  if (createdAt === null || createdAt === undefined || createdAt === "") {
    return Number.MAX_SAFE_INTEGER;
  }
  const ms = Date.parse(createdAt);
  return Number.isFinite(ms) ? ms : Number.MAX_SAFE_INTEGER;
};

/**
 * Sort: priority (urgent→low) → due date (sooner first, null last) → oldest created first.
 */
export const compareMatrixTodos = (a: TodoItem, b: TodoItem): number => {
  const priorityDelta = PRIORITY_RANK[a.priority] - PRIORITY_RANK[b.priority];
  if (priorityDelta !== 0) {
    return priorityDelta;
  }

  const dueA = dueTimestamp(a.due_at);
  const dueB = dueTimestamp(b.due_at);
  if (dueA === null && dueB !== null) {
    return 1;
  }
  if (dueA !== null && dueB === null) {
    return -1;
  }
  if (dueA !== null && dueB !== null && dueA !== dueB) {
    return dueA - dueB;
  }

  const createdDelta = createdTimestamp(a.created_at) - createdTimestamp(b.created_at);
  if (createdDelta !== 0) {
    return createdDelta;
  }

  return a.id - b.id;
};

export const sortMatrixTodos = (todos: readonly TodoItem[]): TodoItem[] =>
  [...todos].sort(compareMatrixTodos);

export const pickNextMatrixTodo = (
  quadrants: MatrixQuadrants,
  excludeId: number,
): TodoItem | null => {
  for (const key of MATRIX_PICK_ORDER) {
    const candidates = sortMatrixTodos(quadrants[key] ?? []).filter(
      (todo) => todo.id !== excludeId,
    );
    const next = candidates[0];
    if (next !== undefined) {
      return next;
    }
  }
  return null;
};
