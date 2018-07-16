<?php

namespace App\Controller;

use App\Entity\Category;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use App\Entity\User;
use App\Entity\Post;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;


class UserController extends AbstractController
{
    /**
    * @Route("/", name="user")
    */
    public function index(Request $request, UserPasswordEncoderInterface $passwordEncoder)
    {
        $user = new User();

        $form = $this->createFormBuilder($user)
            ->add('email', EmailType::class, array(
                'label' => 'Enter Email *',
                'attr' => ['class' => 'form-control', 'id' => 'dist']
            ))
            ->add('password', PasswordType::class, array(
                'label' => 'Enter Password *',
                'attr' => ['class' => 'form-control']
            ))
            ->add('login', SubmitType::class, array(
                'label' => 'Login',
                'attr' => ['class' => 'form-control btn btn-success']
            ))
            ->getForm();

        $form->handleRequest($request);

        if($form->isSubmitted() /* && $form->isValid()*/) {
            $user = $form->getData();

            $repository = $this->getDoctrine()->getRepository(User::class);

            $check = $repository->findOneBy([
                'email' => $user->getEmail()
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
            'form' => $form->createView()
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
                'attr' => ['class' => 'form-control']
            ))
            ->add('password', RepeatedType::class, array(
                'type' => PasswordType::class,
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
                    $this->addFlash('success', 'Password has been changed successfully');
                    return $this->redirectToRoute('user_profile');
                } catch(\Exception $e) {
                    $this->addFlash('error', 'Error during changing password');
                    return $this->redirectToRoute('user_profile');
                }
            } else {
                $this->addFlash('error', 'Email do not match your registration email. Please enter correct email.');
                return $this->redirectToRoute('user_new_password');
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
    public function register(Request $request, UserPasswordEncoderInterface $passwordEncoder)
    {
        $user = new User();

        $form = $this->createFormBuilder($user)
            ->add('email', EmailType::class, array(
                'label' => 'Enter Email *',
                'attr' => ['class' => 'form-control']
            ))
            ->add('password', RepeatedType::class, array(
                'type' => PasswordType::class,
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
            $userData = $form->getData();

            $entityManager = $this->getDoctrine()->getManager();

            $user->setEmail($userData->getEmail());

            $password = $passwordEncoder->encodePassword($user, $userData->getPlainPassword());
            $string = 'hello';
            $salt = md5($string);
            $user->setPassword($password.$salt);
            $user->setIsAdmin(0);

            $entityManager->persist($user);

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
    public function posts(Request $request)
    {
        $repository = $this->getDoctrine()->getRepository(Post::class);

        $session = new Session();

        $posts = $repository->findBy([
            'user_id' => $session->get('user_id')
        ]);
        rsort($posts);

        $currentPage = $request->query->get('page');
        if(!$currentPage) {
            $currentPage = 1;
        }

        $adapter = new ArrayAdapter($posts);
        $pagerfanta = new Pagerfanta($adapter);
        $pagerfanta->setMaxPerPage(10)->setCurrentPage($currentPage);

        return $this->render('user/posts.html.twig',
            [
                'controller_name' => 'UserController',
                'my_pager' => $pagerfanta
            ]
        );
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

        $categoriesTemp = [0 => ''];
        $categoriesIds = [0 => 0];
        foreach($categories as $category) {
            array_push($categoriesTemp, $category->getName());
            array_push($categoriesIds, $category->getId());
        }

        $categoriesDropdown = array_combine($categoriesTemp, $categoriesIds);

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
            ->add('image_path', FileType::class, array(
                'attr' => ['class' => 'form-control']
            ))
            ->add('user_id', HiddenType::class, array(
                'data' => $userId
            ))
            ->add('category_id', ChoiceType::class, array(
                'choices' =>  $categoriesDropdown,
                'label' => 'Select Category *',
                'attr' => ['class' => 'form-control']
            ))
            ->add('register', SubmitType::class, array(
                'label' => 'Submit',
                'attr' => ['class' => 'form-control btn btn-success']
            ))
            ->getForm();

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $post = $form->getData();

            $entityManager = $this->getDoctrine()->getManager();

            $post->setTitle($post->getTitle());
            $post->setContent($post->getContent());
            $post->setCategoryId($post->getCategoryId());
            $date = new \DateTime();
            $post->setCreatedAt($date);
            $post->setEditedAt($date);
            $post->setUserId($session->get('user_id'));

            if($post->getImagePath() != null) {
                $newFileName = 'image' . time();
                $file = $form['image_path']->getData();
                $extension = $file->guessExtension();

                if($file->move('img/posts', $newFileName . '.' . $extension)) {
                    $post->setImagePath($newFileName . '.' . $extension);
                }
            }
            else {
                $post->setImagePath('');
            }

            $entityManager->persist($post);

            try {
                $entityManager->flush();
                $this->addFlash('success', 'New post successfully added');
                return $this->redirectToRoute('user_posts');
            } catch(\Exception $e) {
                $this->addFlash('error', 'Error during saving');
                return $this->redirectToRoute('user_add_new_post');
            }
        }

        return $this->render('user/addnew.html.twig',
            [
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

        $categoriesTemp = [0 => ''];
        $categoriesIds = [0 => 0];
        foreach($categories as $category) {
            array_push($categoriesTemp, $category->getName());
            array_push($categoriesIds, $category->getId());
        }

        $categoriesDropdown = array_combine($categoriesTemp, $categoriesIds);

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
            ->add('image_path', FileType::class, array(
                'attr' => ['class' => 'form-control'],
                'data_class' => null
            ))
            ->add('category_id', ChoiceType::class, array(
                'choices' =>  $categoriesDropdown,
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

            if($post['image_path'] != null) {
                $newFileName = 'image' . time();
                $file = $form['image_path']->getData();
                $extension = $file->guessExtension();

                if($file->move('img/posts', $newFileName . '.' . $extension)) {
                    $editPost->setImagePath($newFileName . '.' . $extension);
                }
            }

            try {
                $entityManager->flush();
                $this->addFlash('success', 'Post has been successfully edited');
                return $this->redirectToRoute('user_posts');
            } catch(\Exception $e) {
                $this->addFlash('error', 'Error during editing post');
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
        $post = $repository->find($postId);

        if($post->getImagePath()) {
            $filename = 'img/posts/' . $post->getImagePath();
        }

        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->remove($post);

        try {
            $entityManager->flush();
            $fileSystem = new Filesystem();

            if($filename) {
                $fileSystem->remove($filename);
            }

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