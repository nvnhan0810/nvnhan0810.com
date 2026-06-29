import { Button } from "@/ts/components/ui/button";
import { Input } from "@/ts/components/ui/input";
import { Label } from "@/ts/components/ui/label";
import PrivateLayout, { RootProps } from "@/ts/layouts/PrivateLayout";
import { router, useForm, usePage } from "@inertiajs/react";
import { useRoute } from "ziggy-js";
import ReadingDigestNav from "../../../components/ReadingDigestNav";
import type { RdDigestRun, RdDigestSettings } from "../../../types";

type Props = RootProps & {
  settings: RdDigestSettings;
  recentRuns: RdDigestRun[];
};

const SettingsPage = ({ auth, settings, recentRuns }: Props) => {
  const route = useRoute();
  const { flash } = usePage<{ flash?: { success?: string } }>().props;

  const { data, setData, put, processing } = useForm({
    notification_time: settings.notification_time,
    timezone: settings.timezone,
  });

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    put(route("admin.reading-digest.settings.update"));
  };

  const sendNow = () => router.post(route("admin.reading-digest.send-now"));

  const resetLearning = () => {
    if (window.confirm("Reset learned preferences from your votes? Ranking will start fresh.")) {
      router.post(route("admin.reading-digest.settings.reset-learning"));
    }
  };

  return (
    <PrivateLayout auth={auth}>
      <ReadingDigestNav />
      <h1 className="text-2xl font-bold text-gray-100 mb-2">Settings</h1>
      <p className="text-sm text-muted-foreground mb-4 max-w-2xl">
        Mỗi ngày vào giờ bạn chọn, hệ thống lấy bài mới từ các source trong subject, chọn bài phù hợp
        (tự tính phía server) rồi gửi Telegram. Bạn vote sau khi đọc để hệ thống học dần sở thích.
      </p>
      {flash?.success && <p className="text-green-400 text-sm mb-4">{flash.success}</p>}
      <form onSubmit={submit} className="max-w-2xl space-y-4 mb-8">
        <div className="grid grid-cols-2 gap-4">
          <div>
            <Label>Giờ gửi digest hằng ngày</Label>
            <Input value={data.notification_time} onChange={(e) => setData("notification_time", e.target.value)} placeholder="08:00" />
          </div>
          <div>
            <Label>Múi giờ</Label>
            <Input value={data.timezone} onChange={(e) => setData("timezone", e.target.value)} placeholder="Asia/Ho_Chi_Minh" />
          </div>
        </div>
        <div className="flex flex-wrap gap-2">
          <Button type="submit" disabled={processing}>Lưu</Button>
          <Button type="button" variant="secondary" onClick={sendNow}>Fetch &amp; gửi ngay</Button>
          <Button type="button" variant="outline" onClick={resetLearning}>Reset sở thích đã học</Button>
        </div>
      </form>

      <h2 className="text-lg font-semibold text-gray-200 mb-2">Lần chạy gần đây</h2>
      <ul className="text-sm text-gray-400 space-y-1">
        {recentRuns.map((run) => (
          <li key={run.id}>{run.run_date} — {run.status === "completed" ? "OK" : run.status}</li>
        ))}
        {recentRuns.length === 0 && <li>Chưa có lần chạy nào.</li>}
      </ul>
    </PrivateLayout>
  );
};

export default SettingsPage;
