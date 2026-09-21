import { Button } from "@/ts/components/ui/button";
import PrivateLayout, { RootProps } from "@/ts/layouts/PrivateLayout";
import { cn } from "@ts/utils";
import { Maximize2, Minimize2 } from "lucide-react";
import { useEffect, useState } from "react";
import { createPortal } from "react-dom";
import TodoNav from "../../components/TodoNav";
import MatrixQuadrant from "./MatrixQuadrant";
import { QUADRANTS, type MatrixQuadrants } from "./quadrants";

type Props = RootProps & {
  quadrants: MatrixQuadrants;
};

const MatrixGrid = ({ quadrants }: { quadrants: MatrixQuadrants }) => (
  <div className="grid grid-cols-1 gap-3 lg:grid-cols-2 lg:grid-rows-2 lg:h-full min-h-0">
    {QUADRANTS.map((meta) => (
      <MatrixQuadrant key={meta.key} meta={meta} todos={quadrants[meta.key] ?? []} />
    ))}
  </div>
);

const MatrixPage = ({ auth, quadrants }: Props) => {
  const [isFullscreen, setIsFullscreen] = useState(false);

  useEffect(() => {
    if (!isFullscreen) {
      return;
    }

    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";

    const onKeyDown = (event: KeyboardEvent): void => {
      if (event.key === "Escape") {
        setIsFullscreen(false);
      }
    };

    window.addEventListener("keydown", onKeyDown);

    return () => {
      document.body.style.overflow = previousOverflow;
      window.removeEventListener("keydown", onKeyDown);
    };
  }, [isFullscreen]);

  const toolbar = (
    <div className="flex flex-wrap items-center gap-3 text-xs">
      <span className="inline-flex items-center gap-1.5 text-sky-300">
        <span className="h-2.5 w-2.5 rounded-sm bg-sky-500" /> Todo
      </span>
      <span className="inline-flex items-center gap-1.5 text-orange-300">
        <span className="h-2.5 w-2.5 rounded-sm bg-orange-500" /> In progress
      </span>
      <Button
        type="button"
        variant="outline"
        size="sm"
        className="cursor-pointer ml-auto"
        onClick={() => setIsFullscreen((value) => !value)}
        title={isFullscreen ? "Thoát toàn màn hình (Esc)" : "Toàn màn hình"}
      >
        {isFullscreen ? (
          <>
            <Minimize2 className="w-4 h-4 mr-1.5" /> Exit
          </>
        ) : (
          <>
            <Maximize2 className="w-4 h-4 mr-1.5" /> Fullscreen
          </>
        )}
      </Button>
    </div>
  );

  return (
    <PrivateLayout auth={auth}>
      <TodoNav />

      <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-100">Eisenhower Matrix</h1>
          <p className="text-sm text-muted-foreground">
            Chỉ todo <span className="text-sky-300">Todo</span> /{" "}
            <span className="text-orange-300">In progress</span> (phân biệt bằng màu
            viền trái). Kéo thả giữa các ô để phân loại.
          </p>
        </div>
        {toolbar}
      </div>

      <div className={cn(isFullscreen && "invisible h-[70vh]")}>
        <MatrixGrid quadrants={quadrants} />
      </div>

      {isFullscreen &&
        createPortal(
          <div className="fixed inset-0 z-[100] flex flex-col bg-background p-4 sm:p-6">
            <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between shrink-0">
              <div>
                <h1 className="text-xl font-bold text-gray-100">Eisenhower Matrix</h1>
                <p className="text-xs text-muted-foreground">
                  Esc hoặc Exit để thoát toàn màn hình
                </p>
              </div>
              {toolbar}
            </div>
            <div className="min-h-0 flex-1 overflow-auto">
              <MatrixGrid quadrants={quadrants} />
            </div>
          </div>,
          document.body,
        )}
    </PrivateLayout>
  );
};

export default MatrixPage;
