<?php

namespace App\Controller;

use App\Entity\Category;
use Pagerfanta\Adapter\DoctrineORMAdapter;
use Pagerfanta\Pagerfanta;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\View\TwitterBootstrap3View;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
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
use Symfony\Component\Filesystem\Filesystem;

class AdminController extends AbstractController
{

    public function __construct()
    {
        $session = new Session();

        if($session->get('is_admin') != 1) {
            return $this->redirect('/');
        }
    }

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
    public function allposts(Request $request)
    {
        $repository = $this->getDoctrine()->getRepository(Post::class);
        $posts = $repository->findAll();
        rsort($posts);

        //$entityManager = $this->getDoctrine()->getManager();

//        $queryBuilder = $entityManager->createQueryBuilder()
//                    ->select('p')
//                    ->from('App\Entity\Post', 'p');

        $currentPage = $request->query->get('page');
        if(!$currentPage) {
            $currentPage = 1;
        }

        $adapter = new ArrayAdapter($posts);
        $pagerfanta = new Pagerfanta($adapter);
        $pagerfanta->setMaxPerPage(10)->setCurrentPage($currentPage);

        return $this->render('admin/allposts.html.twig',
            [
                'controller_name' => 'AdminController',
                'my_pager' => $pagerfanta,
            ]);
    }

