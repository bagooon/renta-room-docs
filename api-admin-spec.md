# 管理者 API 仕様書

## 目的

管理画面から利用する API の契約を明確にするための仕様書です。  
予約管理、部屋管理、予約設定、SwitchBot 手動操作を `APP` から安全に扱えるようにすることを目的とします。

関連資料:

- [api-auth-spec.md](V:\Miyachi-Renta-Room\docs\api-auth-spec.md)
- [api-reservations-spec.md](V:\Miyachi-Renta-Room\docs\api-reservations-spec.md)
- [app-api-rebuild-development-guide.md](V:\Miyachi-Renta-Room\docs\app-api-rebuild-development-guide.md)
- [mysql-schema-design.md](V:\Miyachi-Renta-Room\docs\mysql-schema-design.md)

## 前提

- すべての `/admin/*` API は管理者 JWT 必須
- 利用者 JWT ではアクセス不可
- 監査対象になりうる変更は、必要に応じて `reservation_status_logs` などへ記録する

## 共通エラー形式

```json
{
  "success": false,
  "error": {
    "code": "FORBIDDEN",
    "message": "Admin access is required."
  }
}
```

### 主なエラーコード

- `UNAUTHORIZED`
- `FORBIDDEN`
- `VALIDATION_ERROR`
- `RESERVATION_NOT_FOUND`
- `ROOM_NOT_FOUND`
- `ROOM_INACTIVE`
- `SETTINGS_NOT_FOUND`
- `SWITCHBOT_COMMAND_FAILED`

## 1. `GET /admin/reservations`

### 目的

管理者向け予約一覧を取得する。

### 認証

- 必要

### クエリ例

- `page=1`
- `per_page=20`
- `from=2026-05-01T00:00:00+09:00`
- `to=2026-05-31T23:59:59+09:00`
- `payment_status=paid`
- `is_canceled=0`
- `search_field=email`
- `search_value=taro@example.com`

### レスポンスに最低限含める項目

- `id`
- `client`
- `room`
- `requested_start_at`
- `requested_end_at`
- `occupied_start_at`
- `occupied_end_at`
- `total_amount`
- `payment_status`
- `is_canceled`

### 備考

- `old` の管理画面検索を引き継げるよう、ID、名前、メール、電話番号検索に対応する

## 2. `GET /admin/reservations/{id}`

### 目的

管理者向け予約詳細を取得する。

### 認証

- 必要

### レスポンスに最低限含める項目

- `id`
- `client`
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
- `is_canceled`
- `created_at`

## 3. `PATCH /admin/reservations/{id}`

### 目的

管理者が予約状態を更新する。

### 認証

- 必要

### 更新対象例

- `payment_status`
- `is_canceled`

### リクエスト例

```json
{
  "payment_status": "paid",
  "is_canceled": false
}
```

### ルール

- 状態遷移ルールは API 側で検証する
- 取消済み予約に対する不正更新を防ぐ
- 更新前後の差分をログ化できる形にする
- 初期実装では `reservation_status_logs` に `admin_reservation_updated` を残す

## 4. `POST /admin/reservations/{id}/cancel`

### 目的

管理者権限で予約をキャンセルする。

### 認証

- 必要

### 備考

- 利用者の自己キャンセルより強い権限として扱う
- `paid` の予約は、Stripe 返金成功後にのみキャンセルを確定する
- `paid` の予約は通常更新で `is_canceled = true` や `payment_status` の直接変更を受け付けない
- `pending` の予約をキャンセルした場合は、`payment_status` も `canceled` に更新する
- Stripe ダッシュボードから全額返金された場合は、Webhook 側でも `refunded + is_canceled = 1` へ同期する
- `reservation_status_logs` に `admin_canceled` を残す

## 5. `GET /admin/reservation-blocks`

### 目的

Web 予約を受け付けないブロック枠一覧を取得する。

### 認証

- 必要

### クエリ例

- `page=1`
- `per_page=20`
- `room_id=room_001`
- `q=電話予約`
- `from=2026-06-01T00:00:00+09:00`
- `to=2026-06-30T23:59:59+09:00`

### 備考

- 管理者が手動で押さえるメンテナンス枠、貸切枠、電話予約枠などを想定する
- 並び順は `start_at DESC`, `end_at DESC`, `created_at DESC`
- レスポンスはページネーション対応とし、`meta.page`, `meta.per_page`, `meta.total`, `meta.total_pages` を含める

