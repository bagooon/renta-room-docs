# Xサーバ向け `.htaccess` 設定例

## 目的

Xサーバ上で以下の構成を運用するための `.htaccess` 設定例をまとめます。

```text
public_html/
  .htaccess
  index.php
  app/
    .htaccess
  api/
    .htaccess
    index.php
  wp/
    .htaccess
    index.php
```

前提:

- サービス名は `レンタルーム`
- ベース URL は `https://renta-room.com/`
- 予約用 `APP` は `https://renta-room.com/app`
- `API` は `https://renta-room.com/api`
- 紹介ページは WordPress で実装する
- WordPress 本体は `public_html/wp/` に置く
- 公開 URL は WordPress ルート公開とし、`WordPress アドレス (URL) = https://renta-room.com/wp`、`サイトアドレス (URL) = https://renta-room.com/` を前提にする

## 1. `public_html/.htaccess`

役割:

- `/app` を `app/` へ渡す
- `/api` を `api/` へ渡す
- それ以外を WordPress へ渡す

設定例:

```apache
Options -MultiViews
RewriteEngine On

# /api は api ディレクトリへ渡す
RewriteRule ^api(/.*)?$ api$1 [L]

# /app は app ディレクトリへ渡す
RewriteRule ^app$ app/ [L]
RewriteRule ^app/(.*)$ app/$1 [L]

# WordPress の公開ディレクトリ・主要エンドポイントを wp 配下へ渡す
RewriteRule ^((?:wp-admin|wp-content|wp-includes)(?:/.*)?)$ wp/$1 [L]
RewriteRule ^(wp-login\.php|wp-cron\.php|wp-comments-post\.php|wp-activate\.php|wp-signup\.php|xmlrpc\.php)$ wp/$1 [L]
RewriteRule ^login_34690(.*)$ wp/login_34690$1 [L]

# public_html 直下の既存ファイル・既存ディレクトリはそのまま返す
RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]

# WordPress 配下の実ファイル・実ディレクトリがあればそちらを優先
RewriteCond %{DOCUMENT_ROOT}/wp/$1 -f [OR]
RewriteCond %{DOCUMENT_ROOT}/wp/$1 -d
RewriteRule ^(.+)$ wp/$1 [L]

# それ以外はルートの index.php へ渡す
RewriteRule ^ index.php [L]
```

補足:

- `Options -MultiViews` は意図しないパス解決を防ぐために入れる
- `public_html/index.php` をルート公開用の入口にし、そこで `wp/wp-blog-header.php` を読む構成を前提にする
- WordPress の標準 rewrite は `public_html/wp/.htaccess` に置く想定
- `wp-admin` `wp-content` `wp-includes` や `wp-login.php` などは、先に明示的に `wp/` 配下へ流すと 404 を避けやすい
- SiteGuard などでログインURLを `login_34690` のように変更している場合、ルート側は `wp/login_34690` へ渡し、`wp/.htaccess` 側の SiteGuard ルールを `/wp/` 配置に合わせる

## 2. `public_html/app/.htaccess`

役割:

- Nuxt SPA の直アクセスを `index.html` へフォールバックする
- `app/rooms/...` のような深い URL でもアプリを表示できるようにする

設定例:

```apache
Options -MultiViews
RewriteEngine On
RewriteBase /app/

# 実ファイル・実ディレクトリがあればそのまま返す
RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]

# SPA フォールバック
RewriteRule ^ index.html [L]
```

補足:

- Nuxt を静的配備する前提の例
- `app.baseURL = '/app/'` を Nuxt 側で設定する前提
- アセット URL が相対パスになっていないかデプロイ前に確認する

## 3. `public_html/api/.htaccess`

役割:

- Slim のフロントコントローラ `index.php` へ API リクエストを渡す
- `public_html/api/index.php` を API の唯一の公開入口にする

設定例:

