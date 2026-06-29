# 予約 API 仕様書

## 目的

`APP` と `API` の間で扱う予約関連 API の契約を明確にするための仕様書です。  
特に、可変の予約粒度、入れ替えバッファ、重複判定、仮予約から決済までの流れをぶれずに実装できることを目的とします。

関連資料:

- [app-api-rebuild-development-guide.md](V:\Miyachi-Renta-Room\docs\app-api-rebuild-development-guide.md)
- [mysql-schema-design.md](V:\Miyachi-Renta-Room\docs\mysql-schema-design.md)

## 前提

- バックエンドは `API`
- DB は MySQL
- 予約の最終判定は `API` 側で行う
- 利用者が見る予約時間と、重複判定に使う占有時間は分ける
- 初期値は `slot_minutes = 15`
- 初期値は `buffer_before_minutes = 0`
- 初期値は `buffer_after_minutes = 15`

## 共通ルール

### 日時形式

- リクエスト / レスポンスともに ISO 8601 文字列を使う
- 例: `2026-05-04T10:00:00+09:00`
- `APP` と `API` でタイムゾーンの扱いを統一する

### 認証

- 利用者向け予約 API は JWT 認証必須
- 管理者 API は別トークン / 別権限で扱う

補足:

- 部屋一覧、部屋詳細、空き枠確認は未ログインで利用できる
- 仮予約の確定は `POST /reservations` 実行時にだけ認証を要求する
- `APP` は未ログイン状態で選択した `room_id`, `requested_start_at`, `requested_end_at` を保持し、認証完了後に `POST /reservations` を実行する

### 予約粒度

- `requested_start_at` と `requested_end_at` は `slot_minutes` の倍数でなければならない
- `open_time` と `close_time` は `HHMM` 整数形式で扱う
  - 例: `900`, `930`, `2300`, `2330`
- 予約時間は `min_reservation_minutes` 以上でなければならない
- `max_reservation_minutes` が設定されている場合、それを超えてはならない
- `requested_end_at` は `requested_start_at` より後でなければならない

### 占有時間

予約作成時、`API` は以下を計算して保存する。

- `occupied_start_at = requested_start_at - buffer_before_minutes`
- `occupied_end_at = requested_end_at + buffer_after_minutes`

重複判定、空き枠判定、管理画面上の運用警告は `occupied_*` 基準で行う。

## エラー形式

```json
{
  "success": false,
  "error": {
    "code": "RESERVATION_CONFLICT",
    "message": "The requested time is no longer available."
  }
}
```

### 主なエラーコード

- `UNAUTHORIZED`
- `FORBIDDEN`
- `VALIDATION_ERROR`
- `ROOM_NOT_FOUND`
- `ROOM_INACTIVE`
- `INVALID_SLOT`
- `INVALID_RESERVATION_RANGE`
- `RESERVATION_CONFLICT`
- `PAYMENT_REQUIRED`
- `RESERVATION_NOT_FOUND`
- `RESERVATION_NOT_CANCELABLE`
- `RESERVATION_EXPIRED`

## 1. `GET /reservation-config`

### 目的

フロントが予約 UI を描画するための予約設定を取得する。

### 認証

- 不要

### レスポンス例

```json
{
  "success": true,
  "data": {
    "slot_minutes": 15,
    "open_time": 900,
    "close_time": 2300,
    "buffer_before_minutes": 0,
    "buffer_after_minutes": 15,
    "min_reservation_minutes": 60,
    "max_reservation_minutes": 840,
    "payment_pending_expires_minutes": 30,
    "booking_window_days": 90,
    "cancel_deadline_days": 2
  }
}
```

### 備考

- 将来、部屋ごとの上書き設定がある場合は `room_id` 指定版の追加を検討する

## 2. `GET /rooms`

### 目的

予約可能な部屋一覧を取得する。

### 認証

- 不要

### レスポンスに最低限含める項目

