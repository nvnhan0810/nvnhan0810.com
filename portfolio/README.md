# Portfolio

Dự án Vue 3 + TypeScript cho trang home portfolio, được chuyển từ `index/resources/ts/pages/public/HomePage.tsx` sang Vue SFC. Dự án nằm độc lập trong `nvnhan0810.com/portfolio`; không cần Laravel để chạy giao diện.

## Chạy local

Yêu cầu Node.js 24 LTS (xem `.nvmrc`).

```sh
npm ci
npm run dev
```

Dev server SSR chạy mặc định tại `http://127.0.0.1:5173`, có Vite HMR.

```sh
npm run build   # Type-check + build client và server
npm start       # SSR production tại http://127.0.0.1:3000
# Hoặc đổi địa chỉ/cổng:
HOST=0.0.0.0 PORT=8080 npm start
```

`npm run preview` chạy cùng server SSR production, cần build trước. Kết quả build nằm trong `dist/client` (asset + HTML template) và `dist/server` (Vue SSR renderer). Production cần Node.js 24, `server/`, `dist/`, `package.json`, lockfile và `npm ci --omit=dev`. Server production không tải Vite; không cần thư viện dev để chạy.

Khi deploy, cấu hình reverse proxy về cổng SSR. Không chỉ host `dist/client` như static SPA: HTML template cần được render bởi Node server.

## SSR

- `src/main.ts`: factory tạo Vue SSR app, router và dependencies riêng cho mỗi request.
- `src/entry-server.ts`: tải dữ liệu qua use case, render Vue thành HTML và tạo metadata + state cho hydration.
- `src/entry-client.ts`: hydrate HTML server bằng cùng initial state, sau đó bật các tương tác.
- `server/index.ts`: HTTP server Node; dùng Vite middleware trong dev, serve asset đã build trong production.
- `server/render-html.ts`: chọn locale và ghép HTML template.

Nội dung hồ sơ, kỹ năng, ứng dụng, title, meta description và `html[lang]` có ngay trong HTML response, trước khi JavaScript chạy. State được escape để nội dung không thể đóng thẻ script JSON. Router và state không dùng singleton giữa các request.

Thử `/?lang=en` hoặc `/?lang=vi`; query hợp lệ được ưu tiên trước cookie `portfolio-locale`, mặc định EN. Khi đổi ngôn ngữ, client lưu cookie để server chọn đúng ngôn ngữ lần truy cập tiếp theo; localStorage vẫn được cập nhật. Hydration dùng locale từ server để tránh mismatch với preference cũ trong trình duyệt. HTML cá nhân hóa theo cookie có `Cache-Control: private, no-store`; asset có hash được cache lâu dài.

## Công nghệ

- Vue 3 Composition API, `<script setup lang="ts">`, Vue Router, Vite.
- TypeScript `strict`, `noUncheckedIndexedAccess`, `exactOptionalPropertyTypes`, `noImplicitOverride`, kiểm tra biến và tham số không dùng.
- ESLint flat config với Vue recommended và TypeScript strict type-aware rules.
- Prettier, EditorConfig và cấu hình VS Code.
- Tailwind CSS 4; cấu trúc shadcn-vue với Reka UI, CVA và `tailwind-merge`.
- Vitest, Vue Test Utils và jsdom.

Component shadcn-vue được lưu trong source để có thể chỉnh sửa. `Button` được thiết lập thủ công trên Reka `Primitive`; CLI không phải dependency runtime hay dependency bắt buộc của dự án. `components.json` đã cấu hình alias và đường dẫn CSS. Khi cần thêm component khác:

```sh
npm run ui:add -- dialog
```

Lệnh này tải CLI/component từ registry, cần quyền truy cập mạng.

## Clean Architecture

```text
src/
├── app/                      # Composition root, router, App và global styles
│   ├── bootstrap.ts          # Ghép use case với repository cụ thể
│   └── router/
├── modules/portfolio/
│   ├── domain/               # Entity/types và repository interface; TypeScript thuần
│   ├── application/          # Use case, chỉ phụ thuộc domain
│   ├── infrastructure/       # Dữ liệu chuyển từ index và static repository
│   └── presentation/         # Vue pages, components, composable và injection key
└── shared/                   # UI components, class utilities, cấu hình link
```

