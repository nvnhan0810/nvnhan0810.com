from fastapi import FastAPI, Depends, HTTPException, Header
from pydantic import BaseModel
from sentence_transformers import SentenceTransformer
from keybert import KeyBERT
from sumy.parsers.plaintext import PlaintextParser
from sumy.nlp.tokenizers import Tokenizer
from sumy.summarizers.lex_rank import LexRankSummarizer
import nltk

# Tải bộ từ điển phục vụ ngắt câu cho tính năng tóm tắt
nltk.download('punkt')

app = FastAPI()

# 1. LOAD MODEL (Chỉ load 1 lần vào RAM)
print("Loading model...")
embedding_model = SentenceTransformer('paraphrase-multilingual-MiniLM-L12-v2')

# Truyền chung model cho KeyBERT để không tốn thêm RAM
kw_model = KeyBERT(model=embedding_model)
print("Model loaded successfully!")

class ArticleRequest(BaseModel):
    text: str

# Hàm check Secret Key (Bảo mật)
def verify_api_key(x_api_key: str = Header(...)):
    expected_key = "secret_key_cua_ban_123" 
    if x_api_key != expected_key:
        raise HTTPException(status_code=401, detail="Unauthorized")

@app.post("/api/embed")
def get_embedding(request: ArticleRequest, _: str = Depends(verify_api_key)):
    vector = embedding_model.encode(request.text).tolist()
    return {"embedding": vector}

@app.post("/api/enrich")
def enrich_data(request: ArticleRequest, _: str = Depends(verify_api_key)):
    text = request.text
    
    # --- 1. TÓM TẮT (SUMMARIZATION) ---
    try:
        # Sử dụng LexRank để nhặt ra 3 câu quan trọng nhất
        parser = PlaintextParser.from_string(text, Tokenizer("english")) 
        summarizer = LexRankSummarizer()
        summary_sentences = summarizer(parser.document, 3) 
        summary = " ".join([str(sentence) for sentence in summary_sentences])
    except Exception as e:
        summary = text[:300] + "..." # Fallback an toàn nếu lỗi

    # --- 2. TRÍCH XUẤT TỪ KHÓA (TAGGING) ---
    # keyphrase_ngram_range=(1, 2) nghĩa là cho phép từ khóa gồm 1 hoặc 2 từ (vd: "Microservices", "PostgreSQL tuning")
    keywords = kw_model.extract_keywords(text, keyphrase_ngram_range=(1, 2), stop_words=None, top_n=5)
    
    # KeyBERT trả về list tuple: [('từ khóa 1', 0.85), ('từ khóa 2', 0.72)]
    # Ta chỉ lấy phần text
    tags = [kw[0] for kw in keywords]

    return {
        "summary": summary,
        "tags": tags
    }

@app.get("/health")
def health_check():
    return {"status": "ok"}