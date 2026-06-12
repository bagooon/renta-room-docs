# old ディレクトリ Nuxt3 実装分析

## 概要

`old` ディレクトリには、Nuxt 3 + Vuetify をベースにした「レンタルルーム予約 / 決済 / 解錠」アプリの実装途中コードがあります。  
主な利用者は以下の 2 系統です。

- 予約利用者
  - 部屋を選択して予約する
  - 決済後に自分の予約一覧を見る
  - 利用時間内にスマートロックを解錠 / 施錠する
- 管理者
  - 予約一覧を確認する
  - 入金状態 / キャンセル状態を更新する
  - 部屋情報や SwitchBot 設定を編集する
  - 部屋ドアや入口ドアへコマンド送信する

実装は UI だけではなく、Supabase を使ったデータアクセス、旧決済連携、SwitchBot API による解錠処理まで含まれています。  
一方で、認証や時刻判定、DB 定義の整合性などに未整理な箇所が残っており、そのまま本番運用するには追加整備が必要です。

## 技術構成

- フレームワーク: Nuxt 3
- UI: Vuetify 3
- 言語: TypeScript
- DB / 認証: Supabase
- 多言語対応: `@nuxtjs/i18n`
- コンテンツ: `@nuxt/content`
- 補助拡張:
  - `nuxt-typed-router`
  - 独自 `extends` 配下の Nuxt 拡張群
  - 独自 `Schema` / `Validation` / `Logger` / `GetSupabaseData`
- 外部連携:
  - SwitchBot API
- 旧決済ページ

`old/nuxt.config.ts` の特徴:

- `ssr: false` でクライアントサイド SPA 構成
- `extends: extensions` で独自拡張を多数取り込み
- `APP_URL`、`SWITCH_BOT_TOKEN`、`SWITCH_BOT_SECRET` を `runtimeConfig.public` に公開
- i18n は `ja` / `en` の 2 言語

## ディレクトリの見どころ

- `pages/`
  - 利用者 / 管理者の画面
- `components/`
  - 共通 UI と管理画面カード
- `backend/`
  - Supabase 読み書きのラッパー
- `server/api/`
  - 予約作成 API、ドア操作 API
- `server/utils/`
  - SwitchBot 通信
- `utils/`
  - 管理者認証、利用者認証、日付処理など
- `interfaces/`
  - Zod ベースのスキーマ定義
- `sql/`, `supabase/`
  - テーブル定義、補足メモ、seed
- `extends/`
  - このプロジェクト独自の共通拡張群

## 画面構成

### 1. 利用者向け

#### `/`

- 初期表示後に `/reservation/new` へリダイレクト

#### `/reservation/new`

予約作成の中心画面です。4 ステップのウィザード構成になっています。

1. 部屋選択
2. 日付選択
3. 時間帯選択
4. 利用者情報入力

実装内容:

- `room.list` から部屋一覧を取得
- `blockedDays` と `blockedHoursInNumber` により予約不可日 / 不可時間を算出
- 利用者情報は `ClientAuthUtils` で Supabase Auth に登録またはログイン
- 予約確定時に `/api/reservation` へ POST
- API が返す `urlRedirect` へ遷移し、旧決済画面へ進む

補足:

- 電話番号を数字だけにして、そのままパスワードとして使っている
- 利用者情報は `localStorage` に保存され、次回入力を省略できる

#### `/reservation/list`

利用者が自分の予約一覧と予約詳細を確認する画面です。

実装内容:

- 初回は名前 / メール / 電話番号を入力して本人確認
- `ClientAuthUtils.clientFromStorage()` による自動入力あり
- `reservation.where` で自分の予約一覧を取得
- 予約詳細画面では以下を表示
  - 部屋名
  - 日付
  - 利用時間
  - 料金
  - 支払状況
  - キャンセル状態
- 予約のキャンセル操作あり
- 利用時間中かつ支払済みなら `/api/door` を使って解錠 / 施錠できる

#### `/payment/payment`

旧決済ページへ POST 送信するための画面です。

実装内容:

- hidden form を組み立てて自動 submit
- `price` をクエリから受け取る
- ハッシュ値をフロント側でも生成している

注意:

- 文字化けした日本語文言が含まれる
- 実際の予約フローでは `/api/reservation` から直接旧決済 URL に飛ばしており、このページは現在の主経路ではなさそうです

#### `/payment/confirm`

- `token` を受け取り、`$Backend.update.confirmPayed()` で支払済みに更新
- 完了後に `/reservation/list` へ戻す

#### `/payment/fail`

- `token` を元に、保存済みの `url_payment` を取得
- 再決済導線としてリンクを表示

### 2. 管理者向け

#### `/admin/login`

- 管理者ログイン画面
- `AdminAuthUtils.verify()` を呼ぶ
- 成功時は `/admin/room` に遷移

