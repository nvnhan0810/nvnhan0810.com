# Reading Digest — Thuật toán & DSA tính độ ưu tiên tag / bài viết

> Tài liệu này mô tả **chi tiết** các thuật toán và cấu trúc dữ liệu (DSA) đang được dùng để tính **độ ưu tiên (priority/score)** của các **taxonomy node (tag)** và **bài viết** trong tính năng Reading Digest.
>
> Nguồn code chính:
> - `app/Domains/ReadingDigest/Domain/Services/RetrievalScoringService.php` — chấm điểm bài viết (hybrid score)
> - `app/Domains/ReadingDigest/Domain/Services/InterestScoreService.php` — trọng số hành vi + decay
> - `app/Domains/ReadingDigest/Application/Handlers/RecordInteractionHandler.php` — cập nhật interest score theo tag
> - `app/Domains/ReadingDigest/Application/Handlers/DecayInterestScoresHandler.php` — decay định kỳ
> - `app/Domains/ReadingDigest/Application/Handlers/RebuildUserEmbeddingHandler.php` — vector sở thích người dùng
> - `app/Domains/ReadingDigest/Infrastructure/Persistence/Repositories/RetrievalService.php` — lọc + lấy top-K
> - `app/Domains/ReadingDigest/Infrastructure/Enrichment/RankingService.php` — re-rank bằng LLM
> - `config/reading-digest.php` — các hằng số/trọng số

---

## 1. Tổng quan pipeline tính điểm

Reading Digest dùng kiến trúc **retrieve-then-rank** (2 tầng), giống các hệ recommendation kinh điển:

```
Bài viết thô
   │  (1) Hard filters — loại bỏ theo tập
   ▼
Ứng viên hợp lệ
   │  (2) Hybrid linear scoring — chấm điểm từng bài
   ▼
Sắp xếp giảm dần → lấy Top-K (K=30)
   │  (3) LLM re-ranking (tuỳ chọn) → Top-N (5–10)
   ▼
rd_digest_run_items (thứ hạng cuối)
```

Song song, hệ thống **học sở thích theo thời gian** qua 2 cơ chế:

- **Interest score theo tag**: cộng dồn trọng số hành vi + **exponential decay**.
- **User taste vector (embedding)**: **weighted centroid** (kiểu Rocchio) của các bài đã tương tác tích cực.

---

## 2. Cấu trúc dữ liệu (DSA) đang dùng

| Cấu trúc | Nơi dùng | Mục đích |
|---|---|---|
| **Hash map / associative array** (`array<string,float>`) | `favoriteTopics` (path → điểm), `interestScores` (taxonomy_id → điểm), `interaction_weights` (event → weight) | Tra cứu trọng số O(1) |
| **Set membership** (`in_array`, `whereIn`/`whereNotIn`) | `ignoredTaxonomyIds`, `preferredSources`, `dismissedArticleIds`, `recentlySentArticleIds` | Lọc cứng theo tập, kiểm tra thuộc/không thuộc |
| **Dense vector** (`array<int,float>`, 1536 chiều) | Article embedding, user embedding | Tính cosine similarity |
| **Cây phân cấp (tree)** — `rd_taxonomy_nodes` với `path` dạng dotted (`programming.frontend.react`) | Taxonomy | Biểu diễn tag phân cấp; match theo `path` |
| **Bảng pivot có trọng số** — `rd_article_taxonomy(confidence)` | Liên kết bài ↔ tag | Trọng số độ tin cậy cho mỗi tag |
| **Priority ordering** (`sortByDesc` + `take(K)`) | Retrieval & fallback ranking | Chọn Top-K theo điểm |
| **Accumulator / running sum** | Cộng dồn interest score, tính tổng vector | Học tăng dần |

Về mặt thuật toán, không dùng heap thật sự cho Top-K — Laravel `sortByDesc` là **comparison sort O(n log n)** rồi `take(K)`. Với n ≤ 500 ứng viên, chi phí này không đáng kể.

---

## 3. Thuật toán 1 — Hybrid Linear Scoring (điểm bài viết)

