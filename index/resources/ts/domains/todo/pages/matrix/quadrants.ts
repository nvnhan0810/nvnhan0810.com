import type { TodoItem, TodoPriority, TodoStatus } from "../../types";

export type MatrixQuadrantKey = "do" | "schedule" | "delegate" | "eliminate";

export type QuadrantMeta = {
  key: MatrixQuadrantKey;
  title: string;
  subtitle: string;
  is_urgent: boolean;
  is_important: boolean;
  accent: string;
  panel: string;
  header: string;
};

export const QUADRANTS: QuadrantMeta[] = [
  {
    key: "do",
    title: "Làm ngay",
    subtitle: "Urgent · Important",
    is_urgent: true,
    is_important: true,
    accent: "border-rose-500/70",
    panel: "bg-rose-950/40",
    header: "text-rose-300",
  },
  {
    key: "schedule",
    title: "Lên lịch",
    subtitle: "Not urgent · Important",
    is_urgent: false,
    is_important: true,
    accent: "border-teal-500/70",
    panel: "bg-teal-950/35",
    header: "text-teal-300",
  },
  {
    key: "delegate",
    title: "Giao việc",
    subtitle: "Urgent · Not important",
    is_urgent: true,
    is_important: false,
    accent: "border-amber-500/70",
    panel: "bg-amber-950/35",
    header: "text-amber-300",
  },
  {
    key: "eliminate",
    title: "Loại bỏ",
    subtitle: "Not urgent · Not important",
    is_urgent: false,
    is_important: false,
    accent: "border-slate-500/60",
    panel: "bg-slate-900/50",
    header: "text-slate-300",
  },
];

export const priorityLabel: Record<TodoPriority, string> = {
  low: "Low",
  medium: "Med",
  high: "High",
  urgent: "Urgent",
};

export const statusStyles: Record<
  Extract<TodoStatus, "todo" | "in_progress">,
  { card: string }
> = {
  todo: {
    card: "border-l-4 border-l-sky-500 bg-sky-950/30",
  },
  in_progress: {
    card: "border-l-4 border-l-orange-500 bg-orange-950/40 shadow-[inset_0_0_0_1px_rgba(249,115,22,0.25)]",
  },
};

export type MatrixQuadrants = Record<MatrixQuadrantKey, TodoItem[]>;
