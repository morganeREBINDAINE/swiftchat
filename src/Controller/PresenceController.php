<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\PresenceStatus;
use App\Service\PresenceRedisService;
use App\Service\PresenceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class PresenceController extends AbstractController
{
    public function __construct(
        private readonly PresenceRedisService $presenceRedis,
        private readonly PresenceService $presenceService,
        #[Autowire(service: 'limiter.heartbeat')]
        private readonly RateLimiterFactory $heartbeatLimiter,
    ) {
    }

    #[Route('/presence/heartbeat', name: 'presence_heartbeat', methods: ['POST'])]
    public function heartbeat(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('heartbeat', $request->request->getString('_token'))) {
            return $this->json(['error' => 'Invalid CSRF token.'], Response::HTTP_FORBIDDEN);
        }

        $limiter = $this->heartbeatLimiter->create($user->getId()->toRfc4122());
        if (!$limiter->consume()->isAccepted()) {
            return $this->json(null, Response::HTTP_TOO_MANY_REQUESTS);
        }

        $status = match ($request->request->getString('status')) {
            'away' => PresenceStatus::Away,
            default => PresenceStatus::Online,
        };

        $userId = $user->getId()->toRfc4122();
        $changed = $this->presenceRedis->setPresence($userId, $status);

        if ($changed) {
            $this->presenceService->updateStatus($userId, $status);
        }

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/presence/offline', name: 'presence_offline', methods: ['POST'])]
    public function offline(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('presence_offline', $request->request->getString('_token'))) {
            return $this->json(['error' => 'Invalid CSRF token.'], Response::HTTP_FORBIDDEN);
        }

        $userId = $user->getId()->toRfc4122();
        $this->presenceRedis->removePresence($userId);
        $this->presenceService->updateStatus($userId, PresenceStatus::Offline);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