## 6. `POST /admin/reservation-blocks`

### 目的

Web 予約を停止するブロック枠を新規作成する。

### 認証

- 必要

### リクエスト例

```json
{
  "room_id": "room_001",
  "start_at": "2026-06-20T13:00:00+09:00",
  "end_at": "2026-06-20T15:00:00+09:00",
  "reason": "メンテナンス",
  "note": "ピアノ調律のため",
  "client_id": "cli_001",
  "is_active": true
}
```

### ルール

- `room_id`, `start_at`, `end_at` は必須
- 日跨ぎ不可
- `client_id` は任意
- `reason` は DB 管理の候補から選択できるが、自由文も許可する
- 通常予約と有効な予約停止枠の両方に対して重複作成を禁止する
- `pending` の仮予約も重複対象として扱う
- 作成後は `GET /rooms/{id}/availability` と `POST /reservations` の両方でブロック対象になる

## 7. `PATCH /admin/reservation-blocks/{id}`

### 目的

既存のブロック枠を更新する。

### 認証

- 必要

### ルール

- 更新時も、通常予約および他の有効な予約停止枠との重複を禁止する
- 自分自身の `id` は重複判定から除外する

## 8. `DELETE /admin/reservation-blocks/{id}`

### 目的

ブロック枠を削除する。

### 認証

- 必要

## 9. `GET /admin/rooms`

### 目的

管理画面用の部屋一覧を取得する。

### 認証

- 必要

### レスポンスに最低限含める項目

- `id`
- `title`
- `image_url`
- `description`
- `price_per_hour`
- `max_people`
- `is_active`
- `slot_minutes_override`
- `buffer_before_minutes_override`
- `buffer_after_minutes_override`
- `switchbot_device_id`
- `switchbot_device_mac`

## 10. `GET /admin/rooms/{id}`

### 目的

管理画面用の部屋詳細を取得する。

### 認証

- 必要

### 備考

- 初期実装では部屋に紐づく `equipment` も同時に返す

## 11. `POST /admin/rooms`

### 目的

部屋を新規作成する。

### 認証

- 必要

### リクエスト例

```json
{
  "title": "Room A",
  "image_url": "/images/room-a.jpg",
  "description": "説明文",
  "price_per_hour": 2500,
  "max_people": 4,
  "is_active": true,
  "slot_minutes_override": null,
  "buffer_before_minutes_override": null,
  "buffer_after_minutes_override": null,
  "switchbot_device_id": "switchbot-device-id",
  "switchbot_device_mac": "AA:BB:CC:DD:EE:FF"
}
```

## 12. `PATCH /admin/rooms/{id}`

### 目的

部屋情報を更新する。

### 認証

- 必要

### 更新対象例

- `title`
- `image_url`
- `description`
- `price_per_hour`
- `max_people`
- `is_active`
- `slot_minutes_override`
- `buffer_before_minutes_override`
- `buffer_after_minutes_override`
- `switchbot_device_id`
- `switchbot_device_mac`

### ルール

- override が `NULL` の場合は `app_settings` の既定値を使う
- override を設定した場合は、その部屋だけ予約粒度やバッファを変えられる
- 初期実装では設備割当更新は別 API に分離し、部屋基本情報のみ更新対象とする

## 13. `GET /admin/settings`

### 目的

システム共通設定を取得する。

### 認証

- 必要

### レスポンスに最低限含める項目

- `app_base_url`
- `reservation_slot_minutes`
- `reservation_open_time`
- `reservation_close_time`
- `reservation_buffer_before_minutes`
- `reservation_buffer_after_minutes`
- `min_reservation_minutes`
- `max_reservation_minutes`
- `payment_pending_expires_minutes`
- `booking_window_days`
- `cancel_deadline_days`
- `switchbot_entrance_device_id`
- `entrance_auto_lock_enabled`

### 備考

- `switchbot_token` と `switchbot_secret` を UI に出すかは別途判断
- `app_base_url` の初期値は `https://renta-room.com/` を想定する
- Xサーバ構成では、`public_html/.htaccess` によるルーティング後も公開 URL 基準でこの値を扱う

## 14. `PATCH /admin/settings`

### 目的

システム共通設定を更新する。

### 認証

- 必要

### 更新対象例

- `app_base_url`
- `switchbot_entrance_device_id`
- `entrance_auto_lock_enabled`

