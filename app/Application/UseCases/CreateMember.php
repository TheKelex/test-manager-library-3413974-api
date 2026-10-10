<?php

namespace App\Application\UseCases;

use App\Application\Errors\UseCaseError;
use App\Application\Ports\Outbound\IdGenerator;
use App\Application\Ports\Outbound\MemberRepository;
use App\Domain\Member\Member;

final readonly class CreateMember
{
    public function __construct(
        private MemberRepository $members,
        private IdGenerator $ids,
    ) {}

    public function execute(string $fullName, string $documentNumber): Member
    {
        if ($this->members->findByDocumentNumber(trim($documentNumber)) !== null) {
            throw new UseCaseError('DOCUMENT_TAKEN', 'El documento ya está registrado.');
        }
        $member = new Member($this->ids->newId(), trim($fullName), trim($documentNumber));
        $this->members->saveMember($member);

        return $member;
    }
}
