# MySQL スキーマ設計案

## 目的

`old` ディレクトリの Nuxt3 実装で使われているデータを、Supabase 依存なしで MySQL に移行できるようにするための設計案です。  
現行コードで実際に参照・更新している項目を優先し、まずは「現機能を維持できる最小構成」を定義します。

対象機能:

- 部屋一覧表示
- 予約作成
- 予約一覧 / 予約詳細
- 決済状態更新
- キャンセル
- 管理者ログイン
- 部屋設定編集
- SwitchBot 連携

## 前提

- DB は MySQL 8.0 系を想定
- 文字コードは `utf8mb4`
- 現在の API 実装は `cli_xxx`, `adm_xxx`, `room_xxx`, `res_xxx` のようなプレフィックス付き ID を使っているため、初期案では `VARCHAR(50)` を採用する
- 日時は `DATETIME` を基本にし、アプリ側でタイムゾーンを統一管理
- 現状の Supabase Auth 依存は廃止し、利用者情報も MySQL で持てる前提にする
- 予約粒度は現時点では `15分` を想定するが、将来変更できる設計にする
- 予約と予約の間に入れ替え時間を入れられるよう、バッファ時間を考慮する

## 推奨テーブル一覧

### 1. `clients`

利用者情報です。  
現在のコードでは Supabase Auth の `user.id`, `email`, `raw_user_meta_data.name`, `raw_user_meta_data.phone` を使っているため、その代替として定義します。

主な用途:

- 予約の `client_id`
- 利用者本人確認
- 予約一覧表示時の氏名 / メール / 電話番号検索

推奨カラム:

- `id`
- `name`
- `email`
- `phone`
- `password_hash`
- `last_login_at`
- `created_at`
- `updated_at`

補足:

- 現行コードは電話番号をパスワード代わりにしていますが、移行時は `password_hash` を導入する前提にするのが安全です

### 2. `admin_users`

管理者ログイン用です。  
Supabase の `users.admin` テーブル相当を整理し直したテーブルです。

推奨カラム:

- `id`
- `login_id`
- `password_hash`
- `name`
- `is_active`
- `last_login_at`
- `created_at`
- `updated_at`

補足:

- 現行コードの `identification` は意味的にはログインIDなので、MySQL 側では `login_id` に寄せると分かりやすいです

### 3. `rooms`

貸出対象の部屋です。

主な用途:

- 利用者向け部屋一覧
- 予約の `room_id`
- 管理画面での部屋編集
- SwitchBot の部屋別ロック制御

推奨カラム:

- `id`
- `title`
- `image_url`
- `description`
- `sort_order`
- `is_active`
- `price_per_hour`
- `max_people`
- `slot_minutes_override`
- `buffer_before_minutes_override`
- `buffer_after_minutes_override`
- `switchbot_device_id`
- `created_at`
- `updated_at`

補足:

- 現行の `img` は `image_url` に、`perHour` は `price_per_hour` に寄せると可読性が上がります
- `url` は現状あまり使われていないため、必要なら `description` または `detail_url` に整理
- 予約粒度やバッファを部屋ごとに変えない運用なら、override 系カラムは `NULL` のままにして `app_settings` の既定値を使います

### 4. `reservations`

予約の本体です。

主な用途:

- 予約作成
- 空き時間判定
- 予約一覧 / 詳細
- 決済成功 / 失敗
- キャンセル
- 解錠可能時間の判定

推奨カラム:

- `id`
- `client_id`
- `room_id`
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
- `canceled_at`
- `payment_intent_id`
- `payment_intent_client_secret`
- `payment_started_at`
- `payment_expires_at`
- `payment_confirmed_at`
- `payment_failed_at`
- `receipt_url`
- `expired_at`
- `created_at`
- `updated_at`

補足:

