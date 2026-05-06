<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

class RegistrationController extends AbstractController
{
    public function __construct(
        private readonly VerifyEmailHelperInterface $verifyEmailHelper,
        private readonly MailerInterface $mailer,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(Request $request, UserPasswordHasherInterface $hasher): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPassword($hasher->hashPassword($user, $form->get('plainPassword')->getData()));

            $this->em->persist($user);
            $this->em->flush();

            $signatureComponents = $this->verifyEmailHelper->generateSignature(
                'app_verify_email',
                $user->getId()->toRfc4122(),
                $user->getEmail(),
                ['id' => $user->getId()->toRfc4122()],
            );

            $this->mailer->send(
                (new TemplatedEmail())
                    ->from(new Address('noreply@swiftchat.app', 'SwiftChat'))
                    ->to($user->getEmail())
                    ->subject('Verify your SwiftChat email address')
                    ->htmlTemplate('emails/verification_email.html.twig')
                    ->context([
                        'username' => $user->getUsername(),
                        'signedUrl' => $signatureComponents->getSignedUrl(),
                        'expiresAt' => $signatureComponents->getExpiresAt(),
                    ])
            );

            $this->addFlash('success', 'Account created! Check your inbox to verify your email.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('auth/register.html.twig', ['form' => $form]);
    }

    #[Route('/verify/email', name: 'app_verify_email', methods: ['GET'])]
    public function verifyEmail(Request $request, UserRepository $userRepository): Response
    {
        $id = $request->query->get('id');

        if (!$id) {
            $this->addFlash('error', 'Invalid verification link.');

            return $this->redirectToRoute('app_login');
        }

        try {
            $user = $userRepository->find(Uuid::fromString($id));
        } catch (\Throwable) {
            $this->addFlash('error', 'Invalid verification link.');

            return $this->redirectToRoute('app_login');
        }

        if (!$user instanceof User) {
            $this->addFlash('error', 'Invalid verification link.');

            return $this->redirectToRoute('app_login');
        }

        try {
            $this->verifyEmailHelper->validateEmailConfirmationFromRequest(
                $request,
                $user->getId()->toRfc4122(),
                $user->getEmail(),
            );
        } catch (VerifyEmailExceptionInterface $e) {
            $this->addFlash('error', $e->getReason());

            return $this->redirectToRoute('app_login');
        }

        $user->setIsVerified(true);
        $this->em->flush();

        $this->addFlash('success', 'Email verified! You can now log in.');

        return $this->redirectToRoute('app_login');
    }
}
