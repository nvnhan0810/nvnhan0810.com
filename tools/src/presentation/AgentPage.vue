<script setup lang="ts">
import { agent } from '../domain/tools'
import SiteLayout from './components/SiteLayout.vue'

const installCommand = `curl --fail --proto '=https' --proto-redir '=https' --location \\
  https://backend.nvnhan0810.com/agent/install.sh -o /tmp/nvn-agent-install.sh
bash /tmp/nvn-agent-install.sh
export PATH="$HOME/.local/bin:$PATH"
nvn-agent --version`
</script>

<template>
  <SiteLayout home-href="../">
    <a class="breadcrumb" href="../">← Tất cả công cụ</a>
    <section class="detail-hero">
      <p class="eyebrow"><span class="status-dot"></span> {{ agent.category }}</p>
      <h1>Terminal của bạn.<br /><span>Không gian làm việc của bạn.</span></h1>
      <p class="lead"><strong>{{ agent.name }}</strong> — {{ agent.description }}</p>
      <div class="hero-actions"><a class="button" href="#install">Cài đặt nvn-agent <span aria-hidden="true">↓</span></a><div class="tags"><span v-for="platform in agent.platforms" :key="platform">{{ platform }}</span></div></div>
    </section>
    <section class="features" aria-label="Tính năng">
      <article><span class="feature-number">01 / AI</span><h2>Chat ngay cạnh code</h2><p>Kết nối Codex, Claude hoặc Cursor. Chọn connection và model phù hợp với cách bạn làm việc.</p></article>
      <article><span class="feature-number">02 / WORKSPACE</span><h2>Mọi thứ trong terminal</h2><p>Duyệt file, mở Vim/Neovim, dùng shell và chuyển giữa các pane bằng phím tắt.</p></article>
      <article><span class="feature-number">03 / GIT</span><h2>Theo dõi thay đổi</h2><p>Xem changes, diff và staged ngay trong workspace để nắm rõ những gì đang thay đổi.</p></article>
    </section>
    <div class="guide-layout">
      <aside><p class="eyebrow">BẮT ĐẦU</p><a href="#install">01 — Cài đặt</a><a href="#connect">02 — Kết nối</a><a href="#workspace">03 — Mở workspace</a><a href="#updates">04 — Cập nhật</a></aside>
      <div class="guide-content">
        <section id="install"><p class="eyebrow">01 / CÀI ĐẶT</p><h2>Sẵn sàng trong vài dòng lệnh.</h2><p>Hỗ trợ Linux x86_64 (gồm Ubuntu trong WSL trên Windows Intel) và macOS ARM64 trên chip Apple Silicon dòng M. Trên Windows, chạy các lệnh bên dưới trong terminal Ubuntu WSL; trên Mac dòng M, dùng terminal ARM64 native. Bản Linux cần glibc và thư viện hệ thống tương thích với máy build; nên dùng cùng phiên bản Ubuntu. Không có bản Windows native, Linux ARM64 hay macOS Intel. Cần Bash, curl và sha256sum hoặc shasum. Không cần cài Rust hay Cargo.</p><div class="code-block"><div class="code-label">TERMINAL <span>Bash</span></div><pre><code>{{ installCommand }}</code></pre></div><p class="note">Binary được cài vào <code>~/.local/bin/nvn-agent</code>. Thêm thư mục này vào PATH trong cấu hình shell nếu muốn dùng ở các terminal mới. Installer kiểm tra SHA-256 và version trước khi thay binary; nếu kiểm tra thất bại, bản cũ được giữ nguyên.</p></section>
        <section id="connect"><p class="eyebrow">02 / KẾT NỐI</p><h2>Chọn cách làm việc với AI.</h2><p>Tạo connection và làm theo hướng dẫn trong terminal để chọn provider, kiểu kết nối API key hoặc CLI. Với kết nối CLI, cài và đăng nhập CLI của provider trước.</p><div class="code-block"><pre><code>nvn-agent new
nvn-agent list</code></pre></div></section>
        <section id="workspace"><p class="eyebrow">03 / WORKSPACE</p><h2>Mở project. Bắt đầu làm việc.</h2><p>Chạy nvn-agent từ thư mục project. Cài Vim hoặc Neovim để chỉnh sửa file, và Git để sử dụng các màn hình Git.</p><div class="code-block"><pre><code>cd /path/to/your-project
nvn-agent</code></pre></div><dl class="shortcuts"><div><dt><kbd>Ctrl+B</kbd> rồi <kbd>?</kbd></dt><dd>Xem trợ giúp và phím tắt</dd></div><div><dt><kbd>Ctrl+B</kbd> rồi <kbd>s</kbd></dt><dd>Mở hoặc focus shell</dd></div><div><dt><kbd>Ctrl+B</kbd> rồi <kbd>q</kbd></dt><dd>Thoát workspace</dd></div></dl></section>
        <section id="updates"><p class="eyebrow">04 / CẬP NHẬT</p><h2>Luôn tiến về phía trước.</h2><p>Bản cài mặc định kiểm tra cập nhật ở nền khi mở TUI, tối đa một lần mỗi ngày. Khi tải xong, bạn được nhắc mở lại; lần mở bản mới sẽ có thông báo đã cập nhật một lần.</p><div class="code-block"><div class="code-label">CẬP NHẬT THỦ CÔNG</div><pre><code>nvn-agent update</code></pre></div><p>Linux và WSL nhận bản stable dành cho Linux; Mac dòng M nhận bản stable dành cho macOS. Hai nền tảng có thể ở các phiên bản khác nhau. Chạy lại installer cũng có thể cập nhật bản cài hiện có. Nếu stable bằng hoặc cũ hơn bản local, bản local được giữ nguyên, kể cả khi stable bị rollback.</p><p class="note">Muốn tắt tự động cập nhật? Đặt <code>NV_AGENT_AUTO_UPDATE=0</code> trong môi trường chạy CLI.</p></section>
      </div>
    </div>
    <section class="end-note"><span aria-hidden="true">&gt;_</span><h2>Workspace tiếp theo bắt đầu từ terminal.</h2><a class="text-link" href="#install">Quay lại hướng dẫn cài đặt ↑</a></section>
  </SiteLayout>
</template>