## 15. `GET /admin/equipments`

### 目的

管理画面用の設備マスタ一覧を取得する。

### 認証

- 必要

### レスポンスに最低限含める項目

- `id`
- `name`
- `slug`
- `sort_order`
- `is_active`

## 16. `GET /admin/equipments/{id}`

### 目的

管理画面用の設備詳細を取得する。

### 認証

- 必要

## 17. `POST /admin/equipments`

### 目的

設備マスタを新規作成する。

### 認証

- 必要

### リクエスト例

```json
{
  "name": "モニター",
  "slug": "monitor",
  "sort_order": 30,
  "is_active": true
}
```

## 18. `PATCH /admin/equipments/{id}`

### 目的

設備マスタを更新する。

### 認証

- 必要

## 19. `PATCH /admin/rooms/{id}/equipments`

### 目的

部屋に紐づく設備割当を更新する。

### 認証

- 必要

### リクエスト例

```json
{
  "equipment": [
    {
      "equipment_id": "eq_monitor",
      "quantity": 2,
      "note": "HDMI ケーブル付き"
    },
    {
      "equipment_id": "eq_whiteboard",
      "quantity": 1,
      "note": null
    }
  ]
}
```

### ルール

- 指定した配列で部屋設備を全置換する
- `equipment_id` は既存設備マスタに存在している必要がある
- `quantity` は 1 以上の整数とする

## 20. `PATCH /admin/reservation-config`

### 目的

予約粒度とバッファ設定を更新する。

### 認証

- 必要

### リクエスト例

```json
{
  "reservation_slot_minutes": 15,
  "reservation_open_time": 900,
  "reservation_close_time": 2300,
  "reservation_buffer_before_minutes": 0,
  "reservation_buffer_after_minutes": 15,
  "min_reservation_minutes": 60,
  "max_reservation_minutes": 840,
  "payment_pending_expires_minutes": 30,
  "booking_window_days": 90,
  "cancel_deadline_days": 2
}
```

### ルール

- 既存予約の `slot_minutes` と `buffer_*` は変更しない
- 新規予約から設定を適用する
- `min_reservation_minutes` は `reservation_slot_minutes` の倍数であること
- `max_reservation_minutes` がある場合も `reservation_slot_minutes` の倍数であること
- `reservation_open_time` は `HHMM` 整数形式であること
  - 例: `900`, `930`
- `reservation_close_time` は `HHMM` 整数形式であり、`reservation_open_time` より後であること
  - 例: `2300`, `2330`
- `payment_pending_expires_minutes` は正の整数であること
- `booking_window_days` は 1 日以上の整数であること
- `cancel_deadline_days` は 0 以上の整数であること

## 21. `POST /admin/doors/command`

### 目的

管理者が SwitchBot デバイスへ手動でコマンドを送る。

### 認証

- 必要

### リクエスト例

```json
{
  "target_type": "room",
  "room_id": "room_001",
  "command": "unlock"
}
```

## 22. `GET /admin/switchbot/devices`

### 目的

SwitchBot に登録されているデバイス一覧を取得し、各部屋の `switchbot_device_id` や入口の `switchbot_entrance_device_id` を設定しやすくする。

### 認証

- 必要

### レスポンスに最低限含める項目

- `device_list`
- `infrared_remote_list`

### `device_list` の項目

- `device_id`
- `device_name`
- `device_type`
- `hub_device_id`
- `enable_cloud_service`

### レスポンス例

```json
{
  "success": true,
  "data": {
    "device_list": [
      {
        "device_id": "F12ABCDE3456",
        "device_name": "Room A Lock",
        "device_type": "Smart Lock",
        "hub_device_id": "C12ABCDE3456",
        "enable_cloud_service": true
      }
    ],
    "infrared_remote_list": []
  }
}
```

### 備考

- まずこの一覧を取得し、必要な `device_id` を `PATCH /admin/rooms/{id}` または `PATCH /admin/settings` で保存する
- `GET /v1.1/devices` では `deviceMac` を取得できない前提で扱う
- 初期実装では SwitchBot API の生データをそのまま返さず、管理画面で使いやすい項目に整形して返す

## 23. `GET /admin/rooms/{id}/door/state`

### 目的

管理画面で指定ルームの現在の鍵状態を取得する。  
APP 側はロングポーリングで呼び、Webhook 反映待ちの最新状態を確認できるようにする。

