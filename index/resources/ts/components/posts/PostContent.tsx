import "@sass/markdown.scss";
import {
  resolveTheme,
  useTheme,
} from "@/ts/providers/theme-provider";
import { useEffect, useState } from "react";
import { MarkdownPreview } from "react-markdown-preview";
import "react-markdown-preview/dist/highlight.css";
import "react-markdown-preview/dist/markdown.css";

const applyMermaidTheme = async (mode: "dark" | "light"): Promise<void> => {
  const mermaid = (await import("mermaid")).default;
  mermaid.initialize({
    startOnLoad: false,
    securityLevel: "loose",
    theme: mode === "dark" ? "dark" : "default",
    flowchart: {
      htmlLabels: true,
      curve: "basis",
    },
  });
};

const PostContent = ({ doc }: { doc: string }) => {
  const { theme } = useTheme();
  const mode = resolveTheme(theme);
  const [ready, setReady] = useState(false);

  useEffect(() => {
    let cancelled = false;
    setReady(false);
    void applyMermaidTheme(mode).then(() => {
      if (!cancelled) {
        setReady(true);
      }
    });
    return () => {
      cancelled = true;
    };
  }, [mode]);

  if (!ready) {
    return null;
  }

  // Remount when theme flips so Mermaid SVGs re-render with dark/light palette.
  return <MarkdownPreview key={mode} doc={doc} />;
};

export default PostContent;