- `payed` は `payment_status` に統合するのがおすすめです
- 値は `pending`, `paid`, `failed`, `canceled`, `refunded`, `expired` を想定
- 現行互換を重視するなら `is_paid` の bool でもよいですが、後で拡張しづらくなります
- `requested_*` は利用者に見せる予約時間です
- `occupied_*` は重複判定に使う実占有時間です
- 予約作成時点の `slot_minutes` と `buffer_*` を保存しておくと、後から設定を変えても既存予約の扱いが変わりません
- `pending` の仮予約には有効期限を持たせ、期限切れ時は `expired` として扱います
- `payment_started_at` を持たせると、決済開始後だけ失効猶予を延長する運用に対応しやすくなります
- `receipt_url` を持たせると、Stripe の領収書URLを予約詳細から再利用できます
- `failed` と `expired` はどちらも空き枠計算上は有効予約に含めません

### 5. `equipment`

設備マスタです。  
部屋に紐づく設備の種類を共通管理します。

主な用途:

- ウェブサイト上の部屋設備一覧表
- APP の部屋詳細表示
- 管理画面での設備マスタ管理

推奨カラム:

- `id`
- `name`
- `slug`
- `sort_order`
- `is_active`
- `created_at`
- `updated_at`

補足:

- `slug` は一覧表の列キーや CSS クラス、フロント側の識別子として使いやすいです
- 例: `table-chair`, `whiteboard`, `monitor`, `speaker`, `projector`

### 6. `room_equipments`

部屋と設備の中間テーブルです。  
1 部屋に複数設備、1 設備が複数部屋に紐づく前提で管理します。

主な用途:

- 「どの部屋に何があるか」の一覧表
- 設備数量の保持
- 設備ごとの補足メモ保持

推奨カラム:

- `id`
- `room_id`
- `equipment_id`
- `quantity`
- `note`
- `created_at`
- `updated_at`

補足:

- `quantity` を持たせることで `モニター 2 台` や `椅子 8 脚` のような表現に対応しやすくなります
- `UNIQUE(room_id, equipment_id)` を張っておくと、同じ設備が同じ部屋に二重登録されるのを防げます

### 仮予約解除条件

仮予約は `payment_status = pending` の予約を指します。  
解除条件は次のとおりです。

- 決済期限切れ
  - `payment_expires_at < now`
  - 状態は `expired`
- 決済失敗
  - Stripe Webhook で失敗確定
  - 状態は `failed`
- 利用者キャンセル
  - 状態は `canceled`
- 管理者キャンセル
  - 状態は `canceled`

### 推奨状態遷移

- `pending -> paid`
  - 決済成功
- `pending -> failed`
  - 決済失敗
- `pending -> expired`
  - 決済期限切れ
- `pending -> canceled`
  - 人によるキャンセル
- `paid -> refunded`
  - 返金

### 7. `password_resets`

利用者向けパスワード再発行トークンの保存先です。

主な用途:

- `POST /auth/password/forgot`
- `POST /auth/password/reset`

推奨カラム:

- `id`
- `client_id`
- `token`
- `expires_at`
- `used_at`
- `created_at`

### 8. `app_settings`

システム全体設定です。  
現在の `settings.settings` テーブルを、単一行のアプリ設定として整理します。

主な用途:

- SwitchBot API Token / Secret の保持
- 入口ドアの SwitchBot デバイスID保持
- サイトのベース URL 保持

推奨カラム:

- `id`
- `switchbot_token`
- `switchbot_secret`
- `switchbot_entrance_device_id`
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
- `created_at`
- `updated_at`

補足:

- 実運用では `switchbot_token` と `switchbot_secret` は DB 保存より環境変数や Secrets 管理が望ましいです
- ただし現行画面には管理UIがあるため、まずは DB 項目として持てるようにしておく案です
- `app_base_url` の初期値は `https://renta-room.com/` を想定します
- 初期値は `reservation_slot_minutes = 15`、`reservation_open_time = 900`、`reservation_close_time = 2300`、`reservation_buffer_before_minutes = 0`、`reservation_buffer_after_minutes = 15` を推奨します
- あわせて `min_reservation_minutes = 60`、`max_reservation_minutes = 840` を初期値とします
- あわせて `payment_pending_expires_minutes = 30`、`booking_window_days = 90` を推奨します
- あわせて `cancel_deadline_days = 2` を推奨します

### 9. `reservation_blocks`

Web 予約を止めるための管理ブロック枠です。  
メンテナンス、電話予約、貸切、管理者手動押さえに加え、クライアントを紐づけた停止枠にも対応します。

主な用途:

