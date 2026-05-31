<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\MessageRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepo,
        private readonly MessageRepository $messageRepo,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'admin_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/index.html.twig', [
            'totalUsers' => $this->userRepo->countAll(),
            'messagesToday' => $this->messageRepo->countToday(),
            'activeUsersToday' => $this->messageRepo->countActiveUserIdsToday(),
            'users' => $this->userRepo->findAllOrderedByCreatedAt(),
        ]);
    }

    #[Route('/users/{id}/toggle-enabled', name: 'admin_user_toggle_enabled', methods: ['POST'])]
    public function toggleEnabled(User $user, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin_toggle_' . $user->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        /** @var User $admin */
        $admin = $this->getUser();
        if ($user->getId()->equals($admin->getId())) {
            $this->addFlash('error', 'You cannot disable your own account.');

            return $this->redirectToRoute('admin_index');
        }

        $user->setIsEnabled(!$user->isEnabled());
        $this->em->flush();

        $status = $user->isEnabled() ? 'enabled' : 'disabled';
        $this->addFlash('success', sprintf('Account "%s" has been %s.', $user->getUsername(), $status));

        return $this->redirectToRoute('admin_index');
    }
}
