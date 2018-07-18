<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\User;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class SecurityController extends Controller
{
    /**
     * @Route("/", name="login")
     */
    public function login(Request $request, AuthenticationUtils $authenticationUtils)
    {
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();
        $user = $this->getUser();

        if(!$error && !$lastUsername && $user) {
            $role = $this->getUser()->getRoles();
            if($role[0] == 'ROLE_ADMIN') {
                return $this->redirectToRoute('admin_profile');
            } elseif($role[0] == 'ROLE_USER') {
                return $this->redirectToRoute('user_profile');
            }
        }

        return $this->render('security/login.html.twig', array(
            'last_username' => $lastUsername,
            'error'         => $error,
        ));
    }

    /**
     * @Route("/register", name="register")
     */
    public function register(Request $request, UserPasswordEncoderInterface $passwordEncoder)
    {
        $user = new User();

        $form = $this->createFormBuilder($user)
            ->add('username', EmailType::class, array(
                'label' => 'Enter Email *',
                'attr' => ['class' => 'form-control']
            ))
            ->add('plainPassword', RepeatedType::class, array(
                'type' => PasswordType::class,
                'first_options'  => array(
                    'label' => 'Enter Password *',
                    'attr' => ['class' => 'form-control']
                ),
                'second_options' => array(
                    'label' => 'Repeat Password *',
                    'attr' => ['class' => 'form-control']
                ),
                'invalid_message' => 'The password fields must match.'
            ))
            ->add('register', SubmitType::class, array(
                'label' => 'Register',
                'attr' => ['class' => 'form-control btn btn-success']
            ))
            ->getForm();

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $user = $form->getData();

            $entityManager = $this->getDoctrine()->getManager();

            $user->setEmail($user->getUsername());

            $password = $passwordEncoder->encodePassword($user, $user->getPlainPassword());
            $user->setPassword($password);

            $user->setIsActive(1);

            $entityManager->persist($user);

            try {
                $entityManager->flush();
                $this->addFlash('success', 'User registrated! You can login now.');
                return $this->redirectToRoute('login');
            } catch(\Exception $e) {
                $this->addFlash('error', 'Error happened during persist to DB.');
                return $this->redirectToRoute('register');
            }
        }

        return $this->render('security/register.html.twig', array(
            'form' => $form->createView()
        ));
    }

    /**
     * @Route("/logout", name="security_logout")
     */
    public function logout()
    {
    }
}