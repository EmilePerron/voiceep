<?php

namespace App\Controller\App;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use App\Entity\User;

/**
 * @Route("/app/account", name="app_account_")
 */
class AccountController extends AppController
{
    /**
     * @Route("/settings", name="settings")
     */
    public function settings(Request $request, UserPasswordEncoderInterface $encoder)
    {
        $user = $this->getUser();
        $originalEmail = $user->getEmail();

        $form = $this->createFormBuilder($user)
            ->add('email', EmailType::class, ['label' => 'Email'])
            ->add('newPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'The passwords must match.',
                'required' => false,
                'mapped' => false,
                'options' => ['attr' => ['autocomplete' => 'new-password']],
                'first_options'  => array('label' => 'New password'),
                'second_options' => array('label' => 'Repeat your new password'),
            ])
            ->add('save', SubmitType::class, ['label' => 'Save account settings', 'attr' => ['class' => 'large']])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $em = $this->getDoctrine()->getManager();

                if ($newPassword = $form['newPassword']->getData()) {
                    $user->setPassword($encoder->encodePassword($user, $newPassword));
                }

                if (!$form->getErrors()->count()) {
                    $em->persist($user);
                    $em->flush();

                    return $this->redirectToRoute('app_account_settings', ['success' => true]);
                }
            } else if ($originalEmail != $user->getEmail()) {
                $user->setEmail($originalEmail);
                $token = new UsernamePasswordToken($user, null, 'main', $user->getRoles());
                $this->get('security.token_storage')->setToken($token);
                $this->get('session')->set('_security_main', serialize($token));
            } else {
                # Copy repeated type errors to both fields
                if ($form['newPassword']['first']->getErrors()->count() && !$form['newPassword']['second']->getErrors()->count()) {
                    $errorsToCopy = clone $form['newPassword']['first']->getErrors();
                    do {
                        $form['newPassword']['second']->addError(clone $errorsToCopy->current());
                        $errorsToCopy->next();
                    } while ($errorsToCopy->valid());
                }
            }
        }

        return $this->render('page/protected/account/settings.html.twig',
                            ['form' => $form->createView(),
                             'success' => $request->query->get('success')]);
    }

    /**
     * @Route("/delete", name="delete")
     */
    public function delete(Request $request)
    {
        $userId = $request->query->get('user_id');
        if ($userId && $this->getUser()->getId() == $userId) {
            $em = $this->getDoctrine()->getManager();
            $this->getUser()->delete($em);
            $em->flush();
            $this->get('security.token_storage')->setToken(null);
        } else {
            throw $this->createAccessDeniedException("You are not allowed to access this account.");
        }

        return $this->redirectToRoute('home');
    }
}