Đây là **trái tim** của việc tính độ ưu tiên bài viết, nằm ở `RetrievalScoringService::score()`. Bản chất là một **mô hình tuyến tính có trọng số (weighted linear combination)** cộng thêm các quy tắc override cứng.

### 3.1 Override cứng (short-circuit)

```php
if ($article->force_exclude) return -999;   // luôn loại
if ($article->force_include) return 999;    // luôn giữ
// nếu bài chứa taxonomy nằm trong ignoredTaxonomyIds → return -999
```

Đây là **sentinel values**: bài có điểm ≤ -900 sẽ bị lọc bỏ ở tầng retrieval (`filter(score > -900)`).

### 3.2 Công thức điểm chính

Gọi bài viết là \(a\), người dùng là \(u\). Điểm tổng:

\[
\text{score}(a,u) = S_{\text{tax}} + S_{\text{emb}} + S_{\text{diff}} + S_{\text{type}} + S_{\text{hands}} + S_{\text{src}} + S_{\text{ctx}} - P_{\text{neg}}
\]

**(a) Thành phần taxonomy (tag)** — quan trọng nhất, kết hợp sở thích tường minh + hành vi ngầm:

\[
S_{\text{tax}} = \sum_{t \in \text{tags}(a)} \big( \underbrace{w^{fav}_{t}}_{\text{favorite topic}} \cdot 0.6 + \underbrace{w^{int}_{t}}_{\text{interest score}} \cdot 0.4 \big) \cdot \underbrace{c_t}_{\text{confidence}}
\]

Trong code:

```php
foreach ($article->taxonomyNodes as $node) {
    $pathScore = $favoriteTopics[$node->path] ?? 0;   // sở thích tường minh (theo path)
    $interest  = $interestScores[$node->id]  ?? 0;    // học từ hành vi (theo id)
    $confidence = (float) ($node->pivot->confidence ?? 1);
    $score += ($pathScore * 0.6 + $interest * 0.4) * $confidence;
}
```

- `favoriteTopics`: hash map `path → điểm` do user cấu hình (mặc định React=10, TS=9, Go=8, K8s=7, LLM=6).
- `interestScores`: hash map `taxonomy_id → điểm` **học tự động** từ tương tác (xem §4).
- `confidence` ∈ (0,1]: độ tin cậy khi gán tag cho bài (0.9 nếu từ rule tag-mapping, 0.75 nếu từ LLM path).

**(b) Tương đồng ngữ nghĩa (embedding)** — cosine similarity nhân hệ số 20:

\[
S_{\text{emb}} = 20 \cdot \cos(\vec{u}, \vec{a}) \quad (\text{chỉ khi có cả 2 vector})
\]

**(c) Các boost/penalty theo quy tắc** (thưởng-phạt tuyến tính):

| Thành phần | Điều kiện | Điểm |
|---|---|---|
| \(S_{\text{diff}}\) | difficulty khớp `preferred_difficulty` | +5 |
| \(S_{\text{type}}\) | article_type ∈ `preferred_article_types` | +8 |
| \(S_{\text{hands}}\) | theo `hands_on_score` ∈ [0,1] | +10 × hands_on_score |
| \(S_{\text{src}}\) | source ∈ `preferred_sources` | +3 |
| \(S_{\text{ctx}}\) | context boost (ngắn hạn) | + contextBoost |
| \(P_{\text{neg}}\) | mỗi `negative_signal` | −2 mỗi tín hiệu |

Kết quả `round(score, 4)`.

### 3.3 Vì sao dùng mô hình tuyến tính?

- **Diễn giải được (interpretable)**: mỗi điểm cộng/trừ tương ứng một lý do rõ ràng → dễ debug, dễ giải thích cho user ("+8 vì đúng loại bài bạn thích").
- **Rẻ**: O(số tag mỗi bài + số chiều vector) cho mỗi bài, không cần training.
- **Kết hợp lai (hybrid)**: gộp cả *content-based* (favorite topics, difficulty, type) + *collaborative/behavioral* (interest score) + *semantic* (embedding) trong một biểu thức.

### 3.4 Độ phức tạp

