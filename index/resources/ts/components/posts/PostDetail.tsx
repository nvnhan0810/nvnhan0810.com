import { useTranslation } from "@/ts/providers/i18n-provider";
import { Post } from "@/ts/types/post";
import { Tag } from "@/ts/types/tag";
import TagBadge from "../tags/TagBadge";
import PostContent from "./PostContent";

type Props = {
  post: Post;
  useTagLink?: boolean;
};

const PostDetail = ({ post, useTagLink = false }: Props) => {
  const { locale } = useTranslation();
  const dateLocale = locale === "vi" ? "vi-VN" : "en-US";

  return (
    <article className="prose prose-slate dark:prose-invert max-w-none">
      <header className="mb-8 not-prose">
        <h1 className="text-3xl md:text-4xl lg:text-5xl font-extrabold tracking-tight text-foreground mb-4">
          {post.title}
        </h1>
        
        <div className="flex flex-wrap items-center gap-4 text-sm text-muted-foreground mb-6">
          <time dateTime={post.published_at || post.created_at}>
            {new Date(post.published_at || post.created_at).toLocaleDateString(
              dateLocale,
              {
                year: "numeric",
                month: "long",
                day: "numeric",
              }
            )}
          </time>
          {/* Add author if available in Post type, otherwise skip */}
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

      {post.description && (
        <div className="mb-8 border-l-4 border-emerald-600 bg-muted/30 py-2 pl-4 text-xl italic text-muted-foreground">
          {post.description}
        </div>
      )}

      <div className="mt-8">
        <PostContent doc={post.content ?? ""} />
      </div>
    </article>
  );
};

export default PostDetail;
