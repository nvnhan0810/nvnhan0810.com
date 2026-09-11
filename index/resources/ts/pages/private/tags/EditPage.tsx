import { Button } from "@/ts/components/ui/button";
import { Input } from "@/ts/components/ui/input";
import { Label } from "@/ts/components/ui/label";
import PrivateLayout, { RootProps } from "@/ts/layouts/PrivateLayout";
import { Tag } from "@/ts/types/tag";
import { router } from "@inertiajs/react";
import { useState } from "react";
import { useRoute } from "ziggy-js";

type Props = RootProps & {
  initialTag: Tag;
}

const EditPage = ({ auth, initialTag }: Props) => {
  const route = useRoute();

  const [tag, setTag] = useState<Tag>(initialTag);

  const handleSave = () => {

    router.put(route('admin.tags.update', tag.id), {
      ...tag,
    }, {
      onSuccess: () => {
        router.get(route('admin.tags.index'), {
          success: 'Cập nhật thành công',
        });
      }
    });
  }

  return (
    <PrivateLayout auth={auth}>
      <div>
        <div className="flex justify-between items-center mb-4">
          <h1 className="text-2xl font-bold text-gray-100 text-center">
            Quản Lý thẻ
          </h1>
        </div>
        <div className="max-w-auto overflow-x-auto">
          <form onSubmit={(e) => {
            e.preventDefault();
            handleSave();
          }}>
            <div className="flex flex-col gap-4 mb-4">
              <div className="flex flex-col gap-2">
                <Label>Tên</Label>
                <Input type="text" name="name" value={tag.name} onChange={(e) => setTag({ ...tag, name: e.target.value })} />
              </div>
              <div className="flex flex-col gap-2">
                <Label>Slug</Label>
                <Input type="text" name="slug" value={tag.slug} onChange={(e) => setTag({ ...tag, slug: e.target.value })} />
              </div>
            </div>
            <div className="flex justify-center">
              <Button variant="outline" type="submit">Lưu</Button>
            </div>
          </form>
        </div>
      </div>
    </PrivateLayout>
  );
};

export default EditPage;
