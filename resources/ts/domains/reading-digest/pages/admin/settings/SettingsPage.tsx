import { Button } from "@/ts/components/ui/button";
import { Input } from "@/ts/components/ui/input";
import { Label } from "@/ts/components/ui/label";
import PrivateLayout, { RootProps } from "@/ts/layouts/PrivateLayout";
import { router, useForm } from "@inertiajs/react";
import { useRoute } from "ziggy-js";
import ReadingDigestNav from "../../../components/ReadingDigestNav";
import type { RdDigestRun, RdDigestSettings, RdProfile } from "../../../types";

type Props = RootProps & {
  settings: RdDigestSettings;
  profile: RdProfile;
  recentRuns: RdDigestRun[];
  defaults: Record<string, unknown>;
};

const SettingsPage = ({ auth, settings, profile, recentRuns }: Props) => {
  const route = useRoute();

  const { data, setData, put, processing } = useForm({
    notification_time: settings.notification_time,
    timezone: settings.timezone,
    settings: settings.settings ?? {},
    preferences: profile.preferences ?? {},
  });

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    put(route("admin.reading-digest.settings.update"));
  };

  const sendNow = () => router.post(route("admin.reading-digest.send-now"));

  const favoriteTopics = (data.preferences.favorite_topics ?? {}) as Record<string, number>;

  return (
    <PrivateLayout auth={auth}>
      <ReadingDigestNav />
      <h1 className="text-2xl font-bold text-gray-100 mb-4">Digest Settings</h1>
      <form onSubmit={submit} className="max-w-2xl space-y-4 mb-8">
        <div className="grid grid-cols-2 gap-4">
          <div>
            <Label>Notification time</Label>
            <Input value={data.notification_time} onChange={(e) => setData("notification_time", e.target.value)} />
          </div>
          <div>
            <Label>Timezone</Label>
            <Input value={data.timezone} onChange={(e) => setData("timezone", e.target.value)} />
          </div>
        </div>
        <div>
          <Label>Preferred difficulty</Label>
          <Input
            value={(data.preferences.preferred_difficulty as string) ?? "advanced"}
            onChange={(e) => setData("preferences", { ...data.preferences, preferred_difficulty: e.target.value })}
          />
        </div>
        <div>
          <Label>Favorite topics (path → score)</Label>
          <textarea
            className="w-full min-h-32 border border-border rounded-md bg-background p-2 font-mono text-sm"
            value={JSON.stringify(favoriteTopics, null, 2)}
            onChange={(e) => {
              try {
                setData("preferences", {
                  ...data.preferences,
                  favorite_topics: JSON.parse(e.target.value),
                });
              } catch {
                // ignore invalid JSON while typing
              }
            }}
          />
        </div>
        <div className="flex gap-2">
          <Button type="submit" disabled={processing}>Save</Button>
          <Button type="button" variant="secondary" onClick={sendNow}>Send now</Button>
        </div>
      </form>

      <h2 className="text-lg font-semibold text-gray-200 mb-2">Recent runs</h2>
      <ul className="text-sm text-gray-400 space-y-1">
        {recentRuns.map((run) => (
          <li key={run.id}>{run.run_date} — {run.status}</li>
        ))}
        {recentRuns.length === 0 && <li>No runs yet.</li>}
      </ul>
    </PrivateLayout>
  );
};

export default SettingsPage;
