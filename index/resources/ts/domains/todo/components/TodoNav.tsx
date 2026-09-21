import { Link } from "@inertiajs/react";
import { useRoute } from "ziggy-js";

const links = [
  { route: "matrix.index", label: "Matrix" },
  { route: "todos.index", label: "Todos" },
  { route: "todos.projects.index", label: "Projects" },
] as const;

const TodoNav = () => {
  const route = useRoute();

  return (
    <nav className="flex flex-wrap gap-2 mb-6 border-b border-border pb-3">
      {links.map((link) => (
        <Link
          key={link.route}
          href={route(link.route)}
          className="text-sm px-3 py-1 rounded-md text-muted-foreground hover:text-foreground hover:bg-muted transition-colors cursor-pointer"
        >
          {link.label}
        </Link>
      ))}
    </nav>
  );
};

export default TodoNav;
