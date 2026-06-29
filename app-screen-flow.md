# APP 画面一覧・画面遷移

## 目的

`APP` が担当する画面一覧と画面遷移を整理するための資料です。  
WordPress 既存サイトから `APP` へ利用者を誘導し、空室確認、予約、決済、予約確認まで完結させる前提で定義します。

関連資料:

- [app-api-rebuild-development-guide.md](V:\Miyachi-Renta-Room\docs\app-api-rebuild-development-guide.md)
- [api-reservations-spec.md](V:\Miyachi-Renta-Room\docs\api-reservations-spec.md)
- [api-auth-spec.md](V:\Miyachi-Renta-Room\docs\api-auth-spec.md)
- [api-payments-stripe-spec.md](V:\Miyachi-Renta-Room\docs\api-payments-stripe-spec.md)
- [api-admin-spec.md](V:\Miyachi-Renta-Room\docs\api-admin-spec.md)
- [old-nuxt3-analysis.md](V:\Miyachi-Renta-Room\docs\old-nuxt3-analysis.md)

## 基本方針

- サービス名は `レンタルーム`
- ベース URL は `https://renta-room.com/`
- レンタルサーバは Xサーバを使用する
- WordPress は集客、施設説明、SEO 用のサイトとして利用する
- 空室確認と予約導線は `APP` が受け持つ
- 利用者に予約関連の主要操作を WordPress 内で完結させない
- `APP` は予約 UI と業務 API のフロントとして振る舞う
- `APP` は Nuxt 4 の SPA として実装する
- `APP` の公開パスは `/app`
- `API` の公開パスは `/api`
- 予約用 `APP` の公開 URL は `https://renta-room.com/app`
- `API` の公開 URL は `https://renta-room.com/api`
- 物理配置は `public_html/app`, `public_html/api`, `public_html/wp` とし、`public_html/.htaccess` で公開 URL を振り分ける

## APP 実装ルール

- HTML テンプレートは `pug` で記述する
- UI は `PrimeVue` の既存コンポーネントを優先して組み立てる
- 独自コンポーネントは必要最小限にとどめる
- スタイルは `Tailwind CSS` を使用する
- 開発環境では `/api/*` を Nuxt の dev proxy 経由で `https://renta-room.com/api/` に中継する

## UI 参考方針

予約画面の UI / UX は `SpaceMarket` のスペース詳細ページ内にある**予約 UI 部分のみ**を参考にします。  
参考元:

- https://www.spacemarket.com/spaces/okxxebgvqxr4iglj/

確認日: 2026-05-04

参考にする主な要素:

- スペース詳細ページ内で予約導線が完結している
  - 価格、レビュー、立地、基本情報、空き状況確認、プランが一つの流れで見える
- 予約判断に必要な情報が予約UIの近くにある
  - 定員、広さ、駅距離、設備、キャンセルポリシーなどを見失いにくい
- 予約パネルの役割が明確
  - 空き確認、プラン選択、料金確認、予約ボタンまでの導線がまとまっている
- 予約操作に必要な情報量のバランス
  - 詳細情報を読みながらも、予約操作の主導線を見失いにくい

このプロジェクトで読み替えるポイント:

- WordPress 側では施設紹介を行い、予約行動は `APP` に寄せる
- この案件では検索UIや一覧UIを参考対象にせず、**部屋詳細ページの予約パネル設計**だけを参考にする
- 決済、会員登録、予約確認までを 1 本の予約導線としてつなぐ
- 特にスペース詳細ページの「施設情報を見ながらその場で空室確認と予約へ進める」体験を重視する

注意:

- UI の参考にとどめ、文言、構成、デザイン資産をそのまま複製しない
- トップページ、検索一覧、ブランド表現は参考対象に含めない
- この案件の実運用に合わせて、予約粒度、入れ替えバッファ、スマートロック連携など独自要件を優先する

## WordPress との接点

### 想定導線

