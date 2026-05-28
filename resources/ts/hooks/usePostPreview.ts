import { useCallback, useState } from "react";
import type { Post } from "../types/post";
import {
  buildPreviewPost,
  parseMarkdownToPostFields,
  postFieldsToMarkdown,
  validateParsedPostFields,
} from "../utils/postMarkdown";

type Props = {
  initialPost?: Post;
};

const usePostPreview = ({ initialPost }: Props) => {
  const [post, setPost] = useState<Post | undefined>(initialPost);
  const [errors, setErrors] = useState<string[]>([]);

  const parseContentToPost = useCallback((
    content: string,
    options?: { requireContent?: boolean }
  ) => {
    const parsed = parseMarkdownToPostFields(content);
    const nextErrors = validateParsedPostFields(parsed, options);

    setErrors((current) =>
      current.length === nextErrors.length &&
      current.every((item, idx) => item === nextErrors[idx])
        ? current
        : nextErrors
    );

    setPost((current) => buildPreviewPost(parsed, current));
  }, []);

  const parsePostToContent = useCallback((value: Post) => {
    return postFieldsToMarkdown({
      title: value.title,
      description: value.description,
      content: value.content ?? "",
      tags: value.tags,
    });
  }, []);

  return { post, errors, parsePostToContent, parseContentToPost, setPost };
};

export default usePostPreview;
