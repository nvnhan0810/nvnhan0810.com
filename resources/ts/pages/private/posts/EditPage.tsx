import PostForm from "@/ts/components/posts/PostForm";
import PrivateLayout, { RootProps } from "@/ts/layouts/PrivateLayout";
import { Post, PostPayload } from "@/ts/types/post";
import type { Series } from "@/ts/types/series";
import { router } from "@inertiajs/react";
import { format } from "date-fns";
import { useRoute } from "ziggy-js";

type Props = RootProps & {
  post: Post;
  series: Series[];
  selectedSeriesIds?: number[];
};

const EditPage = ({ auth, post, series, selectedSeriesIds = [] }: Props) => {
  const route = useRoute();

  const handleUpdate = (payload: PostPayload) => {
    router.patch(
      route("admin.posts.update", { id: post.id }),
      {
        translations: payload.translations,
        published_at: payload.published_at
          ? format(payload.published_at, "yyyy-MM-dd")
          : null,
        is_published: payload.is_published,
        tags: payload.tags,
        series_ids: payload.series_ids,
      },
      {
        onSuccess: () => {
          router.visit(route("admin.index"));
        },
        onError: (errors) => {
          console.error("Update post failed:", errors);
        },
      }
    );
  };

  return (
    <PrivateLayout auth={auth}>
      <h2 className="text-center text-2xl font-bold text-gray-100">
        Chỉnh sửa bài viết
      </h2>
      <div className="mt-4 flex flex-col gap-4">
        <PostForm
          onSave={handleUpdate}
          initialPost={post}
          series={series}
          selectedSeriesIds={selectedSeriesIds}
        />
      </div>
    </PrivateLayout>
  );
};

export default EditPage;
