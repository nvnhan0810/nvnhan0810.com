import { Button } from "@/ts/components/ui/button";
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from "@/ts/components/ui/tooltip";
import PrivateLayout, { RootProps } from "@/ts/layouts/PrivateLayout";
import { cn } from "@ts/utils";
import { Inbox, Maximize2, Minimize2, Plus } from "lucide-react";
import { useEffect, useState } from "react";
import { createPortal } from "react-dom";
import TodoFormModal, {
  type TodoCreateDefaults,
} from "../../components/TodoFormModal";
import TodoNav from "../../components/TodoNav";
import { useMatrixSse } from "../../presentation/hooks/useMatrixSse";
import type {
  MatrixQuadrants,
  TodoItem,
  TodoPriority,
  TodoProject,
  TodoStatus,
} from "../../types";
import BacklogPromoteDialog from "./BacklogPromoteDialog";
import MatrixQuadrant from "./MatrixQuadrant";
import { QUADRANTS, type QuadrantMeta } from "./quadrants";

type ModalState =
  | { mode: "create"; defaults: TodoCreateDefaults }
  | { mode: "edit"; todo: TodoItem }
  | null;

type Props = RootProps & {
  quadrants: MatrixQuadrants;
  stream_url: string;
  version: number;
  projects: Pick<TodoProject, "id" | "name">[];
  statuses: TodoStatus[];
  priorities: TodoPriority[];
  backlog: TodoItem[];
};

type GridProps = {
  quadrants: MatrixQuadrants;
  onCreateInQuadrant: (meta: QuadrantMeta) => void;
  onEditTodo: (todo: TodoItem) => void;
};

const MatrixGrid = ({
  quadrants,
  onCreateInQuadrant,
  onEditTodo,
}: GridProps): React.ReactElement => (
  <div className="grid grid-cols-1 gap-3 lg:grid-cols-2 lg:grid-rows-2 lg:h-full min-h-0">
    {QUADRANTS.map((meta) => (
      <MatrixQuadrant
        key={meta.key}
        meta={meta}
        todos={quadrants[meta.key] ?? []}
        onCreateInQuadrant={onCreateInQuadrant}
        onEditTodo={onEditTodo}
      />
    ))}
  </div>
);

const BACKLOG_DEFAULTS: TodoCreateDefaults = {
  status: "backlog",
  is_urgent: false,
  is_important: false,
};

type HintButtonProps = {
  label: string;
  children: React.ReactNode;
  onClick: () => void;
  className?: string;
};

const HintButton = ({
  label,
  children,
  onClick,
  className,
}: HintButtonProps): React.ReactElement => (
  <Tooltip>
    <TooltipTrigger asChild>
      <Button
        type="button"
        variant="outline"
        size="sm"
        className={cn("cursor-pointer", className)}
        onClick={onClick}
        aria-label={label}
      >
        {children}
      </Button>
    </TooltipTrigger>
    <TooltipContent side="bottom">{label}</TooltipContent>
  </Tooltip>
);

