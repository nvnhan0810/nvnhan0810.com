# Tools

Landing page tĩnh giới thiệu công cụ, viết bằng Vue 3 và TypeScript. Home liệt kê công cụ; `/agent/` giới thiệu nvn-agent và hướng dẫn cài đặt.

```sh
npm install
npm run dev
npm run build
npm run preview
```

Yêu cầu Node.js ^22.18.0 hoặc >=24.12.0. Deploy nội dung `dist/` lên static hosting; phục vụ directory index cho `/` và `/agent/`. Hai trang có HTML entry riêng, không cần SPA fallback. Asset dùng đường dẫn tương đối, hỗ trợ host ở domain riêng hoặc dưới `/tools/` (giữ dấu `/` cuối URL thư mục).

Kiến trúc tối giản: `src/domain/` định nghĩa kiểu và nội dung công cụ; `src/presentation/` chứa page, layout và CSS; `src/home.ts` và `src/agent.ts` là entry point. Không có nghiệp vụ, API, kiểm tra version hay xử lý cài đặt trên web, nên không thêm application/infrastructure rỗng. Các lệnh trên trang chỉ là tài liệu để người dùng tự chạy.

Cập nhật hướng dẫn theo installer tại `../backend/modules/agent/assets/install.sh` và tài liệu CLI. Không ghim số version release trên landing page. Giao diện minh họa terminal bằng HTML/CSS, không phải ảnh chụp ứng dụng. Font Google Fonts có fallback sans-serif/monospace.
