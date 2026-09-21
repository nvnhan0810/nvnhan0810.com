import { useEffect, useState } from "react";
import type { MatrixQuadrants } from "../../types";
import { connectMatrixSse } from "../../infrastructure/matrixSseClient";

type Args = {
  streamUrl: string;
  initialQuadrants: MatrixQuadrants;
  initialVersion: number;
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
}: Args): Result => {
  const [quadrants, setQuadrants] = useState<MatrixQuadrants>(initialQuadrants);
  const [version, setVersion] = useState<number>(initialVersion);
  const [isLive, setIsLive] = useState(false);

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
      onError: () => {
        setIsLive(false);
      },
    });

    return disconnect;
  }, [streamUrl]);

  return { quadrants, version, isLive };
};