const MatrixPage = ({
  auth,
  quadrants: initialQuadrants,
  stream_url,
  version: initialVersion,
  projects,
  statuses,
  priorities,
  backlog,
}: Props): React.ReactElement => {
  const [isFullscreen, setIsFullscreen] = useState(false);
  const [backlogOpen, setBacklogOpen] = useState(false);
  const [modal, setModal] = useState<ModalState>(null);
  const { quadrants, isLive } = useMatrixSse({
    streamUrl: stream_url,
    initialQuadrants,
    initialVersion,
  });

  const overlayOpen = modal !== null || backlogOpen;

  useEffect(() => {
    if (!isFullscreen) {
      return;
    }

    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";

    const onKeyDown = (event: KeyboardEvent): void => {
      if (event.key === "Escape" && !overlayOpen) {
        setIsFullscreen(false);
      }
    };

    window.addEventListener("keydown", onKeyDown);

    return () => {
      document.body.style.overflow = previousOverflow;
      window.removeEventListener("keydown", onKeyDown);
    };
  }, [isFullscreen, overlayOpen]);

  const openCreateInQuadrant = (meta: QuadrantMeta): void => {
    setModal({
      mode: "create",
      defaults: {
        status: "todo",
        is_urgent: meta.is_urgent,
        is_important: meta.is_important,
      },
    });
  };

  const openCreateBacklog = (): void => {
    setModal({ mode: "create", defaults: BACKLOG_DEFAULTS });
  };

  const openEdit = (todo: TodoItem): void => {
    setModal({ mode: "edit", todo });
  };

  const toolbar = (
    <div className="flex flex-wrap items-center gap-3 text-xs">
      <Tooltip>
        <TooltipTrigger asChild>
          <span
            className={cn(
              "inline-flex items-center gap-1.5 cursor-default",
              isLive ? "text-emerald-300" : "text-muted-foreground",
            )}
          >
            <span
              className={cn(
                "h-2 w-2 rounded-full",
                isLive ? "bg-emerald-400 animate-pulse" : "bg-slate-500",
              )}
            />
            {isLive ? "Live" : "Offline"}
          </span>
        </TooltipTrigger>
        <TooltipContent side="bottom">
          {isLive ? "SSE đang kết nối" : "SSE đang kết nối lại…"}
        </TooltipContent>
      </Tooltip>

      <span className="inline-flex items-center gap-1.5 text-sky-300">
        <span className="h-2.5 w-2.5 rounded-sm bg-sky-500" /> Todo
      </span>
      <span className="inline-flex items-center gap-1.5 text-orange-300">
        <span className="h-2.5 w-2.5 rounded-sm bg-orange-500" /> In progress
      </span>

      <HintButton
        label="Chọn backlog đưa vào Matrix"
        className="ml-auto"
        onClick={() => setBacklogOpen(true)}
      >
        <Inbox className="w-4 h-4" />
        {backlog.length > 0 && (
          <span className="rounded-full bg-white/10 px-1.5 py-0.5 text-[10px] tabular-nums">
            {backlog.length}
          </span>
        )}
      </HintButton>

      <HintButton
        label={isFullscreen ? "Thoát toàn màn hình (Esc)" : "Toàn màn hình"}
        onClick={() => setIsFullscreen((value) => !value)}
      >
        {isFullscreen ? (
          <Minimize2 className="w-4 h-4" />
        ) : (
          <Maximize2 className="w-4 h-4" />
        )}
      </HintButton>
    </div>
  );

  const formModal = (
    <TodoFormModal
      open={modal !== null}
      onOpenChange={(open) => {
        if (!open) {
          setModal(null);
        }
      }}
      mode={modal?.mode === "edit" ? "edit" : "create"}
      todo={modal?.mode === "edit" ? modal.todo : null}
      defaults={modal?.mode === "create" ? modal.defaults : undefined}
      projects={projects}
      statuses={statuses}
      priorities={priorities}
    />
  );

  const backlogDialog = (
    <BacklogPromoteDialog
      open={backlogOpen}
      onOpenChange={setBacklogOpen}
      backlog={backlog}
      projects={projects}
      priorities={priorities}
      onEditTodo={openEdit}
    />
  );

  return (
    <TooltipProvider delayDuration={250}>
      <PrivateLayout auth={auth}>
        <TodoNav />

        <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <h1 className="text-2xl font-bold text-gray-100 inline-flex items-center gap-2">
            Eisenhower Matrix
            <HintButton
              label="Tạo todo mới (status Backlog)"
              onClick={openCreateBacklog}
            >
              <Plus className="w-4 h-4" />
            </HintButton>
          </h1>
          {toolbar}
        </div>

        <div className={cn(isFullscreen && "invisible h-[70vh]")}>
          <MatrixGrid
            quadrants={quadrants}
            onCreateInQuadrant={openCreateInQuadrant}
            onEditTodo={openEdit}
          />
        </div>

        {formModal}
        {backlogDialog}

        {isFullscreen &&
          createPortal(
            <TooltipProvider delayDuration={250}>
              <div className="fixed inset-0 z-[100] flex flex-col bg-background p-4 sm:p-6">
                <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between shrink-0">
                  <h1 className="text-xl font-bold text-gray-100">
                    Eisenhower Matrix
                  </h1>
                  {toolbar}
                </div>
                <div className="min-h-0 flex-1 overflow-auto">
                  <MatrixGrid
                    quadrants={quadrants}
                    onCreateInQuadrant={openCreateInQuadrant}
                    onEditTodo={openEdit}
                  />
                </div>
              </div>
            </TooltipProvider>,
            document.body,
          )}
      </PrivateLayout>
    </TooltipProvider>
  );
};

export default MatrixPage;
