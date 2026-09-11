import { techIconUrl, techStack } from "@/ts/constants/techStack";

const TechStackGrid = () => (
  <ul className="grid grid-cols-3 gap-4 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6">
    {techStack.map((tool) => (
      <li key={`${tool.slug}-${tool.name}`}>
        <div
          className="group flex flex-col items-center gap-2 rounded-xl border border-border bg-card p-4 transition-colors hover:border-emerald-600/40 hover:bg-emerald-600/5"
          title={tool.name}
        >
          <img
            src={techIconUrl(tool)}
            alt=""
            width={32}
            height={32}
            className="h-8 w-8 opacity-90 transition-opacity group-hover:opacity-100"
            loading="lazy"
            decoding="async"
          />
          <span className="text-center text-xs text-muted-foreground transition-colors group-hover:text-foreground">
            {tool.name}
          </span>
        </div>
      </li>
    ))}
  </ul>
);

export default TechStackGrid;
