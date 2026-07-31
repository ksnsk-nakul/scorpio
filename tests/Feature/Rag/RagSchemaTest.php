<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('creates the book_chunks, chat_threads, and chat_messages tables on the rag connection', function () {
    expect(Schema::connection('rag')->hasTable('book_chunks'))->toBeTrue();
    expect(Schema::connection('rag')->hasTable('chat_threads'))->toBeTrue();
    expect(Schema::connection('rag')->hasTable('chat_messages'))->toBeTrue();

    expect(Schema::connection('rag')->hasColumns('book_chunks', [
        'id', 'book_id', 'chapter_id', 'chunk_index', 'content', 'embedding', 'created_at',
    ]))->toBeTrue();

    expect(Schema::connection('rag')->hasColumns('chat_messages', [
        'id', 'thread_id', 'role', 'content', 'citations', 'created_at',
    ]))->toBeTrue();
});

it('can insert and query a pgvector embedding column directly', function () {
    $vector = '['.implode(',', array_fill(0, 768, 0.01)).']';

    DB::connection('rag')->statement(
        'insert into book_chunks (book_id, chapter_id, chunk_index, content, embedding) values (?, ?, ?, ?, ?::vector)',
        [999999, 999999, 0, 'test chunk', $vector]
    );

    $row = DB::connection('rag')->selectOne(
        'select content from book_chunks where book_id = ? order by embedding <=> ?::vector limit 1',
        [999999, $vector]
    );

    expect($row->content)->toBe('test chunk');

    DB::connection('rag')->table('book_chunks')->where('book_id', 999999)->delete();
});