- `id`
- `title`
- `image_url`
- `price_per_hour`
- `max_people`
- `is_active`
- `equipment`

### 設備情報の形式

`equipment` は部屋に紐づく設備配列です。

```json
[
  {
    "id": "eq_monitor",
    "name": "モニター",
    "slug": "monitor",
    "quantity": 2,
    "note": "HDMI ケーブル付き"
  }
]
```

## 3. `GET /equipments`

### 目的

設備マスタ一覧を取得する。

### 認証

- 不要

### レスポンスに最低限含める項目

- `id`
- `name`
- `slug`
- `sort_order`
- `is_active`

### レスポンス例

```json
{
  "success": true,
  "data": [
    {
      "id": "eq_monitor",
      "name": "モニター",
      "slug": "monitor",
      "sort_order": 30,
      "is_active": true
    }
  ]
}
```

## 4. `GET /rooms/{id}`

### 目的

部屋詳細と、その部屋に適用される予約設定を取得する。

### 認証

- 不要

### レスポンス例

```json
{
  "success": true,
  "data": {
    "room": {
      "id": "room_001",
      "title": "Room A",
      "image_url": "/images/room-a.jpg",
      "price_per_hour": 2500,
      "max_people": 4,
      "is_active": true,
      "equipment": [
        {
          "id": "eq_monitor",
          "name": "モニター",
          "slug": "monitor",
          "quantity": 2,
          "note": "HDMI ケーブル付き"
        },
        {
          "id": "eq_whiteboard",
          "name": "ホワイトボード",
          "slug": "whiteboard",
          "quantity": 1,
          "note": null
        }
      ]
    },
    "reservation_config": {
      "slot_minutes": 15,
      "open_time": 900,
      "close_time": 2300,
      "buffer_before_minutes": 0,
      "buffer_after_minutes": 15,
      "min_reservation_minutes": 60,
      "max_reservation_minutes": 840
    }
  }
}
```

## 5. `GET /rooms/{id}/availability`

### 目的

指定日の空き枠を取得する。

### 認証

- 不要

### クエリ

- `date`
  - 例: `2026-05-04`

### レスポンス例

```json
{
  "success": true,
  "data": {
    "room_id": "room_001",
    "date": "2026-05-04",
    "slot_minutes": 15,
    "open_time": 900,
    "close_time": 2300,
    "available_ranges": [
      {
        "start_at": "2026-05-04T09:00:00+09:00",
        "end_at": "2026-05-04T11:30:00+09:00"
      },
      {
        "start_at": "2026-05-04T13:00:00+09:00",
        "end_at": "2026-05-04T23:00:00+09:00"
      }
    ],
    "blocked_ranges": [
      {
        "start_at": "2026-05-04T11:30:00+09:00",
        "end_at": "2026-05-04T13:00:00+09:00",
        "reason": "occupied"
      }
    ]
  }
}
```

### 備考

- `blocked_ranges` は `occupied_*` を元に組み立てる
- 管理画面で登録した `reservation_blocks` も `blocked_ranges` に含める
- `open_time` / `close_time` の営業時間外は候補枠に含めない
- `APP` はこの結果を使って予約候補を描画するが、最終確定は `POST /reservations` で再判定する

## 6. `POST /reservations`

### 目的

仮予約を作成する。  
この段階では決済前のため、`payment_status = pending` を基本とする。

### 認証

- 必要

### リクエスト例

```json
{
  "room_id": "room_001",
  "requested_start_at": "2026-05-04T10:00:00+09:00",
  "requested_end_at": "2026-05-04T11:00:00+09:00"
}
```

### サーバー側の処理

1. JWT から `client_id` を特定する
2. `room_id` の存在と有効状態を確認する
3. 対象部屋に適用される `slot_minutes` と `buffer_*` を解決する
4. `open_time` / `close_time` の営業時間内かを検証する
5. 入力時刻が予約粒度に合っているか検証する
6. `min_reservation_minutes` / `max_reservation_minutes` を検証する
7. `booking_window_days` の範囲内かを検証する
8. `occupied_start_at` / `occupied_end_at` を算出する
9. 既存予約との重複を確認する
10. 問題なければ `reservations` に `pending` で保存する

