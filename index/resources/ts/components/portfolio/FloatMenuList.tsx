import { useTranslation } from "@/ts/providers/i18n-provider";
import { Link, usePage } from "@inertiajs/react";
import { BookOpen, Boxes, ListTodo, Newspaper } from "lucide-react";
import { useRoute } from "ziggy-js";

type SharedPageProps = {
  todoUrl?: string;
};

const FloatMenuList = () => {
  const { todoUrl } = usePage<SharedPageProps>().props;

  const route = useRoute();

  const { t } = useTranslation();

  return (
    <div className="hidden md:flex flex-col fixed right-0 top-1/2 -translate-y-1/2">
      <Link
        href={route("posts.index")}
        className="group relative inline-flex items-center gap-1.5 text-sm text-emerald-500 p-2 border border-zinc rounded-l-sm hover:bg-primary/10 hover:border-emerald-500"
      >
        <BookOpen className="h-6 w-6" />
        <span className="absolute right-full top-1/2 mr-1 -translate-y-1/2 whitespace-nowrap rounded bg-foreground px-2 py-1 text-xs text-background opacity-0 transition-opacity duration-200 group-hover:opacity-100 pointer-events-none">
          {t("home.allPosts")}
        </span>
      </Link>
      <Link
        href={route("apps.index")}
        className="group relative inline-flex items-center gap-1.5 text-sm text-emerald-500 p-2 border border-zinc rounded-l-sm hover:bg-primary/10 hover:border-emerald-500"
      >
        <Boxes className="h-6 w-6" />
        <span className="absolute right-full top-1/2 mr-1 -translate-y-1/2 whitespace-nowrap rounded bg-foreground px-2 py-1 text-xs text-background opacity-0 transition-opacity duration-200 group-hover:opacity-100 pointer-events-none">
          {t("nav.apps")}
        </span>
      </Link>
      <Link
        href={route("news.index")}
        className="group relative inline-flex items-center gap-1.5 text-sm text-emerald-500 p-2 border border-zinc rounded-l-sm hover:bg-primary/10 hover:border-emerald-500"
      >
        <Newspaper className="h-6 w-6" />
        <span className="absolute right-full top-1/2 mr-1 -translate-y-1/2 whitespace-nowrap rounded bg-foreground px-2 py-1 text-xs text-background opacity-0 transition-opacity duration-200 group-hover:opacity-100 pointer-events-none">
          {t("nav.news")}
        </span>
      </Link>
      <Link
        href={todoUrl}
        className="group relative inline-flex items-center gap-1.5 text-sm text-emerald-500 p-2 border border-zinc rounded-l-sm hover:bg-primary/10 hover:border-emerald-500"
      >
        <ListTodo className="h-6 w-6" />
        <span className="absolute right-full top-1/2 mr-1 -translate-y-1/2 whitespace-nowrap rounded bg-foreground px-2 py-1 text-xs text-background opacity-0 transition-opacity duration-200 group-hover:opacity-100 pointer-events-none">
          {t("nav.todosApp")}
        </span>
      </Link>
    </div>
  );
};

export default FloatMenuList;