### 認証

- 必要

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

- `room_id`
- `device_id`
- `device_mac`
- `lock_state`
- `lock_state_raw`
- `door_state_raw`
- `is_locked`
- `is_unlocked`
- `is_jammed`
- `battery`
- `source`
- `changed_at`
- `polling.changed`

### 備考

- 管理者は利用時間外でも取得可能
- センシティブな状態のため、レスポンスは `Cache-Control: private, no-store` を付与する
- 鍵状態の即時更新は SwitchBot Webhook を正本とする
- `POST /admin/doors/command` 成功時は、Webhook 到着前でも `switchbot_lock_states` を即時更新する
- 初回ロングポーリング時に保存済み状態が無い場合のみ、対象 `deviceId` の Status API を1回参照して初期状態を補完する
- ロングポーリングは保存済み状態を監視し、Webhook による更新が入ったときだけ変化を返す
- `/admin/doors` 画面では、各部屋ごとに 1 本、エントランス用に 1 本のロングポーリングを張る

## 24. `GET /admin/reservation-calendar`

### 目的

月単位で、各日・各ルームの予約状況を 15 分枠単位で俯瞰できるデータを取得する。

### 認証

- 必要

### クエリ例

- `month=2026-06`

### レスポンスに最低限含める項目

- `month`
- `slot_minutes`
- `open_time`
- `close_time`
- `rooms`
- `days`

### `days[].rooms[].slots[]` の状態値

- `empty`
- `reservation`
- `reservation_block`

### 備考

- `reservation` は Web 予約、`reservation_block` は予約停止枠を表す
- 管理画面では色分けして一目で判別できる前提とする
- 表示対象月の設定値に従って、1 日分の時間スロットを生成する

## 25. 時間外の解錠アラート

### 目的

予約時間および予約ブロック時間の外で鍵が解錠状態になった場合、管理者へアラートメールを送る。

### ルール

- `lock_state = unlocked` へ遷移したタイミングで判定する
- 同じ解錠状態が継続している間は重複送信しない
- 宛先は環境変数 `ADMIN_ALERT_EMAILS` にカンマ区切りで設定する
- 送信判定と送信実行は Web API ではなく `cron` バッチで行う
- 予約中 (`paid` かつ `is_canceled = 0` で `occupied_*` 内) は送信しない
- 予約ブロック中 (`reservation_blocks` の有効時間内) は送信しない

## 26. エントランス自動施錠

### 目的

エントランスの閉め忘れを防ぐため、条件を満たしたときにエントランスの鍵を自動施錠する。

### ルール

- 有効 / 無効は `app_settings.entrance_auto_lock_enabled` で管理する
- 管理画面 `/admin/doors` のエントランス欄からトグルで切り替える
- すべての部屋の鍵状態が取得できた回のみ判定する
- いずれかの部屋で `battery = 0` の場合は、自動施錠しない
- すべての部屋の鍵状態が `locked` または `latch_bolt_locked` になってから 15 分経過したら、エントランスへ `lock` コマンドを送る
- すでにエントランスが施錠状態であれば、追加コマンドは送らない
- 実行判定は `cron` バッチ `php bin/monitor-switchbot-locks.php` 内で行う

### 成功レスポンス例

```json
{
  "success": true,
  "data": {
    "target_type": "room",
    "room_id": "room_001",
    "command": "unlock",
    "result": "accepted"
  }
}
```

## 実装ルール

### 1. 管理 API と利用者 API を混ぜない

- 管理操作は必ず `/admin/*` に分離する
- 利用者 API の強拡張で管理権限を表現しない

### 2. 予約の最終判定は API 側

- 管理画面で見えている空き枠も参考値にすぎない
- 管理者が予約を追加する機能を将来入れるなら、同じ重複判定ロジックを使う

### 3. 既存予約を壊さない

- 設定変更後も、既存予約の `occupied_*` は再計算しない
- 新規予約のみ新設定を適用する

### 4. 監査を意識する

- 支払状態変更
- キャンセル
- ドア操作

これらは後で追跡できるようにする。

補足:

- 予約状態変更は `reservation_status_logs`
- ドア操作は `door_command_logs`

## 未確定事項

- 管理者による予約新規作成 API を用意するか
- 管理者による手動返金 API を持つか
- 管理者権限を複数ロールに分けるか
- SwitchBot 秘密情報を UI 編集可能にするか


