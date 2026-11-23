# Tích hợp Todo ↔ Agent

Plan chi tiết (quyết định đã chốt, kiến trúc, data model, API contract, phase, stack, test) nằm ở repo Agent:
`agent/docs/todo-agent-integration-plan.md` (v4).

Phần việc phía repo này:
- Phase 1: status `in_review`, deep link `/tasks/:id`, dependency giữa task (`todo_dependencies`, chống vòng, picker trên FE).
- Phase 2: liên kết tài khoản (link request, token `tdi_`, limiter riêng ở gateway).
- Phase 3: proxy catalog, gắn workspace cho project, chọn repo + checkbox auto cho task.
- Phase 4: API claim/heartbeat/report/release/`pr-updated`, ghi khối `agent-result` vào body, sweeper lease.
  - Claim nhận `{workspaces, limit}`, trả **tối đa `limit` task** (nhiều task cùng workspace được phép, chạy song song ở worktree riêng).
  - Thứ tự theo ma trận Eisenhower: Do → Schedule → Delegate → Eliminate, rồi `priority`, `due_at`, `created_at`.
  - Payload có `previous` (nhánh, PR, kết quả lần trước) để agent chạy lại trên cùng nhánh/PR.
  - Cột mới `pr_has_conflict` + badge ⚠️ conflict; `agent_branch`/`pr_url` giữ nguyên khi task về `todo`.

Quy tắc chính: chỉ claim task `todo` + `agent_auto` + dependency đã `done`; không khóa workspace; thành công, thất bại hay bị hủy trên Agent đều chuyển `in_review`; PR có conflict vẫn tạo, user kéo task về `todo` để agent tự fix trên cùng PR hoặc tự sửa trong chat của task; base branch luôn `main`.
