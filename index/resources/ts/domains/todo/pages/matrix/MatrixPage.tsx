import { Button } from "@/ts/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/ts/components/ui/dropdown-menu";
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from "@/ts/components/ui/tooltip";
import PrivateLayout, { RootProps } from "@/ts/layouts/PrivateLayout";
import { cn } from "@ts/utils";
import { router, usePage } from "@inertiajs/react";
import {
  Bell,
  BellOff,
  Inbox,
  Maximize2,
  Minimize2,
  MoreVertical,
  Plus,
  Timer,
} from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import { createPortal } from "react-dom";
import { useRoute } from "ziggy-js";
import TodoFormModal, {
  type TodoCreateDefaults,
} from "../../components/TodoFormModal";
import TodoNav from "../../components/TodoNav";
import { pickNextMatrixTodo } from "../../application/matrixTodoOrder";
import { useMatrixSse } from "../../presentation/hooks/useMatrixSse";
import { usePomodoro } from "../../presentation/hooks/usePomodoro";
import { useWebPush } from "../../presentation/hooks/useWebPush";
import type {
  MatrixQuadrants,
  TodoItem,
  TodoPriority,
  TodoProject,
  TodoStatus,
} from "../../types";
import type { PomodoroPhase } from "../../constants/pomodoro";
import BacklogPromoteDialog from "./BacklogPromoteDialog";
import MatrixQuadrant from "./MatrixQuadrant";
import PomodoroBar from "./PomodoroBar";
import PomodoroSettingsDialog from "./PomodoroSettingsDialog";
import { QUADRANTS, type QuadrantMeta } from "./quadrants";

type SharedWebPush = {
  configured: boolean;
  publicKey: string | null;
} | null;

type PageShared = {
  webPush?: SharedWebPush;
};
type ModalState =
  | { mode: "create"; defaults: TodoCreateDefaults }
  | { mode: "edit"; todo: TodoItem }
  | null;

type Props = RootProps & {
  quadrants: MatrixQuadrants;
  stream_url: string;
  version: number;
  pomodoro: unknown;
  projects: Pick<TodoProject, "id" | "name">[];
  statuses: TodoStatus[];
  priorities: TodoPriority[];
  backlog: TodoItem[];
};

type GridProps = {
  quadrants: MatrixQuadrants;
  onCreateInQuadrant: (meta: QuadrantMeta) => void;
  onEditTodo: (todo: TodoItem) => void;
  onSelectForPomodoro: (todo: TodoItem) => void;
  activePomodoroTodoId: number | null;
  highlightedTodoId: number | null;
  pomodoroPhase: PomodoroPhase;
};

