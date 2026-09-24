import SsoSecretPanel from "@/ts/components/sso/SsoSecretPanel";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from "@/ts/components/ui/alert-dialog";
import { Button } from "@/ts/components/ui/button";
import PrivateLayout, { type RootProps } from "@/ts/layouts/PrivateLayout";
import type { SsoClient } from "@/ts/types/ssoClient";
import { router } from "@inertiajs/react";
import { Plus } from "lucide-react";
import { useRoute } from "ziggy-js";

type Props = RootProps & {
  clients: SsoClient[];
  sharedSecret: string;
};

const ListPage = ({ auth, clients, sharedSecret }: Props) => {
  const route = useRoute();

  const handleDelete = (id: string) => {
    router.delete(route("admin.sso-clients.destroy", id));
  };

  return (
    <PrivateLayout auth={auth}>
      <div className="flex justify-between items-center mb-4">
        <div>
          <h1 className="text-2xl font-bold text-foreground">SSO clients</h1>
          <p className="text-sm text-muted-foreground mt-1">
            First-party apps đăng nhập qua IdP. Secret giữ ở env; client id / domain / redirect URI ở DB.
          </p>
        </div>
        <Button
          variant="outline"
          title="Thêm client"
          onClick={() => router.get(route("admin.sso-clients.create"))}
        >
          <Plus className="w-4 h-4" />
        </Button>
      </div>

      <SsoSecretPanel secret={sharedSecret} />

      <div className="overflow-x-auto rounded-xl border border-border">
        <table className="w-full text-sm">
          <thead className="bg-muted/50 text-left text-foreground">
            <tr>
              <th className="px-3 py-2 font-semibold">Name</th>
              <th className="px-3 py-2 font-semibold">client_id</th>
              <th className="px-3 py-2 font-semibold">Domain</th>
              <th className="px-3 py-2 font-semibold">Redirect URIs</th>
              <th className="px-3 py-2 font-semibold">Status</th>
              <th className="px-3 py-2 font-semibold">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-border text-foreground">
            {clients.length === 0 && (
              <tr>
                <td colSpan={6} className="px-3 py-6 text-center text-muted-foreground">
                  Chưa có client. Thêm wallets, todo, flc-web, …
                </td>
              </tr>
            )}
            {clients.map((client) => (
              <tr key={client.id} className="bg-card">
                <td className="px-3 py-2">{client.name}</td>
                <td className="px-3 py-2 font-mono text-xs">{client.client_id}</td>
                <td className="px-3 py-2 max-w-[12rem] truncate" title={client.domain ?? undefined}>
                  {client.domain ?? "—"}
                </td>
                <td className="px-3 py-2 max-w-xs">
                  {client.redirect_uris.length > 0 ? (
                    <ul className="list-disc pl-4 space-y-0.5">
                      {client.redirect_uris.map((uri) => (
                        <li key={uri} className="truncate font-mono text-xs" title={uri}>
                          {uri}
                        </li>
                      ))}
                    </ul>
                  ) : (
                    <span className="text-muted-foreground text-xs">
                      {client.redirect_uri_patterns.length > 0
                        ? `${client.redirect_uri_patterns.length} pattern(s)`
                        : "—"}
                    </span>
                  )}
                </td>
                <td className="px-3 py-2">
                  {client.enabled ? (
                    <span className="text-emerald-600 dark:text-green-400">enabled</span>
                  ) : (
                    <span className="text-amber-600 dark:text-amber-400">disabled</span>
                  )}
                </td>
                <td className="px-3 py-2">
                  <div className="flex gap-2">
                    <a href={route("admin.sso-clients.edit", client.id)} className="text-blue-600 dark:text-blue-400">
                      Edit
                    </a>
                    <AlertDialog>
                      <AlertDialogTrigger asChild>
                        <button type="button" className="text-red-600 dark:text-red-400 cursor-pointer">
                          Delete
                        </button>
                      </AlertDialogTrigger>
                      <AlertDialogContent>
                        <AlertDialogHeader>
                          <AlertDialogTitle>Xóa SSO client</AlertDialogTitle>
                          <AlertDialogDescription>
                            Xóa <strong>{client.client_id}</strong>? App con dùng client_id này sẽ không đăng nhập được.
                          </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                          <AlertDialogCancel>Hủy</AlertDialogCancel>
                          <AlertDialogAction
                            className="bg-red-500 hover:bg-red-600"
                            onClick={() => handleDelete(client.id)}
                          >
                            Xóa
                          </AlertDialogAction>
                        </AlertDialogFooter>
                      </AlertDialogContent>
                    </AlertDialog>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </PrivateLayout>
  );
};

export default ListPage;