```apache
Options -MultiViews
RewriteEngine On

# 内部ディレクトリを直接公開しない
RewriteRule ^(bin|database|src|storage)(/.*)?$ - [F,L]

# Authorization ヘッダを PHP 側へ引き継ぐ
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

# 実ファイル・実ディレクトリがあればそのまま返す
RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]

# それ以外は index.php へ渡す
RewriteRule ^ index.php [QSA,L]
```

補足:

- JWT を使うため `Authorization` ヘッダ引き継ぎを入れている
- この案件では `public/index.php` 構成ではなく、`api/` 直下に `index.php` を置く運用を正とする
- `composer.json` のローカル起動スクリプトも `php -S localhost:8080 -t .` に合わせる
- `api/health` のような疎通確認用エンドポイントを用意しておくと初回確認がしやすい
- `bin/`, `database/`, `src/`, `storage/` は Web から直接参照させない

## 4. `public_html/index.php`

役割:

- ルート公開用の WordPress 入口
- `public_html/wp/` に置いた WordPress 本体を、公開 URL では `/` として見せる

設定例:

```php
<?php
define('WP_USE_THEMES', true);
require __DIR__ . '/wp/wp-blog-header.php';
```

## 5. `public_html/wp/.htaccess`

役割:

- WordPress 本体ディレクトリ内の標準 rewrite を保持する

設定例:

```apache
# BEGIN WordPress
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteBase /wp/
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /wp/index.php [L]
</IfModule>
# END WordPress
```

## 確認項目

デプロイ後は少なくとも以下を確認します。

- `https://renta-room.com/`
  - WordPress のトップが表示される
- `https://renta-room.com/wp/wp-admin/`
  - WordPress 管理画面へ入れる
- WordPress 設定画面で `WordPress アドレス = /wp`、`サイトアドレス = /` になっている
- `https://renta-room.com/app`
  - 予約 APP の入口が表示される
- `https://renta-room.com/app/rooms/test`
  - SPA の直アクセスが `index.html` へフォールバックされる
- `https://renta-room.com/api/health`
  - API の疎通確認ができる

## 6. Xサーバ上での Composer 実行方法

Xサーバ上では、PHP 8.3 で Composer を実行するために別途インストールした Composer を使う。

基本形:

```bash
php8.3 ~/bin/composer [option]
```

例:

```bash
php8.3 ~/bin/composer install
php8.3 ~/bin/composer dump-autoload
php8.3 ~/bin/composer validate --no-check-publish
php8.3 ~/bin/composer check-platform-reqs
```

補足:

- `autoload` 変更時は `php8.3 ~/bin/composer dump-autoload` を実行する
- `composer update` は依存更新が必要な場合だけ使い、通常の反映ではむやみに実行しない
- `ext-pdo_mysql` などの platform requirement 確認には `php8.3 ~/bin/composer check-platform-reqs` を使う
- `composer.json` や `vendor/composer/` が更新された場合は、FTP 反映漏れにも注意する

## 7. 仮予約期限切れ cron の設定例

役割:

- `pending` かつ `payment_expires_at < now` の仮予約を `expired` に更新する
- 期限切れになった予約枠を再開放する
- リクエストが無い時間帯でも状態を整える

実行スクリプト:

```text
public_html/api/bin/expire-reservations.php
```

Xサーバの cron コマンド例:

```bash
/usr/bin/php8.3 /home/サーバーID/renta-room.com/public_html/api/bin/expire-reservations.php
```

実運用の実行間隔:

- `5分ごと`

補足:

- API 側でも参照時に期限切れ判定を行うため、cron は補助的な整備ジョブとして考える
- cron 実行結果は JSON で `expired_count` を返す
- Xサーバの `Cron結果の通知アドレス` を設定すると異常検知しやすい

## 注意事項

- Xサーバの既存設定や PHP バージョン設定により挙動が変わることがある
- WordPress プラグインが `.htaccess` を自動更新する場合、`public_html/.htaccess` と `public_html/wp/.htaccess` の責務を混ぜない
- 本番投入前にステージングで rewrite を確認する
- Cloudflare などの CDN を併用する場合はキャッシュ挙動も別途確認する