Cho mỗi bài: \(O(T + D)\) với \(T\) = số tag của bài (nhỏ), \(D\) = số chiều embedding (1536). Toàn bộ retrieval: \(O(n \cdot (T + D))\), n ≤ 500.

---

## 4. Thuật toán 2 — Interest Score theo tag (học từ hành vi)

Đây là cách hệ thống **tự học độ ưu tiên của từng tag** theo thời gian, gồm 2 phần: **cộng dồn có trọng số** và **suy giảm mũ (exponential decay)**.

### 4.1 Cộng dồn trọng số hành vi (`RecordInteractionHandler`)

Khi user tương tác với một bài, mỗi tag của bài được cộng một lượng `delta`:

\[
\text{interest}_{t} \mathrel{+}= w_{\text{event}} \cdot c_t
\]

```php
$weight = $this->interestScoreService->weightForEvent($event, $metadata);
foreach ($article->taxonomyNodes as $node) {
    $confidence = (float) ($node->pivot->confidence ?? 1);
    $delta = $weight * $confidence;
    $score->score = ($score->score ?? 0) + $delta;   // cộng dồn (accumulator)
}
```

Bảng trọng số sự kiện (`config/reading-digest.php` → `interaction_weights`):

| Sự kiện | Trọng số |
|---|---|
| impression (chỉ hiển thị) | −0.5 |
| opened | +1 |
| finished_reading | +3 |
| saved | +5 |
| liked | +5 |
| shared | +8 |
| disliked | −5 |
| dismissed | −5 |
| rated_positive | +4 |
| rated_negative | −6 |

→ Đây là **online learning đơn giản**: mỗi tín hiệu tích cực đẩy tag lên, tín hiệu tiêu cực kéo xuống, có nhân với `confidence` để tag gán chắc chắn ảnh hưởng mạnh hơn.

### 4.2 Suy giảm mũ (Exponential Decay) — `DecayInterestScoresHandler`

Chạy định kỳ (weekly job). Mỗi điểm bị nhân với hệ số suy giảm:

\[
\text{interest}_{t} \leftarrow \text{interest}_{t} \cdot \gamma, \qquad \gamma = 0.98
\]

```php
public function applyDecay(float $score, ?float $factor = null): float {
    $factor ??= (float) config('reading-digest.interest_decay_factor', 0.98);
    return round($score * $factor, 4);
}
```

Ý nghĩa DSA/thuật toán:

- Đây là **Exponentially Weighted Moving Average (EWMA)** phiên bản rời rạc: sở thích cũ mất dần trọng số theo hàm mũ \(\gamma^k\) sau \(k\) chu kỳ.
- Giúp **ưu tiên sở thích gần đây** (recency bias) mà vẫn giữ lại "trí nhớ dài hạn" mờ dần → chống việc sở thích cũ đóng băng ranking.
- Duyệt bằng **chunking** (`chunk(100)`) để xử lý theo lô, tránh nạp toàn bộ bảng vào RAM: O(số bản ghi) thời gian, O(100) bộ nhớ.

---

## 5. Thuật toán 3 — Cosine Similarity (tương đồng ngữ nghĩa)

Dùng để đo bài viết "giống gu" người dùng đến đâu, trên không gian vector 1536 chiều.

\[
\cos(\vec{a}, \vec{b}) = \frac{\vec{a}\cdot\vec{b}}{\lVert \vec{a}\rVert \, \lVert \vec{b}\rVert} = \frac{\sum a_i b_i}{\sqrt{\sum a_i^2}\,\sqrt{\sum b_i^2}}
\]

```php
private function cosineSimilarity(array $a, array $b): float {
    $dot = 0.0; $normA = 0.0; $normB = 0.0;
    $len = min(count($a), count($b));
    for ($i = 0; $i < $len; $i++) {
        $dot   += $a[$i] * $b[$i];
        $normA += $a[$i] ** 2;
        $normB += $b[$i] ** 2;
    }
    if ($normA === 0.0 || $normB === 0.0) return 0.0;
    return $dot / (sqrt($normA) * sqrt($normB));
}
```

