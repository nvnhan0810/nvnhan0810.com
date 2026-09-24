import { useTranslation } from "@/ts/providers/i18n-provider";
import { Link, usePage } from "@inertiajs/react";
import { BookOpen, Boxes, ListTodo, Newspaper } from "lucide-react";
import { useRoute } from "ziggy-js";

type SharedPageProps = {
  todoUrl?: string;
};

const BottomMenuList = () => {
  const { todoUrl } = usePage<SharedPageProps>().props;
  const route = useRoute();

  const { t } = useTranslation();

  return (
    <div className="flex items-center justify-center md:hidden fixed bottom-0 w-full border-t border-t-primary/30 bg-background">
      <Link
        href={route("posts.index")}
        className="group relative inline-flex flex-col items-center gap-1.5 text-sm text-emerald-500 p-2 hover:bg-primary/10"
      >
        <BookOpen className="h-6 w-6" />
        <span className="">{t("home.allPosts")}</span>
      </Link>
      <Link
        href={route("apps.index")}
        className="group relative inline-flex flex-col items-center gap-1.5 text-sm text-emerald-500 p-2 hover:bg-primary/10"
      >
        <Boxes className="h-6 w-6" />
        <span className="">{t("nav.apps")}</span>
      </Link>
      <Link
        href={route("news.index")}
        className="group relative inline-flex flex-col items-center gap-1.5 text-sm text-emerald-500 p-2 hover:bg-primary/10"
      >
        <Newspaper className="h-6 w-6" />
        <span className="">{t("nav.news")}</span>
      </Link>
      <Link
        href={todoUrl}
        className="group relative inline-flex flex-col items-center gap-1.5 text-sm text-emerald-500 p-2 hover:bg-primary/10"
      >
        <ListTodo className="h-6 w-6" />
        <span className="">{t("nav.todosApp")}</span>
      </Link>
    </div>
  );
};

export default BottomMenuList;
