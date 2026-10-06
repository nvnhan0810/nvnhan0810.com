import { useState } from "react";

type Props = {
  url: string;
  title: string;
};

const DocumentPdfFrame = ({ url, title }: Props) => {
  const [loaded, setLoaded] = useState(false);
  const [failed, setFailed] = useState(false);

  return (
    <div className="relative min-h-[min(80vh,900px)] w-full">
      {!loaded && !failed && (
        <p className="absolute inset-x-0 top-0 px-4 py-16 text-center text-sm text-muted-foreground">
          Đang tải PDF từ storage… (file lớn có thể mất vài phút)
        </p>
      )}
      {failed && (
        <div className="px-4 py-16 text-center text-sm text-red-400">
          Không hiển thị được PDF trong trang.
          <div className="mt-2">
            <a href={url} target="_blank" rel="noreferrer" className="text-blue-400 underline">
              Mở tab mới
            </a>
          </div>
        </div>
      )}
      <iframe
        title={title}
        src={url}
        className={`h-[min(80vh,900px)] w-full bg-background ${loaded ? "opacity-100" : "opacity-0"}`}
        onLoad={() => setLoaded(true)}
        onError={() => setFailed(true)}
      />
    </div>
  );
};

export default DocumentPdfFrame;
