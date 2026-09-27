import { BLOG_COPY } from "@/ts/constants/blogCopy";
import { postStatusCardClass } from "@/ts/constants/postStatus";
import type { Post } from "@/ts/types/post";
import type { Tag } from "@/ts/types/tag";
import { cn } from "@/ts/utils";
import { Link } from "@inertiajs/react";
import { Pencil, Trash2 } from "lucide-react";
import type { JSX } from "react";
import { useRoute } from "ziggy-js";
import TagBadge from "../tags/TagBadge";
import { Button } from "../ui/button";

type Props = {
  post: Post;
  canManage?: boolean;
  onEdit?: (post: Post) => void;
  onDelete?: (post: Post) => void;
};

const PostListItem = ({
  post,
  canManage = false,
  onEdit,
  onDelete,
}: Props): JSX.Element => {
  const routes = useRoute();

  return (
    <div
      className={cn(
        "group relative flex h-full flex-col rounded-xl border transition-all duration-300",
        postStatusCardClass(post.status),
      )}
    >
      {canManage && (
        <div className="absolute right-2 top-2 z-10 flex gap-1 opacity-0 transition-opacity group-hover:opacity-100 group-focus-within:opacity-100">
          <Button
            type="button"
            variant="outline"
            size="icon"
            className="h-7 w-7 border-border bg-background/95 shadow-sm"
            title={BLOG_COPY.editPost}
            onClick={(event) => {
              event.preventDefault();
              event.stopPropagation();
              onEdit?.(post);
            }}
          >
            <Pencil className="h-3 w-3" />
          </Button>
          <Button
            type="button"
            variant="outline"
            size="icon"
            className="h-7 w-7 border-border bg-background/95 text-red-500 shadow-sm hover:text-red-600"
            title={BLOG_COPY.deletePost}
            onClick={(event) => {
              event.preventDefault();
              event.stopPropagation();
              onDelete?.(post);
            }}
          >
            <Trash2 className="h-3 w-3" />
          </Button>
        </div>
      )}

      <Link
        href={routes("posts.show", { slug: post.slug })}
        className="flex h-full flex-col p-5"
      >
        <div className="flex-grow">
          <h2 className="mb-3 line-clamp-2 text-xl font-bold text-foreground transition-colors group-hover:text-emerald-500">
            {post.title}
          </h2>

          {post.description && (
            <p className="mb-4 line-clamp-3 text-sm leading-relaxed text-muted-foreground">
              {post.description}
            </p>
          )}
        </div>

        {(post.public_tags ?? post.tags)?.length ? (
          <div className="mt-4 flex flex-wrap gap-2 border-t border-border pt-4">
            {(post.public_tags ?? post.tags ?? []).map((item: Tag) => (
              <TagBadge
                key={item.id}
                tag={item}
                classes="text-xs py-1 px-2"
                useLink={false}
              />
            ))}
          </div>
        ) : null}
      </Link>
    </div>
  );
};

export default PostListItem;
