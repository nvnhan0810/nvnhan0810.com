import SsoSecretPanel from "@/ts/components/sso/SsoSecretPanel";
import { Button } from "@/ts/components/ui/button";
import { Checkbox } from "@/ts/components/ui/checkbox";
import { Input } from "@/ts/components/ui/input";
import { Label } from "@/ts/components/ui/label";
import { Textarea } from "@/ts/components/ui/textarea";
import PrivateLayout, { type RootProps } from "@/ts/layouts/PrivateLayout";
import type { SsoClient } from "@/ts/types/ssoClient";
import { useForm } from "@inertiajs/react";
import { useRoute } from "ziggy-js";

type Props = RootProps & {
  client: SsoClient | null;
  sharedSecret: string;
};

const FormPage = ({ auth, client, sharedSecret }: Props) => {
  const route = useRoute();
  const isEdit = Boolean(client?.id);

  const { data, setData, post, put, processing, errors } = useForm({
    client_id: client?.client_id ?? "",
    name: client?.name ?? "",
    domain: client?.domain ?? "",
    redirect_uris: (client?.redirect_uris ?? []).join("\n"),
    redirect_uri_patterns: (client?.redirect_uri_patterns ?? []).join("\n"),
    enabled: client?.enabled ?? true,
  });

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    if (isEdit && client) {
      put(route("admin.sso-clients.update", client.id));
    } else {
      post(route("admin.sso-clients.store"));
    }
  };

  return (
    <PrivateLayout auth={auth}>
      <h1 className="text-2xl font-bold text-foreground mb-2">
        {isEdit ? "Sửa SSO client" : "Thêm SSO client"}
      </h1>
      <p className="text-sm text-muted-foreground mb-4 max-w-2xl">
        `client_id` phải khớp env app con (`SSO_CLIENT_ID`). Redirect URI phải khớp callback URL của app.
      </p>

      <SsoSecretPanel secret={sharedSecret} />

      <form onSubmit={submit} className="max-w-2xl space-y-4">
        <div>
          <Label>Tên hiển thị</Label>
          <Input
            placeholder="VD: Todo"
            value={data.name}
            onChange={(e) => setData("name", e.target.value)}
          />
          {errors.name && <p className="text-xs text-red-600 dark:text-red-400 mt-1">{errors.name}</p>}
        </div>

        <div>
          <Label>client_id</Label>
          <Input
            placeholder="VD: todo"
            className="font-mono"
            value={data.client_id}
            onChange={(e) => setData("client_id", e.target.value)}
          />
          <p className="text-xs text-muted-foreground mt-1">Chữ thường, số, dấu gạch ngang.</p>
          {errors.client_id && <p className="text-xs text-red-600 dark:text-red-400 mt-1">{errors.client_id}</p>}
        </div>

        <div>
          <Label>Domain (optional)</Label>
          <Input
            placeholder="https://todo.nvnhan0810.com"
            value={data.domain}
            onChange={(e) => setData("domain", e.target.value)}
          />
          <p className="text-xs text-muted-foreground mt-1">
            Base URL app con. Client `todo` dùng field này cho link menu.
          </p>
          {errors.domain && <p className="text-xs text-red-600 dark:text-red-400 mt-1">{errors.domain}</p>}
        </div>

        <div>
          <Label>Redirect URIs (mỗi dòng một URL)</Label>
          <Textarea
            rows={4}
            placeholder={"https://todo.nvnhan0810.com/auth/sso/callback"}
            className="font-mono text-sm"
            value={data.redirect_uris}
            onChange={(e) => setData("redirect_uris", e.target.value)}
          />
          {errors.redirect_uris && (
            <p className="text-xs text-red-600 dark:text-red-400 mt-1">{errors.redirect_uris}</p>
          )}
        </div>

        <div>
          <Label>Redirect URI patterns — PCRE (optional, mỗi dòng một regex)</Label>
          <Textarea
            rows={3}
            placeholder={"/^flc:\\/\\/oauth-callback(\\/|\\?|$)/"}
            className="font-mono text-sm"
            value={data.redirect_uri_patterns}
            onChange={(e) => setData("redirect_uri_patterns", e.target.value)}
          />
          <p className="text-xs text-muted-foreground mt-1">Dùng cho mobile deep link / Chromium app.</p>
          {errors.redirect_uri_patterns && (
            <p className="text-xs text-red-600 dark:text-red-400 mt-1">{errors.redirect_uri_patterns}</p>
          )}
        </div>

        <div className="flex items-center gap-2">
          <Checkbox
            checked={data.enabled}
            onCheckedChange={(v) => setData("enabled", Boolean(v))}
          />
          <Label>Enabled — cho phép authorize / token</Label>
        </div>

        <div className="flex gap-2 pt-2">
          <Button type="submit" disabled={processing}>
            {isEdit ? "Lưu" : "Tạo client"}
          </Button>
          <Button
            type="button"
            variant="outline"
            onClick={() => window.history.back()}
          >
            Hủy
          </Button>
        </div>
      </form>
    </PrivateLayout>
  );
};

export default FormPage;
