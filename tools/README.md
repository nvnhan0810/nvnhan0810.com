# Tools

Landing page tĩnh giới thiệu công cụ, viết bằng Vue 3 và TypeScript. Home liệt kê công cụ; `/agent/` giới thiệu nvn-agent và hướng dẫn cài đặt.

```sh
npm install
npm run dev
npm run build
npm run preview
```

Yêu cầu Node.js ^22.18.0 hoặc >=24.12.0. Deploy nội dung `dist/` lên static hosting; phục vụ directory index cho `/` và `/agent/`. Hai trang có HTML entry riêng. Asset dùng đường dẫn tương đối, hỗ trợ host ở domain riêng hoặc dưới `/tools/` (giữ dấu `/` cuối URL thư mục).

## Deploy và CI/CD

Chạy `./tools/deploy.sh` từ checkout sạch trên branch `main` ở server. Script khóa deploy, pull `origin main` bằng fast-forward, chạy `npm ci --include=dev` và `npm run build`. Có thể gọi từ bất kỳ thư mục nào. `./tools/deploy.sh --build-only` bỏ qua pull để build revision hiện tại. Commit cả `package-lock.json` để cài đúng dependencies trên CI và server.

Nginx dùng cấu hình [deploy/nginx.conf](deploy/nginx.conf), root trỏ tới `tools/dist`. Khi cài hoặc thay cấu hình, chạy `sudo nginx -t` rồi `sudo systemctl reload nginx`. Cấu hình `try_files $uri $uri/ =404` phục vụ `/agent/` bằng `agent/index.html`, tự redirect `/agent` sang `/agent/`, và trả 404 cho asset không tồn tại. Cấu hình cũ `try_files $uri /index.html` trả trang chủ cho `/agent/`, khiến đường dẫn tương đối `./assets/...js` thành `/agent/assets/...js` và gây lỗi MIME `text/html`. Không sửa MIME bằng cách ép HTML thành JavaScript.

Workflow `../.github/workflows/tools.yml` chạy build khi mở PR hoặc push `main` thay đổi tools, lưu artifact `tools-dist`; chỉ gọi deploy webhook sau khi build thành công trên `main`. Có thể chạy thủ công bằng `workflow_dispatch`. Cấu hình secret `DEPLOY_HOOK_TOKEN` hoặc `DEPLOY_WEBHOOK_TOKEN` theo các workflow hiện có. Deploy service cần đăng ký repo key `nvnhan0810_com_tools` để chạy `tools/deploy.sh` trong checkout trên server; workflow gửi yêu cầu deploy, không tự đăng ký key này. Kiểm tra kết quả thực thi trên deploy service.

Sau deploy, kiểm tra `/`, `/agent/` và `/agent` (redirect); JavaScript ở `/assets/` phải trả MIME JavaScript, asset không tồn tại phải trả 404. Deploy thông thường chỉ build assets, không cần restart Supervisor hay reload Nginx.

Kiến trúc tối giản: `src/domain/` định nghĩa kiểu và nội dung công cụ; `src/presentation/` chứa page, layout và CSS; `src/home.ts` và `src/agent.ts` là entry point. Không có nghiệp vụ, API, kiểm tra version hay xử lý cài đặt trên web, nên không thêm application/infrastructure rỗng. Các lệnh trên trang chỉ là tài liệu để người dùng tự chạy.

Cập nhật hướng dẫn theo installer tại `../backend/modules/agent/assets/install.sh` và tài liệu CLI. Không ghim số version release trên landing page. Giao diện minh họa terminal bằng HTML/CSS, không phải ảnh chụp ứng dụng. Font Google Fonts có fallback sans-serif/monospace.
