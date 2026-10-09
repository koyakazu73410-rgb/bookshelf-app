<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::whereIn('email', [
            'yamada@example.com',
            'suzuki@example.com',
            'tanaka@example.com',
            'sato@example.com',
            'takahashi@example.com',
        ])->orderBy('id')->get()->values();

        $isbns = [
            '9784101010014',
            '9784422100524',
            '9784873115658',
            '9784863940246',
            '9784101010021',
            '9784309226712',
            '9784048930598',
            '9784478025819',
            '9784163902302',
            '9784822289607',
            '9784822251468',
        ];

        $books = Book::whereIn('isbn', $isbns)->get()->keyBy('isbn');
        $comments = [
            '物語の語り口が印象的で、最後まで楽しく読めました。',
            '時代背景を感じながら読み進められる作品でした。',
            '登場人物の描写が丁寧で、何度も読み返したくなります。',
            '具体例が豊富で、すぐに実践できる内容でした。',
            '人との接し方を改めて考えるきっかけになりました。',
            '長く読み継がれている理由がよく分かる一冊です。',
            'コードを書く際に意識したいポイントが整理されています。',
            '説明が分かりやすく、日々の開発に役立ちました。',
            'チームで共有して読みたい実践的な内容でした。',
            '日常生活に取り入れられる考え方が多くありました。',
            '繰り返し読むことで理解が深まる本だと思います。',
            '自分の行動を見直す良いきっかけになりました。',
            '主人公のまっすぐな性格が魅力的でした。',
            'テンポがよく、短い時間でも読みやすい作品です。',
            'ユーモアの中に社会への視点が感じられました。',
            '人類史を大きな流れで理解できる興味深い内容でした。',
            '歴史と科学のつながりが分かりやすく説明されています。',
            '幅広いテーマを考える入口になる一冊でした。',
            '読みやすいコードの重要性を再確認できました。',
            '実例が多く、改善方法を具体的に学べます。',
            '開発経験に関係なく参考になる内容だと思います。',
            '対話形式なので難しい考え方も理解しやすかったです。',
            '他人との比較について考え直すきっかけになりました。',
            '前向きに行動するためのヒントが得られました。',
            '芸人として生きる二人の関係が心に残りました。',
            '才能と努力について考えさせられる作品でした。',
            '静かな文章の中に強い感情が描かれています。',
            '思い込みではなくデータを見る大切さを学べました。',
            '図表と具体例が多く、世界の見方が変わりました。',
            'ニュースを見る際にも役立つ考え方だと思います。',
            '物流の変化が世界経済に与えた影響を理解できました。',
            '身近なコンテナから歴史を読み解く視点が面白かったです。',
        ];

        $commentIndex = 0;

        foreach ($isbns as $bookIndex => $isbn) {
            $book = $books->get($isbn);
            $reviewCount = $bookIndex === count($isbns) - 1 ? 2 : 3;

            for ($reviewIndex = 0; $reviewIndex < $reviewCount; $reviewIndex++) {
                $user = $users->get(($bookIndex + $reviewIndex) % $users->count());

                Review::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'book_id' => $book->id,
                    ],
                    [
                        'rating' => 3 + (($bookIndex + $reviewIndex) % 3),
                        'comment' => $comments[$commentIndex],
                    ]
                );

                $commentIndex++;
            }
        }
    }
}