const MatrixGrid = ({
  quadrants,
  onCreateInQuadrant,
  onEditTodo,
  onSelectForPomodoro,
  activePomodoroTodoId,
  highlightedTodoId,
  pomodoroPhase,
}: GridProps): React.ReactElement => (
  <div className="grid grid-cols-1 gap-3 lg:grid-cols-2 lg:grid-rows-2 lg:h-full min-h-0">
    {QUADRANTS.map((meta) => (
      <MatrixQuadrant
        key={meta.key}
        meta={meta}
        todos={quadrants[meta.key] ?? []}
        onCreateInQuadrant={onCreateInQuadrant}
        onEditTodo={onEditTodo}
        onSelectForPomodoro={onSelectForPomodoro}
        activePomodoroTodoId={activePomodoroTodoId}
        highlightedTodoId={highlightedTodoId}
        pomodoroPhase={pomodoroPhase}
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
  pomodoro: initialPomodoro,
  projects,
  statuses,
  priorities,
  backlog,
}: Props): React.ReactElement => {
  const route = useRoute();
  const page = usePage<PageShared>();
  const sharedWebPush = page.props.webPush ?? null;
  const [isFullscreen, setIsFullscreen] = useState(false);
  const [backlogOpen, setBacklogOpen] = useState(false);
  const [pomodoroSettingsOpen, setPomodoroSettingsOpen] = useState(false);
  const [modal, setModal] = useState<ModalState>(null);
  const [highlightedTodoId, setHighlightedTodoId] = useState<number | null>(null);
  const pomodoro = usePomodoro({
    initialPayload: initialPomodoro,
    syncUrl: route("matrix.pomodoro.update"),
  });
  const webPush = useWebPush({
    configured: sharedWebPush?.configured === true,
    publicKey:
      typeof sharedWebPush?.publicKey === "string" ? sharedWebPush.publicKey : null,
    subscribeUrl: route("matrix.web-push.subscribe"),
    unsubscribeUrl: route("matrix.web-push.unsubscribe"),
    presenceUrl: route("matrix.web-push.presence"),
    statusUrl: route("matrix.web-push.status"),
    enablePresence: true,
  });
  const { quadrants, isLive } = useMatrixSse({
    streamUrl: stream_url,
    initialQuadrants,
    initialVersion,
    onPomodoro: pomodoro.applyRemotePayload,
  });

  const matrixTodos = useMemo(
    () => QUADRANTS.flatMap((meta) => quadrants[meta.key] ?? []),
    [quadrants],
  );

  const overlayOpen = modal !== null || backlogOpen || pomodoroSettingsOpen;

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

  const { activeTodoId, clearActiveTodo, isRunning, selectTodo, start, pause } =
    pomodoro;

  const startWithWebPush = (): void => {
    void webPush.ensureSubscribed();
    start();
  };

  const toggleWithWebPush = (): void => {
    if (isRunning) {
      pause();
    } else {
      startWithWebPush();
    }
  };

  // Space toggles pause / resume (ignore when typing in fields).
  useEffect(() => {
    const onKeyDown = (event: KeyboardEvent): void => {
      if (event.code !== "Space" && event.key !== " ") {
        return;
      }
      const target = event.target;
      if (target instanceof HTMLElement) {
        const tag = target.tagName;
        if (
          tag === "INPUT" ||
          tag === "TEXTAREA" ||
          tag === "SELECT" ||
          target.isContentEditable
        ) {
          return;
        }
      }
      if (overlayOpen) {
        return;
      }
      event.preventDefault();
      toggleWithWebPush();
    };

    window.addEventListener("keydown", onKeyDown);
    return () => window.removeEventListener("keydown", onKeyDown);
  }, [overlayOpen, isRunning]);

  useEffect(() => {
    if (highlightedTodoId === null) {
      return;
    }
    const timer = window.setTimeout(() => setHighlightedTodoId(null), 1800);
    return () => window.clearTimeout(timer);
  }, [highlightedTodoId]);

  // Drop active todo if it left the matrix (done / removed).
  useEffect(() => {
    if (activeTodoId === null) {
      return;
    }
    const stillThere = matrixTodos.some((todo) => todo.id === activeTodoId);
    if (!stillThere) {
      clearActiveTodo();
    }
  }, [matrixTodos, activeTodoId, clearActiveTodo]);

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

  const selectForPomodoro = (todo: TodoItem): void => {
    selectTodo(todo.id);
    if (todo.status === "todo") {
      router.patch(
        route("matrix.update", todo.id),
        {
          is_urgent: todo.is_urgent,
          is_important: todo.is_important,
          status: "in_progress",
        },
        { preserveScroll: true },
      );
    }
    if (!isRunning) {
      startWithWebPush();
    } else {
      void webPush.ensureSubscribed();
    }
  };

  const completeActiveTodo = (): void => {
    if (activeTodoId === null) {
      return;
    }
    const completedId = activeTodoId;
    const next = pickNextMatrixTodo(quadrants, completedId);
    if (next !== null) {
      selectTodo(next.id);
      if (next.status === "todo") {
        router.patch(
          route("matrix.update", next.id),
          {
            is_urgent: next.is_urgent,
            is_important: next.is_important,
            status: "in_progress",
          },
          { preserveScroll: true },
        );
      }
    } else {
      selectTodo(null);
    }
    router.patch(route("matrix.complete", completedId), {}, { preserveScroll: true });
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

      <span className="inline-flex items-center gap-1.5 text-sky-300 ml-auto">
        <span className="h-2.5 w-2.5 rounded-sm bg-sky-500" /> Todo
      </span>
      <span className="inline-flex items-center gap-1.5 text-orange-300">
        <span className="h-2.5 w-2.5 rounded-sm bg-orange-500" /> In progress
      </span>

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

      <DropdownMenu>
        <Tooltip>
          <TooltipTrigger asChild>
            <DropdownMenuTrigger asChild>
              <Button
                type="button"
                variant="outline"
                size="sm"
                className="cursor-pointer"
                aria-label="Thêm thao tác"
              >
                <MoreVertical className="w-4 h-4" />
              </Button>
            </DropdownMenuTrigger>
          </TooltipTrigger>
          <TooltipContent side="bottom">Thêm thao tác</TooltipContent>
        </Tooltip>
        <DropdownMenuContent align="end" className="z-[240] w-56">
          <DropdownMenuItem
            className="cursor-pointer"
            onSelect={() => setBacklogOpen(true)}
          >
            <Inbox className="w-4 h-4" />
            Backlog
            {backlog.length > 0 && (
              <span className="ml-auto text-[10px] tabular-nums text-muted-foreground">
                {backlog.length}
              </span>
            )}
          </DropdownMenuItem>
          <DropdownMenuItem
            className="cursor-pointer"
            onSelect={() => setPomodoroSettingsOpen(true)}
          >
            <Timer className="w-4 h-4" />
            Pomodoro settings
          </DropdownMenuItem>
          {webPush.configured && (
            <DropdownMenuItem
              className="cursor-pointer"
              disabled={webPush.busy || (!webPush.supported && !webPush.needsHomeScreen)}
              onSelect={(event) => {
                event.preventDefault();
                if (webPush.subscribed) {
                  void webPush.disable();
                } else {
                  void webPush.enable();
                }
              }}
            >
              {webPush.subscribed ? (
                <BellOff className="w-4 h-4" />
              ) : (
                <Bell className="w-4 h-4" />
              )}
              {webPush.needsHomeScreen && !webPush.subscribed
                ? "iOS: Add to Home Screen"
                : webPush.subscribed
                  ? webPush.serverSubscriptionCount > 0
                    ? `Tắt Web Push (${webPush.serverSubscriptionCount} máy)`
                    : "Tắt Web Push (chưa lưu server)"
                  : "Bật Web Push"}
            </DropdownMenuItem>
          )}
        </DropdownMenuContent>
      </DropdownMenu>
      {webPush.subscribed && webPush.serverSubscriptionCount === 0 && !webPush.error && (
        <span className="basis-full text-[10px] text-amber-300">
          Quyền noti local OK — bấm Bật Web Push lại để lưu lên server (cần cho iPhone)
        </span>
      )}
      {webPush.error && (
        <span
          className="basis-full text-[10px] text-rose-300 truncate"
          title={webPush.error}
        >
          {webPush.error}
        </span>
      )}
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

  const pomodoroSettingsDialog = (
    <PomodoroSettingsDialog
      open={pomodoroSettingsOpen}
      onOpenChange={setPomodoroSettingsOpen}
      settings={pomodoro.settings}
      isTimerRunning={pomodoro.isRunning}
      onSave={pomodoro.saveSettings}
    />
  );

  const locateTodo = (todoId: number): void => {
    setHighlightedTodoId(todoId);
  };

  const pomodoroBar = (
    <PomodoroBar
      pomodoro={{ ...pomodoro, toggle: toggleWithWebPush, start: startWithWebPush }}
      matrixTodos={matrixTodos}
      onLocateTodo={locateTodo}
      onCompleteActiveTodo={completeActiveTodo}
      className="mb-3"
    />
  );

  const grid = (
    <MatrixGrid
      quadrants={quadrants}
      onCreateInQuadrant={openCreateInQuadrant}
      onEditTodo={openEdit}
      onSelectForPomodoro={selectForPomodoro}
      activePomodoroTodoId={pomodoro.activeTodoId}
      highlightedTodoId={highlightedTodoId}
      pomodoroPhase={pomodoro.phase}
    />
  );

  return (
    <TooltipProvider delayDuration={250}>
      <PrivateLayout auth={auth}>
        <TodoNav />

        <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
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

        <div className={cn(isFullscreen && "invisible")}>
          {pomodoroBar}
          <div className={cn(isFullscreen && "h-[70vh]")}>{grid}</div>
        </div>

        {formModal}
        {backlogDialog}
        {pomodoroSettingsDialog}

        {isFullscreen &&
          createPortal(
            <TooltipProvider delayDuration={250}>
              <div className="fixed inset-0 z-[100] flex flex-col bg-background p-4 sm:p-6">
                <div className="mb-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between shrink-0">
                  <h1 className="text-xl font-bold text-gray-100">
                    Eisenhower Matrix
                  </h1>
                  {toolbar}
                </div>
                <div className="shrink-0">{pomodoroBar}</div>
                <div className="min-h-0 flex-1 overflow-auto">{grid}</div>
              </div>
            </TooltipProvider>,
            document.body,
          )}
      </PrivateLayout>
    </TooltipProvider>
  );
};

export default MatrixPage;
