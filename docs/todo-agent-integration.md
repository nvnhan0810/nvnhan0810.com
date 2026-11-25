# Tích hợp Todo ↔ Agent

Plan chi tiết (quyết định đã chốt, kiến trúc, data model, API contract, phase, stack, test) nằm ở repo Agent:
`agent/docs/todo-agent-integration-plan.md` (v4).

Phần việc phía repo này:
- Phase 1 ✅: status `in_review`, deep link `/tasks/:id`, dependency giữa task (`todo_dependencies`, chống vòng, picker trên FE).
- Phase 2 ✅ (code xong, chưa chạy test Postgres/e2e): liên kết tài khoản (`todo_agent_links`, `todo_agent_link_requests`, token `tdi_`/`agi_`, limiter riêng theo token ở gateway, trang Settings → Tích hợp). Env: `TODO_AGENT_INTEGRATION_ENABLED`, `AGENT_BASE_URL`, `AGENT_WEB_URL`, `TODO_INTEGRATION_KEY`. Chi tiết và chỗ khác plan gốc: mục Phase 2 trong plan chính.
- Phase 3 ✅ (code xong, test Postgres đã chạy trên DB tạm; chưa e2e staging): proxy catalog `GET /todos/integrations/agent/catalog` (cache 60 s theo user, `?refresh=1` bỏ cache), chọn workspace trong form project, chọn repo + checkbox "Agent tự động xử lý" trong form task. Repo chỉ lấy trong workspace của project; repo chưa có `main` bị disable; đổi workspace của project thì bỏ chọn repo của các task. Server kiểm tra lại mọi tên/ID từ client qua catalog, tên được lưu làm snapshot. Contract fixture `catalog.v1.json` nằm ở cả hai repo.
- Phase 4 ✅ (code xong 2026-11-25, chưa e2e với Agent): bảng `todo_agent_events` và cột `agent_*` trên `todos` (migration `20261125000001_agent_runs`); API Agent (`/todo/v1/integrations/agent/tasks/claim`, `heartbeat`, `report`, `release`, `pr-updated`) dùng `tdi_`; sweeper `console todo:sweep-agent-leases` (cần cron mỗi phút, dòng cron có trong `docs/deployment.md`); khối `agent-result` trong mô tả được server giữ; badge/panel Agent trên FE. Env mới ở prod: `TODO_WEB_ORIGIN` (cho `todo_url`). Chi tiết trong plan chính.
- Task identifier `PREFIX-N` ✅ (2026-11-25): project có `identifier_prefix` (mặc định `DEF`); task lấy prefix của project lúc tạo (không project thì `DEF`), số tăng dần theo từng prefix. Identifier cố định sau khi tạo. Claim payload có `identifier` cho Agent. Chi tiết: mục Phase 4b trong plan chính.
- Phase 5–6 (phía Agent) ✅ code xong 2026-11-25, Phase 7 làm phần trong repo (log run, docs, env mẫu). Phía Todo không đổi thêm ngoài `docs/deployment.md`. Còn e2e staging, load test, cài cron. Chi tiết trong plan chính.
- Phạm vi Phase 4 đã lên kế hoạch (xem trạng thái ở trên):
  - Claim nhận `{workspaces, limit}`, trả **tối đa `limit` task** (nhiều task cùng workspace được phép, chạy song song ở worktree riêng).
  - Thứ tự theo ma trận Eisenhower: Do → Schedule → Delegate → Eliminate, rồi `priority`, `due_at`, `created_at`.
  - Payload có `previous` (nhánh, PR, kết quả lần trước) để agent chạy lại trên cùng nhánh/PR.
  - Cột mới `pr_has_conflict` + badge ⚠️ conflict; `agent_branch`/`pr_url` giữ nguyên khi task về `todo`.

Quy tắc chính: chỉ claim task `todo` + `agent_auto` + dependency đã `done`; không khóa workspace; thành công, thất bại hay bị hủy trên Agent đều chuyển `in_review`; PR có conflict vẫn tạo, user kéo task về `todo` để agent tự fix trên cùng PR hoặc tự sửa trong chat của task; base branch luôn `main`.
