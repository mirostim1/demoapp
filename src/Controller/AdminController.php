<?php

namespace App\Controller;

use App\Entity\Category;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use App\Entity\User;
use App\Entity\Post;
use App\Form\Login;

class AdminController extends AbstractController
{
    /**
     * @Route("/admin/profile", name="admin_profile")
     */
    public function adminLogin(Request $request, SessionInterface $session)
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

        return $this->render('admin/profile.html.twig',
            [
                'controller_name' => 'AdminController',
                'email' => $session->get('email'),
                'is_admin' => $isAdmin,
                'nr_posts' => $nrPosts
            ]
        );
    }

    /**
     * @Route("/admin/allposts", name="admin_posts")
     */
    public function allposts()
    {
        $repository = $this->getDoctrine()->getRepository(Post::class);

        $session = new Session();

        $posts = $repository->findAll();
        rsort($posts);

        return $this->render('admin/allposts.html.twig',
            [
                'controller_name' => 'AdminController',
                'posts' => $posts
            ]);
    }

    /**
     * @Route("/admin/allusers", name="admin_users")
     */
    public function allusers()
    {
        $repository = $this->getDoctrine()->getRepository(User::class);

        $session = new Session();

        $users = $repository->findAll();
        rsort($users);

        return $this->render('admin/allusers.html.twig',
            [
                'controller_name' => 'AdminController',
                'users' => $users
            ]
        );
    }

    /**
     * @Route("/admin/newuser", name="admin_add_new_user")
     */
    public function addNewUser(Request $request)
    {
        $user = new User();

        $form = $this->createFormBuilder($user)
            ->add('is_admin', ChoiceType::class, array(
                'choices' => array('User' => 0, 'Admin' => 1),
                'attr' => ['class' => 'form-control']
            ))
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
            ->add('submit', SubmitType::class, array(
                'label' => 'Submit',
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
            $newUser->setIsAdmin($user->getIsAdmin());

            $entityManager->persist($newUser);

            try {
                $entityManager->flush();
                $this->addFlash('success', 'New user has been successfully created');
                return $this->redirectToRoute('admin_users');
            } catch(\Exception $e) {
                $this->addFlash('error', 'Error during saving new user');
                return $this->redirectToRoute('admin_add_new_user');
            }
        }

        return $this->render('admin/newuser.html.twig',
            [
                'controller_name' => 'AdminController',
                'form' => $form->createView()
            ]
        );
    }

    /**
     * @Route("/admin/edituser", name="admin_edit_user")
     */
    public function editUser(Request $request)
    {
        $repository = $this->getDoctrine()->getRepository(User::class);

        $user = $repository->findOneBy([
            'id' => $request->request->get('user_id')
        ]);

        $form = $this->createFormBuilder($user)
            ->add('is_admin', ChoiceType::class, array(
                'choices' => array('User' => 0, 'Admin' => 1),
                'attr' => ['class' => 'form-control']
            ))
            ->add('email', EmailType::class, array(
                'label' => 'Enter Email *',
                'required' => true,
                'attr' => ['class' => 'form-control', 'readonly' => true]
            ))
            ->add('id', HiddenType::class, array())
            ->add('submit', SubmitType::class, array(
                'label' => 'Update',
                'attr' => ['class' => 'form-control btn btn-success']
            ))
            ->getForm();

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $user = $form->getData();
            var_dump($user); die();
            $entityManager = $this->getDoctrine()->getManager();
            $editUser = $entityManager->getRepository(User::class)->find($user['id']);

            $editUser->setIsAdmin($user['is_admin']);
            $editUser->setPassword($user['password']);

            try {
                $entityManager->flush();
                $this->addFlash('success', 'User has been successfully edited');
                return $this->redirectToRoute('admin_users');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error during editing user');
                return $this->redirectToRoute('admin_add_new_user');
            }
        }

        return $this->render('admin/edituser.html.twig',
            [
                'controller_name' => 'AdminController',
                'form' => $form->createView()
            ]
        );
    }

    /**
     * @Route("/admin/allmedia", name="admin_media")
     */
    public function allmedia()
    {
        $repository = $this->getDoctrine()->getRepository(Post::class);

        $session = new Session();

        $posts = $repository->findBy([
            'user_id' => $session->get('user_id')
        ]);

        return $this->render('admin/allmedia.html.twig',
            [
                'controller_name' => 'AdminController',
                'posts' => $posts
            ]
        );
    }

    /**
     * @Route("/admin/addnew", name="admin_add_new_post")
     */
    public function addnew(Request $request)
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

        return $this->render('admin/addnew.html.twig',
            [
                'controller_name' => 'AdminController',
                'form' => $form->createView()
            ]
        );
    }

    /**
     * @Route("/admin/editpost", name="admin_edit_post")
     */
    public function editpost(Request $request)
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
                return $this->redirectToRoute('admin_allposts');
            } catch(\Exception $e) {
                $this->addFlash('error', 'Error during editing');
                return $this->redirectToRoute('admin_edit_post');
            }
        }

        return $this->render('admin/editpost.html.twig',
            [
                'controller_name' => 'UserController',
                'form' => $form->createView(),
                'post_id' => $postId
            ]);
    }

    /**
     * @Route("/admin/deletepost", name="admin_delete_post")
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
            $this->addFlash('success', 'Post has been succesfully deleted');
        } catch(\Exception $e) {
            $this->addFlash('error', 'Error while deleting post');
        }

        return $this->redirectToRoute('admin_posts');
    }

    /**
     * @Route("/admin/logout", name="admin_logout")
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
