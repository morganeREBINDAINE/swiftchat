<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Conversation;
use App\Entity\User;
use App\Security\Voter\ConversationVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class ConversationVoterTest extends TestCase
{
    private ConversationVoter $voter;

    protected function setUp(): void
    {
        $this->voter = new ConversationVoter();
    }

    // --- participant access ---

    public function testGrantsViewToParticipant1(): void
    {
        [$alice, $bob, $conv] = $this->makeConversation();

        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($this->tokenFor($alice), $conv, [ConversationVoter::VIEW]),
        );
    }

    public function testGrantsViewToParticipant2(): void
    {
        [$alice, $bob, $conv] = $this->makeConversation();

        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($this->tokenFor($bob), $conv, [ConversationVoter::VIEW]),
        );
    }

    public function testGrantsSendToParticipant(): void
    {
        [$alice, $bob, $conv] = $this->makeConversation();

        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($this->tokenFor($alice), $conv, [ConversationVoter::SEND]),
        );
    }

    // --- denial ---

    public function testDeniesViewToThirdParty(): void
    {
        [, , $conv] = $this->makeConversation();
        $charlie = $this->makeUser();

        $this->assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($this->tokenFor($charlie), $conv, [ConversationVoter::VIEW]),
        );
    }

    public function testDeniesViewToAnonymousUser(): void
    {
        [, , $conv] = $this->makeConversation();

        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn(null);

        $this->assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($token, $conv, [ConversationVoter::VIEW]),
        );
    }

    // --- abstention ---

    public function testAbstainsForUnknownAttribute(): void
    {
        [$alice, , $conv] = $this->makeConversation();

        $this->assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $this->voter->vote($this->tokenFor($alice), $conv, ['UNKNOWN_ATTRIBUTE']),
        );
    }

    public function testAbstainsForNonConversationSubject(): void
    {
        [$alice] = $this->makeConversation();

        $this->assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $this->voter->vote($this->tokenFor($alice), new \stdClass(), [ConversationVoter::VIEW]),
        );
    }

    // --- helpers ---

    /** @return array{User, User, Conversation} */
    private function makeConversation(): array
    {
        $alice = $this->makeUser();
        $bob = $this->makeUser();

        return [$alice, $bob, new Conversation($alice, $bob)];
    }

    private function makeUser(): User
    {
        $user = new User();
        $user->setUsername('user_'.uniqid());
        $user->setEmail(uniqid().'@example.com');
        $user->setPassword('hashed');

        return $user;
    }

    private function tokenFor(User $user): TokenInterface
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }
}
