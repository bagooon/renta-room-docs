# Stripe 決済 API 仕様書

## 目的

Stripe を利用した決済処理の API 契約を明確にするための仕様書です。  
仮予約、PaymentIntent 作成、決済成功反映、失敗時の予約解放、Webhook 処理の役割分担を定義し、予約 API と整合する形で実装できることを目的とします。

関連資料:

- [api-reservations-spec.md](V:\Miyachi-Renta-Room\docs\api-reservations-spec.md)
- [api-auth-spec.md](V:\Miyachi-Renta-Room\docs\api-auth-spec.md)
- [api-admin-spec.md](V:\Miyachi-Renta-Room\docs\api-admin-spec.md)
- [app-api-rebuild-development-guide.md](V:\Miyachi-Renta-Room\docs\app-api-rebuild-development-guide.md)
- [mysql-schema-design.md](V:\Miyachi-Renta-Room\docs\mysql-schema-design.md)

## 前提

- フロントは Stripe.js を利用する
- カード情報は `APP` / `API` で直接保持しない
- 予約は決済前に仮予約として作成する
- 決済の最終確定は Webhook を正とする
- ブラウザ戻りは UX 用、業務確定は Webhook 用と分離する

## 決済モデル

### 基本フロー

1. 利用者が `POST /reservations` で仮予約を作成する
2. `payment_status = pending` の予約が作られる
3. 利用者が `POST /reservations/{id}/payment-intent` を呼ぶ
4. `API` が Stripe PaymentIntent を作成する
5. `APP` が Stripe.js で決済確定を行う
6. Stripe Webhook を `API` が受信する
7. Webhook 検証後、予約の `payment_status` を更新する

### 決済状態

- `pending`
  - 仮予約作成済み、未確定
- `paid`
  - 決済成功確定
- `failed`
  - 決済失敗確定
- `canceled`
  - 予約キャンセル済み
- `refunded`
  - 返金済み
- `expired`
  - 仮予約の有効期限切れ

## 共通エラー形式

```json
{
  "success": false,
  "error": {
    "code": "PAYMENT_INTENT_INVALID",
    "message": "The payment could not be initialized."
  }
}
```

### 主なエラーコード

- `UNAUTHORIZED`
- `FORBIDDEN`
- `RESERVATION_NOT_FOUND`
- `PAYMENT_REQUIRED`
- `PAYMENT_ALREADY_COMPLETED`
- `PAYMENT_INTENT_INVALID`
- `PAYMENT_STATUS_INVALID`
- `WEBHOOK_SIGNATURE_INVALID`

## 仮予約の有効期限

### 推奨方針

- `pending` の仮予約には有効期限を持たせる
- 初期案は `作成から 30分`
- 有効期限を過ぎても未決済なら `expired` 扱いにする

### 理由

- 決済途中離脱で枠を長時間占有しないため
- 30分刻み + バッファ込みの運用では、未解放の仮予約が実運用を圧迫しやすいため

### 推奨追加カラム

`reservations` または関連テーブルに以下を持てるとよい。

- `payment_intent_id`
- `payment_intent_client_secret`
- `payment_started_at`
- `payment_expires_at`
- `payment_confirmed_at`
- `payment_failed_at`
- `receipt_url`

### 現在の運用方針

- 仮予約作成時に `payment_expires_at` を設定する
- `POST /reservations/{id}/payment-intent` 実行時は `payment_started_at` を更新する
- 失効判定は `payment_expires_at` と `payment_started_at + 一定猶予時間` の遅い方を基準にする
- 初期実装の猶予時間は 15 分とする

## 1. `POST /reservations/{id}/payment-intent`

### 目的

仮予約に紐づく Stripe PaymentIntent を作成または再取得する。

### 認証

- 必要

### 前提

- 本人の予約のみ対象
- `payment_status = pending`
- `is_canceled = 0`
- `payment_expires_at` を超過していない

### サーバー側の処理

