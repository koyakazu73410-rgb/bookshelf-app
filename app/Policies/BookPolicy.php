<?php

namespace App\Policies;

use App\Models\Book;
use App\Models\User;

class BookPolicy
{
    /**
     * Determine whether the authenticated user owns the book to update.
     *
     * @param  User  $user  The authenticated user.
     * @param  Book  $book  The book being updated.
     * @return bool True when the user owns the book.
     */
    public function update(User $user, Book $book): bool
    {
        return $user->id === $book->user_id;
    }

    /**
     * Determine whether the authenticated user owns the book to delete.
     *
     * @param  User  $user  The authenticated user.
     * @param  Book  $book  The book being deleted.
     * @return bool True when the user owns the book.
     */
    public function delete(User $user, Book $book): bool
    {
        return $user->id === $book->user_id;
    }
}
