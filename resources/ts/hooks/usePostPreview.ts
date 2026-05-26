import { useState } from "react";
import { Post } from "../types/post";
import {
  buildPreviewPost,
  parseMarkdownToPostFields,
  postFieldsToMarkdown,
} from "../utils/postMarkdown";

type Props = {
  initialPost?: Post;
};

const usePostPreview = ({ initialPost }: Props) => {
  const [post, setPost] = useState<Post | undefined>(initialPost);
  const [errors, setErrors] = useState<string[]>([]);

  const parseContentToPost = (content: string) => {
    const parsed = parseMarkdownToPostFields(content);

    if (parsed.errors.length > 0 || errors.length !== parsed.errors.length) {
      setErrors(parsed.errors);
    } else if (errors.length > 0) {
      setErrors([]);
    }

    setPost(buildPreviewPost(parsed, post));
  };

  const parsePostToContent = (value: Post) => {
    return postFieldsToMarkdown({
      title: value.title,
      description: value.description,
      content: value.content,
      tags: value.tags,
    });
  };

  return { post, errors, parsePostToContent, parseContentToPost, setPost };
};

export default usePostPreview;
