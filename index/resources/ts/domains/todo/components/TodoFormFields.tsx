import Combobox from "@/ts/components/ui/combobox";
import { Checkbox } from "@/ts/components/ui/checkbox";
import { Input } from "@/ts/components/ui/input";
import { Label } from "@/ts/components/ui/label";
import { Textarea } from "@/ts/components/ui/textarea";
import { TODO_PRIORITY_LABEL, TODO_STATUS_LABEL } from "../constants/labels";
import type { TodoPriority, TodoProject, TodoStatus } from "../types";

export type TodoFormValues = {
  project_id: number | string;
  title: string;
  description: string;
  status: TodoStatus;
  priority: TodoPriority;
  due_at: string;
  is_urgent: boolean;
  is_important: boolean;
};

type Props = {
  values: TodoFormValues;
  errors: Partial<Record<keyof TodoFormValues, string>>;
  projects: Pick<TodoProject, "id" | "name">[];
  statuses: TodoStatus[];
  priorities: TodoPriority[];
  onChange: <K extends keyof TodoFormValues>(key: K, value: TodoFormValues[K]) => void;
};

const TodoFormFields = ({
  values,
  errors,
  projects,
  statuses,
  priorities,
  onChange,
}: Props): React.ReactElement => {
  const projectOptions = [
    { value: "", label: "— Không gán —" },
    ...projects.map((project) => ({
      value: String(project.id),
      label: project.name,
    })),
  ];

  const statusOptions = statuses.map((status) => ({
    value: status,
    label: TODO_STATUS_LABEL[status],
  }));

  const priorityOptions = priorities.map((priority) => ({
    value: priority,
    label: TODO_PRIORITY_LABEL[priority],
  }));

  return (
    <div className="space-y-4">
      <div>
        <Label>Title</Label>
        <Input
          value={values.title}
          onChange={(e) => onChange("title", e.target.value)}
          autoFocus
        />
        {errors.title && <p className="text-red-400 text-sm mt-1">{errors.title}</p>}
      </div>

      <div>
        <Label>Project</Label>
        <Combobox
          options={projectOptions}
          value={
            values.project_id === "" || values.project_id == null
              ? ""
              : String(values.project_id)
          }
          handleChange={(next) =>
            onChange("project_id", next === "" ? "" : Number(next))
          }
          placeholder="Chọn project"
          searchPlaceholder="Tìm project..."
        />
        {errors.project_id && (
          <p className="text-red-400 text-sm mt-1">{errors.project_id}</p>
        )}
      </div>

      <div>
        <Label>Description</Label>
        <Textarea
          value={values.description}
          onChange={(e) => onChange("description", e.target.value)}
          rows={4}
        />
        {errors.description && (
          <p className="text-red-400 text-sm mt-1">{errors.description}</p>
        )}
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <Label>Status</Label>
          <Combobox
            options={statusOptions}
            value={values.status}
            handleChange={(next) => onChange("status", next as TodoStatus)}
            placeholder="Chọn status"
            searchPlaceholder="Tìm status..."
          />
          {errors.status && (
            <p className="text-red-400 text-sm mt-1">{errors.status}</p>
          )}
        </div>
        <div>
          <Label>Priority</Label>
          <Combobox
            options={priorityOptions}
            value={values.priority}
            handleChange={(next) => onChange("priority", next as TodoPriority)}
            placeholder="Chọn priority"
            searchPlaceholder="Tìm priority..."
          />
          {errors.priority && (
            <p className="text-red-400 text-sm mt-1">{errors.priority}</p>
          )}
        </div>
      </div>

      <div>
        <Label>Due date</Label>
        <Input
          type="date"
          value={values.due_at}
          onChange={(e) => onChange("due_at", e.target.value)}
        />
        {errors.due_at && (
          <p className="text-red-400 text-sm mt-1">{errors.due_at}</p>
        )}
      </div>

      <div className="flex flex-wrap gap-6">
        <label className="flex items-center gap-2 text-gray-300">
          <Checkbox
            checked={values.is_urgent}
            onCheckedChange={(v) => onChange("is_urgent", v === true)}
          />
          Urgent
        </label>
        <label className="flex items-center gap-2 text-gray-300">
          <Checkbox
            checked={values.is_important}
            onCheckedChange={(v) => onChange("is_important", v === true)}
          />
          Important
        </label>
      </div>
    </div>
  );
};

export default TodoFormFields;
