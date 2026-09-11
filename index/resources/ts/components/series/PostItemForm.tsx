import { Button } from "@/ts/components/ui/button";
import Combobox from "@/ts/components/ui/combobox";
import { useSortable } from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";
import { AlignJustify, TrashIcon } from "lucide-react";
import type { Post } from "@/ts/types/post";

type Props = {
  posts: Post[];
  seriesPost: number;
  index: number;
  onDelete: (index: number) => void;
  onChangePost: (value: string, index: number) => void;
};

const PostItemForm = ({
  posts,
  seriesPost,
  index,
  onDelete,
  onChangePost,
}: Props) => {
  const { attributes, listeners, setNodeRef, transition, transform, isDragging } =
    useSortable({
      id: index.toString(),
    });
  const style = {
    transform: transform ? CSS.Transform.toString(transform) : undefined,
    transition: isDragging ? transition : "none", // reset transition khi không drag
  };
  return (
    <div
      key={index.toString()}
      ref={setNodeRef}
      style={style}
      className="flex justify-between gap-2"
    >
      <Button {...listeners} {...attributes} variant="ghost">
      <AlignJustify className="w-4 h-4" />
      </Button>
      <Combobox
        options={posts.map((post) => ({
          value: post.id.toString(),
          label: post.title,
        }))}
        handleChange={(value) => onChangePost(value, index)}
        value={seriesPost.toString()}
      />
      <Button
        variant="outline"
        title="Xóa bài viết"
        onClick={() => onDelete(index)}
      >
        <TrashIcon className="w-4 h-4" />
      </Button>
    </div>
  );
};

export default PostItemForm;