#### `/admin/reservation`

予約一覧の運用画面です。

実装内容:

- 期間フィルタ
- 検索条件切り替え
  - 予約 ID
  - 名前
  - メール
  - 電話番号
- 状態フィルタ
  - 未払い
  - 通常
  - 完了済み
  - キャンセル済み
- ページング
- 各予約に対する操作
  - 入金状態切替
  - キャンセル
  - 削除

#### `/admin/room`

部屋とスマートロック関連設定の管理画面です。

実装内容:

- `settings` から入口ドアの `entrance_id`、SwitchBot 認証情報を編集
- 部屋ごとに以下を編集
  - タイトル
  - `bot_id`
  - 最大人数
  - 時間単価
- 各部屋のロック開閉コマンド送信
- 入口ドアの開閉コマンド送信

補足:

- `AdminAuthUtils.isLogged()` によるガードは `reservation.vue` にはあるが、`room.vue` ではコメントアウトされている

## 業務フロー

### 予約作成から決済まで

1. 利用者が部屋、日付、時間帯を選択
2. 利用者情報を入力
3. `ClientAuthUtils.authClient()` で Supabase Auth ユーザーを作成またはログイン
4. `/api/reservation` に予約情報を送信
5. サーバー側で重複時間を簡易チェック
6. `reservation.reservation` テーブルへレコード作成
7. 旧決済サービスへリクエストして決済用 URL を取得
8. 予約レコードに `url_payment` を保存
9. フロント側でその URL に遷移
10. 決済成功時は `/payment/confirm?token=...`
11. 決済失敗時は `/payment/fail?token=...`

### 利用当日の解錠

1. 利用者が予約一覧から予約詳細を開く
2. `lockFree()` で「本日」「支払済み」「予約時間内」を満たすか判定
3. `/api/door` に `reservation_id` と `open` を送信
4. API 側で予約と部屋情報を読み出す
5. `SwitchBotUtils.command()` で該当デバイスへ `lock` / `unlock`

### 管理者運用

1. 管理者ログイン
2. 予約一覧で状態確認
3. 必要に応じて
  - 支払済みフラグ更新
  - キャンセル
  - 削除
4. 部屋画面で
  - 部屋設定更新
  - 入口 / 部屋ロック制御
  - SwitchBot 設定更新

## データモデル

### 予約関連

`old/interfaces/db.reservation.ts` と `old/sql/create.schema.reservation.sql` から、予約系の中心は以下です。

#### `reservation.room`

- `id`
- `title`
- `img`
- `url`
- `bot_id`
- `perHour`
- `maxPeople`
- `insertedDt`
- `updatedDt`

#### `reservation.reservation`

実コード上で使われている主項目:

- `id`
- `room_id`
- `client_id`
- `start`
- `end`
- `total`
- `canceled`
- `payed`
- `token_success`
- `token_fail`
- `url_payment`
- `insertedDt`
- `updatedDt`

注意:

- `sql/create.schema.reservation.sql` は `date`, `start`, `end`, `finished` など古い定義が残っており、TypeScript 側のスキーマと一致していません
- `supabase/database-mods.md` にも「`reservation.reservation` の主キー追加」「`date` 列の変更」など、後から修正が必要だった履歴が残っています
- 実際の継続開発では、SQL と Zod スキーマを再整合させる必要があります

### 設定関連

`settings.settings`:

- `id`
- `entrance_id`
- `switchbot_token`
- `switchbot_secret`
- `insertedDt`
- `updatedDt`

### 利用者 / 管理者

利用者:

- Supabase Auth ユーザーを利用
- `raw_user_meta_data` に `name`, `phone` を保存
- 予約テーブルは `client_id` で紐付け

管理者:

- `users.admin` テーブルを参照して資格情報を確認
- その後、Supabase Auth にも `adminAuth()` でサインインしている

## バックエンド実装

### `backend/read.ts`

責務:

- 予約一覧 / 詳細 / ページング取得
- 部屋一覧 / 詳細取得
- 利用者検索
- 支払失敗 / 成功トークン検索
- 予約不可日の計算
- 予約不可時間の計算
- 設定取得

特記事項:

- `paged()` は管理画面の検索・絞り込みの中心
- 利用者検索は `get_clients` RPC を前提にしている
- `blockedDays()` / `blockedHoursInNumber()` は予約重複判定の基盤

### `backend/create.ts`

責務:

- 予約レコード作成
- 利用者の `signUp` / `signIn`
- 管理者の Auth サインイン補助

注意:

- 利用者のパスワードに電話番号を使っている
- 管理者も `adminAuth()` 内で Supabase Auth に依存している

### `backend/update.ts`

責務:

