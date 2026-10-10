<?php

namespace App\Adapters\Outbound\Persistence\Repositories;

use App\Adapters\Outbound\Persistence\Eloquent\Models\MemberRecord;
use App\Application\DTO\PageResult;
use App\Application\Ports\Outbound\MemberRepository;
use App\Domain\Member\Member;
use Illuminate\Support\Facades\DB;

final class EloquentMemberRepository implements MemberRepository
{
    public function findById(string $id): ?Member
    {
        $row = MemberRecord::query()->find($id);

        return $row === null
            ? null
            : new Member((string) $row->id, (string) $row->full_name, (string) $row->document_number);
    }

    public function findByDocumentNumber(string $documentNumber): ?Member
    {
        $row = MemberRecord::query()->where('document_number', trim($documentNumber))->first();

        return $row === null
            ? null
            : new Member((string) $row->id, (string) $row->full_name, (string) $row->document_number);
    }

    public function saveMember(Member $member): void
    {
        $record = MemberRecord::query()->firstOrNew(['id' => $member->id]);
        $record->fill([
            'full_name' => trim($member->fullName),
            'document_number' => trim($member->documentNumber),
        ])->save();
    }

    public function searchMembers(?string $query, int $page, int $size): PageResult
    {
        $members = DB::table('library.member')->when($query !== null && trim($query) !== '', static function ($builder) use ($query): void {
            $term = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($query)).'%';
            $builder->where(static function ($nested) use ($term): void {
                $nested->whereRaw('full_name ILIKE ?', [$term])->orWhereRaw('document_number ILIKE ?', [$term]);
            });
        });
        $total = (clone $members)->count();
        $rows = $members->orderBy('full_name')
            ->offset(($page - 1) * $size)
            ->limit($size)
            ->get();

        return new PageResult(
            $rows->map(static fn ($row): Member => new Member(
                (string) $row->id,
                (string) $row->full_name,
                (string) $row->document_number,
            ))->all(),
            $page,
            $size,
            $total,
        );
    }

    public function activeLoanCount(string $memberId): int
    {
        return DB::table('library.loan as l')
            ->join('library.loan_item as li', 'li.loan_id', '=', 'l.id')
            ->where('l.member_id', $memberId)
            ->whereNull('li.returned_at')
            ->count();
    }
}
