import type { Post } from "@/ts/types/post";

export type ParsedPostFields = {
  title: string;
  description?: string;
  content: string;
  tags: string[];
  errors: string[];
};

const checkSyntax = (
  content: string[],
  type: "title" | "description" | "tags"
): { index: number; result: string } => {
  const syntaxes = {
    title: "# ",
    description: "> ",
    tags: "Tags: ",
  };

  const syntax = syntaxes[type];

  let isChecking = true;
  let index = -1;
  let result = "";

  for (let i = 0; i < content.length; i++) {
    const item = content[i];

    if ((!isChecking || item !== "") && !item.startsWith(syntax)) {
      break;
    }

    if (item.startsWith(syntax)) {
      index = i;
      isChecking = false;
      result = item.replace(new RegExp(`^${syntax}`), "");
    }
  }

  return { index, result };
};

export const parseMarkdownToPostFields = (content: string): ParsedPostFields => {
  let contentArr = content?.split("\n") ?? [];

  const { result: title, index: titleIndex } = checkSyntax(contentArr, "title");
  if (titleIndex > -1) {
    contentArr = contentArr.slice(titleIndex + 1);
  }

  const { result: tagStr, index: tagsIndex } = checkSyntax(contentArr, "tags");
  let tags: string[] = [];
  if (tagsIndex > -1) {
    contentArr = contentArr.slice(tagsIndex + 1);
    tags = tagStr.split(",").map((tag) => tag.trim()).filter(Boolean);
  }

  const { result: description, index: descriptionIndex } = checkSyntax(
    contentArr,
    "description"
  );
  if (descriptionIndex > -1) {
    contentArr = contentArr.slice(descriptionIndex + 1);
  }

  const body = contentArr.join("\n");

  return {
    title,
    description: description || undefined,
    content: body,
    tags,
    errors: [],
  };
};

export const validateParsedPostFields = (
  parsed: ParsedPostFields,
  options?: { requireContent?: boolean }
): string[] => {
  const errors: string[] = [];
  const requireContent = options?.requireContent ?? true;

  if (parsed.title.trim() === "") {
    errors.push("Title is required");
  }

  if (requireContent && parsed.content.trim() === "") {
    errors.push("Body is required");
  }

  return errors;
};

export const postFieldsToMarkdown = (fields: {
  title: string;
  description?: string | null;
  content: string;
  tags?: { name: string }[];
}): string => {
  let content = `# ${fields.title}\n\n`;

  if (fields.tags && fields.tags.length > 0) {
    content += `Tags: ${fields.tags.map((tag) => tag.name).join(",")}\n\n`;
  }

  if (fields.description) {
    content += `> ${fields.description}\n\n`;
  }

  content += fields.content;

  return content;
};

export const buildDocFromPost = (post: Post): string => {
  return postFieldsToMarkdown({
    title: post.title,
    description: post.description,
    content: post.content ?? "",
    tags: post.tags,
  });
};

export const buildPreviewPost = (
  parsed: ParsedPostFields,
  base?: Partial<Post>
): Post => {
  return {
    id: base?.id ?? 0,
    slug: base?.slug ?? "",
    title: parsed.title,
    description: parsed.description,
    content: parsed.content,
    published_at: base?.published_at ?? new Date().toISOString(),
    is_published: base?.is_published ?? false,
    tags:
      parsed.tags.length > 0
        ? parsed.tags.map((tag, index) => ({
            id: index + 1,
            name: tag,
            slug: tag.toLowerCase().replace(/ /g, "-"),
          }))
        : base?.tags ?? [],
    public_tags:
      parsed.tags.length > 0
        ? parsed.tags.map((tag, index) => ({
            id: index + 1,
            name: tag,
            slug: tag.toLowerCase().replace(/ /g, "-"),
          }))
        : base?.public_tags ?? [],
  };
};