- **DSA**: dense vector, tính dot product + 2 norm trong một vòng lặp → **O(D)**, D=1536.
- Kết quả ∈ [−1, 1], nhân 20 rồi cộng vào hybrid score (§3.2b).
- Trên PostgreSQL có extension **pgvector**, vector cũng được lưu ở cột `vector(1536)` để có thể đánh index **IVFFlat** (ANN) nếu cần scale — hiện chấm điểm vẫn làm ở tầng ứng dụng.

---

## 6. Thuật toán 4 — User Taste Vector (Weighted Centroid / Rocchio)

`RebuildUserEmbeddingHandler` xây "vector sở thích" của user = **trung bình có trọng số** các vector bài đã tương tác:

\[
\vec{u} = \frac{1}{\sum_j s_j} \sum_{j} s_j \, \vec{a_j}, \qquad s_j = \begin{cases} +1 & \text{saved/finished/liked} \\ -1 & \text{dismissed/disliked} \end{cases}
\]

```php
$sum = array_fill(0, $dimensions, 0.0);   // accumulator vector
$count = 0;
foreach ($articles as $article) {
    $vector = $article->embedding->vector;
    $weight = in_array($article->id, $negativeArticleIds->all(), true) ? -1 : 1;
    for ($i = 0; $i < min($dimensions, count($vector)); $i++) {
        $sum[$i] += $vector[$i] * $weight;
    }
    $count += $weight;
}
$average = array_map(fn ($v) => $v / $count, $sum);
```

- Đây là biến thể của **thuật toán Rocchio** (relevance feedback): kéo vector user về phía các bài tích cực, đẩy khỏi các bài tiêu cực.
- **DSA**: cộng dồn vector (element-wise accumulator) → O(M·D) với M = số bài tương tác, D = số chiều.
- Chạy nightly (02:00) để cập nhật gu người dùng, sau đó dùng lại trong cosine similarity (§5).

---

## 7. Thuật toán 5 — Hard Filters (lọc theo tập)

Trước khi chấm điểm, `RetrievalService` loại bỏ ứng viên bằng **các phép tập hợp** (đẩy xuống DB query cho hiệu quả):

```php
->whereIn('source_id', $sourceIds)              // chỉ nguồn của subject
->where('force_exclude', false)
->where(published_at >= cutoff OR null)          // theo max_age_days
->whereIn('language', $preferredLanguages)       // lọc ngôn ngữ
->whereNotIn('id', $recentlySentArticleIds)      // không gửi lại (7 ngày)
->whereNotIn('id', $dismissedArticleIds)         // đã dismiss/dislike
->orderByDesc('published_at')->limit(500)
```

- `whereIn`/`whereNotIn` = **set membership test**, DB thực thi bằng index/hash.
- `recentlySentArticleIds`, `dismissedArticleIds` là các **set** dựng sẵn để tránh trùng lặp.
- Sau đó `filter(score > -900)` loại tiếp các bài dính ignored taxonomy / force_exclude.

---

## 8. Thuật toán 6 — Top-K Selection & Two-Stage Ranking

### 8.1 Tầng 1 — Retrieval Top-K

```php
$scored = $articles->map(score...)
    ->filter(fn ($i) => $i['score'] > -900)
    ->sortByDesc('score')     // O(n log n)
    ->take($limit)            // K = 30 (retrieval_candidates)
    ->values()->all();
```

Chọn Top-K bằng **sort giảm dần rồi cắt**. (Với n nhỏ, không cần dùng heap/quickselect O(n) — trade-off đơn giản hoá code.)

### 8.2 Tầng 2 — LLM Re-ranking (`RankingService`)

Top-30 được đưa cho LLM để xếp hạng lại tinh hơn, trả về Top 5–10:

- Nếu **< 5 ứng viên** hoặc **LLM chưa cấu hình/ lỗi** → **fallback** dùng chính điểm hybrid (`sortByDesc('score')->take(limit)`).
- LLM trả JSON `{article_id, score (0–100), reason}`; kết quả được `normalizeRankings` lọc chỉ giữ id hợp lệ (set membership với `candidateIds`).

