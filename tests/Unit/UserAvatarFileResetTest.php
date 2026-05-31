<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\File;

class UserAvatarFileResetTest extends TestCase
{
    public function testAvatarFileIsNullAfterReset(): void
    {
        $user = new User();
        $user->setUsername('alice');
        $user->setEmail('alice@example.com');
        $user->setPassword('hashed');

        $file = $this->createStub(File::class);
        $user->setAvatarFile($file);

        $this->assertNotNull($user->getAvatarFile());

        $user->resetAvatarFile();

        $this->assertNull($user->getAvatarFile());
    }

    public function testUserIsSerializableAfterReset(): void
    {
        $user = new User();
        $user->setUsername('alice');
        $user->setEmail('alice@example.com');
        $user->setPassword('hashed');

        $file = $this->createStub(File::class);
        $user->setAvatarFile($file);
        $user->resetAvatarFile();

        $serialized = serialize($user);
        /** @var User $restored */
        $restored = unserialize($serialized);

        $this->assertNull($restored->getAvatarFile());
        $this->assertSame('alice', $restored->getUsername());
        $this->assertSame('alice@example.com', $restored->getEmail());
    }

    public function testUserWithAvatarFileSetIsNotSerializable(): void
    {
        $user = new User();
        $user->setUsername('alice');
        $user->setEmail('alice@example.com');
        $user->setPassword('hashed');

        // Simulate what VichUploader does before the fix: file stays set after flush
        $user->setAvatarFile(new File(__FILE__));

        $this->expectException(\Exception::class);
        serialize($user);
    }
}