- `GET /rooms/{id}/availability` の `blocked_ranges`
- `POST /reservations` の重複判定
- クライアントのマイページでの停止枠表示
- 鍵操作可能時間の判定

推奨カラム:

- `id`
- `room_id`
- `client_id`
- `start_at`
- `end_at`
- `reason`
- `note`
- `is_active`
- `created_by_admin_id`
- `created_at`
- `updated_at`

補足:

- `client_id` は任意で、電話予約や自社利用などを既存クライアントに紐づけられるようにします
- 重複判定では通常予約と同列に扱い、同時間帯に重ねて作成しない前提にします
- `pending` の仮予約とも競合させると、安全側で運用できます

### 10. `reservation_block_reason_options`

予約停止枠の理由候補マスタです。

主な用途:

- 管理画面の候補表示
- 運用上よく使う理由の共通化

推奨カラム:

- `id`
- `label`
- `sort_order`
- `is_active`
- `created_at`
- `updated_at`

補足:

- 実入力は自由文も許可しつつ、候補の並び順や有効 / 無効を DB 側で制御できるようにします

## 追加であると便利なテーブル

### 11. `reservation_status_logs`

予約の状態遷移監査です。

用途:

- 誰がいつ支払済みにしたか
- 誰がいつキャンセルしたか
- 運用トラブル調査

推奨カラム:

- `id`
- `reservation_id`
- `actor_type`
- `actor_id`
- `action`
- `before_payload`
- `after_payload`
- `created_at`

### 12. `door_command_logs`

SwitchBot への操作ログです。

用途:

- 解錠 / 施錠の監査
- API 失敗時の調査

推奨カラム:

- `id`
- `reservation_id`
- `room_id`
- `target_device_id`
- `command_name`
- `requested_by_type`
- `requested_by_id`
- `success`
- `response_code`
- `response_body`
- `created_at`

### 13. `switchbot_devices`

SwitchBot デバイス一覧のキャッシュです。

用途:

- 管理画面での部屋ロック / 入口ロック紐づけ候補表示
- デバイス一覧の再利用

推奨カラム:

- `device_id`
- `device_mac`
- `device_name`
- `device_type`
- `hub_device_id`
- `enable_cloud_service`
- `last_seen_at`
- `created_at`
- `updated_at`

補足:

- 実運用では `device_mac` が取得できないケースもあるため、主キーは `device_id` を正とします

### 14. `switchbot_lock_states`

SwitchBot ロックの最新状態キャッシュです。

用途:

- 利用者予約詳細での鍵状態表示
- 管理画面の鍵状態一覧
- ロングポーリングの参照元
- 時間外解錠アラート判定

推奨カラム:

- `id`
- `device_id`
- `device_mac`
- `device_type`
- `lock_state_raw`
- `lock_state_normalized`
- `door_state_raw`
- `battery`
- `source`
- `time_of_sample_ms`
- `changed_at`
- `raw_payload`
- `created_at`
- `updated_at`

補足:

- Webhook を主な更新起点とし、1 デバイスにつき最新 1 レコードを保持する想定です
- `lock_state_raw` は `LOCKED`, `UNLOCKED`, `JAMMED` などの生値を保持します
- `lock_state_normalized` はアプリ側で扱いやすい正規化済み値です

## 推奨 DDL 例

実際に流し込み用として管理する SQL は次を正本とする。

- `API/database/schema.sql`

