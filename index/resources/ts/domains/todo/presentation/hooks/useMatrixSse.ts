import { useEffect, useRef, useState } from "react";
import type { PomodoroSyncPayload } from "../../application/parsePomodoroSyncPayload";
import type { MatrixQuadrants } from "../../types";
import { connectMatrixSse } from "../../infrastructure/matrixSseClient";

type Args = {
  streamUrl: string;
  initialQuadrants: MatrixQuadrants;
  initialVersion: number;
  onPomodoro?: (payload: PomodoroSyncPayload) => void;
};

type Result = {
  quadrants: MatrixQuadrants;
  version: number;
  isLive: boolean;
};

export const useMatrixSse = ({
  streamUrl,
  initialQuadrants,
  initialVersion,
  onPomodoro,
}: Args): Result => {
  const [quadrants, setQuadrants] = useState<MatrixQuadrants>(initialQuadrants);
  const [version, setVersion] = useState<number>(initialVersion);
  const [isLive, setIsLive] = useState(false);
  const onPomodoroRef = useRef(onPomodoro);
  onPomodoroRef.current = onPomodoro;

  useEffect(() => {
    setQuadrants(initialQuadrants);
    setVersion(initialVersion);
  }, [initialQuadrants, initialVersion]);

  useEffect(() => {
    const disconnect = connectMatrixSse(streamUrl, {
      onMatrix: (payload) => {
        setQuadrants(payload.quadrants);
        setVersion(payload.version);
        setIsLive(true);
      },
      onPomodoro: (payload) => {
        setIsLive(true);
        onPomodoroRef.current?.(payload);
      },
      onError: () => {
        setIsLive(false);
      },
    });

    return disconnect;
  }, [streamUrl]);

  return { quadrants, version, isLive };
};
