import PostDetail from "@/ts/components/posts/PostDetail";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from "@/ts/components/ui/dialog";
import type { Post } from "@/ts/types/post";
import type { JSX } from "react";

type Props = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  post: Post | null;
};

const PostPreviewDialog = ({ open, onOpenChange, post }: Props): JSX.Element => {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="flex h-[calc(100dvh-1.5rem)] max-h-[calc(100dvh-1.5rem)] w-[calc(100%-2rem)] max-w-5xl flex-col gap-4 overflow-hidden">
        <DialogHeader className="shrink-0">
          <DialogTitle>Xem trước</DialogTitle>
        </DialogHeader>
        <div className="min-h-0 flex-1 overflow-y-auto rounded-md border border-border bg-card p-4">
          {post ? <PostDetail post={post} /> : null}
        </div>
      </DialogContent>
    </Dialog>
  );
};

export default PostPreviewDialog;