```sql
CREATE TABLE clients (
  id VARCHAR(50) NOT NULL,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL,
  phone VARCHAR(20) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_clients_email (email),
  UNIQUE KEY uq_clients_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE admin_users (
  id VARCHAR(50) NOT NULL,
  login_id VARCHAR(255) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  name VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_users_login_id (login_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE rooms (
  id VARCHAR(50) NOT NULL,
  title VARCHAR(255) NOT NULL,
  image_url VARCHAR(1000) NOT NULL,
  description TEXT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  price_per_hour DECIMAL(10,2) NOT NULL,
  max_people INT NOT NULL,
  slot_minutes_override INT NULL,
  buffer_before_minutes_override INT NULL,
  buffer_after_minutes_override INT NULL,
  switchbot_device_id VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_rooms_active_sort (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE equipment (
  id VARCHAR(50) NOT NULL,
  name VARCHAR(255) NOT NULL,
  slug VARCHAR(255) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_equipment_slug (slug),
  KEY idx_equipment_active_sort (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE room_equipments (
  id BIGINT NOT NULL AUTO_INCREMENT,
  room_id VARCHAR(50) NOT NULL,
  equipment_id VARCHAR(50) NOT NULL,
  quantity INT NOT NULL DEFAULT 1,
  note VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_room_equipments_room FOREIGN KEY (room_id) REFERENCES rooms(id),
  CONSTRAINT fk_room_equipments_equipment FOREIGN KEY (equipment_id) REFERENCES equipment(id),
  UNIQUE KEY uq_room_equipments_room_equipment (room_id, equipment_id),
  KEY idx_room_equipments_equipment (equipment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE reservations (
  id VARCHAR(50) NOT NULL,
  client_id VARCHAR(50) NOT NULL,
  room_id VARCHAR(50) NOT NULL,
  requested_start_at DATETIME NOT NULL,
  requested_end_at DATETIME NOT NULL,
  occupied_start_at DATETIME NOT NULL,
  occupied_end_at DATETIME NOT NULL,
  slot_minutes INT NOT NULL,
  buffer_before_minutes INT NOT NULL DEFAULT 0,
  buffer_after_minutes INT NOT NULL DEFAULT 0,
  total_amount DECIMAL(10,2) NOT NULL,
  payment_status VARCHAR(20) NOT NULL DEFAULT 'pending',
  is_canceled TINYINT(1) NOT NULL DEFAULT 0,
  canceled_at DATETIME NULL,
  payment_intent_id VARCHAR(255) NULL,
  payment_intent_client_secret TEXT NULL,
  payment_started_at DATETIME NULL,
  payment_expires_at DATETIME NULL,
  payment_confirmed_at DATETIME NULL,
  payment_failed_at DATETIME NULL,
  receipt_url TEXT NULL,
  expired_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_reservations_client FOREIGN KEY (client_id) REFERENCES clients(id),
  CONSTRAINT fk_reservations_room FOREIGN KEY (room_id) REFERENCES rooms(id),
  KEY idx_reservations_room_occupied_time (room_id, occupied_start_at, occupied_end_at),
  KEY idx_reservations_room_requested_time (room_id, requested_start_at, requested_end_at),
  KEY idx_reservations_client_time (client_id, requested_start_at),
  KEY idx_reservations_payment_status (payment_status),
  KEY idx_reservations_payment_expires_at (payment_expires_at),
  KEY idx_reservations_canceled (is_canceled),
  UNIQUE KEY uq_reservations_payment_intent_id (payment_intent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE password_resets (
  id BIGINT NOT NULL AUTO_INCREMENT,
  client_id VARCHAR(50) NOT NULL,
  token VARCHAR(255) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_password_resets_client FOREIGN KEY (client_id) REFERENCES clients(id),
  UNIQUE KEY uq_password_resets_token (token),
  KEY idx_password_resets_client_expires (client_id, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE app_settings (
  id BIGINT NOT NULL AUTO_INCREMENT,
  switchbot_token VARCHAR(255) NULL,
  switchbot_secret VARCHAR(255) NULL,
  switchbot_entrance_device_id VARCHAR(255) NULL,
  app_base_url VARCHAR(1000) NULL,
  reservation_slot_minutes INT NOT NULL DEFAULT 15,
  reservation_open_time INT NOT NULL DEFAULT 900,
  reservation_close_time INT NOT NULL DEFAULT 2300,
  reservation_buffer_before_minutes INT NOT NULL DEFAULT 0,
  reservation_buffer_after_minutes INT NOT NULL DEFAULT 15,
  min_reservation_minutes INT NOT NULL DEFAULT 60,
  max_reservation_minutes INT NOT NULL DEFAULT 840,
  payment_pending_expires_minutes INT NOT NULL DEFAULT 30,
  booking_window_days INT NOT NULL DEFAULT 90,
  cancel_deadline_days INT NOT NULL DEFAULT 2,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE reservation_blocks (
  id BIGINT NOT NULL AUTO_INCREMENT,
  room_id VARCHAR(50) NOT NULL,
  client_id VARCHAR(50) NULL,
  start_at DATETIME NOT NULL,
  end_at DATETIME NOT NULL,
  reason VARCHAR(50) NOT NULL DEFAULT 'admin_block',
  note VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by_admin_id VARCHAR(50) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_reservation_blocks_room FOREIGN KEY (room_id) REFERENCES rooms(id),
  CONSTRAINT fk_reservation_blocks_client FOREIGN KEY (client_id) REFERENCES clients(id),
  CONSTRAINT fk_reservation_blocks_admin FOREIGN KEY (created_by_admin_id) REFERENCES admin_users(id),
  KEY idx_reservation_blocks_room_time (room_id, start_at, end_at),
  KEY idx_reservation_blocks_client_time (client_id, start_at, end_at),
  KEY idx_reservation_blocks_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE reservation_block_reason_options (
  id BIGINT NOT NULL AUTO_INCREMENT,
  label VARCHAR(255) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_reservation_block_reason_options_label (label),
  KEY idx_reservation_block_reason_options_active_sort (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE reservation_status_logs (
  id BIGINT NOT NULL AUTO_INCREMENT,
  reservation_id VARCHAR(50) NOT NULL,
  actor_type VARCHAR(20) NOT NULL,
  actor_id VARCHAR(50) NULL,
  action VARCHAR(50) NOT NULL,
  before_payload JSON NULL,
  after_payload JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_reservation_status_logs_reservation FOREIGN KEY (reservation_id) REFERENCES reservations(id),
  KEY idx_reservation_status_logs_reservation_created (reservation_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE door_command_logs (
  id BIGINT NOT NULL AUTO_INCREMENT,
  reservation_id VARCHAR(50) NULL,
  room_id VARCHAR(50) NULL,
  target_device_id VARCHAR(255) NOT NULL,
  command_name VARCHAR(50) NOT NULL,
  requested_by_type VARCHAR(20) NOT NULL,
  requested_by_id VARCHAR(50) NULL,
  success TINYINT(1) NOT NULL,
  response_code VARCHAR(50) NULL,
  response_body LONGTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_door_command_logs_reservation FOREIGN KEY (reservation_id) REFERENCES reservations(id),
  CONSTRAINT fk_door_command_logs_room FOREIGN KEY (room_id) REFERENCES rooms(id),
  KEY idx_door_command_logs_created (created_at),
  KEY idx_door_command_logs_reservation (reservation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE switchbot_devices (
  device_id VARCHAR(255) NOT NULL,
  device_mac VARCHAR(255) NULL,
  device_name VARCHAR(255) NOT NULL,
  device_type VARCHAR(255) NOT NULL,
  hub_device_id VARCHAR(255) NULL,
  enable_cloud_service TINYINT(1) NOT NULL DEFAULT 0,
  last_seen_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (device_id),
  UNIQUE KEY uq_switchbot_devices_device_mac (device_mac),
  KEY idx_switchbot_devices_type (device_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE switchbot_lock_states (
  id BIGINT NOT NULL AUTO_INCREMENT,
  device_id VARCHAR(255) NULL,
  device_mac VARCHAR(255) NULL,
  device_type VARCHAR(255) NULL,
  lock_state_raw VARCHAR(50) NULL,
  lock_state_normalized VARCHAR(50) NULL,
  door_state_raw VARCHAR(50) NULL,
  battery INT NULL,
  source VARCHAR(50) NOT NULL,
  time_of_sample_ms BIGINT NULL,
  changed_at DATETIME NOT NULL,
  raw_payload LONGTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_switchbot_lock_states_device_mac (device_mac),
  UNIQUE KEY uq_switchbot_lock_states_device_id (device_id),
  KEY idx_switchbot_lock_states_changed_at (changed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## 予約重複判定の考え方

現行コードはアプリ側で `blockedDays` / `blockedHoursInNumber` を計算していますが、MySQL 移行後は DB 問い合わせでも判定できるようにしておくと安全です。

基本条件:

- 同じ `room_id`
- `is_canceled = 0`
- `payment_status NOT IN ('failed', 'expired')`
- 既存予約の `occupied_start_at < 新規occupied_end_at`
- 既存予約の `occupied_end_at > 新規occupied_start_at`

### バッファ込み判定

利用者が選んだ時間そのものではなく、バッファを加味した占有時間で判定します。

例:

- 利用者予約: `10:00 - 11:00`
- `buffer_after_minutes = 15`
- 実占有: `10:00 - 11:30`

この場合、次の予約は `11:30` 以降でないと成立しません。

### 将来の粒度変更への備え

- `reservation_slot_minutes` を `15` から `30` や `60` に変更できるようにする
- API 側で `requested_start_at` と `requested_end_at` が `slot_minutes` の倍数かを検証する
- 料金計算と空き枠表示も `slot_minutes` に追従させる
- 既存予約は作成時点の `slot_minutes` と `buffer_*` を保持するため、設定変更後も意味が変わらない

判定例:

```sql
SELECT id
FROM reservations
WHERE room_id = :room_id
  AND is_canceled = 0
  AND payment_status NOT IN ('failed', 'expired')
  AND occupied_start_at < :new_occupied_end_at
  AND occupied_end_at > :new_occupied_start_at
