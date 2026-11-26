import { flushPromises, mount } from "@vue/test-utils";
import { beforeEach, expect, it, vi } from "vitest";
import ProjectEditor from "../presentation/components/ProjectEditor.vue";
import TaskEditor from "../presentation/components/TaskEditor.vue";
import {
  catalogProblem,
  workspaceRepos,
  type AgentCatalog,
} from "../application/agent-link";
import { ApiError } from "../infrastructure/transport";
import type { TodoProject } from "../domain/todo";

const mocks = vi.hoisted(() => ({
  request: vi.fn(),
  submit: vi.fn(),
}));
vi.mock("../infrastructure/todos-api", async (importOriginal) => ({
  ...(await importOriginal<typeof import("../infrastructure/todos-api")>()),
  request: mocks.request,
}));
vi.mock("../application/navigation", () => ({ submit: mocks.submit }));

const catalog: AgentCatalog = {
  workspaces: [
    {
      id: "ws-1",
      name: "Work",
      repos: [
        { id: "r-api", name: "api", full_name: "acme/api", has_main: true },
        {
          id: "r-old",
          name: "legacy",
          full_name: "acme/legacy",
          has_main: false,
        },
      ],
    },
    {
      id: "ws-2",
      name: "Home",
      repos: [
        { id: "r-blog", name: "blog", full_name: "me/blog", has_main: true },
      ],
    },
  ],
};

beforeEach(() => {
  mocks.request.mockReset();
  mocks.submit.mockReset();
  mocks.submit.mockResolvedValue(undefined);
  mocks.request.mockResolvedValue(catalog);
});

const project = (id: number, workspace: string | null = null): TodoProject => ({
  id,
  name: `P${id}`,
  git_repo_url: null,
  domain: null,
  agent_workspace_id: workspace,
  agent_workspace_name: workspace,
});

it("lists only the repos of the project's workspace", () => {
  expect(workspaceRepos(catalog, "ws-2").map((r) => r.id)).toEqual(["r-blog"]);
  expect(workspaceRepos(catalog, null)).toEqual([]);
  expect(workspaceRepos(null, "ws-1")).toEqual([]);
});

it("saves the chosen Agent workspace on the project and clears an empty choice", async () => {
  const wrapper = mount(ProjectEditor, {
    props: { project: project(3) },
  });
  await flushPromises();
  expect(mocks.request).toHaveBeenCalledWith("/integrations/agent/catalog");
  await wrapper.get("select").setValue("ws-1");
  await wrapper.get("form").trigger("submit");
  await flushPromises();
  expect(mocks.submit).toHaveBeenLastCalledWith(
    "/todos/projects/3",
    "PUT",
    expect.objectContaining({ agent_workspace_id: "ws-1" }),
  );
  await wrapper.get("select").setValue("");
  await wrapper.get("form").trigger("submit");
  await flushPromises();
  expect(mocks.submit).toHaveBeenLastCalledWith(
    "/todos/projects/3",
    "PUT",
    expect.objectContaining({ agent_workspace_id: null }),
  );
  wrapper.unmount();
});

it("disables the workspace picker and explains why when the Agent is not linked", async () => {
  mocks.request.mockRejectedValueOnce(new ApiError(409, "not_linked"));
  const wrapper = mount(ProjectEditor, { props: { project: null } });
  await flushPromises();
  expect(wrapper.get("select").attributes("disabled")).toBeDefined();
  expect(wrapper.text()).toContain("Settings → Tích hợp");
  wrapper.unmount();
});