1. 利用者が WordPress の部屋紹介ページを見る
2. WordPress 上の `空室確認・予約する` ボタンを押す
3. `/app` 配下の `APP` へ遷移し、実装上は `/app/rooms` へ案内する

### 配置とルーティング

- `public_html/.htaccess`
  - 公開 URL の振り分けを担当する
- `public_html/app/`
  - 予約用 `APP` の実ファイルを置く
- `public_html/api/`
  - 予約用 `API` の実ファイルを置く
- `public_html/wp/`
  - WordPress の実ファイルを置く

運用前提:

- 利用者には `https://renta-room.com/app` を公開する
- 実体は `public_html/app/` に配置する
- `https://renta-room.com/api/...` は `public_html/api/` へルーティングする
- 紹介ページや通常ページは WordPress 側へルーティングする

### 推奨入口 URL

- 共通入口: `/app`
- 部屋指定入口: `/app?room_id=room_001`
- 日付指定入口: `/app?room_id=room_001&date=2026-05-10`

絶対 URL 例:

- 共通入口: `https://renta-room.com/app`
- 部屋指定入口: `https://renta-room.com/app?room_id=room_001`
- 日付指定入口: `https://renta-room.com/app?room_id=room_001&date=2026-05-10`

### クエリの扱い

- `room_id` と `date` は初期表示のヒントとして受け取る
- クエリ値だけで予約成立判定はしない
- 最終判定は `APP -> API` で再検証する

## 利用者向け画面一覧

### 1. 予約入口

- パス: `/app`
- 役割:
  - `APP` の公開入口
  - WordPress からの遷移先
  - 現在の実装では独立したトップ画面は持たず、`/app/rooms` へリダイレクトする
  - `room_id` クエリがあれば、必要に応じて該当部屋の導線復帰に利用する

利用API:

- `GET /api/reservation-config`
- `GET /api/rooms`
- 必要に応じて `GET /api/rooms/{id}`

### 2. 部屋一覧

- パス: `/app/rooms`
- 役割:
  - 予約可能な部屋一覧表示
  - 部屋ごとの価格、定員、サムネイル表示
  - 部屋詳細または空室確認へ遷移

一覧で優先表示したい情報:

- 部屋名
- 価格帯
- 最大人数
- サムネイル
- 主用途
- 空室確認ボタン

利用API:

- `GET /api/rooms`

### 3. 部屋詳細・空室確認

- パス: `/app/rooms/{id}`
- 役割:
  - 部屋詳細表示
  - 適用される予約粒度、利用可能時間の案内
  - カレンダーと空き枠表示
  - 希望日選択

UI 方針:

- 上部で部屋の魅力と基本情報を見せる
- 中段で日時選択と空室確認を行う
- 下段で料金、利用ルール、注意事項を確認できるようにする
- SpaceMarket のスペース詳細ページのように、予約判断に必要な情報を同一画面で見られる構成にする
- PC では「左に詳細情報、右に予約パネル」の 2 カラム構成を第一候補とする
- モバイルでは予約パネルを下部固定CTAまたはセクション固定導線で補う

優先表示したい情報:

- 部屋名
- 価格帯
- レビュー評価
- 定員
- 面積
- 最寄駅 / 徒歩分数
- 主用途
- 空き状況確認
- 注意事項

利用API:

- `GET /api/rooms/{id}`
- `GET /api/rooms/{id}/availability`

### 4. 日付・時間選択

- パス: `/app/rooms/{id}/entry`
- 役割:
  - 日付選択
  - 時間帯選択
  - 空き枠の可視化
  - 予約候補の確認

UI 前提:

- `slot_minutes` に追従する
- バッファ込みの空き判定結果を UI に反映する
- 必要なら「次の予約まで入れ替え時間を含む」旨を文言表示する
- SpaceMarket のように、利用者が迷わないようステップ状態を明示する
- モバイルで片手操作しやすいレイアウトを優先する
- 詳細ページから連続した体験として、部屋情報を見失わずに日時選択できるようにする

将来検討:

- `プラン選択`
- `人数オプション`
- `追加オプション`