LIMIT 1;
```

## 現行コードから見た移行マッピング

### `reservation.room` -> `rooms`

- `id` -> `id`
- `title` -> `title`
- `img` -> `image_url`
- `perHour` -> `price_per_hour`
- `maxPeople` -> `max_people`
- `bot_id` -> `switchbot_device_id`

### 新規追加: `equipment` / `room_equipments`

- 部屋に紐づく設備は `rooms` に列追加せず、中間テーブルで管理
- ウェブサイトの一覧表では
  - 行: `rooms`
  - 列: `equipment`
  - セル: `quantity` または有無
- `note` を使えば「HDMI ケーブル付き」などの補足も持てる

### `reservation.reservation` -> `reservations`

- `id` -> `id`
- `client_id` -> `client_id`
- `room_id` -> `room_id`
- `start` -> `requested_start_at`
- `end` -> `requested_end_at`
- `requested_start_at` と `requested_end_at` から `occupied_start_at` / `occupied_end_at` を生成
- `total` -> `total_amount`
- `canceled` -> `is_canceled`
- `payed` -> `payment_status`
- `client_secret` -> `payment_intent_client_secret`
- `receipt_url` -> `receipt_url`

### `settings.settings` -> `app_settings`

- `entrance_id` -> `switchbot_entrance_device_id`
- `switchbot_token` -> `switchbot_token`
- `switchbot_secret` -> `switchbot_secret`
- 予約粒度 / バッファ設定を追加
- 仮予約有効期限 / 予約可能日数設定を追加

### `users.admin` -> `admin_users`

- `identification` -> `login_id`
- `password` -> `password_hash`

## 実装上の改善提案

### 最低限

- 利用者を MySQL の `clients` に持つ
- 管理者を `admin_users` に統一
- 予約状態を `payment_status` で管理
- 部屋と入口の SwitchBot デバイスIDを明示的に分離
- 予約重複判定は `occupied_*` カラム基準に統一する
- 予約粒度とバッファは設定値で管理する
- `password_resets` を設けて再発行トークンを DB 管理する

### できれば

- Token / Secret は DB ではなく Secrets 管理に移す
- 予約状態変更履歴をログ化する
- 解錠コマンドの実行ログを残す
- `CHAR(36)` ではなく `BINARY(16)` UUID を採用する

## 補足

- `payment_redirect_url`, `payment_success_token`, `payment_fail_token` は旧実装の名残として削除済みです
- 実際の正本 SQL は常に `API/database/schema.sql` を優先し、このドキュメントは設計意図の説明として扱います

## 参照元

- 現行分析: [old-nuxt3-analysis.md](v:\Miyachi-Renta-Room\docs\old-nuxt3-analysis.md)
- 旧コード:
  - [read.ts](v:\Miyachi-Renta-Room\old\backend\read.ts)
  - [create.ts](v:\Miyachi-Renta-Room\old\backend\create.ts)
  - [update.ts](v:\Miyachi-Renta-Room\old\backend\update.ts)
  - [reservation.ts](v:\Miyachi-Renta-Room\old\server\api\reservation.ts)
  - [door.ts](v:\Miyachi-Renta-Room\old\server\api\door.ts)

