---
name: app-changelog
description: >-
  Maintain CAILY user-facing version changelog (docs/CHANGELOG.md) and keep it
  in sync with APP_VERSION. Use when making major product changes, bumping
  APP_VERSION / CACHE_VERSION / PROJECT_CACHE_VERSION, shipping performance or
  UX fixes users will notice, or when the user mentions 変更履歴 / changelog /
  version history.
---

# CAILY 変更履歴（スキル）

## いつ使うか

次のような**大きな変更**をしたら、このスキルに従う：

- バージョン番号を上げた（`application/version.php`）
- 利用者が気づく改善・不具合修正（速さ、見た目、操作、誤表示など）
- 画面の追加・削除・大きく違う動き

小さな typo だけ・コメントだけの修正は、変更履歴に書かなくてよい。

## 必ず触るファイル

| ファイル | 役割 |
|---|---|
| `docs/CHANGELOG.md` | **利用者向け**の本文（日本語・わかりやすい言葉）。画面 `/changelog/` がこれを表示する |
| `application/version.php` | `APP_VERSION`（必要なら `PROJECT_CACHE_VERSION` など） |
| （任意）`assets/json/locales/vi.json` など | 新しい UI 文言を訳すとき |

データベースには保存しない。履歴は Markdown のみ。

## 書き方のルール（最重要）

1. **いちばん上に追記**する（新しい版が先）。
2. 見出し形式：

```markdown
## X.Y.Z — YYYY-MM-DD
- （箇条書き。専門用語を避ける）
```

3. 内容は必須。例：
   - ❌ `Promise.all で getById と permission を並列化`
   - ✅ `案件詳細を開くとき、必要な情報をまとめて取りに行き、待ち時間を短くしました`
4. 日本語を基本にする（アプリの主言語）。社内向けにベトナム語を足す場合は、同じ版の下に短く添えてよい。
5. 技術メモが必要なら、同じ版の末尾に小さく「開発メモ」として書いてもよいが、**利用者向け段落を技術メモだけで済ませない**。

## 画面まわり

- 左下バージョン（`.app-version`）の下に「変更履歴」リンク → `ROOT/changelog/`
- 表示ページ：`changelog/index.php`（`docs/CHANGELOG.md` を読み取り）
- ログイン後の通常レイアウトを使う。DB・API は不要。

## チェックリスト（大きな変更のあと）

- [ ] `APP_VERSION` を上げた（キャッシュが絡む JS/CSS も必要なら対応バージョンを上げた）
- [ ] `docs/CHANGELOG.md` に版見出し＋わかりやすいまとめを追記した
- [ ] `/changelog/` を開いて、新しい内容が上に出ることを確認した
- [ ] 文言を増やしたら i18n（`data-i18n` / locale）を確認した

## 例（短文）

```markdown
## 2.2.45 — 2026-09-10
- 顧客情報画面で、部署の選択欄が二重に見えていた問題を直し、1つだけ表示されるようにしました。
```
