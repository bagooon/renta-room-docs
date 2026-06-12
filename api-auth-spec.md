# 認証 API 仕様書

## 目的

`APP` と `API` の間で扱う認証 API の契約を明確にするための仕様書です。  
利用者認証、管理者認証、JWT の扱い、パスワード再発行の流れを統一し、予約 API や管理 API の前提を揃えることを目的とします。

関連資料:

- [app-api-rebuild-development-guide.md](V:\Miyachi-Renta-Room\docs\app-api-rebuild-development-guide.md)
- [mysql-schema-design.md](V:\Miyachi-Renta-Room\docs\mysql-schema-design.md)
- [api-reservations-spec.md](V:\Miyachi-Renta-Room\docs\api-reservations-spec.md)

## 前提

- 認証基盤は `API` 側に実装する
- 利用者と管理者は別テーブル、別権限で扱う
- Supabase Auth は使わない
- JWT には `firebase/php-jwt` を利用する

## 認証モデル

### 利用者

- テーブル: `clients`
- ログインID: `email`
- パスワード: `password_hash`

### 管理者

- テーブル: `admin_users`
- ログインID: `login_id`
- パスワード: `password_hash`

## JWT 方針

### トークン種別

- 利用者アクセストークン
- 管理者アクセストークン
- パスワード再発行トークンは JWT ではなく DB 保存トークンを推奨

### JWT に含める主なクレーム

- `sub`
  - ユーザーID
- `role`
  - `client` または `admin`
- `iat`
  - 発行時刻
- `exp`
  - 有効期限
- `iss`
  - 発行者識別子

### 推奨有効期限

- 利用者アクセストークン: `24時間`
- 管理者アクセストークン: `8時間`

補足:

- リフレッシュトークンは初期実装では必須にしない
- 必要になったら別仕様として追加する

## 共通エラー形式

```json
{
  "success": false,
  "error": {
    "code": "INVALID_CREDENTIALS",
    "message": "The email address or password is incorrect."
  }
}
```

### 主なエラーコード

- `VALIDATION_ERROR`
- `INVALID_CREDENTIALS`
- `UNAUTHORIZED`
- `FORBIDDEN`
- `TOKEN_EXPIRED`
- `TOKEN_INVALID`
- `EMAIL_ALREADY_EXISTS`
- `PASSWORD_RESET_TOKEN_INVALID`
- `PASSWORD_RESET_TOKEN_EXPIRED`
- `ACCOUNT_INACTIVE`

## 1. `POST /auth/register`

### 目的

利用者アカウントを新規作成する。

### 認証

- 不要

### リクエスト例

```json
{
  "name": "山田 太郎",
  "email": "taro@example.com",
  "phone": "09012345678",
  "password": "PlainTextPassword",
  "password_confirmation": "PlainTextPassword"
}
```

### バリデーション

- `email` は一意
- `password` と `password_confirmation` は一致必須
- `password` の強度ルールは別途定義するが、最低長は必須

### 成功レスポンス例

```json
{
  "success": true,
  "data": {
    "client_id": "cli_001"
  }
}
```

## 2. `POST /auth/login`

### 目的

利用者ログインを行い、JWT を返す。

### 認証

- 不要

### リクエスト例

```json
{
  "email": "taro@example.com",
  "password": "PlainTextPassword"
}
```

### 成功レスポンス例

```json
{
  "success": true,
  "data": {
    "access_token": "jwt-token",
    "token_type": "Bearer",
    "expires_in": 86400,
    "user": {
      "id": "cli_001",
      "name": "山田 太郎",
      "email": "taro@example.com",
      "role": "client"
    }
  }
}
```

### サーバー側の処理

1. `clients` から `email` を検索する
2. `password_hash` を照合する
3. `last_login_at` を更新する
4. `role = client` の JWT を発行する

## 3. `GET /auth/me`

### 目的

ログイン中利用者のプロフィールを取得する。

### 認証

- 必要

### レスポンス例

```json
{
  "success": true,
  "data": {
    "id": "cli_001",
    "name": "山田 太郎",
    "email": "taro@example.com",
    "phone": "09012345678",
    "role": "client"
  }
}
```

