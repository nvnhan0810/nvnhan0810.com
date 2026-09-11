import type { Post } from "@/ts/types/post";
import PostItemForm from "./PostItemForm";
import {
  DndContext,
  type DragEndEvent,
  KeyboardSensor,
  PointerSensor,
  useSensor,
  useSensors,
} from "@dnd-kit/core";
import {
  arrayMove,
  SortableContext,
  sortableKeyboardCoordinates,
} from "@dnd-kit/sortable";
import { verticalListSortingStrategy } from "@dnd-kit/sortable";
import { closestCenter } from "@dnd-kit/core";
import { useState } from "react";
import { Label } from "../ui/label";
import { Button } from "../ui/button";
import { PlusIcon } from "lucide-react";

type Props = {
  posts: Post[];
  initSerialPosts: number[];
  onChangeSerialPosts: (serialPosts: number[]) => void;
};

const PostListForm = ({
  posts,
  initSerialPosts,
  onChangeSerialPosts,
}: Props) => {
  const [seriesPosts, setSeriesPosts] = useState<number[]>(initSerialPosts);

  const sensors = useSensors(
    useSensor(PointerSensor),
    useSensor(KeyboardSensor, {
      coordinateGetter: sortableKeyboardCoordinates,
    })
  );

  const handleDragEnd = (event: DragEndEvent) => {
    const { active, over } = event;
    if (active && over && active.id !== over.id) {
      const newList = arrayMove(seriesPosts, Number(active.id), Number(over.id));

      setSeriesPosts(newList);
      onChangeSerialPosts(newList);
    }
  };

  const handleAddNewPostRow = () => {
    const newList = [...seriesPosts, 0];

    setSeriesPosts(newList);
    onChangeSerialPosts(newList);
  };

  const handleChangePost = (id: string, index: number) => {
    const newList = seriesPosts.map((postId, i) => (i === index ? parseInt(id, 10) : postId));

    setSeriesPosts(newList);
    onChangeSerialPosts(newList);
  };

  const handleDeletePost = (index: number) => {
    const newList = seriesPosts.filter((_, i) => i !== index);

    setSeriesPosts(newList);
    onChangeSerialPosts(newList);
  };

  return (
    <>
      <div className="flex justify-between">
        <Label className="text-gray-100">Bài viết</Label>
        <Button
          variant="outline"
          title="Thêm bài viết"
          onClick={() => handleAddNewPostRow()}
        >
          <PlusIcon className="w-4 h-4" />
        </Button>
      </div>
      <div className="flex flex-col gap-2">
        <DndContext
          sensors={sensors}
          collisionDetection={closestCenter}
          onDragEnd={handleDragEnd}
        >
          <SortableContext
            items={seriesPosts.map((_, index) => index.toString())}
            strategy={verticalListSortingStrategy}
          >
            {seriesPosts.map((seriesPost, index) => (
              <PostItemForm
                posts={posts}
                key={index.toString()}
                seriesPost={seriesPost}
                index={index}
                onDelete={handleDeletePost}
                onChangePost={handleChangePost}
              />
            ))}
          </SortableContext>
        </DndContext>
      </div>
    </>
  );
};

export default PostListForm;
