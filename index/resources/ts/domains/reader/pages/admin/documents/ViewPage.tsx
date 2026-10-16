import type { RootProps } from "@/ts/layouts/PrivateLayout";
import { useMemo } from "react";
import { useRoute } from "ziggy-js";
import DocumentPdfViewer from "../../../components/DocumentPdfViewer";
import { parseReadingProgress, type ReadingProgress } from "../../../domain/reading-progress";
import { createReadingProgressHttp } from "../../../infrastructure/reading-progress-http";
import type { ReaderDocument } from "../../../types";

const progressHttp = createReadingProgressHttp({
  show: (id) => `/admin/reader/documents/${id}/progress`,
  upsert: (id) => `/admin/reader/documents/${id}/progress`,
});

type ProgressProp = {
  document_id: string;
  page_index: number;
  revision: number;
  updated_at: string | null;
};

type Props = RootProps & {
  document: ReaderDocument;
  fileUrl: string;
  fileOpenUrl: string;
  fileStreamUrl: string;
  progress: ProgressProp | null;
};

function toReadingProgress(progress: ProgressProp | null): ReadingProgress | null {
  if (progress === null) {
    return null;
  }

  try {
    return parseReadingProgress(progress);
  } catch {
    return null;
  }
}

const ViewPage = ({ document, fileUrl, progress }: Props) => {
  const route = useRoute();
  const canView = document.status === "ready";
  const initialProgress = useMemo(() => toReadingProgress(progress), [progress]);
  const listUrl = route("admin.reader.documents.index");
  // Seaweed presigned URL (CORS *). Do not use ?stream=1 — PHP proxy → 504 on large PDFs.

  if (!canView) {
    return (
      <div className="flex h-dvh flex-col items-center justify-center gap-3 bg-background px-4 text-center text-sm text-muted-foreground">
        <p>File chưa sẵn sàng để xem.</p>
        <a href={listUrl} className="text-blue-400 underline">
          Quay lại danh sách
        </a>
      </div>
    );
  }

  return (
    <DocumentPdfViewer
      documentId={document.id}
      url={fileUrl}
      title={document.title}
      listUrl={listUrl}
      initialProgress={initialProgress}
      progressHttp={progressHttp}
    />
  );
};

export default ViewPage;
