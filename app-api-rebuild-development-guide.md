# APP / API 再構築 開発ガイド

## 目的

`old` ディレクトリに残っている途中実装を参考にしつつ、今後の本開発は親ディレクトリ配下の `APP` と `API` に分離して進めるための方針を定義します。

このドキュメントは、既存解析資料を踏まえたうえで、再開発時の前提、採用技術、責務分担、認証、DB、決済、移行対象を整理するための開発用ドキュメントです。

関連資料:

- [old-nuxt3-analysis.md](V:\Miyachi-Renta-Room\docs\old-nuxt3-analysis.md)
- [mysql-schema-design.md](V:\Miyachi-Renta-Room\docs\mysql-schema-design.md)

## 開発方針

### 全体方針

- サービス名は `レンタルーム` とする
- ベース URL は `https://renta-room.com/` とする
- レンタルサーバは Xサーバを利用する
- 紹介ページは WordPress で実装する
- フロントエンドは親ディレクトリの `APP` に新規実装する
- バックエンドは親ディレクトリの `API` に新規実装する
- `old` は参照用資産として扱い、直接継ぎ足す前提ではなく、必要な業務ロジックと画面要件を抽出して再構成する
- Supabase 依存は廃止し、MySQL を正とする構成へ移行する
- 決済は Stripe を採用する
- 一般的なレンタルサーバで運用できることを優先し、バックエンドは PHP で構築する
- `APP` は Nuxt 4 で実装する
- `APP` は SPA 構成で運用する
- WordPress サイトと同居させるため、`APP` の公開パスは `/app` とする
- バックエンド API の公開パスは `/api` とする
- 予約用 `APP` の公開 URL は `https://renta-room.com/app` とする
- `API` の公開 URL は `https://renta-room.com/api` とする
- 物理配置は `public_html/app`, `public_html/api`, `public_html/wp` に分離する

### ディレクトリ責務

#### `APP`

- 利用者向け予約 UI
- 利用者ログイン / パスワード再発行 UI
- マイページ UI
  - 利用者情報表示
  - 氏名変更
  - メールアドレス変更
  - パスワード変更
- 予約一覧 / 予約詳細 UI
- Stripe.js を用いた決済 UI
- 領収書表示 UI
- 管理者ログイン UI
- 管理者向け予約管理 / 部屋管理 UI
- 管理者向け月間予約カレンダー UI

利用者予約導線の前提:

- 部屋選択、日付選択、時間選択は未ログインでも進められる
- 認証が必要なのは `POST /reservations` による仮予約確定時点
- `APP` は未ログイン時に選択済みの `room_id`, `requested_start_at`, `requested_end_at` を一時保持し、ログインまたは新規登録後に予約確認へ復帰させる
- 一時保持は `sessionStorage` の `reservation_draft` を推奨する
- 仮予約成功後と決済完了後は `reservation_draft` を削除する

#### `API`

- 認証 API
- 利用者プロフィール更新 API
- 利用者パスワード変更 API
- 予約 API
- 決済 API
- 管理 API
- SwitchBot 連携 API
- 領収書 URL 同期
- MySQL アクセス

#### `old`

- 既存画面フローの参照
- 現行ロジックの読み替え元
- 移行対象の業務仕様確認元

## 採用技術

### フロントエンド

- 実装先: `APP`
- フレームワーク: Nuxt 4
- SPA 構成で実装する
- テンプレートは `pug` で記述する
- UI コンポーネントは `PrimeVue` を優先して使用する
- 独自コンポーネントは必要最小限にとどめる
- スタイルは `Tailwind CSS` を使用する
- 既存 `old` の Nuxt 3 資産を参考にする
- 予約導線や管理画面の UI 構成は `old/pages` をベースに再設計する
- 決済画面は Stripe.js を利用した簡易実装とする
- Stripe 公開鍵は `APP/nuxt.config.ts` の `runtimeConfig.public.stripePublishableKey` で受ける

補足:

