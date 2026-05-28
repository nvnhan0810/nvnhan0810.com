import type { Locale } from "@/ts/i18n";
import type { Post, PostTranslationFields } from "@/ts/types/post";

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
  description?: string;
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

export const buildTranslationsFromDocs = (
  docs: Record<Locale, string>,
  sourceUrls?: Partial<Record<Locale, string>>
): Record<Locale, PostTranslationFields> => {
  const translations: Partial<Record<Locale, PostTranslationFields>> = {};

  for (const locale of Object.keys(docs) as Locale[]) {
    const parsed = parseMarkdownToPostFields(docs[locale]);

    const sourceUrl = sourceUrls?.[locale]?.trim() ?? "";
    if (parsed.title === "" || parsed.content === "") {
      continue;
    }

    translations[locale] = {
      locale,
      title: parsed.title,
      description: parsed.description,
      content: parsed.content || null,
      source_url: sourceUrl || null,
    };
  }

  return translations as Record<Locale, PostTranslationFields>;
};

export const buildDocsFromPost = (post: Post): Record<Locale, string> => {
  const docs: Record<Locale, string> = { en: "", vi: "" };

  if (post.translations) {
    for (const locale of Object.keys(post.translations) as Locale[]) {
      const translation = post.translations[locale];
      if (!translation) {
        continue;
      }
      docs[locale] = postFieldsToMarkdown({
        title: translation.title,
        description: translation.description,
        content: translation.content ?? "",
        tags: post.tags,
      });
    }
  } else {
    docs.en = postFieldsToMarkdown({
      title: post.title,
      description: post.description,
      content: post.content ?? "",
      tags: post.tags,
    });
  }

  return docs;
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
