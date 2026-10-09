<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenreManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guest_cannot_access_genre_management(): void
    {
        $genre = Genre::create(['name' => '小説']);

        $this->get(route('genres.index'))->assertRedirect(route('login'));
        $this->get(route('genres.show', $genre))->assertRedirect(route('login'));
        $this->get(route('genres.create'))->assertRedirect(route('login'));
        $this->post(route('genres.store'), ['name' => '技術書'])->assertRedirect(route('login'));
        $this->get(route('genres.edit', $genre))->assertRedirect(route('login'));
        $this->put(route('genres.update', $genre), ['name' => '文学'])->assertRedirect(route('login'));
        $this->delete(route('genres.destroy', $genre))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_genre_list_and_details(): void
    {
        $user = User::factory()->create();
        $genre = Genre::create(['name' => '小説']);
        $books = Book::factory()->count(11)->for($user)->create();
        $genre->books()->attach($books->pluck('id'));

        $this->actingAs($user)
            ->get(route('genres.index'))
            ->assertOk()
            ->assertSee('小説')
            ->assertSee('11冊');

        $this->actingAs($user)
            ->get(route('genres.show', $genre))
            ->assertOk()
            ->assertViewHas('books', fn ($paginatedBooks) => $paginatedBooks->perPage() === 10)
            ->assertSee($books->first()->title);
    }

    public function test_authenticated_user_can_create_genre(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('genres.store'), ['name' => '技術書'])
            ->assertRedirect(route('genres.index'))
            ->assertSessionHas('success', 'ジャンルを登録しました。');

        $this->assertDatabaseHas('genres', ['name' => '技術書']);
    }

    public function test_genre_name_is_required_and_unique(): void
    {
        $user = User::factory()->create();
        Genre::create(['name' => '小説']);

        $this->actingAs($user)
            ->from(route('genres.create'))
            ->post(route('genres.store'), ['name' => ''])
            ->assertRedirect(route('genres.create'))
            ->assertSessionHasErrors(['name' => 'ジャンル名を入力してください。']);

        $this->actingAs($user)
            ->from(route('genres.create'))
            ->post(route('genres.store'), ['name' => '小説'])
            ->assertRedirect(route('genres.create'))
            ->assertSessionHasErrors(['name' => 'このジャンル名はすでに登録されています。']);
    }

    public function test_authenticated_user_can_update_genre_and_keep_same_name(): void
    {
        $user = User::factory()->create();
        $genre = Genre::create(['name' => '小説']);

        $this->actingAs($user)
            ->put(route('genres.update', $genre), ['name' => '小説'])
            ->assertRedirect(route('genres.index'))
            ->assertSessionHas('success', 'ジャンルを更新しました。');

        $this->actingAs($user)
            ->put(route('genres.update', $genre), ['name' => '文学'])
            ->assertRedirect(route('genres.index'))
            ->assertSessionHas('success', 'ジャンルを更新しました。');

        $this->assertDatabaseHas('genres', ['id' => $genre->id, 'name' => '文学']);
    }

    public function test_genre_linked_to_book_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $genre = Genre::create(['name' => '小説']);
        $book = Book::factory()->for($user)->create();
        $genre->books()->attach($book);

        $this->actingAs($user)
            ->delete(route('genres.destroy', $genre))
            ->assertRedirect(route('genres.index'))
            ->assertSessionHas('error', 'このジャンルには書籍が紐付いているため削除できません。');

        $this->assertDatabaseHas('genres', ['id' => $genre->id]);
        $this->assertDatabaseHas('book_genre', [
            'book_id' => $book->id,
            'genre_id' => $genre->id,
        ]);
    }

    public function test_genre_without_books_can_be_deleted(): void
    {
        $user = User::factory()->create();
        $genre = Genre::create(['name' => '小説']);

        $this->actingAs($user)
            ->delete(route('genres.destroy', $genre))
            ->assertRedirect(route('genres.index'))
            ->assertSessionHas('success', 'ジャンルを削除しました。');

        $this->assertDatabaseMissing('genres', ['id' => $genre->id]);
    }
}