この 3 つは SpaceMarket の詳細ページで重要な予約操作要素として見られるため、この案件でも料金体系が複雑化した段階で導入を検討する

利用API:

- `GET /api/reservation-config`
- `GET /api/rooms/{id}`
- `GET /api/rooms/{id}/availability`

### 5. ログイン / 新規登録

- パス:
  - `/login`
  - `/register`
- 役割:
  - 日時選択後、予約確定直前に認証させる
  - 既存会員はログイン
  - 新規利用者はアカウント登録
  - 未ログイン状態で選択済みの `room_id`, `requested_start_at`, `requested_end_at` を保持したまま復帰させる
  - 通常ログイン / 通常新規登録では、完了後に `/app/reservations` のマイページへ遷移させる
  - `redirect` クエリがある場合はその URL を優先する
  - `reservation_draft.return_path` がある場合は、仮予約から決済導線への復帰を優先する

利用API:

- `POST /api/auth/login`
- `POST /api/auth/register`

### 6. 予約確認

- パス: `/app/confirm`
- 役割:
  - 選択した部屋、日時、料金を確認する
  - `requested_*` と料金を表示する
  - 利用規約確認
  - 予約作成実行
  - この画面遷移時点では認証済みであることを前提にする

確認画面で明示したい内容:

- 部屋名
- 利用日
- 利用開始 / 終了時刻
- 料金
- 定員情報
- アクセスの要点
- キャンセルポリシー
- 注意事項

利用API:

- `POST /api/reservations`

### 7. 決済

- パス: `/app/payment/{reservationId}`
- 役割:
  - Stripe Payment Element の表示
  - 決済開始
  - 決済中状態の表示

利用API:

- `POST /api/reservations/{id}/payment-intent`
- 必要に応じて `GET /api/reservations/{id}/payment-status`

### 8. 決済結果

- パス:
- `/app/payment/{reservationId}/processing`
- `/app/reservations/{reservationId}`
- `/app/payment/{reservationId}/failed`
- 役割:
  - 決済後の状態表示
  - `Webhook` 反映待ち
  - 成功時は予約詳細へ誘導
  - 失敗時は再決済または予約確認へ戻す

UI 方針:

- 成功 / 処理中 / 失敗 を見分けやすくする
- 失敗時は次に何をすればよいかを明確に出す
- `pending` と `expired` の違いが分かる文言にする

利用API:

- `GET /api/reservations/{id}/payment-status`
- `GET /api/reservations/{id}`

### 9. マイページ / 予約一覧

- パス: `/app/reservations`
- 役割:
  - ログイン中利用者のマイページ
  - 利用者プロフィールの表示
  - ユーザー名変更
  - メールアドレス変更
  - パスワード変更
  - 現在操作可能な鍵の一覧表示
  - 利用時間内の予約をすぐ解錠 / 施錠できる導線
  - ログイン中利用者の予約一覧表示
  - 支払状態、キャンセル状態の確認
  - 各予約詳細への導線

表示ルール:

- マイページ上部に、現在の時刻で鍵操作できる予約だけを表示する
- 対象は、支払済みの通常予約と、有効な予約停止枠のうち、占有時間内にあるものとする
- 一覧に表示された鍵UIから、そのまま解錠 / 施錠を実行できるようにする
- 各鍵UIには予約詳細への導線も併設する

利用API:

- `GET /api/auth/me`
- `PATCH /api/auth/me`
- `POST /api/auth/password/change`
- `GET /api/reservations`

### 10. 予約詳細

- パス: `/app/reservations/{id}`
- 役割:
  - 部屋名、予約時間、料金、決済状態表示
  - キャンセル
  - 利用当日の解錠 / 施錠

利用API:

- `GET /api/reservations/{id}`
- `POST /api/reservations/{id}/cancel`
- `POST /api/reservations/{id}/door/unlock`
- `POST /api/reservations/{id}/door/lock`

### 11. パスワード再発行

- パス:
  - `/password/forgot`
  - `/password/reset`