- `old` は `Nuxt 3 + Vuetify` ベースのため、Nuxt 4 へ読み替えつつ画面構造の参考にする
- ただし Supabase 前提コードはそのまま流用せず、API 通信へ置き換える
- 配置先が `/app` のため、Nuxt 側で `app.baseURL = '/app/'` 相当の設定を前提にする
- SPA 配置のため、レンタルサーバ側で `/app/*` を `APP` のエントリへフォールバックさせる設定が必要
- Xサーバ運用を前提に、`public_html/.htaccess` で `/app` を `app` 配下へルーティングする
- 開発時の `/api/*` 呼び出しは Nuxt の dev proxy で `https://renta-room.com/api/` へ中継する
- Stripe 公開鍵は `nuxt.config.ts` の `runtimeConfig.public.stripePublishableKey` を経由してフロントへ渡す
- 運用上の実値は `NUXT_PUBLIC_STRIPE_PUBLISHABLE_KEY` などの環境変数で切り替える想定とする

### バックエンド

- 実装先: `API`
- 言語: PHP
- フレームワーク: `slim/slim`
- JWT ライブラリ: `firebase/php-jwt`
- DB: MySQL
- 公開入口: `API/index.php`
- ローカル起動: `php -S localhost:8080 -t .`

採用理由:

- 一般的なレンタルサーバでの運用に載せやすい
- Slim は軽量で、API 中心の構成に向いている
- `firebase/php-jwt` によりログインセッションをトークンベースで扱える

### 廃止する依存

- Supabase Auth
- Supabase Database

## 想定システム構成

### フロントから見た構成

1. WordPress サイトから `/app` 配下の `APP` へ遷移する
   - `/app` 自体は実装上 `/app/rooms` へ案内する
2. `APP` が `/api` に対してログイン、予約、決済関連のリクエストを送る
3. `API` が MySQL を参照して認証、予約、部屋情報、設定情報を処理する
4. 決済時は `APP` で Stripe.js を用いてカード情報を安全に送信する
5. `API` は Stripe の決済結果を受けて予約の支払状態を更新する
6. 利用時間内の解錠要求時のみ、`API` から SwitchBot API を呼び出す
7. `API` は仮予約の期限切れを `expired` として扱い、予約枠を再開放する
8. `API` は Stripe Webhook で `receipt_url` を保存し、予約詳細から参照できるようにする

### 実装上の分離

- `APP` は表示と入力に責務を限定する
- 認証判定、予約重複判定、料金計算、支払状態更新、解錠可否判定は `API` 側で実施する
- 秘密鍵、Stripe Secret Key、SwitchBot Token などはフロントへ出さない
- `APP` からの API 呼び出し先は `/api` を基準に統一する
- WordPress 配下の相対パス事故を避けるため、API パスは `/api/...` のルート基準で扱う
- WordPress の公開向け部屋紹介ページでも、公開 API から部屋情報・設備情報を取得して埋め込めるようにする

### 配置前提

- レンタルサーバは Xサーバを前提にする
- 公開ルートは `public_html/` とする
- `public_html/app/`
  - 予約用 `APP` の配備先
- `public_html/api/`
  - 予約用 `API` の配備先
  - `index.php` を API の公開入口として配置する
- `public_html/wp/`
  - WordPress の配備先
- `public_html/index.php`
  - WordPress ルート公開用の入口
- `public_html/.htaccess`
  - `/app` と `/api` と WordPress へのルーティングを担当する
- 予約導線の公開URLは `/app`
- API エンドポイントの公開URLは `/api/...`
- ベース URL は `https://renta-room.com/` とする
- 予約入口の絶対 URL は `https://renta-room.com/app` とする
- API エンドポイントの絶対 URL は `https://renta-room.com/api/...` とする
- WordPress 設定は `WordPress アドレス (URL) = https://renta-room.com/wp`、`サイトアドレス (URL) = https://renta-room.com/` を前提にする

補足:

- SPA なので、`/app/rooms/{id}` のような直アクセスに対応できるよう rewrite 設定が必要
- API は WordPress テーマ配下ではなく、独立した `public_html/api/` 配置を前提にする
- WordPress は `public_html/wp/` に置き、トップ導線や紹介ページは `.htaccess` で WordPress 側へ流す
- ファイル更新やバックアップのしやすさを優先し、`APP` / `API` / `WordPress` を物理ディレクトリで分離する

### `public_html/.htaccess` 想定ルール案

目的:

