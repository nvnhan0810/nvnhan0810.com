import { BLOG_COPY } from "@/ts/constants/blogCopy";
import {
  postStatusDetailClass,
  postStatusLabel,
  postStatusMetaClass,
} from "@/ts/constants/postStatus";
import { useTranslation } from "@/ts/providers/i18n-provider";
import { Post } from "@/ts/types/post";
import { Tag } from "@/ts/types/tag";
import { cn } from "@/ts/utils";
import TagBadge from "../tags/TagBadge";
import PostContent from "./PostContent";

type Props = {
  post: Post;
  useTagLink?: boolean;
  showStatus?: boolean;
};

const PostDetail = ({
  post,
  useTagLink = false,
  showStatus = false,
}: Props) => {
  const { locale } = useTranslation();
  const dateLocale = locale === "vi" ? "vi-VN" : "en-US";

  return (
    <article
      className={cn(
        "prose prose-slate dark:prose-invert max-w-none",
        showStatus && postStatusDetailClass(post.status),
      )}
    >
      <header className="mb-8 not-prose">
        <h1 className="text-3xl md:text-4xl lg:text-5xl font-extrabold tracking-tight text-foreground mb-4">
          {post.title}
        </h1>

        {post.description?.trim() && (
          <p className="mb-5 max-w-2xl text-sm leading-relaxed italic text-muted-foreground md:text-[0.95rem]">
            {post.description.trim()}
          </p>
        )}

        <div className="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-muted-foreground mb-6">
          {(() => {
            const dateValue = post.published_at ?? post.created_at;
            if (!dateValue) {
              return null;
            }

            return (
              <time dateTime={dateValue}>
                {new Date(dateValue).toLocaleDateString(dateLocale, {
                  year: "numeric",
                  month: "long",
                  day: "numeric",
                })}
              </time>
            );
          })()}

          {showStatus && (
            <span className={cn("font-medium", postStatusMetaClass(post.status))}>
              {BLOG_COPY.status}: {postStatusLabel(post.status)}
            </span>
          )}
        </div>

        {post.public_tags != undefined && post.public_tags?.length > 0 && (
          <div className="flex flex-wrap gap-2 mb-6">
            {post.public_tags.map((item: Tag) => (
              <TagBadge key={item.id} tag={item} useLink={useTagLink} />
            ))}
          </div>
        )}

        <hr className="border-border" />
      </header>

      <div className="mt-8 not-prose">
        <PostContent doc={post.content ?? ""} />
      </div>
    </article>
  );
};

export default PostDetail;
