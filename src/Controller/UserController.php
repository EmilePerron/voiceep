<?php

namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Validator\Constraints\IsTrue;
use App\Entity\User;
use App\Entity\Project;

class UserController extends Controller
{
    /**
     * @Route("/modal/signup", name="signup")
     */
    public function signup(Request $request, UserPasswordEncoderInterface $encoder, \Swift_Mailer $mailer)
    {
        $user = new User();

        $form = $this->createFormBuilder($user)
            ->setAction($this->generateUrl('signup'))
            ->add('email', EmailType::class, ['label' => 'Email'])
            ->add('password', PasswordType::class)
            ->add('terms', CheckboxType::class, ['label' => "I have read and agree with Voiceep's <a href='" . $this->generateUrl('terms_of_use') . "' target='_blank'>terms of use</a> and <a href='" . $this->generateUrl('privacy_policy') . "' target='_blank'>privacy policy</a>.",
                                                'data' => true,
                                                'mapped' => false,
                                                'constraints' => new IsTrue()])
            ->add('signup', SubmitType::class, ['label' => 'Sign up', 'attr' => ['class' => 'large']])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $response = [];

            if ($form->isValid() && $user->getPassword()) {
                $user = $form->getData();
                $user->setPassword($encoder->encodePassword($user, $user->getPassword()));

                $em = $this->getDoctrine()->getManager();
                $em->persist($user);
                $em->flush();

                # Send the registration confirmation email
                $message = (new \Swift_Message('Welcome to Voiceep!'))
                        ->setFrom(['info@voiceep.com' => 'Voiceep'])
                        ->setTo($user->getEmail())
                        ->setBody($this->renderView('email/registration.html.twig'), 'text/html');
                $mailer->send($message);

                # Login the user programatically
                $token = new UsernamePasswordToken($user, null, 'main', $user->getRoles());
                $this->get('security.token_storage')->setToken($token);
                $this->get('session')->set('_security_main', serialize($token));
                $event = new InteractiveLoginEvent($request, $token);
                $this->get("event_dispatcher")->dispatch("security.interactive_login", $event);

                $response['redirect_url'] = $this->generateUrl('app_dashboard');
            } else {
                $response['errors'] = [];

                foreach ($form->getErrors(true) as $error) {
                    $response['errors'][] = $error->getMessage();
                }
            }

            return new JsonResponse($response);
        }

        return $this->render('component/modal/signup.html.twig',
                            ['form' => $form->createView()]);
    }

    /**
     * @Route("/signin", name="signin")
     */
    public function signin(AuthenticationUtils $authenticationUtils)
    {
        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('page/public/signin.html.twig', [
               'last_username' => $lastUsername,
               'error'         => $error,
           ]);
    }
}
