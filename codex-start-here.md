# Codex Start Here

## このファイルの役割

Codex がこのプロジェクトに入ったとき、最初に何を理解し、どの順番で読み、どこから手を付けるべきかを短時間で把握するためのガイドです。

## プロジェクトの現在地

- サービス名は `レンタルーム`
- ベース URL は `https://renta-room.com/`
- レンタルサーバは Xサーバを使用する
- `old` は他社が途中で停止した実装
- `old` には Nuxt 3 ベースの予約、決済、解錠、管理画面の試作コードがある
- 今後の本開発は `old` を延命するのではなく、親ディレクトリの `APP` と `API` に再構築する
- バックエンドは PHP で実装し、一般的なレンタルサーバでの運用を想定する
- `APP` は Nuxt 4 の SPA として実装する
- `APP` の公開パスは `/reservation`
- `API` の公開パスは `/api`
- 物理配置は `public_html/app`, `public_html/api`, `public_html/wp` を前提にする

## 正式な再構築方針

- フロントエンドは `APP`
- バックエンドは `API`
- DB は MySQL
- 認証はメールアドレス + パスワード
- パスワード再発行を実装する
- 決済は Stripe を使う
- Supabase 依存をなくす
- `APP` は Nuxt 4 SPA
- WordPress と同居するため `APP` は `/app` 配下
- API は `/api` 配下
- 紹介ページは WordPress で実装し、予約導線は `https://renta-room.com/app` へ集約する
- API のベースは `https://renta-room.com/api` とする
- `public_html/.htaccess` で `/app` を `app`、`/api` を `api`、通常ページを `wp` へルーティングする前提で考える

詳細:

- [app-api-rebuild-development-guide.md](V:\Miyachi-Renta-Room\docs\app-api-rebuild-development-guide.md)

## 読む順番

### 1. 現状把握

- [old-nuxt3-analysis.md](V:\Miyachi-Renta-Room\docs\old-nuxt3-analysis.md)

ここで把握すること:

- 旧コードがどこまで作られているか
- 利用者向け / 管理者向けの主要フロー
- 危険な実装や再利用しない部分

### 2. 今後の実装方針

- [app-api-rebuild-development-guide.md](V:\Miyachi-Renta-Room\docs\app-api-rebuild-development-guide.md)

ここで把握すること:

- `APP` と `API` の責務
- 採用技術
- 認証 / 決済 / SwitchBot の基本方針

### 3. DB 設計

- [mysql-schema-design.md](V:\Miyachi-Renta-Room\docs\mysql-schema-design.md)

ここで把握すること:

- Supabase から MySQL への置き換え方
- 必要テーブル
- 予約テーブル設計

### 4. 予約 API 仕様

- [api-reservations-spec.md](V:\Miyachi-Renta-Room\docs\api-reservations-spec.md)

ここで把握すること:

- 予約作成の request / response
- 可変スロットとバッファの扱い
- 重複判定の正仕様
- 仮予約から決済前までの流れ

### 5. 認証 / 管理 API 仕様

- [api-auth-spec.md](V:\Miyachi-Renta-Room\docs\api-auth-spec.md)
- [api-admin-spec.md](V:\Miyachi-Renta-Room\docs\api-admin-spec.md)

ここで把握すること:

- 利用者と管理者の JWT 分離
- パスワード再発行フロー
- 管理画面から必要になる API 一覧

### 6. 決済 API 仕様

- [api-payments-stripe-spec.md](V:\Miyachi-Renta-Room\docs\api-payments-stripe-spec.md)

ここで把握すること:

- PaymentIntent 作成フロー
- Webhook を正にする方針
- pending 予約の失効ルール
- APP 側の `expires_at` 表示と期限切れ再取得方針

### 7. Xサーバ運用

- [xserver-htaccess-examples.md](V:\Miyachi-Renta-Room\docs\xserver-htaccess-examples.md)

ここで把握すること:

- WordPress / APP / API の `.htaccess`
- Xサーバ上での Composer 実行方法
- 仮予約期限切れ cron の CLI 実行方法

### 8. APP 画面遷移

- [app-screen-flow.md](V:\Miyachi-Renta-Room\docs\app-screen-flow.md)

ここで把握すること:

- WordPress からの予約入口
- 利用者の予約導線
- 管理者画面の全体構成

### 9. 外部連携

- [switchbot-api-notes.md](V:\Miyachi-Renta-Room\docs\switchbot-api-notes.md)

## 実装優先順位

### 最優先

1. `API` の土台を作る
2. MySQL スキーマを確定する
3. JWT 認証を作る
4. 部屋一覧 / 予約作成 / 予約一覧 API を作る
5. `APP` の予約導線を再実装する

### 次点

1. Stripe 決済
2. パスワード再発行
3. 管理画面 API
4. SwitchBot 連携

補足:

- 仮予約の期限切れ処理は API の参照時チェックに加えて、Xサーバ cron の CLI バッチ併用を前提にする

## `old` から拾うべきもの

- 画面構成
- 予約ステップ UI
- 管理画面で必要な操作
- 部屋、予約、設定、解錠の業務概念

## `old` から拾わないもの

- Supabase 依存コード
- 電話番号をパスワードにする認証
- フロントに秘密情報を持たせる設計
- 権限チェックの弱い API

## Codex 向けの作業ルール

- まず `docs` を更新し、仕様の解像度を上げてから実装する
- `old` の挙動を確認したら、そのまま移植せず「今後の正仕様」に読み替える
- 予約、決済、解錠の最終判定は `API` 側で行う
- 認証、権限、秘密情報の扱いは旧実装より厳格にする
- `APP` のテンプレートは `pug` で記述する
- `pug` では Tailwind のクラス省略記法に `:` `/` `[]` を含めない
- `lg:flex-row` `bg-white/80` `tracking-[0.25em]` のようなクラスは `div(class="...")` の形で書く
- `.foo.bar` のような `pug` のクラス省略記法は、記号を含まない単純なクラスだけに使う
- `pug` でタグの子として複数行テキストを書くときは `|` を付ける
- Codex が `API` 側を PowerShell 経由で編集する場合、コード本文をダブルクォート文字列で埋め込まない
- PowerShell でコード断片を扱う必要がある場合は、必ず `@'' ... ''@` ではなく `@' ... '@` のシングルクォート here-string を使う
- `$this` `$response` `$query` のような `$` を含むコードは、PowerShell の変数展開で壊れやすいので文字列置換より `apply_patch` 相当の直接編集を優先する
- PowerShell でやむを得ず置換した場合は、直後に `php -l` や `rg` で壊れていないか確認する

## 次に追加すると良い資料

- 認証シーケンス
- SwitchBot 権限制御ルール


