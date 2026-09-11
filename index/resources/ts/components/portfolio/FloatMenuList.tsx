import { useTranslation } from "@/ts/providers/i18n-provider";
import { Link } from "@inertiajs/react";
import { BookOpen } from "lucide-react";
import { useRoute } from "ziggy-js";

const FloatMenuList = () => {
	const route = useRoute();

	const { t } = useTranslation();

	return (
		<div className="hidden md:block absolute right-0 top-1/2 -translate-y-1/2">
			<Link
				href={route("posts.index")}
				className="group relative inline-flex items-center gap-1.5 text-sm text-emerald-500 p-2 border border-zinc rounded-l-sm hover:bg-primary/10 hover:border-emerald-500"
			>
				<BookOpen className="h-4 w-4" />
				<span className="absolute right-full top-1/2 mr-1 -translate-y-1/2 whitespace-nowrap rounded bg-gray-800 px-2 py-1 text-xs text-white opacity-0 transition-opacity duration-200 group-hover:opacity-100 pointer-events-none">
					{t("home.allPosts")}
				</span>
			</Link>
		</div>
	);
};

export default FloatMenuList;
