<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS library');

        Schema::create('library.category', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 120)->unique();
        });

        Schema::create('library.book', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('title', 250);
            $table->string('author', 200);
            $table->char('isbn', 13);
            $table->uuid('category_id');
            $table->integer('total_copies');
            $table->integer('available_copies');
            $table->string('cover_key', 512)->nullable();
            $table->timestampTz('deleted_at')->nullable();

            $table->foreign('category_id', 'fk_book_category')
                ->references('id')
                ->on('library.category')
                ->restrictOnDelete();
        });

        Schema::create('library.member', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('full_name', 200);
            $table->string('document_number', 30)->unique();
        });

        Schema::create('library.user', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('username', 120)->unique();
            $table->string('password_hash', 512);
            $table->string('role', 40);
        });

        Schema::create('library.loan', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('member_id');
            $table->uuid('loaned_by_user_id');
            $table->timestampTz('loaned_at');

            $table->foreign('member_id', 'fk_loan_member')
                ->references('id')
                ->on('library.member')
                ->restrictOnDelete();
            $table->foreign('loaned_by_user_id', 'fk_loan_user')
                ->references('id')
                ->on('library.user')
                ->restrictOnDelete();
        });

        Schema::create('library.loan_item', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('loan_id');
            $table->uuid('book_id');
            $table->string('book_title', 250);
            $table->char('isbn', 13);
            $table->string('category_name', 120);
            $table->timestampTz('due_at');
            $table->timestampTz('returned_at')->nullable();

            $table->foreign('loan_id', 'fk_loan_item_loan')
                ->references('id')
                ->on('library.loan')
                ->cascadeOnDelete();
            $table->foreign('book_id', 'fk_loan_item_book')
                ->references('id')
                ->on('library.book')
                ->restrictOnDelete();
        });

        DB::statement(
            'CREATE UNIQUE INDEX uq_book_active_isbn ON library.book (isbn) WHERE deleted_at IS NULL',
        );
        DB::statement(
            'CREATE INDEX ix_book_active_category_title ON library.book (category_id, title) WHERE deleted_at IS NULL',
        );
        DB::statement('CREATE INDEX ix_loan_loaned_at ON library.loan (loaned_at)');
        DB::statement(
            'CREATE UNIQUE INDEX uq_loan_item_loan_book ON library.loan_item (loan_id, book_id)',
        );
        DB::statement('CREATE INDEX ix_loan_item_book ON library.loan_item (book_id)');
        DB::statement(
            'CREATE INDEX ix_loan_item_open_due_at ON library.loan_item (due_at) WHERE returned_at IS NULL',
        );
        DB::statement(
            'ALTER TABLE library.book ADD CONSTRAINT ck_book_available_range CHECK (available_copies >= 0 AND available_copies <= total_copies)',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('library.loan_item');
        Schema::dropIfExists('library.loan');
        Schema::dropIfExists('library.user');
        Schema::dropIfExists('library.member');
        Schema::dropIfExists('library.book');
        Schema::dropIfExists('library.category');

        DB::statement('DROP SCHEMA IF EXISTS library');
    }
};
