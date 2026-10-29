# Portfolio

Dự án Vue 3 + TypeScript cho trang home portfolio, được chuyển từ `index/resources/ts/pages/public/HomePage.tsx` sang Vue SFC. Dự án nằm độc lập trong `nvnhan0810.com/portfolio`; không cần Laravel để chạy giao diện.

## Chạy local

Yêu cầu Node.js 24 LTS (xem `.nvmrc`).

```sh
npm ci
npm run dev
```

Mở URL do Vite in ra. Build production bằng `npm run build`; kết quả nằm trong `dist/`. `npm run preview` chỉ dùng để xem thử bản build. Khi triển khai SPA, cấu hình web server fallback các đường dẫn frontend về `index.html`.

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

Giữ theme tối, màu emerald, nội dung hồ sơ EN/VI, 17 công nghệ, 4 ứng dụng/package, các phần about/skills/apps/contact, menu nhanh desktop/mobile và favicon/AWS icon của source. Locale được lưu bằng `localStorage`, mặc định EN như trang gốc; cập nhật `lang`, title và meta description khi chuyển ngôn ngữ.

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
# Hoặc chạy toàn bộ:
npm run check
```

`lint` chỉ kiểm tra; `lint:fix` và `format` mới sửa file. Test kiểm tra cấu trúc trang, link liên hệ/ứng dụng, đổi và khôi phục ngôn ngữ, lỗi repository có thể retry và xác nhận section blog đã được bỏ.
