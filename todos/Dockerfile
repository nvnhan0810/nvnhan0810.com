# syntax=docker/dockerfile:1
FROM node:24-bookworm-slim

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm install

# Bạn hoàn toàn có thể copy source vào làm fallback
COPY . . 

EXPOSE 5177

# [QUAN TRỌNG]: Chạy npm install ở Runtime để chống lỗi đè Volume
CMD ["sh", "-c", "npm install && npm run dev"]