1. 対象予約を取得する
2. 本人所有を確認する
3. `pending` であることを確認する
4. 期限切れなら `expired` に更新してエラーを返す
5. 未作成なら PaymentIntent を作成する
6. 既存 PaymentIntent が利用可能なら再利用する
7. `client_secret` を返す

### Stripe に渡す主な情報

- `amount`
- `currency`
- `description`
- `metadata.reservation_id`
- `metadata.client_id`
- `metadata.room_id`

### Stripe に渡す補足

- `description` は `レンタルルーム利用料` とする

### 成功レスポンス例

```json
{
  "success": true,
  "data": {
    "reservation_id": "res_001",
    "payment_status": "pending",
    "payment_intent_id": "pi_123",
    "client_secret": "pi_123_secret_abc",
    "expires_at": "2026-05-04T10:30:00+09:00"
  }
}
```

## 2. `GET /reservations/{id}/payment-status`

### 目的

決済処理後に予約の支払状態を確認する。

### 認証

- 必要

### レスポンス例

```json
{
  "success": true,
  "data": {
    "reservation_id": "res_001",
    "payment_status": "paid",
    "payment_confirmed_at": "2026-05-04T10:05:12+09:00",
    "receipt_url": "https://pay.stripe.com/receipts/..."
  }
}
```

### 備考

- `APP` 側は Stripe.js 完了後にこの API をポーリングまたは再読込してもよい
- 最終判定は Stripe Webhook が更新した DB 状態を参照する

## 3. `POST /stripe/webhooks`

### 目的

Stripe からの Webhook を受信し、決済状態を業務上の確定情報として反映する。

### 認証

- Stripe 署名検証必須

### 対象イベント例

- `payment_intent.succeeded`
- `payment_intent.payment_failed`
- `payment_intent.canceled`
- `charge.refunded`

### 現時点で登録する推奨イベント

この案件で最初に Stripe ダッシュボードへ登録する webhook イベントは次の 4 つとする。

- `payment_intent.succeeded`
- `payment_intent.payment_failed`
- `payment_intent.canceled`
- `charge.refunded`

理由:

- `payment_intent.succeeded`
  - 予約を `paid` に確定するため
- `payment_intent.payment_failed`
  - 予約を `failed` に更新するため
- `payment_intent.canceled`
  - 期限切れまたは明示キャンセル後の失効系状態を扱うため
- `charge.refunded`
  - 予約を `refunded` に更新するため

補足:

- Stripe Checkout 前提ではないため、`checkout.session.completed` は初期必須にしない
- チャージバック運用まで入れる場合は将来 `charge.dispute.created` を追加検討する

### エンドポイント設定

本番・開発ともに webhook の受信パスは次を使う。

- パス: `POST /stripe/webhooks`
- 公開 URL: `https://renta-room.com/api/stripe/webhooks`

Xサーバ上では、`public_html/api/.htaccess` によりこのパスを `public_html/api/index.php` へ流す。

### 環境変数

署名検証に使う webhook secret は `.env` に保存する。

```env
STRIPE_WEBHOOK_SECRET=whsec_xxx
```

補足:

- 本番では Stripe ダッシュボードに表示される webhook secret を使う
- ローカルで Stripe CLI を使う場合は、`stripe listen` が表示する `whsec_...` を使う

### テスト方法

ローカルまたは検証環境では Stripe CLI で次のように確認できる。

```bash
stripe listen --forward-to http://localhost:8080/stripe/webhooks
stripe trigger payment_intent.succeeded
```

Xサーバ上で PHP 8.3 の Composer を使う場合は、必要に応じて以下のコマンドで依存や autoload を更新する。

```bash
php8.3 ~/bin/composer dump-autoload
```

### サーバー側の処理

1. Webhook 署名を検証する
2. `payment_intent_id` を取得する
3. `metadata.reservation_id` で対象予約を特定する
4. 冪等に処理する
5. 予約状態を更新する

### 状態反映ルール

