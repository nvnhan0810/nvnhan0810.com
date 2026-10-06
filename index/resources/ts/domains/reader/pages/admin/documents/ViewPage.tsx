import { Button } from "@/ts/components/ui/button";
import PrivateLayout, { type RootProps } from "@/ts/layouts/PrivateLayout";
import { router } from "@inertiajs/react";
import { useRoute } from "ziggy-js";
import DocumentPdfFrame from "../../../components/DocumentPdfFrame";
import ReaderNav from "../../../components/ReaderNav";
import { formatBytes, type ReaderDocument } from "../../../types";

type Props = RootProps & {
  document: ReaderDocument;
  /** Direct storage URL when available — avoids proxying large PDFs through PHP. */
  fileUrl: string;
  /** Same-origin route that redirects to storage (fallback / open in new tab). */
  fileOpenUrl: string;
};

const ViewPage = ({ auth, document, fileUrl, fileOpenUrl }: Props) => {
  const route = useRoute();
  const canView = document.status === "ready";

  return (
    <PrivateLayout auth={auth}>
      <ReaderNav />
      <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold text-foreground">{document.title}</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            {document.page_count > 0 ? `${document.page_count} trang` : "—"} ·{" "}
            {formatBytes(document.byte_size)} · {document.status}
          </p>
        </div>
        <div className="flex flex-wrap gap-2">
          <Button
            variant="outline"
            onClick={() => router.get(route("admin.reader.documents.edit", document.id))}
          >
            Edit
          </Button>
          <Button variant="outline" asChild>
            <a href={fileOpenUrl} target="_blank" rel="noreferrer">
              Mở tab mới
            </a>
          </Button>
          <Button
            variant="outline"
            onClick={() => router.get(route("admin.reader.documents.index"))}
          >
            Quay lại
          </Button>
        </div>
      </div>

      <div className="overflow-hidden rounded-xl border border-border bg-muted/20">
        {canView ? (
          <DocumentPdfFrame url={fileUrl} title={document.title} />
        ) : (
          <div className="px-4 py-16 text-center text-sm text-muted-foreground">
            File chưa sẵn sàng để xem.
          </div>
        )}
      </div>
    </PrivateLayout>
  );
};

export default ViewPage;
