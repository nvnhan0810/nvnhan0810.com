import { request } from "../infrastructure/todos-api";
import { t } from "./locale";
export interface AgentLinkStatus {
  enabled: boolean;
  linked: boolean;
  email?: string;
  linked_at?: string;
  last_seen_at?: string | null;
}
/** Ask Todo for a one-time consent URL on the Agent and return it. */
export async function startAgentLink(): Promise<string> {
  const result = await request<{ redirect_url: string }>(
    "/integrations/agent/link/start",
    { method: "POST", body: "{}" },
  );
  // Only ever navigate to http(s); the URL comes from our own backend config.
  const url = new URL(result.redirect_url);
  if (!["http:", "https:"].includes(url.protocol))
    throw new Error("Địa chỉ Agent không hợp lệ.");
  return url.toString();
}
export function unlinkAgent() {
  return request<{ linked: false; agent_notified: boolean }>(
    "/integrations/agent/link",
    { method: "DELETE" },
  );
}
export function agentStatus() {
  return request<AgentLinkStatus>("/integrations/agent/status");
}

export interface AgentRepo {
  id: string;
  name: string;
  full_name: string;
  /** `origin/main` exists. Agent cannot work on a repo without it. */
  /** Repo's default base branch on Agent (older Agents omit it: main). */
  base_branch?: string;
  /** Whether origin/<base_branch> exists. */
  has_main: boolean;
}
export interface AgentWorkspace {
  id: string;
  name: string;
  repos: AgentRepo[];
}
export interface AgentCatalog {
  workspaces: AgentWorkspace[];
}
/** Workspaces and repos the Agent offers this user (proxied and cached 60 s by Todo). */
export function agentCatalog(refresh = false) {
  return request<AgentCatalog>(
    `/integrations/agent/catalog${refresh ? "?refresh=1" : ""}`,
  );
}
/** Repos a task of a project may pick: only those of the project's workspace. */
export function workspaceRepos(
  catalog: AgentCatalog | null,
  workspaceId: string | null | undefined,
): AgentRepo[] {
  if (!catalog || !workspaceId) return [];
  return catalog.workspaces.find((w) => w.id === workspaceId)?.repos ?? [];
}

/**
 * Human message for a failed catalog call. `not_linked` says how to fix it. When the integration
 * is switched off on the server there is nothing to explain, so the message is empty.
 */
export function catalogProblem(e: unknown): string {
  const code = e instanceof Error ? e.message : "";
  if (code === "integration_disabled") return "";
  return code === "not_linked"
    ? t("agentCatalogNotLinked")
    : t("agentCatalogUnavailable");
}
