<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\AvatarUploadFormType;
use App\Form\ChangePasswordFormType;
use App\Form\EditProfileFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class ProfileController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly Security $security,
    ) {
    }

    #[Route('/profile', name: 'app_profile', methods: ['GET'])]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('profile/index.html.twig', [
            'user' => $user,
            'editForm' => $this->createForm(EditProfileFormType::class, $user, [
                'action' => $this->generateUrl('profile_edit'),
            ]),
            'passwordForm' => $this->createForm(ChangePasswordFormType::class, null, [
                'action' => $this->generateUrl('profile_password'),
            ]),
            'avatarForm' => $this->createForm(AvatarUploadFormType::class, $user, [
                'action' => $this->generateUrl('profile_avatar'),
            ]),
        ]);
    }

    #[Route('/profile/edit', name: 'profile_edit', methods: ['POST'])]
    public function edit(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(EditProfileFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->security->login($user, 'form_login', 'main');
            $this->addFlash('success', 'Profile updated.');

            return $this->redirectToRoute('app_profile');
        }

        return $this->render('profile/index.html.twig', [
            'user' => $user,
            'editForm' => $form,
            'passwordForm' => $this->createForm(ChangePasswordFormType::class, null, [
                'action' => $this->generateUrl('profile_password'),
            ]),
            'avatarForm' => $this->createForm(AvatarUploadFormType::class, $user, [
                'action' => $this->generateUrl('profile_avatar'),
            ]),
        ]);
    }

    #[Route('/profile/password', name: 'profile_password', methods: ['POST'])]
    public function changePassword(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$this->hasher->isPasswordValid($user, $form->get('currentPassword')->getData())) {
                $this->addFlash('error', 'Current password is incorrect.');

                return $this->redirectToRoute('app_profile');
            }

            $user->setPassword($this->hasher->hashPassword($user, $form->get('newPassword')->getData()));
            $this->em->flush();
            $this->addFlash('success', 'Password changed successfully.');

            return $this->redirectToRoute('app_profile');
        }

        return $this->render('profile/index.html.twig', [
            'user' => $user,
            'editForm' => $this->createForm(EditProfileFormType::class, $user, [
                'action' => $this->generateUrl('profile_edit'),
            ]),
            'passwordForm' => $form,
            'avatarForm' => $this->createForm(AvatarUploadFormType::class, $user, [
                'action' => $this->generateUrl('profile_avatar'),
            ]),
        ]);
    }

    #[Route('/profile/avatar', name: 'profile_avatar', methods: ['POST'])]
    public function uploadAvatar(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(AvatarUploadFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->addFlash('success', 'Avatar updated.');

            return $this->redirectToRoute('app_profile');
        }

        return $this->render('profile/index.html.twig', [
            'user' => $user,
            'editForm' => $this->createForm(EditProfileFormType::class, $user, [
                'action' => $this->generateUrl('profile_edit'),
            ]),
            'passwordForm' => $this->createForm(ChangePasswordFormType::class, null, [
                'action' => $this->generateUrl('profile_password'),
            ]),
            'avatarForm' => $form,
        ]);
    }
}
