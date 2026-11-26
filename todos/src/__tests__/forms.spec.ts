import { flushPromises, mount } from "@vue/test-utils";
import { beforeEach, it, expect, vi } from "vitest";
import TaskEditor from "../presentation/components/TaskEditor.vue";
import ProjectEditor from "../presentation/components/ProjectEditor.vue";
import { ValidationError } from "../infrastructure/todos-api";
const mocks = vi.hoisted(() => ({ submit: vi.fn() }));
vi.mock("../application/navigation", () => ({ submit: mocks.submit }));
beforeEach(() => {
  mocks.submit.mockReset();
});
it("shows server field errors without losing the entered task", async () => {
  mocks.submit.mockRejectedValueOnce(
    new ValidationError({ title: "Title already exists" }),
  );
  const wrapper = mount(TaskEditor, { props: { projects: [] } });
  await wrapper.get("input").setValue("New task");
  await wrapper.get("form").trigger("submit");
  await flushPromises();
  expect(wrapper.text()).toContain("Title already exists");
  expect(wrapper.get("input").element.value).toBe("New task");
  expect(wrapper.emitted("saved")).toBeUndefined();
  mocks.submit.mockResolvedValueOnce(undefined);
  await wrapper.get("form").trigger("submit");
  await flushPromises();
  expect(wrapper.emitted("saved")).toHaveLength(1);
  expect(mocks.submit).toHaveBeenLastCalledWith(
    "/todos",
    "POST",
    expect.objectContaining({
      title: "New task",
      project_id: null,
      status: "backlog",
      due_at: null,
    }),
  );
  wrapper.unmount();
});
it("submits project changes to the existing project URL", async () => {
  mocks.submit.mockResolvedValue(undefined);
  const wrapper = mount(ProjectEditor, {
    props: {
      project: { id: 12, name: "Old", git_repo_url: null, domain: null },
    },
  });
  await wrapper.get("input").setValue("Updated");
  await wrapper.get("form").trigger("submit");
  await flushPromises();
  expect(mocks.submit).toHaveBeenCalledWith(
    "/todos/projects/12",
    "PUT",
    expect.objectContaining({ name: "Updated" }),
  );
  wrapper.unmount();
});
const candidates = [
  {
    id: 1,
    identifier: "DEF-1",
    title: "Same A",
    status: "todo" as const,
    project_id: 5,
  },
  {
    id: 2,
    identifier: "DEF-2",
    title: "Same B",
    status: "done" as const,
    project_id: 5,
  },
  {
    id: 3,
    identifier: "DEF-3",
    title: "Other",
    status: "todo" as const,
    project_id: 6,
  },
  {
    id: 9,
    identifier: "DEF-9",
    title: "Self",
    status: "todo" as const,
    project_id: 5,
  },
];
const project = (id: number) => ({
  id,
  name: `P${id}`,
  git_repo_url: null,
  domain: null,
});
it("offers only same-project tasks, never itself, and submits depends_on", async () => {
  mocks.submit.mockResolvedValue(undefined);
  const wrapper = mount(TaskEditor, {
    props: {
      projects: [project(5), project(6)],
      candidates,
      todo: {
        id: 9,
        project_id: 5,
        title: "Self",
        description: null,
        status: "todo",
        priority: "medium",
        started_at: null,
        due_at: null,
        closed_at: null,
        is_urgent: false,
        is_important: false,
        depends_on: [
          { id: 2, identifier: "DEF-2", title: "Same B", status: "done" },
        ],
      },
    },
  });
  const boxes = wrapper.findAll(".dependencies input[type=checkbox]");
  expect(boxes).toHaveLength(2);
  expect(wrapper.find(".dependencies").text()).not.toContain("Other");
  expect(wrapper.find(".dependencies").text()).not.toContain("Self");
  // Tasks are named by identifier, never by numeric id.
  expect(wrapper.find(".dependencies").text()).toContain("DEF-1");
  expect(wrapper.find(".dependencies").text()).not.toContain("#1");
  await boxes[0].setValue(true);
  await wrapper.get("form").trigger("submit");
  await flushPromises();
  expect(mocks.submit).toHaveBeenLastCalledWith(
    "/todos/9",
    "PUT",
    expect.objectContaining({ depends_on: [2, 1] }),
  );
  wrapper.unmount();
});
it("drops selected dependencies when the project changes", async () => {
  mocks.submit.mockResolvedValue(undefined);
  const wrapper = mount(TaskEditor, {
    props: { projects: [project(5), project(6)], candidates },
  });
  await wrapper.get("select").setValue(5);
  await wrapper.findAll(".dependencies input[type=checkbox]")[0].setValue(true);
  await wrapper.get("select").setValue(6);
  await flushPromises();
  await wrapper.get("form").trigger("submit");
  await flushPromises();
  expect(mocks.submit).toHaveBeenLastCalledWith(
    "/todos",
    "POST",
    expect.objectContaining({ project_id: 6, depends_on: [] }),
  );
  wrapper.unmount();
});