Luồng dữ liệu: `main.ts` → `app/bootstrap.ts` → inject `GetPortfolio` → composable → page. Use case phụ thuộc `PortfolioRepository` interface, không phụ thuộc repository cụ thể. Thay adapter trong `bootstrap.ts` khi cần lấy dữ liệu qua API, không import infrastructure vào component.

ESLint kiểm tra các ranh giới import: domain không import application/infrastructure/presentation/Vue; application không import infrastructure/presentation/Vue; presentation không import infrastructure hoặc composition root; shared không import feature hoặc app.

## Giao diện chuyển từ index

Giữ theme tối, màu emerald, nội dung hồ sơ EN/VI, 17 công nghệ, 4 ứng dụng/package, các phần about/skills/apps/contact, menu nhanh desktop/mobile và favicon/AWS icon của source. Locale được lưu bằng cookie và `localStorage`, mặc định EN như trang gốc; cập nhật `lang`, title và meta description khi chuyển ngôn ngữ.

Các nguồn đối chiếu:

- `../index/resources/ts/pages/public/HomePage.tsx`
- `../index/resources/ts/layouts/PortfolioLayout.tsx`
- `../index/resources/ts/components/portfolio/`
- `../index/resources/ts/constants/{profile,techStack,apps}.ts`
- `../index/resources/ts/i18n/locales/{en,vi}.ts`
- `../index/resources/sass/app.scss`

Dữ liệu hồ sơ/catalog đã được sao chép vào `src/modules/portfolio/infrastructure/data/portfolio-data.ts`, không có import xuyên sang project `index`. Icon công nghệ còn dùng `cdn.simpleicons.org` như source; AWS icon và favicon đã được copy local.

**Phạm vi giao diện:** section blog và mục điều hướng `#blog` đã được bỏ khỏi home. Các link blog bên ngoài tại phần liên hệ và menu nhanh vẫn trỏ về site hiện có. Các link apps, posts, news và todo được cấu hình tại `src/shared/config/site.ts`. Các trang con và backend Laravel không nằm trong phạm vi chuyển UI home.

## Kiểm tra

```sh
npm run type-check
npm run lint
npm run format:check
npm test
npm run build
npm run test:ssr  # Kiểm tra HTTP dev và production; cần build trước và quyền mở cổng local
# Hoặc chạy toàn bộ:
npm run check
```

`lint` chỉ kiểm tra; `lint:fix` và `format` mới sửa file. Test kiểm tra cấu trúc trang, link liên hệ/ứng dụng, đổi và khôi phục ngôn ngữ, lỗi repository có thể retry và xác nhận section blog đã được bỏ. Test SSR kiểm tra render không có browser, isolation giữa request, redirect, escape HTML/state và hydration tương tác không mismatch. `test:ssr` khởi động server dev/production tạm để kiểm tra HTTP thực tế, rồi dừng cả hai server.

## Docker production và GitHub Actions

Image dành cho `https://nvnhan0810.com`, chạy HTTP SSR trên `0.0.0.0:8080` bằng Node.js 24, user không phải root. Node chạy trực tiếp làm tiến trình chính và nhận SIGTERM khi container dừng. Runtime chỉ chứa production dependencies, output build và các file server cần thiết. Không cần Nginx hoặc Supervisor trong container.

```sh
# Chạy từ thư mục portfolio/
docker build -t portfolio:local .
docker run --rm --name portfolio -p 127.0.0.1:8080:8080 portfolio:local
curl -f http://127.0.0.1:8080/healthz
```

`GET /healthz` trả HTTP 200 với nội dung `ok`, dùng được cho startup/readiness/liveness probes trong repo k3s. Dockerfile cũng có HEALTHCHECK. Kubernetes quản lý probe theo manifest riêng, không tự sử dụng Docker HEALTHCHECK. Có thể chạy image với filesystem chỉ đọc và drop toàn bộ Linux capabilities.

