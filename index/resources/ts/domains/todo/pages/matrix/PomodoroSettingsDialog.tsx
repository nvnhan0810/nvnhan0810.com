import { Button } from "@/ts/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/ts/components/ui/dialog";
import { Input } from "@/ts/components/ui/input";
import { Label } from "@/ts/components/ui/label";
import { useEffect, useState } from "react";
import {
  DEFAULT_POMODORO_SETTINGS,
  type PomodoroSettings,
} from "../../constants/pomodoro";

type Props = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  settings: PomodoroSettings;
  isTimerRunning: boolean;
  onSave: (next: PomodoroSettings) => void;
};

const PomodoroSettingsDialog = ({
  open,
  onOpenChange,
  settings,
  isTimerRunning,
  onSave,
}: Props): React.ReactElement => {
  const [form, setForm] = useState<PomodoroSettings>(DEFAULT_POMODORO_SETTINGS);

  useEffect(() => {
    if (open) {
      setForm(settings);
    }
  }, [open, settings]);

  const setField = <K extends keyof PomodoroSettings>(
    key: K,
    value: PomodoroSettings[K],
  ): void => {
    setForm((prev) => ({ ...prev, [key]: value }));
  };

  const submit = (event: React.FormEvent): void => {
    event.preventDefault();
    const next: PomodoroSettings = {
      focusMinutes: Math.min(180, Math.max(1, Math.round(form.focusMinutes) || 1)),
      shortBreakMinutes: Math.min(
        60,
        Math.max(1, Math.round(form.shortBreakMinutes) || 1),
      ),
      sessionsBeforeLongBreak: Math.min(
        12,
        Math.max(1, Math.round(form.sessionsBeforeLongBreak) || 1),
      ),
      longBreakMinutes: Math.min(
        60,
        Math.max(1, Math.round(form.longBreakMinutes) || 1),
      ),
    };
    onSave(next);
    onOpenChange(false);
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-md z-[220]">
        <form onSubmit={submit} className="space-y-4">
          <DialogHeader>
            <DialogTitle>Pomodoro settings</DialogTitle>
            <DialogDescription>
              {isTimerRunning
                ? "Timer đang chạy — giá trị mới áp dụng từ phase kế tiếp (đồng bộ mọi thiết bị)."
                : "Đồng bộ settings trên mọi thiết bị đã đăng nhập."}
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-4">
            <div>
              <Label htmlFor="pomodoro-focus">(1) Tập trung (phút)</Label>
              <Input
                id="pomodoro-focus"
                type="number"
                min={1}
                max={180}
                value={form.focusMinutes}
                onChange={(e) => setField("focusMinutes", Number(e.target.value))}
              />
            </div>
            <div>
              <Label htmlFor="pomodoro-short">(2) Nghỉ ngắn (phút)</Label>
              <Input
                id="pomodoro-short"
                type="number"
                min={1}
                max={60}
                value={form.shortBreakMinutes}
                onChange={(e) =>
                  setField("shortBreakMinutes", Number(e.target.value))
                }
              />
            </div>
            <div>
              <Label htmlFor="pomodoro-sessions">
                (3) Số lần tập trung trước nghỉ dài
              </Label>
              <Input
                id="pomodoro-sessions"
                type="number"
                min={1}
                max={12}
                value={form.sessionsBeforeLongBreak}
                onChange={(e) =>
                  setField("sessionsBeforeLongBreak", Number(e.target.value))
                }
              />
            </div>
            <div>
              <Label htmlFor="pomodoro-long">(4) Nghỉ dài (phút)</Label>
              <Input
                id="pomodoro-long"
                type="number"
                min={1}
                max={60}
                value={form.longBreakMinutes}
                onChange={(e) =>
                  setField("longBreakMinutes", Number(e.target.value))
                }
              />
            </div>
          </div>

          <DialogFooter>
            <Button
              type="button"
              variant="outline"
              onClick={() => onOpenChange(false)}
            >
              Hủy
            </Button>
            <Button type="submit">Lưu</Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
};

export default PomodoroSettingsDialog;