- 部屋更新
- 設定更新
- 予約状態更新
- 決済成功時の `payed = true` 更新

## サーバー API

### `POST /api/reservation`

責務:

- 予約内容を受信
- 部屋存在確認
- 空き時間確認
- 金額計算
- 予約作成
- 旧決済サービスへリクエストしてリダイレクト先 URL を取得
- `url_payment` を保存して返却

返却値:

- `success`
- `reservation_id`
- `urlRedirect`

懸念点:

- 旧決済サービスの加盟店情報やハッシュ鍵がソースコードに直書き
- `appUrl` を `runtimeConfig.public.appUrl` から取得している
- 空き時間チェックが簡易で、境界条件に弱い可能性がある

### `POST /api/door`

責務:

- `reservation_id` 指定なら予約から部屋を特定して開閉
- `bot_id` 指定なら直接そのデバイスへ開閉コマンド送信

実装先:

- `server/utils/SwitchBot.ts`

懸念点:

- 利用者本人確認や権限チェックが薄い
- `dateAvaliable()` が `await` されていない箇所があり、判定が意図どおりでない可能性がある

## 認証の実装方針

### 利用者認証

`utils/client.ts`

- メールアドレスで Supabase Auth ユーザーを作成 / ログイン
- パスワードは電話番号
- `localStorage` に名前 / メール / 電話番号を保存

この方式の特徴:

- ログイン UI を簡略化できる
- 一方で認証強度は弱い
- 電話番号をパスワード代わりにしているため、継続利用時には見直し候補

### 管理者認証

`utils/admin.ts`

- `users.admin` テーブルで資格情報を確認
- 成功したら Supabase Auth でもサインイン

この方式の特徴:

- 独自テーブルと Supabase Auth を二重に使っている
- 認証責務が分散しており、保守時に混乱しやすい

## 実装済みと見てよい部分

- 利用者向け予約導線の主要 UI
- 予約一覧 / 予約詳細表示
- 管理者向け予約一覧 UI
- 管理者向け部屋設定 UI
- Supabase 経由の CRUD ラッパー
- 旧決済遷移の大枠
- SwitchBot 送信処理の大枠
- 日本語 / 英語の文言定義

## 実装途中・不安定と見られる部分

### 1. DB 定義の不整合

- SQL ファイルと Zod スキーマが一致していない
- `reservation.reservation` のカラム設計が途中で変わった形跡がある
- `supabase/database-mods.md` に追加修正前提のメモがある

### 2. セキュリティ上の懸念

- 旧決済サービスの固定値がコードに直書き
- 利用者認証が「電話番号 = パスワード」
- `localStorage` に認証相当情報を保存
- 解錠 API の権限チェックが弱い

### 3. 時刻 / 予約重複判定の曖昧さ

- UTC 文字列とローカル時間処理が混在
- 30 分単位計算にズレが出る可能性がある
- `blockedHoursInNumber()` の端点処理が粗い
- `server/api/door.ts` では `dateAvaliable(...)` を `await` していない

### 4. 認証設計の分散

- 管理者認証が `users.admin` と Supabase Auth の二重管理
- 利用者認証も本来のログイン体験より簡易実装寄り

### 5. 画面遷移 / 導線の未整理

- `/payment/payment` が現行フローで主に使われていない可能性
- `admin/room.vue` のログインガードがコメントアウトされている
- `app.vue` のホーム導線に外部 URL `https://renta-room.com/` が直書き

### 6. 表示品質

- `payment/payment.vue` に文字化けあり
- TODO コメントが複数残っている

## 継続開発時の優先確認ポイント

1. 実際に使う DB スキーマを確定する
2. Supabase 側の schema / migration / seed の現物と `old/sql` の差分を確認する
3. 認証方式を整理する
4. 決済情報の秘匿方法を見直す
5. 解錠 API に権限チェックを追加する
6. 日時の扱いを UTC かローカルかで統一する
7. `admin/room` を含む管理画面ガードを整える
8. 未使用ページや古いフローを整理する

## 再開時のおすすめ調査順

1. `old/interfaces/*` と Supabase 実DB の整合確認
2. `backend/read.ts`, `create.ts`, `update.ts` の責務整理
3. `/api/reservation`, `/api/door` の権限と境界条件の見直し
4. `pages/reservation/*` の UX 改善
5. `pages/admin/*` の認証・運用フロー固め

## 要約

この `old` 実装は、単なる画面モックではなく、以下を一通り備えた「予約サービスの試作版」に近い状態です。

- 部屋予約
- 決済連携
- 予約一覧
- 管理者運用
- スマートロック操作

ただし、継続開発の前提としては「完成済みコード」ではなく、「動く骨格はあるが、DB・認証・セキュリティ・時間判定の再設計が必要なベース」と捉えるのが適切です。
