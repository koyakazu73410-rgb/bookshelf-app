<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Database\Seeder;

class BookSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::firstOrFail();

        $books = [
            ['title' => '吾輩は猫である', 'author' => '夏目漱石', 'isbn' => '9784101010014', 'published_date' => '1905-01-01', 'description' => '猫の視点から人間社会を風刺的に描いた夏目漱石の長編小説です。', 'genres' => ['小説']],
            ['title' => '人を動かす', 'author' => 'D・カーネギー', 'isbn' => '9784422100524', 'published_date' => '1936-10-01', 'description' => '人間関係を築くための原則を具体例とともに紹介する一冊です。', 'genres' => ['ビジネス', '自己啓発']],
            ['title' => 'リーダブルコード', 'author' => 'Dustin Boswell', 'isbn' => '9784873115658', 'published_date' => '2012-06-23', 'description' => '読みやすく保守しやすいコードを書くための実践的な技法を解説します。', 'genres' => ['技術書']],
            ['title' => '7つの習慣', 'author' => 'スティーブン・R・コヴィー', 'isbn' => '9784863940246', 'published_date' => '2013-08-30', 'description' => '主体的な人生を送るための原則と習慣を体系的に解説します。', 'genres' => ['ビジネス', '自己啓発']],
            ['title' => '坊っちゃん', 'author' => '夏目漱石', 'isbn' => '9784101010021', 'published_date' => '1906-04-01', 'description' => '正義感の強い主人公と地方の中学校をめぐる騒動を描いた小説です。', 'genres' => ['小説']],
            ['title' => 'サピエンス全史', 'author' => 'ユヴァル・ノア・ハラリ', 'isbn' => '9784309226712', 'published_date' => '2016-09-08', 'description' => '人類の歴史を認知革命、農業革命、科学革命の観点から読み解きます。', 'genres' => ['歴史', '科学']],
            ['title' => 'Clean Code', 'author' => 'Robert C. Martin', 'isbn' => '9784048930598', 'published_date' => '2017-12-18', 'description' => 'クリーンで理解しやすいコードを書くための原則と実例を紹介します。', 'genres' => ['技術書']],
            ['title' => '嫌われる勇気', 'author' => '岸見一郎・古賀史健', 'isbn' => '9784478025819', 'published_date' => '2013-12-13', 'description' => 'アドラー心理学の考え方を哲人と青年の対話形式で解説します。', 'genres' => ['自己啓発']],
            ['title' => '火花', 'author' => '又吉直樹', 'isbn' => '9784163902302', 'published_date' => '2015-03-11', 'description' => '二人の芸人の交流を通して才能と生き方を描いた小説です。', 'genres' => ['小説']],
            ['title' => 'FACTFULNESS', 'author' => 'ハンス・ロスリング', 'isbn' => '9784822289607', 'published_date' => '2019-01-11', 'description' => 'データをもとに世界を正しく見るための思考法を解説します。', 'genres' => ['ビジネス', '科学']],
            ['title' => 'コンテナ物語', 'author' => 'マルク・レビンソン', 'isbn' => '9784822251468', 'published_date' => '2007-01-18', 'description' => 'コンテナ輸送が世界経済と物流を変革した歴史を描きます。', 'genres' => ['ビジネス', '歴史']],
        ];

        foreach ($books as $index => $bookData) {
            $genreNames = $bookData['genres'];
            unset($bookData['genres']);

            $book = Book::firstOrCreate(
                ['isbn' => $bookData['isbn']],
                array_merge($bookData, [
                    'user_id' => $user->id,
                    'image_url' => 'https://placehold.co/200x300/e2e8f0/475569?text='.($index + 1),
                ])
            );

            $genreIds = Genre::whereIn('name', $genreNames)->pluck('id');
            $book->genres()->sync($genreIds);
        }
    }
}
