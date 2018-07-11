<?php

namespace App\Controller;

use App\Entity\Category;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use App\Entity\User;
use App\Entity\Post;

class UserController extends AbstractController
{
    /**
     * @Route("/user/", name="user")
     */
    public function index(Request $request)
    {
        $user = new User();

        $form = $this->createFormBuilder($user)
            ->add('email', EmailType::class, array(
                'label' => 'Enter Email *',
                'required' => true,
                'attr' => ['class' => 'form-control']
            ))
            ->add('password', PasswordType::class, array(
                'label' => 'Enter Password *',
                'required' => true,
                'attr' => ['class' => 'form-control']
            ))
            ->add('login', SubmitType::class, array(
                'label' => 'Login',
                'attr' => ['class' => 'form-control btn btn-success']
            ))
            ->getForm();

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $user = $form->getData();

            $repository = $this->getDoctrine()->getRepository(User::class);

            $check = $repository->findOneBy([
                'email' => $user->getEmail(),
                'password' => $user->getPassword()
            ]);

            if($check) {
                $session = new Session();
                $session->set('logged_in', 1);
                $session->set('email', $check->getEmail());
                $session->set('is_admin', $check->getIsAdmin());
                $session->set('user_id', $check->getId());

                if($check->getIsAdmin() == 1) {
                    return $this->redirectToRoute('admin_profile');
                } else {
                    return $this->redirectToRoute('user_profile');
                }
            } else {
                $this->addFlash('error', 'Wrong credentials');
                return $this->redirectToRoute('user');
            }
        }

        return $this->render('user/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/user/profile", name="user_profile")
     */
    public function login(Request $request, SessionInterface $session)
    {
        $session = new Session();

        if($session->get('is_admin') == 1) {
            $isAdmin = 'Yes';
        } else {
            $isAdmin = 'No';
        }

        $userId = $session->get('user_id');

        $repository = $this->getDoctrine()->getRepository(Post::class);

        $posts = $repository->findBy([
            'user_id' => $userId
        ]);

        $nrPosts = count($posts);

        return $this->render('user/profile.html.twig',
            [
                'controller_name' => 'UserController',
                'email' => $session->get('email'),
                'is_admin' => $isAdmin,
                'nr_posts' => $nrPosts
            ]
        );
    }

    /**
     * @Route("/user/newpass", name="user_new_password")
     */
    public function newPassword(Request $request)
    {
        $user = new User();

        $form = $this->createFormBuilder($user)
            ->add('email', EmailType::class, array(
                'label' => 'Enter Email *',
                'required' => true,
                'attr' => ['class' => 'form-control']
            ))
            ->add('password', RepeatedType::class, array(
                'type' => PasswordType::class,
                'required' => true,
                'first_options'  => array(
                    'label' => 'Password *',
                    'attr' => ['class' => 'form-control']
                ),
                'second_options' => array(
                    'label' => 'Repeat Password *',
                    'attr' => ['class' => 'form-control']
                ),
                'invalid_message' => 'The password fields must match.'
            ))
            ->add('submit', SubmitType::class, array(
                'label' => 'Submit',
                'attr' => ['class' => 'form-control btn btn-success']
            ))
            ->getForm();

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $session = new Session();
            $userId = $session->get('user_id');

            $repository = $this->getDoctrine()->getRepository(User::class);

            $newPass = $repository->findOneBy([
                'id' => $userId
            ]);

            $entityManager = $this->getDoctrine()->getManager();

            if($newPass->getEmail() == $data->getEmail()) {
                $newPass->setPassword($data->getPassword());

                $entityManager->persist($newPass);

                try {
                    $entityManager->flush();
                    $this->addFlash('success', 'Password changed successfully');
                    return $this->redirectToRoute('user_profile');
                } catch(\Exception $e) {
                    $this->addFlash('error', 'Error during changing password');
                    return $this->redirectToRoute('user_profile');
                }
            }
        }

        return $this->render('user/newpass.html.twig',
            [
                'form' => $form->createView()
            ]
        );
    }

