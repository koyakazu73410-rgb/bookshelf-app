# Bookshelf API v1 Postman Collection

`Bookshelf_API_v1.postman_collection.json`は、Bookshelf APIの公開GETとSanctum認証付き書き込みAPIを確認するためのCollectionです。

## APIの認証範囲

| HTTPメソッド | 認証 | 用途 |
|---|---|---|
| GET | 不要 | 書籍一覧・検索・詳細 |
| POST | Sanctum Bearer Token必須 | 書籍登録 |
| PUT | Sanctum Bearer Token必須＋所有者のみ | 書籍更新 |
| DELETE | Sanctum Bearer Token必須＋所有者のみ | 書籍削除 |

トークンなし、または不正なトークンで書き込みAPIへアクセスすると401になります。認証済みでも別ユーザーの書籍を更新・削除すると403になります。書籍APIの403・404は`{"error":"エラーメッセージ"}`形式です。

## インポート方法

1. Bookshelfアプリを`http://localhost:81`で起動します。
2. Postman左上の`Import`を選択します。
3. `postman/Bookshelf_API_v1.postman_collection.json`をドラッグ＆ドロップします。
4. インポートされた`Bookshelf API v1`を開きます。
5. `Variables`タブで後述の値を設定します。

## Sanctumトークンの準備

Bookshelfにはトークン発行APIがありません。開発環境ではLaravel Tinkerから確認用トークンを発行します。

最初に、DBに存在する2ユーザーのIDを確認してください。以下の`1`と`2`は例なので、実在するIDへ置き換えます。

```bash
./vendor/bin/sail artisan tinker
```

Tinker内で所有者ユーザー用トークンを発行します。

```php
$owner = App\Models\User::findOrFail(1);
$owner->createToken('postman-owner')->plainTextToken;
```

次に、別ユーザー用トークンを発行します。

```php
$other = App\Models\User::findOrFail(2);
$other->createToken('postman-other')->plainTextToken;
```

表示されたトークンはそれぞれPostmanの次の変数へ設定します。

- 所有者ユーザーのトークン → `bearerToken`
- 別ユーザーのトークン → `otherUserBearerToken`

トークンはPostmanのCurrent valueにだけ貼り付け、Collection JSONやGitへ保存しないでください。トークンをチャット、画面共有、ログへ貼り付けないでください。

## Collection Variables

| 変数 | 初期値 | 用途 |
|---|---|---|
| `baseUrl` | `http://localhost:81` | APIのベースURL |
| `bearerToken` | 空 | 書籍所有者として使用するSanctum Token |
| `otherUserBearerToken` | 空 | 403確認用の別ユーザーSanctum Token |
| `invalidBearerToken` | ダミー値 | 不正トークン401確認用。実トークンへ変更しない |
| `protectedBookId` | 空 | `bearerToken`のユーザーが所有する、403確認用の既存書籍ID |
| `genreId` | `1` | 登録・更新・ジャンル検索に使う既存ジャンルID |
| `existingBookId` | `1` | GET詳細と未認証PUT・DELETEに使う既存書籍ID |
| `createdBookId` | 空 | POST成功時に自動保存される確認用書籍ID |
| `testIsbn` | 空 | POST直前に自動生成される13桁ISBN |
| `duplicateIsbn` | `9784101010014` | ISBN重複422確認用の既存ISBN |
| `missingBookId` | `999999999` | 404確認用の存在しない書籍ID |

`protectedBookId`には、`bearerToken`を発行したユーザーが所有する書籍を設定してください。所有者の書籍IDはTinkerで次のように確認できます。

```php
App\Models\Book::where('user_id', $owner->id)->value('id');
```

書籍が存在しない場合は、画面または認証済みPOST APIで所有者ユーザーの書籍を1件作成してから設定してください。403確認ではその書籍を変更・削除しません。

## 正常系の実行手順

`正常系`フォルダを上から順に実行します。

1. `GET 書籍一覧 [200]`
2. `GET キーワード検索 [200]`
3. `GET ジャンル検索 [200]`
4. `GET ページネーション [200]`
5. `GET 書籍詳細 [200]`
6. `POST 書籍登録 [201]`
7. `PUT 登録した書籍を更新 [200]`
8. `DELETE 登録した書籍を削除 [204]`

GETリクエストにはAuthorizationを設定していません。POST・PUT・DELETEには`Bearer {{bearerToken}}`が設定されています。

POSTは実行直前に重複しにくいISBNを生成し、成功レスポンスの`data.id`を`createdBookId`へ保存します。PUTとDELETEはこのIDを利用するため、順番を変えないでください。

DELETEはCollection自身が作成した確認用書籍だけを対象にします。既存の重要な書籍IDを`createdBookId`へ手動設定しないでください。

POST・PUTのBodyには`user_id`を含めていません。書籍所有者はBearer Tokenで認証されたユーザーになります。

## 422・404異常系

`異常系`フォルダでは次を確認します。

| リクエスト | 認証 | 期待結果 |
|---|---|---:|
| GET 存在しない書籍ID | 不要 | 404 |
| POST 必須項目不足 | `bearerToken` | 422 |
| POST 不正な入力値 | `bearerToken` | 422 |
| POST ISBN重複 | `bearerToken` | 422 |
| GET 不正な検索パラメータ | 不要 | 422 |

書籍登録で必須なのは`title`、`author`、`genres`です。`isbn`と`published_date`は任意ですが、入力した場合は形式検証されます。

`POST ISBN重複 [422]`が201になる場合は、`duplicateIsbn`を現在DBに存在するISBNへ変更してください。

## 認証・認可エラーの実行手順

`認証・認可エラー`フォルダでは次を確認します。

| リクエスト | 使用する認証 | 期待結果 |
|---|---|---:|
| POST 未認証 | 認証なし | 401 |
| PUT 未認証 | 認証なし | 401 |
| DELETE 未認証 | 認証なし | 401 |
| POST 不正Bearer Token | `invalidBearerToken` | 401 |
| PUT 非所有者 | `otherUserBearerToken` | 403 |
| DELETE 非所有者 | `otherUserBearerToken` | 403 |

401確認ではDBデータは変更されません。

403確認前に、以下を確認してください。

1. `bearerToken`と`otherUserBearerToken`が別ユーザーのトークンである。
2. `protectedBookId`が`bearerToken`側ユーザーの所有書籍である。
3. `protectedBookId`が実在している。

条件が逆の場合は403になりません。存在しないIDの場合は404になります。

403では`{"error":"この操作を行う権限がありません。"}`、404では`{"error":"書籍が見つかりません。"}`になることもTestsで確認します。401は既存どおり`message`キーを使用します。

## Postman Testsの確認

各リクエストの`Scripts`→`Post-response`に期待ステータスコードのTestsがあります。リクエスト送信後、レスポンス欄の`Test Results`がすべて`PASS`であることを確認します。

フォルダ単位で実行する場合はフォルダのメニューから`Run folder`を選びます。正常系フォルダはPOST・PUT・DELETEが前のリクエスト結果に依存するため、並び順を変更しないでください。

## API仕様上の注意

- GET一覧・詳細は公開APIです。
- POST・PUT・DELETEにはSanctum Bearer Tokenが必要です。
- 更新・削除は書籍所有者だけが実行できます。
- 登録時の所有者は認証ユーザーであり、`user_id`は受け付けません。
- 一覧の既定件数は20件、`per_page`は1〜100です。
- 検索対象は書籍タイトルと著者です。
- ISBNは任意ですが、入力時は13桁の数字かつ一意である必要があります。
- バリデーションエラーは422、存在しない書籍は404、削除成功は本文なしの204です。