Workflow riêng: `../.github/workflows/portfolio.yaml`.

- Push vào `main` khi `portfolio/**` thay đổi, hoặc chạy thủ công: chỉ build/push Docker image và gọi API deploy. Không chạy test, lint hoặc container smoke test trong GitHub Actions.
- Image chỉ có tag SHA commit ngắn đúng **7 ký tự**, không prefix, không `latest` hoặc tag version. Ví dụ: `ghcr.io/nvnhan0810/nvnhan0810.com/portfolio:a1b2c3d`.
- Build dùng `docker/metadata-action` với `type=sha,format=short,prefix=` và `DOCKER_METADATA_SHORT_SHA_LENGTH=7` như mẫu workflow trong repo. Dockerfile vẫn type-check và build client/server; không chạy test.
- API: `POST https://deploy.nvnhan0810.com/deploy`, payload gồm `repo=portfolio`, `tag=production`, image và `image_tag` trùng tag đã push. Cấu hình GitHub variable `PORTFOLIO_DEPLOY_REPO` nếu sau này đổi tên mapping; mặc định `portfolio`.
- Dùng `GITHUB_TOKEN` để push GHCR; cần secret `DEPLOY_HOOK_TOKEN` hoặc `DEPLOY_WEBHOOK_TOKEN` để gọi deploy API. Thiếu token sẽ báo lỗi rõ ràng.
- Chạy thủ công với `deploy_only=true` để deploy lại image có sẵn; `image_tag` phải là SHA 7 ký tự hex viết thường. Bỏ trống để dùng SHA của commit đang chọn.

Repo k3s riêng quản lý triển khai và mapping API. Không có manifest hoặc thao tác cluster trong repo này. Workflow `nvnhan0810.com.yml` hiện tại được giữ nguyên.

## Git hooks: chỉ kiểm tra trước push

Kích hoạt **một lần cho mỗi clone**, từ thư mục gốc repo:

```sh
bash .githooks/install
```

Script đặt Git local config `core.hooksPath=.githooks`. Hooks được lưu cùng source để các clone khác có thể cài lại.

- Commit không chạy kiểm tra portfolio.
- `pre-push`: nếu commit sắp push thay đổi `portfolio/` so với remote tip, kiểm tra snapshot của commit đó. Nhánh mới được kiểm tra nếu có portfolio; xóa nhánh/tag được bỏ qua; cùng commit được kiểm tra một lần trong mỗi lần push.
- Snapshot cài dependencies từ lockfile bằng `npm ci --prefer-offline`, chạy `npm run check` (format, ESLint, tests, types, production build), sau đó `npm run test:ssr`.
- Bất kỳ bước nào lỗi sẽ chặn push. Snapshot và build output được dọn sau khi chạy, không sửa working tree hay staging area.
- Nếu chỉ thay đổi project khác thì hooks không chạy kiểm tra portfolio. Cần Node 24, npm và khả năng mở cổng localhost cho SSR test; lần đầu cài dependencies có thể cần mạng.

Đây là kiểm tra local; người dùng Git vẫn có thể bỏ qua hook bằng `--no-verify`. GitHub Actions được giữ ở phạm vi build và gọi deploy API theo yêu cầu.

Image contract cho repo triển khai:

```text
Image:        ghcr.io/nvnhan0810/nvnhan0810.com/portfolio:<7-char-SHA>
Port:         8080/TCP
Health:       GET /healthz -> 200 ok
User:         UID 1000, GID 1000
Environment:  NODE_ENV=production, HOST=0.0.0.0, PORT=8080
Storage:      Không cần persistent volume
Domain:       nvnhan0810.com
```

## Apps

Portfolio phục vụ `/apps`, `/apps/foreign-language-course` và `/apps/db-management-tool` bằng SSR, có nội dung EN/VI và metadata riêng. Slug chi tiết không hợp lệ chuyển về `/apps`. Nội dung được chuyển từ catalog và bản dịch của `index`; posts/news vẫn thuộc Laravel.