    /**
     * @Route("/user/register", name="user_register")
     */
    public function register(Request $request)
    {
        $user = new User();

        $form = $this->createFormBuilder($user)
            ->add('email', EmailType::class, array(
                'label' => 'Enter Email *',
                'required' => true,
                'attr' => ['class' => 'form-control']
            ))
            ->add('password', RepeatedType::class, array(
                'type' => PasswordType::class,
                'required' => true,
                'first_options'  => array(
                    'label' => 'Password *',
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

            $newUser = new User();
            $newUser->setEmail($user->getEmail());
            $newUser->setPassword($user->getPassword());
            $newUser->setIsAdmin(0);

            $entityManager->persist($newUser);

            try {
                $entityManager->flush();
                $this->addFlash('success', 'User registrated! You can login now.');
                return $this->redirectToRoute('user');
            } catch(\Exception $e) {
                $this->addFlash('error', 'Error happened during persist to DB.');
                return $this->redirectToRoute('user_register');
            }
        }

        return $this->render('user/register.html.twig', [
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/user/posts", name="user_posts")
     */
    public function posts()
    {
        $repository = $this->getDoctrine()->getRepository(Post::class);

        $session = new Session();

        $posts = $repository->findBy([
            'user_id' => $session->get('user_id')
        ]);

        return $this->render('user/posts.html.twig',
            [
                'controller_name' => 'UserController',
                'posts' => $posts
            ]);
    }

    /**
     * @Route("/user/addnew", name="user_add_new_post")
     */
    public function addNewPost(Request $request)
    {
        $session = new Session();
        $userId = $session->get('user_id');

        $repository = $this->getDoctrine()->getRepository(Category::class);

        $categories = $repository->findAll();

        $cats = [0 => null];
        foreach($categories as $category) {
            array_push($cats, [$category->getName() => $category->getId()]);
        }

        //var_dump($cats['data']); die();

        $post = new Post();

        $form = $this->createFormBuilder($post)
            ->add('title', TextType::class, array(
                'label' => 'Enter Title *',
                'required' => true,
                'attr' => ['class' => 'form-control']
            ))
            ->add('content', TextareaType::class, array(
                'label' => 'Enter Content of Post *',
                'required' => true,
                'attr' => ['class' => 'form-control']
            ))
            ->add('user_id', HiddenType::class, array(
                'data' => $userId
            ))
            ->add('category_id', ChoiceType::class, array(
                'choices' =>  $cats,
                'label' => 'Select Category',
                'attr' => ['class' => 'form-control']
            ))
            ->add('register', SubmitType::class, array(
                'label' => 'Save',
                'attr' => ['class' => 'form-control btn btn-success']
            ))
            ->getForm();

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $post = $form->getData();

            $entityManager = $this->getDoctrine()->getManager();

            $newPost = new Post();
            $newPost->setTitle($post->getTitle());
            $newPost->setContent($post->getContent());
            $newPost->setCategoryId($post->getCategoryId());
            $date = new \DateTime();
            $newPost->setCreatedAt($date);
            $newPost->setEditedAt($date);
            $newPost->setUserId($session->get('user_id'));

            $entityManager->persist($newPost);

            try {
                $entityManager->flush();
                $this->addFlash('success', 'New post saved');
                return $this->redirectToRoute('user_posts');
            } catch(\Exception $e) {
                $this->addFlash('error', 'Error during saving');
                return $this->redirectToRoute('user_add_new_post');
            }
        }

        return $this->render('user/addnew.html.twig',
            [
                'controller_name' => 'UserController',
                'form' => $form->createView()
            ]);
    }

    /**
     * @Route("/user/editpost", name="user_edit_post")
     */
    public function editPost(Request $request)
    {
        $postId = $request->request->get('post_id');

        $session = new Session();
        $userId = $session->get('user_id');

        $repository = $this->getDoctrine()->getRepository(Category::class);

        $categories = $repository->findAll();

        $cats = [0 => null];
        foreach($categories as $category) {
            array_push($cats, [$category->getName() => $category->getId()]);
        }

        $repository = $this->getDoctrine()->getRepository(Post::class);

        $post = $repository->findOneBy([
            'id' => $postId
        ]);

        $form = $this->createFormBuilder($post)
            ->add('title', TextType::class, array(
                'label' => 'Enter Title *',
                'required' => true,
                'attr' => ['class' => 'form-control']
            ))
            ->add('content', TextareaType::class, array(
                'label' => 'Enter Content of Post *',
                'required' => true,
                'attr' => ['class' => 'form-control']
            ))
            ->add('category_id', ChoiceType::class, array(
                'choices' =>  $cats,
                'label' => 'Select Category',
                'attr' => ['class' => 'form-control']
            ))
            ->add('id', HiddenType::class, array())
            ->add('register', SubmitType::class, array(
                'label' => 'Update',
                'attr' => ['class' => 'form-control btn btn-success']
            ))
            ->getForm();

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $post = $form->getData();

            $entityManager = $this->getDoctrine()->getManager();
            $editPost = $entityManager->getRepository(Post::class)->find($post['id']);

            $editPost->setTitle($post['title']);
            $editPost->setContent($post['content']);
            $editPost->setCategoryId($post['category_id']);
            $date = new \DateTime();
            $editPost->setEditedAt($date);
            $editPost->setUserId($session->get('user_id'));

            try {
                $entityManager->flush();
                $this->addFlash('success', 'Successfully edited');
                return $this->redirectToRoute('user_posts');
            } catch(\Exception $e) {
                $this->addFlash('error', 'Error during editing');
                return $this->redirectToRoute('user_edit_post');
            }
        }

        return $this->render('user/editpost.html.twig',
            [
                'controller_name' => 'UserController',
                'form' => $form->createView(),
                'post_id' => $postId
            ]);
    }

    /**
     * @Route("/user/deletepost", name="user_delete_post")
     */
    public function deletePost(Request $request)
    {
        $postId = $request->request->get('post_id');

        $repository = $this->getDoctrine()->getRepository(Post::class);

        $entityManager = $this->getDoctrine()->getManager();

        $post = $repository->find($postId);

        $entityManager->remove($post);

        try {
            $entityManager->flush();
            $this->addFlash('success', 'Post has been successfully deleted');
        } catch(\Exception $e) {
            $this->addFlash('error', 'Error while deleting post');
        }

        return $this->redirectToRoute('user_posts');
    }

    /**
     * @Route("/user/logout", name="user_logout")
     */
    public function logout()
    {
        $session = new Session();

        $session->remove('logged_in');
        $session->remove('is_admin');
        $session->remove('email');
        $session->remove('user_id');

        $this->addFlash('success', 'Successfully logout');

        return $this->redirectToRoute('user');
    }
}