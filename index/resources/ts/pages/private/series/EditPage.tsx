import { Button } from "@/ts/components/ui/button";
import { Input } from "@/ts/components/ui/input";
import { Textarea } from "@/ts/components/ui/textarea";
import PrivateLayout, { type RootProps } from "@/ts/layouts/PrivateLayout";
import type { Post } from "@/ts/types/post";
import type { Series } from "@/ts/types/series";
import { router } from "@inertiajs/react";
import { useState } from "react";
import { useRoute } from "ziggy-js";
import PostListForm from "@/ts/components/series/PostListForm";

type Props = RootProps & {
  series: Series;
  posts: Post[];
};

const EditPage = ({ auth, series, posts }: Props) => {
  const route = useRoute();

  const initSerialPosts = series.posts
    .sort((a, b) => a.pivot.order - b.pivot.order)
    .map((post) => post.id);

  const [title, setTitle] = useState(series.name);
  const [description, setDescription] = useState(series.description);
  const [seriesPosts, setSeriesPosts] = useState<number[]>(initSerialPosts);

  const handleChangeSerialPosts = (result: number[]) => {
    setSeriesPosts([...result]);
  };

  const handleUpdate = () => {
    router.patch(
      route("admin.series.update", { id: series.id }),
      {
        name: title,
        description: description,
        posts: seriesPosts.map((postId, index) => ({
          order: index + 1,
          post_id: postId,
        })),
      },
      {
        onSuccess: () => {
          router.visit(route("admin.series.index"));
        },
        onError: (errors) => {
          console.error("Update series failed:", errors);
        },
      }
    );
  };

  return (
    <PrivateLayout auth={auth}>
      <h2 className="text-2xl font-bold text-gray-100 text-center">
        Chỉnh sửa series
      </h2>
      <div className="flex flex-col gap-4 mt-4">
        <div className="mb-2">
          <Input
            name="name"
            placeholder="Tên series"
            value={title}
            onChange={(e) => setTitle(e.target.value)}
          />
        </div>
        <div className="mb-2">
          <Textarea
            name="description"
            placeholder="Mô tả series"
            value={description ?? ""}
            onChange={(e) => setDescription(e.target.value)}
          />
        </div>

        <PostListForm
          posts={posts}
          initSerialPosts={initSerialPosts}
          onChangeSerialPosts={handleChangeSerialPosts}
        />
      </div>
      <div className="flex justify-center mt-4">
        <Button onClick={handleUpdate}>Cập nhật series</Button>
      </div>
    </PrivateLayout>
  );
};

export default EditPage;
