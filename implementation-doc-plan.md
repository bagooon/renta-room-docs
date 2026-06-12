# 実装ドキュメント整備計画

## 目的

Codex を使った実装を継続しやすくするため、今後 `docs` に追加していくと効果が高い資料を優先順で整理します。

## 優先度 A

### API 仕様書

必要理由:

- `APP` と `API` の契約を明確にできる
- エンドポイント、入力、出力、認証要否を固定できる

含めたい内容:

- エンドポイント一覧
- request / response
- エラー形式
- 認証要否
- 管理者権限要否

現状:

- [api-reservations-spec.md](V:\Miyachi-Renta-Room\docs\api-reservations-spec.md) を追加済み
- [api-auth-spec.md](V:\Miyachi-Renta-Room\docs\api-auth-spec.md) を追加済み
- [api-admin-spec.md](V:\Miyachi-Renta-Room\docs\api-admin-spec.md) を追加済み
- [api-payments-stripe-spec.md](V:\Miyachi-Renta-Room\docs\api-payments-stripe-spec.md) を追加済み
- まずは利用者向け予約 API の最小セットを定義
- 認証 API と管理者 API の基礎仕様も追加済み
- 決済 API の基礎仕様も追加済み
- 今後は画面仕様、環境変数設計、テスト方針を優先する

### 画面一覧と画面遷移

必要理由:

- `old` の UI をどこまで移植するか判断しやすくなる
- フロントの実装順を決めやすい

含めたい内容:

- 利用者画面一覧
- 管理画面一覧
- 遷移図
- 各画面の主機能

現状:

- [app-screen-flow.md](V:\Miyachi-Renta-Room\docs\app-screen-flow.md) を追加済み
- WordPress から `APP` へ入る予約導線も反映済み

### 認証仕様

必要理由:

- JWT の扱い、期限、再発行、管理者との分離を明確にできる

含めたい内容:

- ログイン
- ログアウト
- トークン期限
- リフレッシュ要否
- パスワード再発行
- 管理者認証

## 優先度 B

### Stripe 決済フロー

- PaymentIntent 作成タイミング
- 仮予約との関係
- 決済成功時の状態更新
- 失敗時の扱い

### SwitchBot 連携仕様

- 解錠条件
- 利用者権限
- 管理者権限
- ログ保存

### 環境変数設計

- `APP` 用
- `API` 用
- 秘密情報
- 開発 / 本番差分

## 優先度 C

### テスト方針

- API 単体テスト
- 予約重複判定テスト
- 認証テスト
- 決済モックテスト

### migration / seed 運用ルール

- 初期データ
- schema 更新手順
- ローカル再現方法

## 運用メモ

- 仕様が曖昧なままコードを書き始めると、`old` の実装に引っ張られやすい
- そのため、Codex と並走するときは「仕様書を短くても先に作る」運用が有効
- 特に `API 仕様書` と `認証仕様` は最優先で追加したい