    /**
     * @Route("/admin/editpost", name="admin_edit_post")
     */
    public function editpost(Request $request)
    {
        $postId = $request->request->get('post_id');

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
            ->add('id', HiddenType::class, array(
                'attr' => ['class' => 'form-control']
            ))
            ->add('category_id', ChoiceType::class, array(
                'choices' =>  $categoriesDropdown,
                'label' => 'Select Category',
                'attr' => ['class' => 'form-control']
            ))
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
                return $this->redirectToRoute('admin_posts');
            } catch(\Exception $e) {
                $this->addFlash('error', 'Error during editing post');
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
     * @Route("/admin/addnew", name="admin_add_new_post")
     */
    public function addnew(Request $request)
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
            ->add('user_id', HiddenType::class, array(
                'data' => $userId
            ))
            ->add('image_path', FileType::class, array(
                'attr' => ['class' => 'form-control']
            ))
            ->add('category_id', ChoiceType::class, array(
                'choices' =>  $categoriesDropdown,
                'label' => 'Select Category *',
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
            } else {
                $post->setImagePath('');
            }

            $entityManager->persist($post);

            try {
                $entityManager->flush();
                $this->addFlash('success', 'New post has been successfully submitted');
                return $this->redirectToRoute('admin_posts');
            } catch(\Exception $e) {
                $this->addFlash('error', 'Error during saving post. Please try again.');
                return $this->redirectToRoute('admin_add_new_post');
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
     * @Route("/admin/deletepost", name="admin_delete_post")
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

            $this->addFlash('success', 'Post has been succesfully deleted');
        } catch(\Exception $e) {
            $this->addFlash('error', 'Error while deleting post');
        }

        return $this->redirectToRoute('admin_posts');
    }

    /**
     * @Route("/admin/allusers", name="admin_users")
     */
    public function allusers(Request $request)
    {
        $repository = $this->getDoctrine()->getRepository(User::class);

        $session = new Session();

        $users = $repository->findAll();
        rsort($users);

        $currentPage = $request->query->get('page');
        if(!$currentPage) {
            $currentPage = 1;
        }

        $adapter = new ArrayAdapter($users);
        $pagerfanta = new Pagerfanta($adapter);
        $pagerfanta->setMaxPerPage(10)->setCurrentPage($currentPage);

        return $this->render('admin/allusers.html.twig',
            [
                'controller_name' => 'AdminController',
                'my_pager' => $pagerfanta
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

            $user->setEmail($user->getEmail());
            $user->setPassword($user->getPassword());
            $user->setIsAdmin($user->getIsAdmin());

            $entityManager->persist($user);

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

        if(isset($user)) {
            $userId = $user->getId();
        }

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

            $userId = $user['id'];

            $entityManager = $this->getDoctrine()->getManager();
            $editUser = $entityManager->getRepository(User::class)->find($user['id']);

            $editUser->setIsAdmin($user['is_admin']);

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
                'form' => $form->createView(),
                'user_id' => $userId
            ]
        );
    }

    /**
     * @Route("/admin/deleteuser", name="admin_delete_user")
     */
    public function deleteUser(Request $request)
    {
        $userId = $request->request->get('user_id');
        
        $repository = $this->getDoctrine()->getRepository(User::class);

        $entityManager = $this->getDoctrine()->getManager();

        $user = $repository->find($userId);

        $entityManager->remove($user);

        try {
            $entityManager->flush();
            $this->addFlash('success', 'User has been successfully deleted');
        } catch(\Exception $e) {
            $this->addFlash('error', 'Error while deleting user');
        }

        return $this->redirectToRoute('admin_users');
    }

    /**
     * @Route("/admin/allmedia", name="admin_media")
     */
    public function allmedia(Request $request)
    {
        $repository = $this->getDoctrine()->getRepository(Post::class);
        $posts = $repository->findAll();

        $temp = [];
        foreach($posts as $post) {
            if($post->image_path) {
                array_push($temp, $post->image_path);
            }
        }

        $files = scandir('img/posts');

        $imagesInDir = [];
        $flag = 0;
        foreach($files as $key => $image) {
            if($key > 1) {
                foreach($temp as $item) {
                    if($image == $item) {
                        $flag = 1;
                    }
                }
                if($flag == 1) {
                    array_push($imagesInDir, ['path' => $image, 'used' => 1]);
                    $flag = 0;
                } else {
                    array_push($imagesInDir, ['path' => $image, 'used' => 0]);
                }
            }
        }

        $currentPage = $request->query->get('page');
        if(!$currentPage) {
            $currentPage = 1;
        }

        $adapter = new ArrayAdapter($imagesInDir);
        $pagerfanta = new Pagerfanta($adapter);
        $pagerfanta->setMaxPerPage(10)->setCurrentPage($currentPage);

        return $this->render('admin/allmedia.html.twig',
            [
                'controller_name' => 'AdminController',
                'my_pager' => $pagerfanta
            ]
        );
    }

    /**
     * @Route("/admin/deleteimage", name="admin_delete_image")
     */
    public function deleteImage(Request $request)
    {
        $image = $request->request->get('image_name');

        $imagePath = 'img/posts/' . $image;

        if(file_exists($imagePath)) {
            $fileSystem = new Filesystem();

            try {
                $repository = $this->getDoctrine()->getRepository(Post::class);
                $entityManager = $this->getDoctrine()->getManager();

                $post = $repository->findOneBy([
                    'image_path' => $image
                ]);

                if($post) {
                    if($post->image_path == $image) {
                        $post->setImagePath('');
                        $entityManager->flush();
                    }
                }

                $fileSystem->remove($imagePath);

                $this->addFlash('success', 'Image has been successfully deleted');
            } catch(\Exception $e) {
                $this->addFlash('error', 'Error during delete image');
            }
        } else {
            $this->addFlash('error', 'Selected file not exsits');
        }

        return $this->redirectToRoute('admin_media');
    }

    /**
     * @Route("/admin/deleteimages", name="admin_delete_images")
     */
    public function deleteImages(Request $request)
    {
        $images = $request->request->get('checkbox');

        $repository = $this->getDoctrine()->getRepository(Post::class);
        $entityManager = $this->getDoctrine()->getManager();
        $fileSystem = new Filesystem();

        foreach($images as $image) {
            $imagePath = 'img/posts/' . $image;

            if(file_exists($imagePath)) {
                try {
                    $post = $repository->findOneBy([
                        'image_path' => $image
                    ]);

                    if($post) {
                        if($post->image_path == $image) {
                            $post->setImagePath('');
                            $entityManager->flush();
                        }
                    }
                    $fileSystem->remove($imagePath);
                    $msg = 'Selected images have been successfully deleted';
                } catch(\Exception $e) {
                    $this->addFlash('error', 'Error during delete selected images');
                }
            }
        }

        if($msg) {
            $this->addFlash('success', $msg);
        }

        return $this->redirectToRoute('admin_media');
    }

    /**
     * @Route("/admin/allcategories", name="admin_categories")
     */
    public function categories(Request $request)
    {
        $repository = $this->getDoctrine()->getRepository(Category::class);
        $categories = $repository->findAll();
        rsort($categories);

        $category = new Category();

        $form = $this->createFormBuilder($category)
            ->add('name', TextType::class, array(
                'label' => 'Name for category *',
                'attr' => ['class' => 'form-control']
            ))
            ->add('submit', SubmitType::class, array(
                'label' => 'Add',
                'attr' => ['class' => 'form-control btn btn-success']
            ))
            ->getForm();

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $category = $form->getData();

            $date = new \DateTime();
            $category->setCreatedAt($date);

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($category);

            try {
                $entityManager->flush();
                $this->addFlash('success', 'Category has been successfully created');
            } catch(\Exception $e) {
                $this->addFlash('error', 'Error during saving new category');
            }

            return $this->redirectToRoute('admin_categories');
        }

        return $this->render('admin/allcategories.html.twig',
            [
                'categories' => $categories,
                'form' => $form->createView(),
            ]
        );
    }

    /**
     * @Route("/admin/deletecategory", name="admin_delete_category")
     */
    public function deleteCategory(Request $request)
    {
        $categoryId = $request->request->get('category_id');

        $repository = $this->getDoctrine()->getRepository(Category::class);
        $category = $repository->findOneBy(
            [
                'id' => $categoryId
            ]
        );

        try {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($category);
            $entityManager->flush();
            $this->addFlash('success', 'Category has been succesfully deleted');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Error during delete category');
        }

        return $this->redirectToRoute('admin_categories');
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
