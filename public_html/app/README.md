# public_html/app

このディレクトリには、予約用フロントエンド `APP` のビルド成果物を配置します。

- 対象: `V:\Miyachi-Renta-Room\APP`
- 公開 URL: `https://renta-room.com/app`
- 想定内容: Nuxt SPA の出力ファイル一式
- 直下の `.htaccess` は、SPA の直アクセスを `index.html` へフォールバックするために使用します

補足:

- ローカルのソースコード一式をそのまま置く場所ではなく、配信用の成果物を置く想定です
- ルート `public_html/.htaccess` から `/app` へのアクセスがこのディレクトリへルーティングされます
