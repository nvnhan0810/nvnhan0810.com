FROM python:3.10-slim

# 1. Tạo non-root user với UID 1000 (Chuẩn K8s)
RUN useradd -m -u 1000 appuser

WORKDIR /app

# 2. Đổi thư mục lưu cache của HuggingFace (chứa model AI) vào /app/.cache
ENV HF_HOME=/app/.cache
RUN mkdir -p $HF_HOME && chown -R appuser:appuser /app

# 3. Cài đặt thư viện
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# 4. Pre-download model & cập nhật quyền sở hữu cho cache vừa tải về
RUN python -c "from sentence_transformers import SentenceTransformer; SentenceTransformer('paraphrase-multilingual-MiniLM-L12-v2')" \
    && chown -R appuser:appuser $HF_HOME

# 5. Copy mã nguồn
COPY auth.py main.py ./
RUN chown appuser:appuser auth.py main.py

# 6. Chuyển sang chạy bằng user non-root
USER appuser

EXPOSE 8000

CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "8000"]