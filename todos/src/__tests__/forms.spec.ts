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
