import { Post } from "@/ts/types/post";
import { Tag } from "@/ts/types/tag";
import { Link } from "@inertiajs/react";
import { useRoute } from "ziggy-js";
import TagBadge from "../tags/TagBadge";

const PostListItem = ({ post }: { post: Post }) => {
  const routes = useRoute();

  return (
    <Link
      key={post.id}
      href={routes('posts.show', { slug: post.slug })}
      className="group flex h-full flex-col rounded-xl border border-border bg-card p-5 transition-all duration-300 hover:border-emerald-600/40 hover:bg-emerald-600/5"
    >
      <div className="flex-grow">
        <h2 className="mb-3 line-clamp-2 text-xl font-bold text-foreground transition-colors group-hover:text-emerald-500">
          {post.title}
        </h2>

        {post.description && (
          <p className="text-muted-foreground text-sm leading-relaxed mb-4 line-clamp-3">
            {post.description}
          </p>
        )}
      </div>

      {post.public_tags != undefined && post.public_tags.length > 0 && (
        <div className="mt-4 pt-4 border-t border-border flex flex-wrap gap-2">
          {post.public_tags.map((item: Tag) => (
            <TagBadge
              key={item.id}
              tag={item}
              classes="text-xs py-1 px-2"
              useLink={false}
            />
          ))}
        </div>
      )}
    </Link>
  );
};

export default PostListItem;
