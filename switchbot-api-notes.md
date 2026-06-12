# SwitchBot API 情報整理

## このプロジェクトで必要な用途

現行コードでは SwitchBot API を、主にドア施錠 / 解錠のために使っています。

対象:

- 入口ドア
- 各部屋のドア

現行の呼び出し経路:

- [door.ts](v:\Miyachi-Renta-Room\old\server\api\door.ts)
- [SwitchBot.ts](v:\Miyachi-Renta-Room\old\server\utils\SwitchBot.ts)
- [room.vue](v:\Miyachi-Renta-Room\old\pages\admin\room.vue)
- [list.vue](v:\Miyachi-Renta-Room\old\pages\reservation\list.vue)

## このプロジェクトで保持が必要な情報

### システム共通

- `switchbot_token`
- `switchbot_secret`
- `switchbot_entrance_device_id`

### 部屋ごと

- `switchbot_device_id`

現行コード上の対応:

- `settings.switchbot_token`
- `settings.switchbot_secret`
- `settings.entrance_id`
- `room.bot_id`

ただし、リポジトリにある seed 値はダミーです。

- `some token`
- `some secret`
- `some code`
- `some id`

そのため、**構造は分かるが実運用に使える実値は手元にない** 状態です。

## 現行コードの使い方

### 1. 認証情報取得

[SwitchBot.ts](v:\Miyachi-Renta-Room\old\server\utils\SwitchBot.ts) では、`$Backend.read.settings()` で以下を取得しています。

- `switchbot_token`
- `switchbot_secret`

### 2. 署名生成

現行コードは以下を連結して HMAC-SHA256 を作り、Base64 化しています。

- `token`
- `timestamp`
- `nonce`

使っているヘッダ:

- `Authorization`
- `sign`
- `nonce`
- `t`
- `Content-Type: application/json`

### 3. コマンド送信

送信先:

- `POST https://api.switch-bot.com/v1.1/devices/{deviceId}/commands`

現行の body:

```json
{
  "command": "lock or unlock",
  "parameter": "default",
  "commandType": "command"
}
```

## API の基本仕様

以下は SwitchBot の公開 API ドキュメントをベースにした要点です。

### ベースURL

- `https://api.switch-bot.com`

### デバイス一覧取得

- `GET /v1.1/devices`

用途:

- 実際の `deviceId` を確認する
- 入口ドア / 各部屋ドアに対応するデバイスIDを確定する

### デバイスコマンド送信

- `POST /v1.1/devices/{deviceId}/commands`

このプロジェクトで使う代表コマンド:

- `lock`
- `unlock`

Lock 系デバイスでは、公式ドキュメント上で `lock` と `unlock` が定義されています。

### デバイス状態取得

- `GET /v1.1/devices/{deviceId}/status`

用途:

- 初回表示時に現在の lock 状態を補助的に取得する
- Webhook 未着時のフォールバックに使う

### Webhook

- `POST /v1.1/webhook/setupWebhook`
- `POST /v1.1/webhook/queryWebhook`
- `POST /v1.1/webhook/updateWebhook`
- `POST /v1.1/webhook/deleteWebhook`

Lock 系デバイスの Webhook では `context.deviceMac` と `context.lockState` が届く。  
このため、アプリ側では `deviceId` だけでなく `deviceMac` も保持できる設計にしておくと状態照合が安定する。

## 現在の実装方針

### 1. Webhook を最新状態の正本として扱う

現在の実装では、Webhook が届いた時点の payload をそのまま最新状態として `switchbot_lock_states` に保存する。

- `source = webhook`
- `device_mac` はそのまま保存する
- 実運用ログ上では `device_mac` と `device_id` が同一値で届いているため、Webhook 保存時は `device_id` にも同じ値を入れる

つまり、Webhook は「ステータス取得の起点」ではなく、「状態更新の正本」として扱っている。

### 2. コマンド送信直後は暫定状態を保存する

`POST /reservations/{id}/door/unlock` / `lock` と `POST /admin/doors/command` は、SwitchBot command API の成功時に `switchbot_lock_states` を即時更新する。

- `source = command_api`
- `lock_state_raw = LOCK` / `UNLOCK`
- 画面はこのレスポンスでまず即時更新する

その後、Webhook が届いたら `source = webhook` の状態で上書きされる。

整理すると:

- `command_api`: 操作直後の暫定状態
- `webhook`: 実機から返ってきた最終状態

### 3. 初回表示だけ status API を補助的に使う

`GET /reservations/{id}/door/state` / `GET /admin/rooms/{id}/door/state` の初回取得では、状態テーブルが空のときだけ `GET /v1.1/devices/{deviceId}/status` を補助的に使う。

ただし、鍵操作後の即時反映の正本は `command_api` と `webhook` であり、コマンド送信直後に `status` API を取り直して画面へ返すことはしない。

理由:

- 実機反映までの過渡状態で古い `locked` / `unlocked` を拾うことがある
- その結果、サムターン表示が行ったり来たりしやすくなる

## 画面反映の流れ

### 利用者画面 / 管理画面 共通

1. 鍵操作 API を呼ぶ
2. API レスポンスの `command_api` 状態で画面を即時更新する
3. long polling を一度切断し、再開する
4. Webhook により `switchbot_lock_states` が更新される
5. long polling がその更新を拾って画面を最終状態へ寄せる

### long polling の制御

- `state_cursor` として `time_of_sample_ms` を優先利用する
- 操作時は `AbortController` で進行中の polling HTTP リクエスト自体を中断する
- 再開時は `since = state_cursor` で継続し、不要な初回再取得を避ける