## 4. `POST /auth/logout`

### 目的

ログアウトを行う。

### 認証

- 必要

### 備考

- JWT を完全失効させる blacklist を最初から持つかは未確定
- 初期実装では、API はトークン形式と権限を確認したうえで正常応答し、クライアント側でトークンを破棄する
- 管理者側は必要に応じて server-side revoke を追加検討する

### 成功レスポンス例

```json
{
  "success": true,
  "data": {
    "logged_out": true
  }
}
```

## 5. `POST /auth/password/forgot`

### 目的

パスワード再発行申請を受け付ける。

### 認証

- 不要

### リクエスト例

```json
{
  "email": "taro@example.com"
}
```

### サーバー側の処理

1. 対象 `email` を検索する
2. 対象が存在する場合のみ再発行トークンを生成する
3. `password_resets` に保存する
4. 再設定 URL をメール送信する

### レスポンス方針

- メール存在有無でレスポンスを変えない

### 成功レスポンス例

```json
{
  "success": true,
  "data": {
    "accepted": true
  }
}
```

## 6. `POST /auth/password/reset`

### 目的

再発行トークンを使って新しいパスワードを設定する。

### 認証

- 不要

### リクエスト例

```json
{
  "token": "reset-token",
  "password": "NewPlainTextPassword",
  "password_confirmation": "NewPlainTextPassword"
}
```

### バリデーション

- トークンが存在する
- 未使用である
- 有効期限内である
- パスワード確認が一致する

### 成功レスポンス例

```json
{
  "success": true,
  "data": {
    "password_reset": true
  }
}
```

## 7. `POST /admin/auth/login`

### 目的

管理者ログインを行い、管理者用 JWT を返す。

### 認証

- 不要

### リクエスト例

```json
{
  "login_id": "admin001",
  "password": "PlainTextPassword"
}
```

### 成功レスポンス例

```json
{
  "success": true,
  "data": {
    "access_token": "admin-jwt-token",
    "token_type": "Bearer",
    "expires_in": 28800,
    "user": {
      "id": "adm_001",
      "name": "管理者",
      "login_id": "admin001",
      "role": "admin"
    }
  }
}
```

### サーバー側の処理

1. `admin_users` から `login_id` を検索する
2. `is_active` を確認する
3. `password_hash` を照合する
4. `last_login_at` を更新する
5. `role = admin` の JWT を発行する

## 8. `GET /admin/auth/me`

### 目的

ログイン中管理者の情報を取得する。

### 認証

- 必要
- 管理者 JWT 必須

## 認可ルール

### 利用者トークンで許可する API

- `/auth/me`
- `/reservations`
- `/reservations/{id}`
- `/reservations/{id}/cancel`
- `/reservations/{id}/payment-intent`
- `/reservations/{id}/door/*`

### 管理者トークンで許可する API

- `/admin/*`

### 禁止ルール

- 利用者 JWT で `/admin/*` は禁止
- 管理者 JWT で他人の利用者向け `/reservations/*` をそのまま流用しない
- 管理操作は必ず管理者専用エンドポイントへ分ける

## 実装ルール

### パスワード

- 平文保存しない
- `password_hash` に安全なハッシュを保存する

### エラーメッセージ

- ログイン失敗時は、存在しないメールかパスワード違いかを区別して返さない

### 監査

- 管理者ログインは監査ログ対象にしてよい
- パスワード再発行も必要ならイベントログ化する

## 未確定事項

- リフレッシュトークンを導入するか
- JWT 失効 blacklist を持つか
- パスワード強度ルールをどこまで厳しくするか

## 実装メモ

- パスワード再発行メールは `PHPMailer` を利用
- SMTP 設定は `API/.env` の `SMTP_*`, `MAIL_FROM`, `MAIL_FROM_NAME`, `MAIL_REPLY_TO` を利用
- 互換のため、`MAIL_REPLY-TO` も読み取り対象にする
- 再発行トークンの有効期限は初期実装で `60分`