- `/app` を `public_html/app/` の SPA へルーティングする
- `/api` を `public_html/api/` の PHP API へルーティングする
- それ以外を `public_html/wp/` の WordPress へルーティングする

想定構成:

```text
public_html/
  .htaccess
  index.php
  app/
  api/
    index.php
  wp/
    .htaccess
    index.php
```

想定ルール例:

```apache
Options -MultiViews
RewriteEngine On

# /api は api ディレクトリへ渡す
RewriteRule ^api(/.*)?$ api$1 [L]

# /app は app ディレクトリへ渡す
RewriteRule ^app$ app/ [L]
RewriteRule ^app/(.*)$ app/$1 [L]

# 既存の実ファイル・実ディレクトリはそのまま返す
RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]

# WordPress 配下の既存資産はそのまま返す
RewriteCond %{DOCUMENT_ROOT}/wp/$1 -f [OR]
RewriteCond %{DOCUMENT_ROOT}/wp/$1 -d
RewriteRule ^(.+)$ wp/$1 [L]

# それ以外はルートの index.php へ流す
RewriteRule ^ index.php [L]
```

補足:

- `/app` 配下の直アクセスに対応するため、`app/` 側にも SPA 用の `.htaccess` が必要になる
- `public_html/app/.htaccess` では、実ファイルがなければ `index.html` へフォールバックさせる
- `api/` 側は `public_html/api/index.php` を公開入口にし、`public_html/api/.htaccess` で `index.php` へ流す
- WordPress は `public_html/index.php` をルート公開用入口にし、WordPress 標準の rewrite ルールは `public_html/wp/.htaccess` 側へ寄せる
- API のローカル起動は `composer.json` の `start = php -S localhost:8080 -t .` を前提にする
- Xサーバ上で Composer を実行するときは `php8.3 ~/bin/composer [option]` を使う
- 実際の Xサーバ環境では `MultiViews` や既存設定の影響を受けることがあるため、初回デプロイ時に `/`, `/wp/wp-admin/`, `/app`, `/app/rooms/test`, `/api/health` を個別確認する

詳細な設定例:

- [xserver-htaccess-examples.md](V:\Miyachi-Renta-Room\docs\xserver-htaccess-examples.md)

## 認証方針

### 利用者認証

- メールアドレス + パスワードでログインする
- パスワードはハッシュ化して MySQL に保存する
- `old` にある「電話番号をパスワード代わりに使う」実装は廃止する
- ログイン成功時は JWT を発行する

推奨フロー:

1. 新規登録
2. ログイン
3. `/app/reservations` のマイページで氏名 / メールアドレス確認
4. マイページで氏名 / メールアドレス変更
5. マイページでパスワード変更
6. ログアウト
7. パスワード再発行申請
8. 再発行トークン確認
9. 新パスワード設定

### パスワード再発行

- メールアドレスを受け取って再発行トークンを発行する
- 有効期限付きトークンを DB に保存する
- メール内リンクから新パスワード設定画面へ遷移させる
- トークン検証後に新しいパスワードを保存する

必要テーブル例:

- `password_resets`

主な保持項目:

- `id`
- `client_id`
- `token`
- `expires_at`
- `used_at`
- `created_at`

### 管理者認証

- 管理者も MySQL の専用テーブルで管理する
- `old` にある Supabase との二重認証構造は採用しない
- 利用者と管理者はテーブルと権限を分ける

## DB 方針

### 基本方針

- MySQL を正本データベースとする
- `old` の Supabase テーブル依存をなくし、全データを MySQL に集約する
- テーブル設計は [mysql-schema-design.md](V:\Miyachi-Renta-Room\docs\mysql-schema-design.md) をベースに進める

### 最低限必要なテーブル

- `clients`
- `admin_users`
- `rooms`
- `reservations`
- `app_settings`
- `password_resets`

必要に応じて追加:

- `reservation_status_logs`
- `door_command_logs`

### 予約データの考え方

- 空き時間判定は API 側で実施する
- 重複判定は MySQL クエリで厳密に行う
- 支払状態は bool ではなく状態値で持つ
- 予約の「利用者に見せる時間」と「実際に占有する時間」は分けて扱える設計にする
- 運営予定枠も通常予約と同様に重複判定へ含める

### 予約枠と入れ替え時間の方針

