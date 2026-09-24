import { Button } from "@/ts/components/ui/button";
import { Input } from "@/ts/components/ui/input";
import { Label } from "@/ts/components/ui/label";
import { Check, Copy, Eye, EyeOff } from "lucide-react";
import { useState } from "react";

type Props = {
  secret: string;
};

const SsoSecretPanel = ({ secret }: Props) => {
  const [visible, setVisible] = useState(false);
  const [copied, setCopied] = useState(false);

  const configured = secret.trim() !== "";
  const display = !configured
    ? "(chưa cấu hình SSO_SECRET trong env)"
    : visible
      ? secret
      : "•".repeat(Math.min(48, Math.max(16, secret.length)));

  const copy = async () => {
    if (!configured) {
      return;
    }

    try {
      await navigator.clipboard.writeText(secret);
      setCopied(true);
      window.setTimeout(() => setCopied(false), 2000);
    } catch {
      // ignore clipboard failures
    }
  };

  return (
    <div className="mb-6 rounded-md border border-border bg-muted/30 p-4">
      <Label className="text-foreground">SSO_SECRET (shared — từ env)</Label>
      <p className="text-xs text-muted-foreground mt-1 mb-2">
        Secret dùng chung cho mọi first-party. Copy sang env app con (`SSO_SECRET`). Không lưu trong DB.
      </p>
      <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
        <Input
          readOnly
          value={display}
          className="font-mono text-sm"
          type="text"
        />
        <div className="flex gap-2 shrink-0">
          <Button
            type="button"
            variant="outline"
            size="sm"
            disabled={!configured}
            onClick={() => setVisible((v) => !v)}
            title={visible ? "Ẩn" : "Hiện"}
          >
            {visible ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
          </Button>
          <Button
            type="button"
            variant="outline"
            size="sm"
            disabled={!configured}
            onClick={() => void copy()}
            title="Copy"
          >
            {copied ? <Check className="w-4 h-4 text-green-500" /> : <Copy className="w-4 h-4" />}
            <span className="ml-1">{copied ? "Đã copy" : "Copy"}</span>
          </Button>
        </div>
      </div>
    </div>
  );
};

export default SsoSecretPanel;
