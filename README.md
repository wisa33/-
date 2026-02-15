# rugby-php-app

PHP + MySQL + 素の JavaScript で作る、ラグビー1シーン分析アプリです。

## セットアップ

1. `.env.example` を `.env` にコピーして DB 情報を設定
2. MySQL で以下を順に実行
   - `sql/schema_core.sql`
   - `sql/seed.sql`
   - （任意）`sql/triggers.sql`
   - （任意）`sql/schema_optional.sql`
3. Web サーバのドキュメントルートを `public/` に設定
4. `public/setup.php` で接続確認

## 画面構成

- `public/index.php`: Play 一覧 / 作成
- `public/play.php`: Submission 一覧 / 作成
- `public/analyzer.php`: 3カラム分析 UI（Options / Facts / Hypothetical）
- `public/export.php`: 印刷向けレポート
- `public/api.php`: JSON API

## UI 改善ポイント

- 表示文言を日本語化し、画面の意味がすぐ分かる構成に調整
- 3カラムに「①Options / ②Facts / ③Hypothetical」を明示
- Autosave 時に「保存中 / 保存成功 / エラー」を入力欄で視覚的に表現
- 矢印再描画を `ResizeObserver + MutationObserver + rAF` で最適化
- Option カードクリックで分岐切替を直感的に操作可能

## ローカルデモ（MySQLなし環境向け）

この実行環境では MySQL クライアント/サーバが使えない場合があるため、`DB_DSN=sqlite:...` で SQLite デモを起動できます。

```bash
./scripts/init_demo_sqlite.sh
php -S 0.0.0.0:8080 -t public
```

`.env` 例:

```env
DB_DSN=sqlite:/workspace/-/storage/demo.sqlite
```