- 役割:
  - 再発行申請
  - 再設定

利用API:

- `POST /api/auth/password/forgot`
- `POST /api/auth/password/reset`

## 管理者向け画面一覧

### 1. 管理者ログイン

- パス: `/admin/login`
- 役割:
  - 管理者認証

利用API:

- `POST /api/admin/auth/login`

### 2. 管理者ダッシュボード

- パス: `/admin`
- 役割:
  - 予約管理、部屋管理、設定管理への入口

利用API:

- `GET /api/admin/auth/me`

### 3. 予約一覧

- パス: `/admin/reservations`
- 役割:
  - 予約検索
  - 支払状態確認
  - キャンセル状態確認
  - 予約詳細への遷移

利用API:

- `GET /api/admin/reservations`

### 4. 予約詳細

- パス: `/admin/reservations/{id}`
- 役割:
  - 利用者情報確認
  - 支払状態更新
  - キャンセル実行

利用API:

- `GET /api/admin/reservations/{id}`
- `PATCH /api/admin/reservations/{id}`
- `POST /api/admin/reservations/{id}/cancel`

### 5. 部屋一覧

- パス: `/admin/rooms`
- 役割:
  - 部屋一覧確認
  - 部屋編集画面への遷移
  - 新規部屋作成

利用API:

- `GET /api/admin/rooms`
- `POST /api/admin/rooms`

### 6. 部屋編集

- パス: `/admin/rooms/{id}`
- 役割:
  - 部屋情報編集
  - 部屋ごとの予約粒度 / バッファ上書き
  - SwitchBot デバイスID編集

利用API:

- `GET /api/admin/rooms/{id}`
- `PATCH /api/admin/rooms/{id}`

### 7. 予約設定

- パス: `/admin/settings/reservation`
- 役割:
  - 共通予約粒度の変更
  - バッファ時間の変更
  - 最小 / 最大予約時間の変更

利用API:

- `GET /api/admin/settings`
- `PATCH /api/admin/reservation-config`

### 8. システム設定

- パス: `/admin/settings/system`
- 役割:
  - アプリ基本設定
  - 入口ドアの SwitchBot 設定確認

利用API:

- `GET /api/admin/settings`
- `PATCH /api/admin/settings`

### 9. ドア操作

- パス: `/admin/doors`
- 役割:
  - 入口ドア開閉
  - 部屋ドア開閉
  - 手動コマンド送信

利用API:

- `POST /api/admin/doors/command`

### 10. 月間予約状況

- パス: `/admin/reservation-calendar`
- 役割:
  - 年月を指定して月間の予約状況を一覧する
  - 1日ごと、15分ごとの枠で各ルームの埋まり状況を俯瞰する
  - 通常予約と予約停止枠を色分けして判別できるようにする

利用API:

- `GET /api/admin/reservation-calendar`

## 利用者向け画面遷移

### 標準予約フロー

1. WordPress
2. `/app`
3. `/app/rooms`
4. `/app/rooms/{id}`
5. `/app/rooms/{id}/entry`
6. 日時選択内容を一時保持する
7. 未ログインなら `/login` または `/register`
8. 認証成功後に保持していた日時選択内容で `/app/confirm` へ復帰
9. `POST /api/reservations`
10. `/app/payment/{reservationId}`
11. `/app/payment/{reservationId}/processing`
12. 成功なら `/app/reservations/{reservationId}`
13. 必要に応じて `/app/reservations` へ戻る

### 未ログイン時の予約導線ルール

- 部屋選択、日付選択、時間選択までは未ログインで進める
- 未ログイン状態では予約枠は確保されない
- `POST /api/reservations` 実行時のみ認証必須とする
- `APP` は `room_id`, `requested_start_at`, `requested_end_at` を `sessionStorage` などに保持し、認証後に復元する
- 認証後の `POST /api/reservations` で `409 RESERVATION_CONFLICT` が返った場合は、枠が埋まった案内を表示して日時選択へ戻す