### APP 側の前提導線

- 日時選択までは未ログインで進める
- `POST /reservations` の送信直前にログインまたは新規登録を求める
- 認証後は保持していた `room_id`, `requested_start_at`, `requested_end_at` をそのまま送る
- 未ログイン中は予約枠は確保されないため、`POST /reservations` 時点で重複が発生した場合は `409 RESERVATION_CONFLICT` を返す

### 重複判定条件

```sql
SELECT id
FROM reservations
WHERE room_id = :room_id
  AND is_canceled = 0
  AND occupied_start_at < :new_occupied_end_at
  AND occupied_end_at > :new_occupied_start_at
LIMIT 1;
```

```sql
SELECT id
FROM reservation_blocks
WHERE room_id = :room_id
  AND is_active = 1
  AND start_at < :new_occupied_end_at
  AND end_at > :new_occupied_start_at
LIMIT 1;
```

### 成功レスポンス例

```json
{
  "success": true,
  "data": {
    "reservation_id": "res_001",
    "room_id": "room_001",
    "requested_start_at": "2026-05-04T10:00:00+09:00",
    "requested_end_at": "2026-05-04T11:00:00+09:00",
    "occupied_start_at": "2026-05-04T10:00:00+09:00",
    "occupied_end_at": "2026-05-04T11:30:00+09:00",
    "slot_minutes": 15,
    "buffer_before_minutes": 0,
    "buffer_after_minutes": 15,
    "total_amount": 2500,
    "payment_status": "pending",
    "payment_expires_at": "2026-05-04T09:30:00+09:00"
  }
}
```

### 備考

- 予約作成、決済開始、利用者キャンセル、期限切れ自動失効、Stripe webhook による支払状態更新は `reservation_status_logs` に記録する
- `payment_intent.succeeded` の反映時に予約完了メールを送る
- キャンセル成功時に予約キャンセルメールを送る

### 失敗条件

- 部屋が存在しない
- 部屋が停止中
- 予約粒度に合っていない
- 営業時間外
- 最小 / 最大予約時間を満たさない
- 予約可能日数の範囲を超えている
- 重複予約が存在する

## 7. `GET /reservations`

### 目的

ログイン中利用者の予約一覧を取得する。

### 認証

- 必要

### レスポンスに最低限含める項目

- `id`
- `room_id`
- `requested_start_at`
- `requested_end_at`
- `occupied_start_at`
- `occupied_end_at`
- `total_amount`
- `payment_status`
- `payment_expires_at`
- `expired_at`
- `is_canceled`

## 8. `GET /reservations/{id}`

### 目的

ログイン中利用者の予約詳細を取得する。

### 認証

- 必要

### 制約

- 本人の予約のみ参照可能

### レスポンスに最低限含める項目

- `id`
- `room`
- `requested_start_at`
- `requested_end_at`
- `occupied_start_at`
- `occupied_end_at`
- `slot_minutes`
- `buffer_before_minutes`
- `buffer_after_minutes`
- `total_amount`
- `payment_status`
- `payment_expires_at`
- `expired_at`
- `is_canceled`

## 9. `POST /reservations/{id}/payment-intent`

### 目的

Stripe 決済用の `client_secret` を発行する。

### 認証

- 必要

### 前提

- 本人の予約のみ対象
- `payment_status = pending`
- `is_canceled = 0`

### 成功レスポンス例

```json
{
  "success": true,
  "data": {
    "reservation_id": "res_001",
    "payment_status": "pending",
    "payment_intent_id": "pi_xxx",
    "client_secret": "pi_xxx_secret_xxx",
    "expires_at": "2026-05-04T09:30:00+09:00"
  }
}
```

### 期限切れ時の扱い