### 過渡 webhook への対応

SwitchBot 実機では、たとえば `unlock` 中に一時的に `LOCKED` が届くことがある。  
そのため APP 側では、直前の自分の操作と矛盾する短時間の Webhook を一度だけ保留し、見た目の揺れを抑えている。

ただし、その場合でも `state_cursor` 自体は進め、同じ webhook を polling が何度も取り直さないようにしている。

## `JAMMED` の扱い

Webhook や status API の `lockState = JAMMED` は、現在の実装では `lock_state = jammed` として保存する。

このとき状態APIでは:

- `is_jammed = true`
- `is_locked = false`
- `is_unlocked = false`

となる。

APP 側では `jammed` を通常の `閉` と同一扱いにせず、`異常` として表示する。

- 予約詳細画面: `異常`
- 管理画面: `異常`
- `door_state_raw = OPENED` なら「扉が開いている可能性があります」と案内する

## 運用メモ

### 1. `switchbot_lock_states` の source を確認する

状態の切り分けで見る優先順:

- `webhook`: 実機確定状態
- `command_api`: 操作直後の暫定状態
- `status_api`: 初回補助取得

### 2. `device_id` / `device_mac`

現運用では、Webhook の `deviceMac` がそのまま `device_id` と同じ値として扱える前提で保存している。  
将来この前提が崩れる場合は、`switchbot_devices` または `rooms` との対応解決ロジックを再導入する必要がある。

## Token / Secret の取得方法

SwitchBot のサポート記事では、アプリの開発者向けオプションから Token 情報を確認する方式が案内されています。

概要:

1. SwitchBot アプリを開く
2. `プロフィール` -> `設定` -> `アプリバージョン`
3. アプリバージョンを数回連続タップ
4. `開発者向けオプション` を開く
5. Token 情報を確認する

現状の移行作業で必要なのは、少なくとも以下です。

- API Token
- Secret Key
- 対象デバイスの `deviceId`

## MySQL に持たせる推奨項目

### `app_settings`

- `switchbot_token`
- `switchbot_secret`
- `switchbot_entrance_device_id`

### `rooms`

- `switchbot_device_id`
- `switchbot_device_mac`

## 推奨運用

### 1. まずデバイスIDの棚卸しをする

最低限、以下の対応表を作ると後で困りません。

- 入口ドア -> SwitchBot deviceId
- Room A -> SwitchBot deviceId
- Room B -> SwitchBot deviceId

可能なら次も揃える:

- 入口ドア -> SwitchBot deviceMac
- Room A -> SwitchBot deviceMac
- Room B -> SwitchBot deviceMac

### 2. API 資格情報は Secrets 管理を優先する

現行 UI は DB に保存する設計ですが、本番では以下が望ましいです。

- `SWITCHBOT_TOKEN`
- `SWITCHBOT_SECRET`

ただし、管理画面で変更したい要件があるなら DB 保存もありです。

### 3. コマンド実行ログを残す

最低限記録したい項目:

- 実行日時
- 対象デバイスID
- コマンド名
- 実行者
- 成功 / 失敗
- API 応答

## 現行実装の注意点

### 1. nonce が固定値

現行コードでは `nonce = "requestID"` 固定です。  
実運用ではランダムな UUID 等にしたほうが安全です。

### 2. 失敗時の切り分け情報が少ない

現在は `statusCode === 100` を成功扱いしていますが、レスポンス本文の保存やエラーログが弱めです。

### 3. 権限チェックが弱い

`/api/door` は呼び出し元が本当にその予約の本人か、管理者かの検証が弱いです。  
MySQL 移行時にここは強化対象です。

### 4. 対象デバイスの妥当性確認が必要

`switchbot_entrance_device_id` と `rooms.switchbot_device_id` は、実在デバイスに結び付く値かどうかを事前検証したほうが安全です。

## 移行時の作業メモ

1. SwitchBot アプリで Token / Secret を確認
2. `GET /v1.1/devices` で全デバイス一覧を取得
3. 入口ドアと各部屋ドアの `deviceId` を特定
4. MySQL の `app_settings` と `rooms` に登録
5. `POST /v1.1/devices/{deviceId}/commands` の疎通確認
6. SwitchBot Webhook の受信先を `POST /switchbot/webhooks/lock-state` で登録
7. `SWITCHBOT_WEBHOOK_SECRET` を設定し、Webhook URL に secret クエリを付ける
8. ドア操作ログテーブルと状態保存テーブルを追加
9. 時間外の解錠アラートを使う場合は `ADMIN_ALERT_EMAILS` を設定する
10. `cron` で `php bin/monitor-switchbot-locks.php` を定期実行する

## 参照元

ローカルコード:

- [SwitchBot.ts](v:\Miyachi-Renta-Room\old\server\utils\SwitchBot.ts)
- [door.ts](v:\Miyachi-Renta-Room\old\server\api\door.ts)
- [room.vue](v:\Miyachi-Renta-Room\old\pages\admin\room.vue)
- [seed.sql](v:\Miyachi-Renta-Room\old\supabase\seed.sql)

公式情報:

- SwitchBot Open API Documents  
  https://github.com/OpenWonderLabs/SwitchBotAPI
- Token の取得方法  
  https://support.switch-bot.com/hc/ja/articles/12822710195351-%E3%83%88%E3%83%BC%E3%82%AF%E3%83%B3%E3%81%AE%E5%8F%96%E5%BE%97%E6%96%B9%E6%B3%95

