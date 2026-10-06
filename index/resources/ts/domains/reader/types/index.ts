export type ReaderDocument = {
  id: string;
  title: string;
  page_count: number;
  content_type: string;
  byte_size: number;
  content_sha256: string | null;
  status: string;
  is_favorite: boolean;
  revision: number;
  has_thumbnail: boolean;
  deleted: boolean;
  deleted_at: string | null;
  purge_at: string | null;
  last_opened_at: string | null;
  created_at: string;
  updated_at: string;
};

export type ReaderCollection = {
  id: string;
  name: string;
  document_count: number;
  revision: number;
  created_at: string;
  updated_at: string;
};

export function formatBytes(bytes: number): string {
  if (bytes <= 0) {
    return "—";
  }
  if (bytes < 1024) {
    return `${bytes} B`;
  }
  if (bytes < 1024 * 1024) {
    return `${(bytes / 1024).toFixed(1)} KB`;
  }
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}
