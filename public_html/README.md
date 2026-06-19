# public_html

このディレクトリは、本番サーバ `public_html/` の配置イメージをまとめたものです。

## 役割

- `app/`
  - 予約用フロントエンド `APP` の配備先
  - 公開 URL は `https://renta-room.com/app`
- `api/`
  - バックエンド `API` の配備先
  - 公開 URL は `https://renta-room.com/api`
- `wp/`
  - WordPress 本体の配備先
  - サイト本体は `https://renta-room.com/` として公開

## ルート直下のファイル

- `.htaccess`
  - `/app`, `/api`, WordPress への振り分けを担当します
- `index.php`
  - ルート公開用の WordPress 入口です
  - `wp/wp-blog-header.php` を読み込む構成を想定します

## 補足

- この `docs/public_html` は説明用のひな形です
- `app/`, `api/`, `wp/` の中身は空ですが、各ディレクトリ内の `README.md` に実際に配置する内容を記載しています
- 実サーバへ反映する際は、ここに置いた `.htaccess` や `index.php` を正本候補として扱います
- WordPress については、リポジトリ直下の `WP/` は主にテーマテンプレート管理用です
- 実サーバへ置く `wp-config.php`, `.htaccess`, WordPress 本体構成の説明は `docs/public_html/wp/` を基準にします