- 現状の前提は `15分刻み`
- ただし将来変更できるよう、予約粒度は固定実装にしない
- 予約と予約の間に入れ替え時間を設けられるよう、バッファ時間を設定値として持つ
- 重複判定は「申込時間」ではなく「占有時間」で行う

推奨する考え方:

- `requested_start_at` / `requested_end_at`
  - 利用者が画面で予約した時間
- `occupied_start_at` / `occupied_end_at`
  - 重複判定に使う実占有時間
- `slot_minutes`
  - 予約粒度。初期値は `15`
- `buffer_before_minutes`
  - 予約前の準備時間
- `buffer_after_minutes`
  - 予約後の清掃 / 入れ替え時間

初期運用案:

- `slot_minutes = 15`
- `buffer_before_minutes = 0`
- `buffer_after_minutes = 15`
- `min_reservation_minutes = 60`
- `max_reservation_minutes = 840`

この形にしておくと、利用者には `10:00 - 11:00` と見せつつ、内部では `10:00 - 11:15` を占有時間として扱えます。

### 予約粒度を固定しない理由

- 将来 `15分刻み` や `60分刻み` へ変更する可能性がある
- 部屋ごとに異なる運用にしたくなる可能性がある
- 清掃時間や準備時間が部屋により変わる可能性がある

そのため、`15分` をコードに埋め込まず、設定値または部屋設定から解決する前提で実装する

推奨状態例:

- `pending`
- `paid`
- `failed`
- `canceled`
- `refunded`
- `expired`

## 決済方針

### Stripe 採用

- クレジットカード決済には Stripe を利用する
- フロントでは Stripe の JS モジュールを用いた簡易実装とする
- カード番号などの機密情報は `APP` サーバや `API` サーバで直接保持しない

### 実装イメージ

#### `APP`

- Stripe.js を読み込む
- Payment Element または最小限のカード入力 UI を表示する
- 決済用の `client_secret` を `API` から取得する
- Stripe.js で決済確定を行う

#### `API`

- 予約作成時に仮予約を保存する
- Stripe PaymentIntent を作成する
- `client_secret` を返却する
- 決済成功後に予約の `payment_status` を `paid` へ更新する
- `payment_intent.succeeded` で `receipt_url` を保存する
- `payment_expires_at` を超過した仮予約を `expired` へ更新する

### 決済実装の注意

- 決済成功の最終判定は API 側で行う
- 返金や失敗時の状態遷移を `reservations` に反映できるようにする
- 期限切れの仮予約は重複判定から除外し、予約枠を再開放する
- 期限切れ判定は API の参照時チェックに加え、Xサーバ cron の CLI バッチでも定期実行できるようにする
- 決済中は `payment_started_at` を更新し、一定時間は失効対象外にして誤失効を避ける
- 決済成功後に予約を確定できなかった場合は自動返金する

## SwitchBot 連携方針

- `old/server/api/door.ts` と `server/utils/SwitchBot.ts` の責務を参考にする
- 実際の連携は `API` 側に閉じ込める
- 利用者の解錠は以下を満たす場合のみ許可する

許可条件:

- ログイン済み本人である
- 対象予約の所有者である
- 支払済みである
- キャンセルされていない
- 現在時刻が利用可能時間内である

- 管理者による手動解錠は別権限で扱う
- 実行ログは `door_command_logs` へ保存できる形にする

## `old` からの移行対象

### 再利用価値が高いもの

- 画面フロー
- 予約導線のステップ構成
- 管理画面の必要機能
- 部屋情報、予約情報、設定情報の基本概念
- SwitchBot 連携の業務要件

### そのまま使わないもの

- Supabase 関連コード
- 電話番号をパスワード代わりにする実装
- フロント側に秘密情報を持つ設計
- 認証と権限の弱い API

### 読み替え元の主な箇所

- `old/pages/reservation/*`
- `old/pages/admin/*`
- `old/backend/read.ts`
- `old/backend/create.ts`
- `old/backend/update.ts`
- `old/server/api/reservation.ts`
- `old/server/api/door.ts`

## API の初期設計案

### 利用者向け

