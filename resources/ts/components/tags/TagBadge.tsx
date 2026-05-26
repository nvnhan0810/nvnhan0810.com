import { Tag } from "@/ts/types/tag";
import { cn } from "@/ts/utils";
import { Link } from "@inertiajs/react";
import { useRoute } from "ziggy-js";

interface TagBadgeProps {
  tag: Tag;
  classes?: string;
  useLink?: boolean;
}

const TagBadge = ({ tag, classes = '', useLink = true }: TagBadgeProps) => {
  const routes = useRoute();

  // Format tag name: camelCase to Title Case if needed, or just display as is
  const displayName = tag.name.replace(/([a-z])([A-Z])/g, '$1 $2').trim();

  const baseClasses = "inline-flex items-center border rounded-full px-2.5 py-0.5 text-xs font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2";
  const defaultColorClasses =
    "border-border bg-muted/50 text-muted-foreground hover:border-emerald-600/40 hover:bg-emerald-600/10 hover:text-emerald-500";

  if ((tag.public_posts_count !== undefined && tag.public_posts_count === 0)) {
    return null;
  }

  if (useLink) {
    return (
      <Link href={routes('posts.index', { tag: tag.slug })}>
        <span className={cn(baseClasses, defaultColorClasses, classes)}>
          {displayName}
        </span>
      </Link>
    );
  }

  return (
    <span className={cn(baseClasses, defaultColorClasses, classes)}>
      {displayName}
    </span>
  );
};

export default TagBadge;
