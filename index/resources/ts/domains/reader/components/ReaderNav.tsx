import { Link } from "@inertiajs/react";
import { useRoute } from "ziggy-js";

const links = [
  { route: "admin.reader.documents.index", label: "Documents" },
  { route: "admin.reader.collections.index", label: "Collections" },
  { route: "admin.reader.trash.index", label: "Trash" },
] as const;

const ReaderNav = () => {
  const route = useRoute();

  return (
    <nav className="mb-6 flex flex-wrap gap-2 border-b border-border pb-3">
      {links.map((link) => (
        <Link
          key={link.route}
          href={route(link.route)}
          className="rounded-md px-3 py-1 text-sm text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
        >
          {link.label}
        </Link>
      ))}
    </nav>
  );
};

export default ReaderNav;