- `payment_intent.succeeded`
  - `payment_status = paid`
  - `payment_confirmed_at` を更新
  - `latest_charge.receipt_url` を取得できた場合は `receipt_url` を保存する
  - ただし予約がすでに確保不能になっていた場合は、自動返金して `refunded + is_canceled = 1` に更新する
- `payment_intent.payment_failed`
  - `payment_status = failed`
  - `payment_failed_at` を更新
- `payment_intent.canceled`
  - `payment_status = failed` または `expired`
- `charge.refunded`
  - `payment_status = refunded`
  - 全額返金が確認できた場合のみ、未キャンセルの予約を自動的に `is_canceled = 1` へ更新する
  - 利用日の 2 日前以降であっても、Webhook による返金反映は受け付ける
  - 部分返金の時点では予約をキャンセルしない
  - 複数回の部分返金後に累計で全額返金になった時点で、キャンセル処理とキャンセルメール送信を 1 回だけ行う

### 返金時の運用ルール

- 利用者による `pending` 仮予約キャンセルでは Stripe 返金処理は行わない
- 利用者による `paid` 予約キャンセルでは、返金成功後にだけキャンセル確定とする
- 決済成功直後でも、重複や期限切れにより予約を確定できない場合は自動返金する
- Stripe ダッシュボードから手動で全額返金した場合も、`charge.refunded` Webhook により予約をキャンセル状態へ同期する
- すでに `refunded` かつ `is_canceled = 1` の予約に同じ返金通知が再送されても、キャンセルメールは再送しない
- キャンセルメールには、返金済みの場合のみ「返金完了」の案内を含める

### レスポンス

- Stripe 要件に従い `2xx` を返す

## 仮予約失効処理

### 推奨実装

- バッチまたはリクエスト時チェックで `pending` かつ `payment_expires_at < now` を検出する
- 対象は `payment_status = expired` に更新する
- `expired` 更新時は予約枠を即座に再開放する

### cron 併用方針

- 初期実装でも `リクエスト時チェック + cron` の併用を推奨する
- リクエスト時チェックは業務上の即時整合を担保する
- cron は無アクセス時の掃除、運用画面整合、将来の通知拡張の土台とする
- Xサーバでは PHP CLI で `public_html/api/bin/expire-reservations.php` を定期実行する

### 反映タイミング案

- `POST /reservations/{id}/payment-intent` 呼び出し時
- `GET /rooms/{id}/availability` の内部計算前
- 定期ジョブ

### ポイント

- expired になった予約は重複判定から除外する
- ただし運用調査のためレコード自体は残す
- 初期実装では API の参照時チェックでも十分に成立する

## APP への通知方針

### 初期実装の推奨

- `payment_intent` レスポンスの `expires_at` を使ってフロントで残り時間を表示する
- 決済画面では定期的に `GET /reservations/{id}/payment-status` を再取得する
- API から `payment_status = expired` が返ったら、決済UIを閉じて「期限切れ・再予約が必要」を案内する
- 画面離脱やブラウザ停止があっても、再訪時に予約詳細再取得で `expired` を判定する
- 支払完了後は `receipt_url` があれば予約詳細から領収書を開けるようにする

### 将来拡張

- WebSocket
- SSE
- メール通知

これらは将来追加できるが、初期実装では必須にしない。業務上の正は常に API の `payment_status` とする。

## 冪等性

### Webhook

- 同じ Stripe イベントが複数回来ても安全に処理できるようにする
- `payment_intent_id` とイベント ID を利用して重複処理を防ぐ

### PaymentIntent 作成

- 同じ予約に対して二重作成しない
- 有効な `payment_intent_id` が既にある場合は再利用を優先する

## 実装ルール

### 1. 料金の正は予約テーブル

- 決済金額は予約作成時に確定した `total_amount` を使う
- フロントが送った金額を信用しない

### 2. Webhook を正にする

- ブラウザ側の成功表示だけで `paid` にしない
- 必ず Webhook またはサーバー照会結果で最終確定する

### 3. 失敗時も履歴は残す

- `failed` や `expired` でも予約レコードを削除しない
- 運用調査と UX 改善に使えるようにする

