import { Link } from "@inertiajs/react";
import { useRoute } from "ziggy-js";

const links = [
  { route: "admin.reading-digest.today", label: "Today" },
  { route: "admin.reading-digest.subjects.index", label: "Subjects" },
  { route: "admin.reading-digest.sources.index", label: "Sources" },
  { route: "admin.reading-digest.articles.index", label: "Articles" },
  { route: "admin.reading-digest.taxonomy.index", label: "Taxonomy" },
  { route: "admin.reading-digest.settings.index", label: "Settings" },
  { route: "admin.reading-digest.profile.index", label: "Profile" },
];

const ReadingDigestNav = () => {
  const route = useRoute();

  return (
    <nav className="flex flex-wrap gap-2 mb-6 border-b border-border pb-3">
      {links.map((link) => (
        <Link
          key={link.route}
          href={route(link.route)}
          className="text-sm px-3 py-1 rounded-md text-muted-foreground hover:text-foreground hover:bg-muted transition-colors"
        >
          {link.label}
        </Link>
      ))}
    </nav>
  );
};

export default ReadingDigestNav;
