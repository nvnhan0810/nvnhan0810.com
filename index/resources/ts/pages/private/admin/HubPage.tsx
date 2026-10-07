import { Button } from "@/ts/components/ui/button";
import PrivateLayout, { RootProps } from "@/ts/layouts/PrivateLayout";
import { Link } from "@inertiajs/react";
import { useRoute } from "ziggy-js";

type AdminModule = {
  key: string;
  label: string;
  description: string;
  path: string;
  route: string;
};

type Props = RootProps & {
  modules: AdminModule[];
};

const HubPage = ({ auth, modules }: Props) => {
  const route = useRoute();

  return (
    <PrivateLayout auth={auth}>
      <div className="mx-auto w-full max-w-3xl space-y-6">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">Admin</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Chọn module để vào trang quản lý. Header phía trên cũng có cùng danh sách khi đã đăng nhập.
          </p>
        </div>

        <ul className="divide-y divide-border rounded-lg border border-border">
          {modules.map((mod) => (
            <li
              key={mod.key}
              className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
            >
              <div className="min-w-0 space-y-1">
                <div className="font-medium">{mod.label}</div>
                <p className="text-sm text-muted-foreground">{mod.description}</p>
                <code className="block text-xs text-muted-foreground">{mod.path}</code>
              </div>
              <Button asChild variant="outline" className="shrink-0 self-start sm:self-center">
                <Link href={route(mod.route)}>Mở</Link>
              </Button>
            </li>
          ))}
        </ul>
      </div>
    </PrivateLayout>
  );
};

export default HubPage;