### 未ログイン時に保持する予約入力

推奨保存先:

- `sessionStorage`

推奨キー:

- `reservation_draft`

保存データ例:

```json
{
  "room_id": "room_001",
  "requested_start_at": "2026-06-10T10:00:00+09:00",
  "requested_end_at": "2026-06-10T11:00:00+09:00",
  "return_path": "/app/confirm",
  "saved_at": "2026-06-05T14:00:00+09:00"
}
```

各項目の意図:

- `room_id`
  - 認証後にどの部屋の確認画面へ戻すかを判断する
- `requested_start_at`
  - API に送る開始時刻
- `requested_end_at`
  - API に送る終了時刻
- `return_path`
  - ログイン後の復帰先
- `saved_at`
  - 古い保持データの破棄判断に使う

復帰ルール:

1. 未ログインで `予約へ進む` を押した時点で `reservation_draft` を保存する
2. `/login` または `/register` へ遷移する
3. 認証成功後に `reservation_draft` を読む
4. `redirect` クエリがある場合はそれを優先し、なければ `reservation_draft.return_path` を使って `/app/confirm` などへ戻す
5. `POST /api/reservations` 成功後は `reservation_draft` を削除する
6. `409 RESERVATION_CONFLICT` の場合は `reservation_draft` を保持したまま日時選択へ戻す

破棄ルール:

- `saved_at` が一定時間以上古い場合は破棄する
- 決済完了後は削除する
- 利用者が明示的に入力をやり直した場合は上書きする

### 再決済フロー

1. /app/reservations/{id}
2. payment_status = failed または pending
3. /app/payment/{reservationId}

再案内一覧のルール:

- pending は 支払いへ進む を表示する
- expired は 空き状況を再確認する を表示する
- expired はそのまま決済へ進ませず、`reservation_draft` に戻して部屋詳細へ案内する
- 部屋詳細では、同じ日時がまだ空いている場合のみ開始時間と終了時間を自動復元する
- 一覧に表示する expired は `expired_at` から24時間以内、かつ利用開始時刻前のものに限る
- 利用開始時刻を過ぎた pending / expired は一覧に表示しない

### キャンセルフロー

1. `/app/reservations`
2. `/app/reservations/{id}`
3. キャンセル確認
4. 一覧へ戻る

## 管理者向け画面遷移

1. `/admin/login`
2. `/admin`
3. `/admin/reservations` または `/admin/rooms` または `/admin/settings/reservation`

## UI 設計上の注意

- 空き表示は API の `availability` を正とする
- 最終予約成立は `POST /api/reservations` の結果で確定する
- `requested_*` を利用者向け表示に使う
- `occupied_*` は通常 UI には直接出さず、必要なら補足文言で表現する
- Stripe の成功表示だけで予約完了扱いにしない
- 決済後は予約詳細画面で `Webhook` 反映待ちを考慮する
- 一覧、詳細、予約確認の各段階で「価格」「日時」「人数」を見失わせない
- モバイル利用を前提に、下部固定 CTA やステップ表示を検討する
- Nuxt 4 SPA を `/app` 配下に置く前提なので、内部リンクは `baseURL` を前提に実装する
- API 呼び出しは常に `/api/...` を基準にする

## WordPress との責務分担

### WordPress が持つもの

- 施設紹介
- 部屋紹介
- アクセス情報
- 料金説明
- FAQ
- 集客導線
- 公開向け部屋紹介ページ
  - 実装上は公開 API から部屋情報・設備情報を取得して埋め込める

### APP が持つもの

- 空室確認
- 予約ステップ UI
- ログイン / 新規登録
- パスワード再発行
- マイページ
  - 氏名 / メールアドレス表示
  - 氏名変更 / メールアドレス変更 / パスワード変更
- 決済
- 予約一覧 / 予約詳細
- 当日利用導線

## 今後追加するとよい補助資料

- 画面ワイヤーフレーム
- 主要コンポーネント一覧
- 状態別 UI パターン
- エラーメッセージ一覧



