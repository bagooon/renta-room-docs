# public_html/api

このディレクトリには、バックエンド `API` を配置します。

- 対象: `V:\Miyachi-Renta-Room\API`
- 公開 URL: `https://renta-room.com/api`
- 想定内容: `index.php`, `src/`, `vendor/`, `bin/`, `database/`, `storage/` など API 実行に必要な一式
- 直下の `.htaccess` は、Slim のフロントコントローラ `index.php` へリクエストを渡すために使用します

補足:

- `bin/`, `database/`, `src/`, `storage/` などは Web から直接参照させず、`.htaccess` で保護する前提です
- `/api` へのアクセスはルート `public_html/.htaccess` からこのディレクトリへルーティングされます