it("offers only the project workspace's repos, disables repos without main, and gates auto", async () => {
  const wrapper = mount(TaskEditor, {
    props: { projects: [project(5, "ws-1")] },
  });
  await flushPromises();
  // The project select comes first; the repo select is the second one.
  await wrapper.findAll("select")[0].setValue(5);
  await flushPromises();
  const repoSelect = wrapper.findAll("select")[1];
  const options = repoSelect.findAll("option");
  const values = options.map((o) => o.element.getAttribute("value"));
  expect(values).not.toContain("r-blog");
  const legacy = options.find((o) => o.text().includes("legacy"));
  expect(legacy?.attributes("disabled")).toBeDefined();

  const autoBox = () =>
    wrapper
      .findAll("label.checkbox")
      .find((l) => l.text().includes("Agent"))!
      .get("input");
  expect(autoBox().attributes("disabled")).toBeDefined();

  await wrapper.get("input").setValue("Ship it");
  await repoSelect.setValue("r-api");
  await flushPromises();
  expect(autoBox().attributes("disabled")).toBeUndefined();
  await autoBox().setValue(true);
  await wrapper.get("form").trigger("submit");
  await flushPromises();
  expect(mocks.submit).toHaveBeenLastCalledWith(
    "/todos",
    "POST",
    expect.objectContaining({
      project_id: 5,
      agent_repo_id: "r-api",
      agent_auto: true,
    }),
  );
  wrapper.unmount();
});

it("unpicks the repo when the project moves to another workspace", async () => {
  const wrapper = mount(TaskEditor, {
    props: {
      projects: [project(5, "ws-1"), project(6, "ws-2")],
      todo: {
        id: 9,
        project_id: 5,
        title: "T",
        description: null,
        status: "todo",
        priority: "medium",
        started_at: null,
        due_at: null,
        closed_at: null,
        is_urgent: false,
        is_important: false,
        agent_repo_id: "r-api",
        agent_repo_name: "api",
        agent_auto: true,
      },
    },
  });
  await flushPromises();
  await wrapper.findAll("select")[0].setValue(6);
  await flushPromises();
  await wrapper.get("form").trigger("submit");
  await flushPromises();
  expect(mocks.submit).toHaveBeenLastCalledWith(
    "/todos/9",
    "PUT",
    expect.objectContaining({ agent_repo_id: null, agent_auto: false }),
  );
  wrapper.unmount();
});

it("pauses a running task and resumes a paused one from the agent panel", async () => {
  const base = {
    id: 9,
    project_id: 5,
    title: "T",
    description: null,
    status: "in_progress" as const,
    priority: "medium" as const,
    started_at: null,
    due_at: null,
    closed_at: null,
    is_urgent: false,
    is_important: false,
    agent_repo_id: "r-api",
    agent_repo_name: "api",
    agent_auto: true,
  };
  const running = mount(TaskEditor, {
    props: {
      projects: [project(5, "ws-1")],
      todo: { ...base, agent_state: "running" },
    },
  });
  await flushPromises();
  const pause = running
    .findAll("button")
    .find((b) => b.text() === "Tạm ngưng")!;
  await pause.trigger("click");
  await flushPromises();
  expect(mocks.submit).toHaveBeenLastCalledWith(
    "/todos/9/agent/pause",
    "POST",
    {
      return_to: "todos",
    },
  );
  expect(running.emitted("saved")).toHaveLength(1);
  running.unmount();

  const pausing = mount(TaskEditor, {
    props: {
      projects: [project(5, "ws-1")],
      todo: { ...base, agent_state: "running", agent_pause_requested: true },
    },
  });
  await flushPromises();
  expect(pausing.text()).toContain("Đang chờ Agent tạm ngưng");
  expect(pausing.findAll("button").some((b) => b.text() === "Tạm ngưng")).toBe(
    false,
  );
  pausing.unmount();

  const paused = mount(TaskEditor, {
    props: {
      projects: [project(5, "ws-1")],
      todo: { ...base, agent_state: "paused", agent_branch: "agent/t-9" },
    },
  });
  await flushPromises();
  expect(paused.text()).toContain("Agent tạm ngưng");
  await paused
    .findAll("button")
    .find((b) => b.text() === "Tiếp tục")!
    .trigger("click");
  await flushPromises();
  expect(mocks.submit).toHaveBeenLastCalledWith(
    "/todos/9/agent/resume",
    "POST",
    {
      return_to: "todos",
    },
  );
  paused.unmount();
});

it("explains a failed catalog call, and stays silent when the integration is off", () => {
  expect(catalogProblem(new ApiError(409, "not_linked"))).toContain(
    "Settings → Tích hợp",
  );
  expect(catalogProblem(new ApiError(502, "agent_unavailable"))).toContain(
    "Không lấy được",
  );
  expect(catalogProblem(new ApiError(503, "integration_disabled"))).toBe("");
});
