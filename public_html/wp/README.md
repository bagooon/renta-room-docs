# public_html/wp

このディレクトリには、WordPress 本体を配置します。

- 対象: 本番運用中の WordPress 一式
- 公開上の役割: サイト本体、紹介ページ、管理画面
- 想定内容: `wp-admin/`, `wp-content/`, `wp-includes/`, `index.php` など WordPress の標準構成
- 直下の `.htaccess` と `wp-config.php` は、本番配置に合わせた設定ファイルの置き場です

補足:

- 公開 URL の見え方はサイトルート `https://renta-room.com/` ですが、物理配置は `public_html/wp/` を前提にします
- ルート `public_html/index.php` から `wp/wp-blog-header.php` を読み込む構成を想定します
- リポジトリ直下の `WP/` はテーマテンプレートや関連ソースの管理用であり、このディレクトリとは役割が異なります
- `.htaccess` や `wp-config.php` の確認・修正は、実配置イメージに近いこちら `docs/public_html/wp/` を優先します