### 4. expired の扱い

- `expired` は `canceled` と意味を分ける
- `expired` は未完了の自動失効
- `canceled` は人がキャンセルした状態

## APP 側の実装前提

- `POST /reservations` 実行後に予約IDを保持する
- `POST /reservations/{id}/payment-intent` で `client_secret` を取得する
- Stripe.js で決済確定を行う
- 完了後は `GET /reservations/{id}/payment-status` または予約詳細再取得で状態確認する

## APP 側の最小実装フロー

### 1. 仮予約作成

フロントは、部屋と利用時間を使って先に仮予約を作成する。

利用API:

- `POST /reservations`

リクエスト例:

```json
{
  "room_id": "room_001",
  "requested_start_at": "2026-06-04T10:00:00+09:00",
  "requested_end_at": "2026-06-04T11:00:00+09:00"
}
```

このレスポンスで返る `reservation_id` を以後の決済処理で使う。

### 2. PaymentIntent 作成

フロントは、ログイン済み利用者の Bearer トークン付きで `payment-intent` API を呼ぶ。

利用API:

- `POST /reservations/{id}/payment-intent`

レスポンスで最低限使う値:

- `reservation_id`
- `payment_status`
- `payment_intent_id`
- `client_secret`
- `expires_at`
- `publishable_key`

### 3. Stripe.js 初期化

フロントは、返却された `client_secret` を使って Stripe.js の Elements を初期化する。

実装イメージ:

```javascript
const stripe = Stripe('pk_live_or_test_xxx')
const elements = stripe.elements({ clientSecret })
```

実装メモ:

- `POST /reservations/{id}/payment-intent` のレスポンスに `publishable_key` を含め、`APP` はその値を `loadStripe(...)` に渡す
- Stripe 公開鍵の正本は `API/.env` の `STRIPE_PUBLIC_KEY` とする
- `APP` 側で公開鍵を保持せず、必ず API レスポンスの `publishable_key` を使う

補足:

- `publishable_key` は `STRIPE_SECRET_KEY` と同じ Stripe アカウント・同じ test/live モードの組み合わせである必要がある
- 秘密鍵は `APP` やブラウザに置かない

### 4. 決済確定

フロントは Payment Element などの入力 UI を表示し、利用者操作後に `stripe.confirmPayment(...)` を呼ぶ。

補足:

- フロントは金額を決めない
- 金額、対象予約、期限は API が管理する

### 5. 決済後の状態確認

フロントは、Stripe.js 完了後に予約の支払状態を確認する。

利用API:

- `GET /reservations/{id}/payment-status`

または:

- `GET /reservations/{id}`

確認したい主な値:

- `payment_status`
- `payment_expires_at`
- `expired_at`
- `payment_confirmed_at`

### 6. 画面遷移の基本方針

推奨遷移:

1. `/reservation/confirm`
2. `POST /reservations`
3. `/reservation/payment/{reservationId}`
4. `POST /reservations/{id}/payment-intent`
5. Stripe.js で決済
6. `/reservation/payment/{reservationId}/processing`
7. `GET /reservations/{id}/payment-status`
8. 成功なら `/reservation/reservations/{reservationId}`
9. 最終的に `/mypage/reservations/{id}`

### 7. 現時点の API 実装との関係

現時点では Stripe SDK を用いた PaymentIntent 作成と Webhook 反映まで実装済みであり、次の運用を前提にする。

- `payment-intent` は実際の `payment_intent_id` と `client_secret` を返す
- `publishable_key` も同時に返し、`APP` はその値で Stripe.js を初期化する
- Webhook が `paid`, `failed`, `refunded` を最終確定する
- `receipt_url` は Webhook 反映時に保存し、未保存の旧データは予約詳細取得時にも補完できる

## 未確定事項

- 仮予約の有効期限を `30分` にするか `15分` にするか
- `failed` と `expired` の再決済導線をどう分けるか
- `payment_events` のような専用ログテーブルを追加するか






