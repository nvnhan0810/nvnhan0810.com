import PostForm from "@/ts/components/posts/PostForm";
import PrivateLayout, { RootProps } from "@/ts/layouts/PrivateLayout";
import { PostPayload } from "@/ts/types/post";
import type { Series } from "@/ts/types/series";
import { router } from "@inertiajs/react";
import { format } from "date-fns";
import { useRoute } from "ziggy-js";

type Props = RootProps & {
  series: Series[];
};

const CreatePage = ({ auth, series }: Props) => {
  const route = useRoute();

  const handleCreate = (payload: PostPayload) => {
    router.post(
      route("admin.posts.store"),
      {
        translations: payload.translations,
        tags: payload.tags,
        published_at: payload.published_at
          ? format(payload.published_at, "yyyy-MM-dd")
          : null,
        is_published: payload.is_published,
        series_ids: payload.series_ids,
      },
      {
        onSuccess: () => {
          router.visit(route("admin.index"));
        },
        onError: (errors) => {
          console.error("Create post failed:", errors);
        },
      }
    );
  };

  return (
    <PrivateLayout auth={auth}>
      <h2 className="text-center text-2xl font-bold text-gray-100">
        Bài viết mới
      </h2>
      <div className="mt-4 flex flex-col gap-4">
        <PostForm onSave={handleCreate} series={series} />
      </div>
    </PrivateLayout>
  );
};

export default CreatePage;
