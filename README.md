# Docs Index

## 目的

この `docs` は、`old` に残っている途中実装を解析し、`APP` と `API` へ再構築していくための開発資料置き場です。  
Codex を使った開発では、毎回どの資料から読むべきかが分かることを優先し、まずこのファイルを入口にします。

## サービス前提

- サービス名は `レンタルーム`
- ベース URL は `https://renta-room.com/`
- レンタルサーバは Xサーバを使用する
- 紹介ページは WordPress で実装する
- 予約用 `APP` は `https://renta-room.com/reservation` 配下に配置する
- `API` は `https://renta-room.com/api` 配下に配置する
- 公開ディレクトリは `public_html/` を起点にし、`app/`, `api/`, `wp/` に分けて配置する
- ルーティングは `public_html/.htaccess` で調整する

## 最初に読む順番

1. [codex-start-here.md](V:\Miyachi-Renta-Room\docs\codex-start-here.md)
2. [old-nuxt3-analysis.md](V:\Miyachi-Renta-Room\docs\old-nuxt3-analysis.md)
3. [app-api-rebuild-development-guide.md](V:\Miyachi-Renta-Room\docs\app-api-rebuild-development-guide.md)
4. [mysql-schema-design.md](V:\Miyachi-Renta-Room\docs\mysql-schema-design.md)

この 4 ファイルで、現状把握、再構築方針、DB 方針まで追えるようにしています。

## ドキュメント分類

### 1. 全体把握

- [codex-start-here.md](V:\Miyachi-Renta-Room\docs\codex-start-here.md)
  - Codex が最初に確認するべき前提、参照順、実装優先順位
- [old-nuxt3-analysis.md](V:\Miyachi-Renta-Room\docs\old-nuxt3-analysis.md)
  - `old` 実装の機能、構造、懸念点の分析

### 2. 再構築設計

- [app-api-rebuild-development-guide.md](V:\Miyachi-Renta-Room\docs\app-api-rebuild-development-guide.md)
  - `APP` / `API` / `MySQL` / `Stripe` を前提にした再構築方針
  - Xサーバ向けの `public_html/.htaccess` ルーティング案を含む
- [xserver-htaccess-examples.md](V:\Miyachi-Renta-Room\docs\xserver-htaccess-examples.md)
  - `public_html/.htaccess`, `app/.htaccess`, `api/.htaccess` の具体例
- [mysql-schema-design.md](V:\Miyachi-Renta-Room\docs\mysql-schema-design.md)
  - MySQL テーブル設計案
- [api-reservations-spec.md](V:\Miyachi-Renta-Room\docs\api-reservations-spec.md)
  - 予約作成、空き枠、重複判定、決済前フローの API 仕様
- [api-auth-spec.md](V:\Miyachi-Renta-Room\docs\api-auth-spec.md)
  - 利用者認証、管理者認証、JWT、パスワード再発行の API 仕様
- [api-admin-spec.md](V:\Miyachi-Renta-Room\docs\api-admin-spec.md)
  - 管理画面向け予約管理、部屋管理、設定管理、ドア操作の API 仕様
- [api-payments-stripe-spec.md](V:\Miyachi-Renta-Room\docs\api-payments-stripe-spec.md)
  - Stripe PaymentIntent、Webhook、仮予約失効を含む決済 API 仕様
- [app-screen-flow.md](V:\Miyachi-Renta-Room\docs\app-screen-flow.md)
  - WordPress からの予約入口を含む `APP` の画面一覧と画面遷移

### 3. 外部連携メモ

- [switchbot-api-notes.md](V:\Miyachi-Renta-Room\docs\switchbot-api-notes.md)
  - SwitchBot 連携に必要な情報整理

## Codex で開発を進めるときの使い分け

### 画面や業務フローを確認したいとき

- `old-nuxt3-analysis.md`

### 今後の実装方針を確認したいとき

- `app-api-rebuild-development-guide.md`

### DB を決めたいとき

- `mysql-schema-design.md`

### 予約 API を実装したいとき

- `api-reservations-spec.md`

### 認証を実装したいとき

- `api-auth-spec.md`

### 管理画面 API を実装したいとき

- `api-admin-spec.md`

### Stripe 決済を実装したいとき

- `api-payments-stripe-spec.md`

### Xサーバ運用や cron を確認したいとき

- `xserver-htaccess-examples.md`

### APP の画面設計を進めたいとき

- `app-screen-flow.md`
- `APP` 実装ルールとしては `pug + PrimeVue + Tailwind CSS` を前提にする

### 解錠まわりを進めたいとき

- `switchbot-api-notes.md`

## 今後追加したいドキュメント

- 認証仕様
- `.env` 設計
- migration 運用ルール
- テスト方針

## 更新ルール

- 新しい仕様を決めたら、まず `docs` に反映してから実装する
- 実装中に仕様変更が出たら、関連ドキュメントを同じターンで更新する
- `old` 解析と再構築設計を混ぜず、参照資料と今後の正仕様を分けて書く

## 現時点の正

現時点で実装方針として正にするドキュメントは以下です。

- [app-api-rebuild-development-guide.md](V:\Miyachi-Renta-Room\docs\app-api-rebuild-development-guide.md)
- [mysql-schema-design.md](V:\Miyachi-Renta-Room\docs\mysql-schema-design.md)
- [api-reservations-spec.md](V:\Miyachi-Renta-Room\docs\api-reservations-spec.md)
- [api-payments-stripe-spec.md](V:\Miyachi-Renta-Room\docs\api-payments-stripe-spec.md)
- [xserver-htaccess-examples.md](V:\Miyachi-Renta-Room\docs\xserver-htaccess-examples.md)

`old` 配下のコードは参考実装であり、そのまま本番仕様とはみなしません。