- payment_expires_at を過ぎた pending 予約は xpired に更新する
- xpired になった予約は重複判定から除外し、予約枠を再開放する
- APP は payment_expires_at を使ってカウントダウン表示できる
- APP は GET /reservations/{id}/payment-status または GET /reservations/{id} の再取得で xpired を検知する
- 初期実装では push 通知を必須にせず、画面滞在中のポーリングまたは画面再入場時の再取得で十分とする
- APP の再案内一覧では、xpired をそのまま決済再開させず、空き状況の再確認導線として扱う
- APP の再案内一覧に表示する xpired は xpired_at から24時間以内、かつ equested_start_at より前のものに限る
- APP の再案内一覧では、equested_start_at を過ぎた pending / xpired を表示しない
## 10. `POST /reservations/{id}/cancel`

### 目的

予約をキャンセルする。

### 認証

- 必要

### 制約

- 本人の予約のみ対象
- 決済状態や開始時刻との関係でキャンセル不可条件を設ける
- キャンセル可否の最終判定は API 側で行う
- `cancel_deadline_days = 2` の場合、利用日の 2 日前 00:00 以降は自己キャンセル不可

### キャンセル時の決済連動

- `pending` の仮予約キャンセルでは Stripe 返金処理を行わない
- `paid` の予約キャンセルでは、Stripe 返金成功後にのみ `is_canceled = 1` とする
- 返金失敗時はキャンセル確定しない
- 利用者キャンセルではキャンセル期限を適用するが、Stripe Webhook による全額返金反映は期限に関係なく受け付ける
- Stripe 側で部分返金だけが行われた場合は自動キャンセルしない
- 複数回の部分返金後に全額返金へ到達した場合は、その時点で自動キャンセルする
- 返金済みキャンセルの通知メールには、返金完了の案内を含める

### 成功レスポンス例

```json
{
  "success": true,
  "data": {
    "reservation_id": "res_001",
    "is_canceled": true,
    "canceled_at": "2026-05-04T09:00:00+09:00"
  }
}
```

## 11. `GET /reservations/{id}/door/state`

### 目的

利用者向け予約詳細画面で、現在の鍵の開閉状態を取得する。  
APP 側はこの API をロングポーリングで呼び、SwitchBot Webhook で反映された最新状態をできるだけ早く画面へ反映する。

### 認証

- 必要

### 利用条件

- ログイン中利用者本人の予約であること
- `payment_status = paid` であること
- `is_canceled = 0` であること
- 現在時刻が `occupied_start_at <= now <= occupied_end_at` を満たすこと

### クエリ

- `since`
  - 任意
  - 前回取得した `changed_at` を ISO8601 で渡す
- `timeout`
  - 任意
  - ロングポーリング最大待機秒数
  - 初期値 `20`
  - 上限 `25`

### レスポンスに最低限含める項目

- `reservation_id`
- `room_id`
- `device_id`
- `device_mac`
- `lock_state`
- `lock_state_raw`
- `door_state_raw`
- `is_locked`
- `is_unlocked`
- `is_jammed`
- `changed_at`
- `source`
- `is_operable`
- `polling.changed`

### 成功レスポンス例

```json
{
  "success": true,
  "data": {
    "reservation_id": "res_001",
    "room_id": "room_001",
    "device_id": "F12ABCDE3456",
    "device_mac": "AA:BB:CC:DD:EE:FF",
    "lock_state_raw": "LOCKED",
    "lock_state": "locked",
    "door_state_raw": null,
    "is_locked": true,
    "is_unlocked": false,
    "is_jammed": false,
    "battery": 91,
    "source": "webhook",
    "changed_at": "2026-06-10T12:34:56+09:00",
    "time_of_sample_ms": 1781062496000,
    "is_operable": true,
    "polling": {
      "since": "2026-06-10T12:34:10+09:00",
      "timeout": 20,
      "changed": true
    }
  }
}
```

### 備考