```php
private function retrievalFallback(array $candidates, int $limit, string $reason): array {
    return collect($candidates)->sortByDesc('score')->take($limit)->values()
        ->map(fn ($item, $i) => [
            'article_id' => $item['article']->id,
            'score' => $item['score'] ?: (100 - $i),   // giữ thứ tự nếu điểm = 0
            'reason' => $reason,
        ])->all();
}
```

Đây là mẫu **coarse-to-fine ranking**: tầng 1 rẻ, deterministic, lọc rộng; tầng 2 đắt hơn (LLM), tinh chỉnh trên tập nhỏ.

---

## 9. Bảng tổng hợp thuật toán / DSA

| # | Bài toán | Thuật toán | DSA chính | Độ phức tạp |
|---|---|---|---|---|
| 1 | Điểm ưu tiên bài viết | Weighted linear combination + rule boosts | Hash map, dense vector | O(T + D) / bài |
| 2 | Học sở thích tag | Online accumulation trọng số | Hash map + accumulator | O(T) / tương tác |
| 3 | Quên dần theo thời gian | Exponential decay (EWMA) | Chunked scan | O(N), O(100) RAM |
| 4 | Tương đồng ngữ nghĩa | Cosine similarity | Dense vector 1536D | O(D) |
| 5 | Vector gu người dùng | Weighted centroid (Rocchio) | Accumulator vector | O(M·D) |
| 6 | Loại ứng viên | Hard filters / set operations | Set, DB index | O(n) |
| 7 | Chọn Top-K | Sort desc + take | Priority ordering | O(n log n) |
| 8 | Xếp hạng tinh | Two-stage retrieve-then-rank + LLM | JSON + set validate | O(K) + call LLM |

---

## 10. Ví dụ tính điểm (worked example)

Bài viết X có 2 tag:

- `programming.frontend.react` — favorite=10, interest=6, confidence=0.9
- `programming.performance` — favorite=0, interest=2, confidence=0.75

Giả sử: cosine(user, article) = 0.4; difficulty khớp (`advanced`); article_type = `deep_dive` ∈ preferred; hands_on_score = 0.7; source thuộc preferred; 1 negative_signal.

```
S_tax  = (10*0.6 + 6*0.4)*0.9  +  (0*0.6 + 2*0.4)*0.75
       = (6 + 2.4)*0.9         +  (0.8)*0.75
       = 7.56                  +  0.6            = 8.16
S_emb  = 20 * 0.4                                = 8.0
S_diff = +5
S_type = +8
S_hands= 10 * 0.7                               = 7.0
S_src  = +3
P_neg  = 1 * 2                                  = -2

score  = 8.16 + 8.0 + 5 + 8 + 7.0 + 3 - 2       = 37.16
```

→ Bài X có điểm 37.16, dùng để sort Top-K, rồi (nếu bật) LLM re-rank tinh lại.

---

## 11. Hằng số cấu hình (điều chỉnh trọng số)

| Hằng số | Giá trị | Vị trí |
|---|---|---|
| Trọng số favorite topic trong \(S_{tax}\) | 0.6 | `RetrievalScoringService` |
| Trọng số interest score trong \(S_{tax}\) | 0.4 | `RetrievalScoringService` |
| Hệ số cosine | ×20 | `RetrievalScoringService` |
| Boost difficulty / type / source | +5 / +8 / +3 | `RetrievalScoringService` |
| Hệ số hands-on | ×10 | `RetrievalScoringService` |
| Penalty mỗi negative signal | −2 | `RetrievalScoringService` |
| `retrieval_candidates` (K) | 30 | `config/reading-digest.php` |
| `articles_per_subject` (N) | 5 | `config/reading-digest.php` |
| `interest_decay_factor` (γ) | 0.98 | `config/reading-digest.php` |
| `interaction_weights` | xem §4.1 | `config/reading-digest.php` |
| `embedding_dimensions` (D) | 1536 | `config/reading-digest.php` |

Muốn đổi "khẩu vị" ranking: chỉnh các trọng số trong `RetrievalScoringService` và `interaction_weights`. Hầu hết hằng số scoring hiện **hard-code trong service** (không qua config) — nếu cần A/B test thì nên đưa ra config trước.