- `POST /auth/register`
- `POST /auth/login`
- `GET /auth/me`
- `PATCH /auth/me`
- `POST /auth/password/change`
- `POST /auth/logout`
- `POST /auth/password/forgot`
- `POST /auth/password/reset`
- `GET /rooms`
- `GET /rooms/{id}`
- `GET /reservation-config`
- `POST /reservations`
- `GET /reservations`
- `GET /reservations/{id}`
- `GET /reservations/{id}/payment-status`
- `POST /reservations/{id}/cancel`
- `POST /reservations/{id}/payment-intent`
- `POST /reservations/{id}/door/unlock`
- `POST /reservations/{id}/door/lock`

### 管理者向け

- `POST /admin/auth/login`
- `GET /admin/reservations`
- `PATCH /admin/reservations/{id}`
- `GET /admin/rooms`
- `POST /admin/rooms`
- `PATCH /admin/rooms/{id}`
- `GET /admin/settings`
- `PATCH /admin/settings`
- `PATCH /admin/reservation-config`
- `POST /admin/doors/command`
- `GET /admin/reservation-calendar`

### 予約 API の補足方針

- `POST /reservations` では `requested_start_at` と `requested_end_at` を受ける
- `POST /reservations` のみ認証必須とし、それ以前の空室確認や日時選択は未ログインで進める
- API 側で予約粒度チェックを行う
- API 側でバッファ込みの `occupied_start_at` / `occupied_end_at` を算出する
- 重複判定は `occupied_*` を使う
- 料金計算は原則 `requested_*` を基準に行う
- `APP` は認証後に保持済み入力で `POST /reservations` を実行し、`409 RESERVATION_CONFLICT` の場合は再選択導線へ戻す

### 設定 API の補足方針

`GET /reservation-config` で、少なくとも以下を返せるようにする。

- `slot_minutes`
- `open_time`
- `close_time`
- `buffer_before_minutes`
- `buffer_after_minutes`
- `min_reservation_minutes`
- `max_reservation_minutes`
- `payment_pending_expires_minutes`
- `booking_window_days`

これにより、`APP` 側は表示上の予約 UI を設定追従にできる。

### 仮予約期限切れの運用方針

- `payment_pending_expires_minutes` を予約作成時点で `payment_expires_at` に反映する
- `pending` かつ `payment_expires_at < now` の予約は `expired` とする
- ただし決済開始後は `payment_started_at + 一定猶予時間` も考慮して失効判定する
- `expired` の予約は予約枠計算と重複判定から除外する
- `APP` は `payment_expires_at` を使って残り時間を表示し、期限切れ時は再予約導線を案内する
- Xサーバでは CLI バッチ `public_html/api/bin/expire-reservations.php` を cron で 5 分ごとに実行する

## 開発順序

### Phase 1

- MySQL スキーマ確定
- `API` の Slim ベース構成作成
- JWT 認証基盤作成
- 利用者 / 管理者のログイン API 作成

### Phase 2

- 部屋一覧 API
- 予約作成 API
- 予約一覧 / 詳細 API
- 空き時間判定ロジック実装

### Phase 3

- Stripe 決済実装
- 支払状態反映
- パスワード再発行実装

### Phase 4

- 管理画面 API
- SwitchBot 解錠 API
- 監査ログ整備
- 月間予約カレンダー

### Phase 5

- `APP` 側 UI 実装
- `old` 画面の必要機能移植
- 動作検証

## 注意事項

- 予約金額の計算はフロントではなく API を正とする
- 日時はタイムゾーンを明確に決めて統一する
- 管理者機能と利用者機能の認可を分離する
- 環境変数で管理すべき秘密情報は DB やフロントに露出させない
- `old` は参考実装であり、本番品質の完成版として扱わない
- バッファ時間の仕様変更が既存予約へ遡及しないよう、予約作成時点の占有時間を保存する
- 予約粒度は UI 制約だけでなく API 側でも必ず検証する

## このドキュメントの結論

今後の開発では、`old` を土台に直接延命するのではなく、以下の構成で再構築する方針とします。

- `APP`: フロントエンド
- `API`: PHP + Slim + JWT のバックエンド
- `MySQL`: 業務データの保存先
- `Stripe`: クレジットカード決済

特に重要なのは、Supabase 依存の撤廃、認証の再設計、予約重複判定の API 集約、Stripe ベースの安全な決済導線への移行です。