- センシティブな状態のため、レスポンスは `Cache-Control: private, no-store` を付与する
- 鍵状態の即時更新は SwitchBot Webhook を正本とする
- `POST /reservations/{id}/door/lock` / `unlock` 成功時は、Webhook 到着前でも `switchbot_lock_states` を即時更新する
- 初回ロングポーリング時に保存済み状態が無い場合のみ、対象 `deviceId` の Status API を1回参照して初期状態を補完する
- ロングポーリングは保存済み状態を監視し、Webhook による更新が入ったときだけ変化を返す
- 即時性の正本は SwitchBot Webhook で受信した状態とする

## 12. `POST /reservations/{id}/door/unlock`

### 目的

利用者本人が予約中の部屋を解錠する。

### 認証

- 必要

### 制約

- 本人の予約のみ対象
- `payment_status = paid`
- `is_canceled = 0`
- 現在時刻が `occupied_start_at` から `occupied_end_at` の範囲内

### 動作

- 部屋の解錠成功時は、エントランス用 SwitchBot デバイスが設定されていれば、エントランスの鍵も続けて解錠する
- エントランス解錠はベストエフォートで行い、部屋の解錠成功を優先してレスポンスを返す

### 成功レスポンス例

```json
{
  "success": true,
  "data": {
    "reservation_id": "res_001",
    "room_id": "room_001",
    "command": "unlock",
    "result": "accepted",
    "lock_state": "unlocked",
    "is_unlocked": true,
    "changed_at": "2026-06-10T12:34:56+09:00",
    "time_of_sample_ms": 1781062496000,
    "state_cursor": "ms:1781062496000",
    "source": "command_api",
    "entrance": {
      "requested": true,
      "success": true,
      "lock_state": "unlocked",
      "is_unlocked": true,
      "changed_at": "2026-06-10T12:34:57+09:00",
      "time_of_sample_ms": 1781062497000,
      "state_cursor": "ms:1781062497000",
      "source": "command_api"
    }
  }
}
```

## 13. `POST /reservations/{id}/door/lock`

### 目的

利用者本人が予約中の部屋を施錠する。

### 認証

- 必要

### 制約

- `POST /reservations/{id}/door/unlock` と同条件

### 備考

- いずれのドア操作も `door_command_logs` に記録する
- `lock` 実行時はエントランス連動施錠は行わない

## 実装ルール

### 1. フロントの空き表示は参考値

- `APP` が表示していたとしても、最終的な重複判定は `POST /reservations` 実行時に再確認する

### 2. 過去予約を設定変更で壊さない

- `slot_minutes`
- `open_time`
- `close_time`
- `buffer_before_minutes`
- `buffer_after_minutes`
- `payment_pending_expires_minutes`
- `booking_window_days`
- `cancel_deadline_days`

これらは予約作成時点の値を `reservations` に保存する。

### 3. 料金計算

- 料金は `requested_*` ベースで行う
- `occupied_*` に含まれるバッファ時間は原則課金対象外とする
- 将来、清掃料金を別建てにする場合は別項目で拡張する

### 4. バッファの扱い

- 初期案は `after` のみ
- 将来、予約前準備が必要になった場合は `buffer_before_minutes` を使う
- 実装は `before` と `after` の両方に対応しておく

### 5. 仮予約期限切れの枠解放

- `pending` かつ `payment_expires_at < now` の予約は `expired` として扱う
- `POST /reservations` の重複判定前に期限切れを反映する
- `GET /reservations`, `GET /reservations/{id}`, `GET /reservations/{id}/payment-status` でも期限切れを反映する
- 定期ジョブを導入した場合は同じ条件でバッチ失効を行う
- 将来的に WebSocket や SSE を入れる場合も、業務上の正は API の `expired` 状態とする
- 自動失効時も `reservation_status_logs` に `payment_expired` を残す

## 未確定事項

- 管理者が強制的に詰めて予約を入れられるか
- 部屋ごとに異なる予約粒度を許可するか
- 祝日や特別営業時間による枠制御を入れるか



